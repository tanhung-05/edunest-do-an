<?php

namespace App\Actions\M27;

use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Enums\M27\PostTopic;
use App\Models\M03\Course;
use App\Models\M11\Setting;
use App\Models\M27\CommunityComment;
use App\Models\M27\CommunityPost;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * QT-29 (02e §5): đăng bài, sửa (A1), bình luận, thích (A2), báo cáo (A3), Giáo vụ ẩn/hiện lại (bước 9, A4).
 * Thứ tự khóa: `users` người thao tác → `community_posts` → `community_comments`. Mọi lần ghi của học viên và kiểm duyệt
 * cùng khóa dòng bài nên "bài còn hiện" được kiểm tại điểm ghi (BR-HT-60).
 */
final class Community
{
    public const HIEN = 'HIEN';

    public const DA_AN = 'DA_AN';

    /** @param  array{topic: PostTopic, course_id?: ?int, title: string, body: string}  $data */
    public function post(User $student, array $data, ?UploadedFile $image, CarbonImmutable $at): CommunityPost
    {
        [$title, $body] = self::text($data['title'], $data['body']);
        $path = self::storeImage($image);

        return self::cleanupOnFail($path, fn () => Tx::run(function () use ($student, $data, $title, $body, $path, $at) {
            $student = self::student($student, true);
            // BR-DG-28, CR-100: tối đa N bài trong 60 phút, đếm dưới khóa dòng người đăng (X2).
            $limit = Setting::number('community.posts_per_hour');
            $recent = CommunityPost::query()->where('user_id', $student->id)->where('created_at', '>', $at->subHour())->count();
            if ($recent >= $limit) {
                throw new DomainRejection('Bạn đã đăng '.$limit.' bài trong 60 phút qua. Thử lại sau.', 'body');
            }
            $courseId = $data['course_id'] ?? null;
            if ($courseId !== null && ! Course::query()->whereKey($courseId)->whereIn('sale_status', [SaleStatus::DangBan->value, SaleStatus::NgungBan->value])->exists()) {
                throw new DomainRejection('Khóa gắn thẻ không hợp lệ.', 'course_id');
            }

            return CommunityPost::query()->create([
                'user_id' => $student->id, 'topic' => $data['topic'], 'course_id' => $courseId,
                'title' => $title, 'body' => $body, 'image_path' => $path, 'created_at' => $at, 'updated_at' => $at,
            ])->refresh();
        }));
    }

    /**
     * A1, CR-177: chủ bài đã xác minh sửa bài đang hiện; giữ mức hiển thị, bình luận, báo cáo.
     *
     * @param  array{title: string, body: string}  $data
     */
    public function edit(User $student, CommunityPost $post, array $data, ?UploadedFile $image, CarbonImmutable $at): CommunityPost
    {
        [$title, $body] = self::text($data['title'], $data['body']);
        $path = self::storeImage($image);

        return self::cleanupOnFail($path, fn () => Tx::run(function () use ($student, $post, $title, $body, $path, $at) {
            $student = self::student($student, false);
            if (! $student->hasVerifiedEmail()) {
                throw DomainRejection::forbidden('Xác minh email trước khi sửa bài.');
            }
            $p = CommunityPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            if ($p->user_id !== $student->id) {
                throw DomainRejection::forbidden('Chỉ sửa được bài của chính bạn.');
            }
            if ($p->status !== self::HIEN) {
                throw new DomainRejection('Bài đang bị ẩn, không sửa được.', 'body');
            }
            $fill = ['title' => $title, 'body' => $body, 'edited_at' => $at];
            if ($path !== null) {
                $fill['image_path'] = $path;
            }
            $p->forceFill($fill)->save();

            return $p;
        }));
    }

    /** Bước 4–5. */
    public function comment(User $student, CommunityPost $post, string $body): CommunityComment
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new DomainRejection('Bình luận cần từ 1 đến 2000 ký tự.', 'body');
        }

        return Tx::run(function () use ($student, $post, $body) {
            $student = self::student($student, false);
            $p = self::visiblePost($post);

            return CommunityComment::query()->create(['community_post_id' => $p->id, 'user_id' => $student->id, 'body' => $body]);
        });
    }

    /** A2, BR-DG-29: ghi lựa chọn theo cặp; gửi lại cùng lựa chọn không đảo. */
    public function like(User $student, CommunityPost $post, bool $liked): CommunityPost
    {
        return Tx::run(function () use ($student, $post, $liked) {
            $student = self::student($student, false);
            $p = self::visiblePost($post);
            $changed = $liked
                ? DB::table('community_likes')->insertOrIgnore(['community_post_id' => $p->id, 'user_id' => $student->id, 'created_at' => now()])
                : DB::table('community_likes')->where('community_post_id', $p->id)->where('user_id', $student->id)->delete();
            if ($changed > 0) {
                $p->forceFill(['like_count' => DB::table('community_likes')->where('community_post_id', $p->id)->count()])->save();
            }

            return $p;
        });
    }

    /** A3, X3: tối đa một báo cáo mỗi (học viên, bài); bài giữ mức hiển thị. Trả `false` khi đã báo cáo trước đó. */
    public function report(User $student, CommunityPost $post, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainRejection('Nhập lý do báo cáo từ 1 đến 500 ký tự.', 'reason');
        }

        return Tx::run(function () use ($student, $post, $reason) {
            $student = self::student($student, false);
            $p = self::visiblePost($post);

            return DB::table('community_reports')->insertOrIgnore([
                'community_post_id' => $p->id, 'user_id' => $student->id, 'reason' => $reason, 'created_at' => now(),
            ]) > 0;
        });
    }

    /** Bước 8–9 và A4 cho bài. */
    public function moderatePost(User $academic, CommunityPost $post, bool $hide, string $reason): CommunityPost
    {
        $reason = self::reason($reason);

        return Tx::run(function () use ($academic, $post, $hide, $reason) {
            $actor = self::academic($academic);
            $p = CommunityPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            self::flip($actor, $p, $hide, $reason, 'COMMUNITY_POST');

            return $p;
        });
    }

    /** Bước 8–9 và A4 cho bình luận: khóa bài cha trước (chung với bước 5). */
    public function moderateComment(User $academic, CommunityComment $comment, bool $hide, string $reason): CommunityComment
    {
        $reason = self::reason($reason);

        return Tx::run(function () use ($academic, $comment, $hide, $reason) {
            $actor = self::academic($academic);
            CommunityPost::query()->whereKey($comment->community_post_id)->lockForUpdate()->firstOrFail();
            $c = CommunityComment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();
            self::flip($actor, $c, $hide, $reason, 'COMMUNITY_COMMENT');

            return $c;
        });
    }

    /** CR-175: Học viên (kể cả chưa xác minh) đọc bài đang hiện; tác giả thấy bài ẩn của mình kèm lý do. */
    public static function canRead(User $user, CommunityPost $post): bool
    {
        return $user->isActive() && $user->hasEffectiveRole(RoleCode::HocVien)
            && ($post->status === self::HIEN || $post->user_id === $user->id);
    }

    private static function flip(User $actor, CommunityPost|CommunityComment $row, bool $hide, string $reason, string $action): void
    {
        $to = $hide ? self::DA_AN : self::HIEN;
        if ($row->status === $to) {
            throw new DomainRejection($hide ? 'Nội dung đã bị ẩn.' : 'Nội dung đang hiển thị.', 'reason');
        }
        $from = $row->status;
        $row->forceFill($hide
            ? ['status' => $to, 'hidden_reason' => $reason, 'hidden_at' => now(), 'hidden_by_id' => $actor->id]
            : ['status' => $to, 'hidden_reason' => null, 'hidden_at' => null, 'hidden_by_id' => null])->save();
        Audit::log($action.($hide ? '_HIDE' : '_UNHIDE'), $actor, $row, ['status' => $from], ['status' => $to], $reason);
    }

    private static function student(User $user, bool $forPost): User
    {
        $query = User::query()->whereKey($user->id);
        $locked = ($forPost ? $query->lockForUpdate() : $query->sharedLock())->firstOrFail();
        if (! $locked->isActive() || $locked->auth_generation !== $user->auth_generation) {
            throw DomainRejection::forbidden('Phiên đăng nhập không còn hiệu lực.');
        }
        if (! $locked->hasEffectiveRole(RoleCode::HocVien)) {
            throw DomainRejection::forbidden('Chỉ Học viên dùng bảng tin cộng đồng.');
        }

        return $locked;
    }

    private static function academic(User $user): User
    {
        $locked = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
        OrderLocks::academic($locked);

        return $locked;
    }

    private static function visiblePost(CommunityPost $post): CommunityPost
    {
        $p = CommunityPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
        if ($p->status !== self::HIEN) {
            throw new DomainRejection('Bài không còn hiển thị.', 'body');
        }

        return $p;
    }

    /** @return array{0: string, 1: string} */
    private static function text(string $title, string $body): array
    {
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new DomainRejection('Tiêu đề cần từ 1 đến 200 ký tự.', 'title');
        }
        if ($body === '' || mb_strlen($body) > 10000) {
            throw new DomainRejection('Nội dung cần từ 1 đến 10000 ký tự.', 'body');
        }

        return [$title, $body];
    }

    private static function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainRejection('Nhập lý do từ 1 đến 500 ký tự.', 'reason');
        }

        return $reason;
    }

    private static function storeImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }
        $path = $image->store('community', 'local');

        return is_string($path) ? $path : null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private static function cleanupOnFail(?string $path, callable $work): mixed
    {
        try {
            return $work();
        } catch (\Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }
}
