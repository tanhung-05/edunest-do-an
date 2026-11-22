<?php

namespace App\Actions\M25;

use App\Actions\M17\Discussions;
use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Enums\M09\Activity;
use App\Enums\M25\AiKind;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M11\Setting;
use App\Models\M17\Discussion;
use App\Models\M25\AiConversation;
use App\Models\M25\AiMessage;
use App\Models\User;
use App\Queries\M06\PublicCatalog;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use App\Support\M09\LearningLocks;
use App\Support\M25\AiProvider;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * QT-27b, 27c (02e §3): hỏi đáp trong bài, chuyển Giáo viên, tư vấn khóa. Lượt tính tại mốc ghi nhận để gửi (CR-170):
 * cùng giao dịch khóa dòng cờ AI (FOR SHARE, tuần tự với lần bật/tắt) rồi dòng hạn mức ngày (FOR UPDATE), ghi lượt hỏi
 * mang khóa yêu cầu và tăng `used`. Gọi nhà cung cấp ngoài giao dịch; lỗi sau mốc giữ lượt, lưu phản hồi `LOI`.
 * Gửi lặp cùng khóa: đã có phản hồi `OK` thì trả lại, chưa có hoặc `LOI` thì xử lý lại không tính thêm lượt.
 */
final class AiAssistant
{
    public function __construct(private AiProvider $provider) {}

    /** QT-27b bước 6–8. */
    public function askLesson(User $student, Enrollment $enrollment, Lesson $lesson, string $question, string $requestKey): AiMessage
    {
        $question = self::question($question);
        self::requestKey($requestKey);

        $ask = Tx::run(function () use ($student, $enrollment, $lesson, $question, $requestKey) {
            [$student, $enrollment] = self::lessonGate($student, $enrollment, $lesson);
            $existing = self::existing($requestKey, $student->id);
            if ($existing !== null) {
                return $existing;
            }
            self::takeTurn('u:'.$student->id, self::limit('ai.daily_limit_user'));
            $conversation = AiConversation::query()->firstOrCreate(
                ['kind' => AiKind::BaiHoc, 'user_id' => $student->id, 'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
            );

            return AiMessage::query()->create([
                'ai_conversation_id' => $conversation->id, 'role' => 'USER', 'content' => $question, 'request_key' => $requestKey,
            ]);
        });

        $done = self::replyOf($ask);
        if ($done !== null && $done->status === 'OK') {
            return $done;
        }

        // Chỉ gửi câu hỏi và nội dung bài (BR-MR-18); không có họ tên, email.
        $lessonText = trim(($lesson->objectives ?? '').' '.($lesson->content ?? ''));
        try {
            $result = $this->provider->answerFromLesson($ask->content, $lessonText);
        } catch (Throwable $e) {
            report($e);

            return self::saveReply($ask, 'AI đang gặp lỗi, bạn thử gửi lại sau. Lượt hỏi này đã được tính.', null, 'LOI');
        }

        return Tx::run(function () use ($student, $enrollment, $lesson, $ask, $result) {
            // Kiểm lại quyền khi trả phản hồi và ghi lịch sử (bước 8).
            self::lessonGate($student, $enrollment, $lesson);

            return self::saveReply($ask, $result['answer'], ['found' => $result['found']], 'OK');
        });
    }

    /** QT-27b bước 9–10, A4: một hội thoại chuyển tối đa một câu hỏi hỏi đáp; chuyển lại trả câu đã tạo. */
    public function handOff(User $student, AiConversation $conversation): Discussion
    {
        return Tx::run(function () use ($student, $conversation) {
            if ($conversation->kind !== AiKind::BaiHoc || $conversation->user_id !== $student->id) {
                throw DomainRejection::forbidden('Không phải hội thoại của bạn.');
            }
            $enrollment = Enrollment::query()->findOrFail($conversation->enrollment_id);
            $lesson = Lesson::query()->findOrFail($conversation->lesson_id);
            [$student, $enrollment] = self::lessonGate($student, $enrollment, $lesson);
            $conversation = AiConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if ($conversation->discussion_id !== null) {
                return Discussion::query()->findOrFail($conversation->discussion_id);
            }
            $last = AiMessage::query()->where('ai_conversation_id', $conversation->id)->where('role', 'USER')->orderByDesc('id')->first();
            if ($last === null) {
                throw new DomainRejection('Hãy hỏi AI trước khi chuyển cho giáo viên.', 'general');
            }
            $discussion = app(Discussions::class)->ask($student, $enrollment, $lesson, $last->content, null);
            $conversation->forceFill(['discussion_id' => $discussion->id])->save();

            return $discussion;
        });
    }

    /**
     * QT-27c bước 11–13. Khách tính lượt theo IP (dạng băm), lịch sử theo khóa phiên; người đăng nhập theo tài khoản.
     */
    public function advise(?User $user, string $guestKey, string $ip, string $question, string $requestKey): AiMessage
    {
        $question = self::question($question);
        self::requestKey($requestKey);
        $owner = $user === null ? ['user_id' => null, 'guest_key' => $guestKey] : ['user_id' => $user->id, 'guest_key' => null];

        $ask = Tx::run(function () use ($user, $owner, $question, $requestKey) {
            if ($user !== null) {
                $locked = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
                if (! $locked->isActive() || $locked->auth_generation !== $user->auth_generation) {
                    throw DomainRejection::forbidden('Phiên đăng nhập không còn hiệu lực.');
                }
            }
            $existing = self::existing($requestKey, $user?->id, $owner['guest_key']);
            if ($existing !== null) {
                return $existing;
            }
            // CR-257: tư vấn chọn khóa không giới hạn lượt theo ngày (chỉ chặn khi AI tắt); chống lạm dụng bằng giới hạn
            // tần suất của tuyến. Hỏi trong bài vẫn theo hạn mức ngày (CR-240).
            self::requireEnabled();
            $conversation = AiConversation::query()->firstOrCreate(['kind' => AiKind::TuVan, ...$owner]);

            return AiMessage::query()->create([
                'ai_conversation_id' => $conversation->id, 'role' => 'USER', 'content' => $question, 'request_key' => $requestKey,
            ]);
        });

        $done = self::replyOf($ask);
        if ($done !== null && $done->status === 'OK') {
            return $done;
        }

        // Ngữ cảnh hộp chat: tối đa 8 lượt trước đó của cùng hội thoại (chỉ nội dung, không định danh).
        $history = array_values(AiMessage::query()->where('ai_conversation_id', $ask->ai_conversation_id)->where('id', '<', $ask->id)
            ->where('status', '!=', 'LOI')->orderByDesc('id')->limit(8)->get(['role', 'content'])->reverse()->values()
            ->map(fn (AiMessage $m) => ['role' => (string) $m->role, 'content' => (string) $m->content])->all());
        try {
            $result = $this->provider->adviseCourses($ask->content, self::catalog(), $history);
        } catch (Throwable $e) {
            report($e);

            return self::saveReply($ask, 'AI đang gặp lỗi, bạn thử gửi lại sau. Lượt hỏi này đã được tính.', null, 'LOI');
        }

        // E7, E14: đối chiếu danh mục ngay lúc trả, chỉ giữ khóa có thật đang bán; trả thẻ khóa (ảnh, giá, giáo viên) để hộp chat
        // hiện luôn khóa học (CR-257).
        $links = PublicCatalog::cardsFor($result['course_ids']);
        // AI có gợi ý mà không khóa nào còn bán thì thay bằng câu chuẩn; AI hỏi lại hoặc từ chối (không gợi ý) thì giữ lời AI.
        $answer = $links === [] && $result['course_ids'] !== []
            ? 'Hiện chưa có khóa đang bán phù hợp với yêu cầu. Bạn thử nêu môn học hoặc lớp cụ thể hơn.'
            : $result['answer'];

        return self::saveReply($ask, $answer, ['courses' => $links], 'OK');
    }

    /** A7: Quản trị bật/tắt AI. Cùng dòng cờ với điểm ghi nhận để gửi (FOR UPDATE ở đây, FOR SHARE ở lượt hỏi). */
    public function setEnabled(User $actor, bool $enabled): Setting
    {
        return Tx::run(function () use ($actor, $enabled) {
            $user = User::query()->whereKey($actor->id)->sharedLock()->firstOrFail();
            if (! $user->hasEffectiveRole(RoleCode::QuanTri) || ! $user->isActive()) {
                throw DomainRejection::forbidden('Chỉ Quản trị đổi cấu hình AI.');
            }
            Setting::query()->insertOrIgnore(['key' => 'ai.enabled', 'value' => json_encode(Setting::DEFAULTS['ai.enabled']), 'created_at' => now(), 'updated_at' => now()]);
            $setting = Setting::query()->where('key', 'ai.enabled')->lockForUpdate()->firstOrFail();
            $old = $setting->value;
            if ($old !== $enabled) {
                $setting->update(['value' => $enabled, 'updated_by_id' => $user->id]);
                Audit::log('AI_ENABLED_SET', $user, $setting, ['value' => $old], ['value' => $enabled]);
            }

            return $setting;
        });
    }

    /**
     * Danh mục gửi AI: khóa đang bán, khóa nhiều học viên trước. Tóm tắt kèm môn, cấp độ, giá để AI tư vấn sát hơn.
     *
     * @return list<array{id: int, title: string, summary: string}>
     */
    public static function catalog(): array
    {
        $out = [];
        $courses = Course::query()->with(['currentVersion:id,summary', 'category:id,name', 'level:id,name'])
            ->select('courses.*')->selectSub(DB::table('enrollments')->selectRaw('count(*)')->whereColumn('enrollments.course_id', 'courses.id'), 'enrollments_count')
            ->where('sale_status', SaleStatus::DangBan->value)
            ->whereNotNull('current_version_id')->orderByDesc('enrollments_count')->orderBy('id')->limit(200)->get();
        foreach ($courses as $c) {
            $facts = array_filter([
                $c->category?->name !== null ? 'Môn: '.$c->category->name : null,
                $c->level?->name !== null ? 'Cấp độ: '.$c->level->name : null,
                'Giá niêm yết: '.number_format($c->price, 0, ',', '.').'đ',
            ]);
            $out[] = ['id' => $c->id, 'title' => $c->title, 'summary' => trim(implode('. ', $facts).'. '.($c->currentVersion->summary ?? ''))];
        }

        return $out;
    }

    /** Lượt đã dùng hôm nay (giờ VN) của một chủ thể. */
    public static function usedToday(string $subject): int
    {
        return (int) DB::table('ai_usage_days')->where('subject', $subject)->where('day', self::today())->value('used');
    }

    public static function limit(string $key): int
    {
        return Setting::number($key);
    }

    /**
     * Bước 7–8: phiên, ghi danh `CON_HAN`, đúng bài của phiên bản ghi danh, bài đã mở (E15/E16/E22/E23/E30).
     *
     * @return array{0: User, 1: Enrollment}
     */
    private static function lessonGate(User $student, Enrollment $enrollment, Lesson $lesson): array
    {
        [$student, $enrollment] = LearningLocks::student($student, $enrollment);
        LearningAccess::authorize($student, $enrollment, Activity::ViewLesson, $lesson);
        Discussions::requireActive($enrollment);

        return [$student, $enrollment];
    }

    /** Cờ AI dưới khóa đọc (tuần tự với lần bật/tắt của Quản trị). */
    private static function requireEnabled(): void
    {
        $flag = Setting::query()->where('key', 'ai.enabled')->sharedLock()->first();
        if (($flag === null ? Setting::DEFAULTS['ai.enabled'] : $flag->value) !== true) {
            throw new DomainRejection('Trợ lý AI đang tắt.', 'question');
        }
    }

    /** CR-170: dưới khóa dòng cờ và dòng hạn mức; AI tắt (X2) hoặc hết lượt (X3) thì 0 lượt. */
    private static function takeTurn(string $subject, int $limit): void
    {
        $flag = Setting::query()->where('key', 'ai.enabled')->sharedLock()->first();
        $enabled = $flag === null ? Setting::DEFAULTS['ai.enabled'] : $flag->value;
        if ($enabled !== true) {
            throw new DomainRejection('Trợ lý AI đang tắt.', 'question');
        }
        $day = self::today();
        DB::table('ai_usage_days')->insertOrIgnore(['subject' => $subject, 'day' => $day, 'used' => 0]);
        $row = DB::table('ai_usage_days')->where('subject', $subject)->where('day', $day)->lockForUpdate()->first();
        $used = (int) ($row->used ?? 0);
        if ($used >= $limit) {
            throw new DomainRejection('Bạn đã dùng hết '.$limit.' lượt hỏi AI hôm nay.', 'question');
        }
        DB::table('ai_usage_days')->where('subject', $subject)->where('day', $day)->update(['used' => $used + 1]);
    }

    /** Gửi lặp cùng khóa: chỉ chủ của lượt hỏi được dùng lại; khóa của người khác bị từ chối. */
    private static function existing(string $requestKey, ?int $userId, ?string $guestKey = null): ?AiMessage
    {
        $ask = AiMessage::query()->where('request_key', $requestKey)->first();
        if ($ask === null) {
            return null;
        }
        $conversation = AiConversation::query()->findOrFail($ask->ai_conversation_id);
        $mine = $userId !== null ? $conversation->user_id === $userId : ($conversation->user_id === null && $conversation->guest_key === $guestKey);
        if (! $mine) {
            throw DomainRejection::forbidden('Khóa yêu cầu không hợp lệ.');
        }

        return $ask;
    }

    private static function replyOf(AiMessage $ask): ?AiMessage
    {
        return AiMessage::query()->where('reply_to_id', $ask->id)->first();
    }

    /** @param  ?array<string, mixed>  $meta */
    private static function saveReply(AiMessage $ask, string $content, ?array $meta, string $status): AiMessage
    {
        return Tx::run(fn () => self::writeReply($ask, $content, $meta, $status));
    }

    /** @param  ?array<string, mixed>  $meta */
    private static function writeReply(AiMessage $ask, string $content, ?array $meta, string $status): AiMessage
    {
        $reply = AiMessage::query()->where('reply_to_id', $ask->id)->lockForUpdate()->first();
        $data = ['content' => $content, 'meta' => $meta, 'status' => $status];
        if ($reply !== null) {
            $reply->forceFill($data)->save();

            return $reply;
        }

        return AiMessage::query()->create([...$data, 'ai_conversation_id' => $ask->ai_conversation_id, 'role' => 'AI', 'reply_to_id' => $ask->id]);
    }

    private static function question(string $question): string
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > 1000) {
            throw new DomainRejection('Câu hỏi cần từ 1 đến 1000 ký tự.', 'question');
        }

        return $question;
    }

    private static function requestKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $key) !== 1) {
            throw new DomainRejection('Khóa yêu cầu không hợp lệ.', 'question');
        }
    }

    private static function today(): string
    {
        return CarbonImmutable::now('Asia/Ho_Chi_Minh')->toDateString();
    }
}
