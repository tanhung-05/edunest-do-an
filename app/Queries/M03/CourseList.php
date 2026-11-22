<?php

namespace App\Queries\M03;

use App\Enums\M05\CourseVersionStatus;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\M05\CourseVersion;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Danh sách khóa cho vùng nhân sự (MH-GVU-03, MH-GV-01) và nhãn quá hạn (A9, CR-14/116). */
final class CourseList
{
    /** Quá hạn từ 00:00 ngày kế tiếp hạn (giờ Việt Nam) khi bản đầu chưa từng gửi duyệt. */
    public static function overdue(?CarbonImmutable $dueDate, bool $everSubmitted): bool
    {
        if ($dueDate === null || $everSubmitted) {
            return false;
        }
        $today = now()->setTimezone('Asia/Ho_Chi_Minh')->toDateString();

        return $today > $dueDate->toDateString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forAcademic(string $search = ''): array
    {
        return Course::query()->with(['currentAuthor.user:id,name', 'openVersion', 'currentVersion'])
            ->withExists(['versions as ever_submitted' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn (Course $c) => self::row($c))->values()->all();
    }

    /**
     * "Khóa tôi soạn" của Giáo viên.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forAuthor(User $user): array
    {
        $courseIds = CourseAuthor::query()->where('user_id', $user->id)->whereNotNull('current_key')->pluck('course_id');

        return Course::query()->with(['currentAuthor.user:id,name', 'openVersion', 'currentVersion'])
            ->withExists(['versions as ever_submitted' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->whereIn('id', $courseIds)->orderByDesc('id')->get()
            ->map(function (Course $c) {
                $row = self::row($c);
                $open = $c->openVersion;
                if ($open !== null && $open->status === CourseVersionStatus::TraLai) {
                    $row['return_reason'] = $open->reviews()->latest('id')->value('reason');
                }

                return $row;
            })->values()->all();
    }

    /**
     * Hàng chờ duyệt của Giáo vụ (bản `CHO_DUYET`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function reviewQueue(): array
    {
        return CourseVersion::query()->with('course:id,title')->where('status', CourseVersionStatus::ChoDuyet->value)
            ->orderBy('submitted_at')->get()->map(fn (CourseVersion $v) => [
                'version_id' => $v->id, 'course_id' => $v->course_id, 'title' => $v->course->title,
                'version_no' => $v->version_no, 'submitted_at' => $v->submitted_at?->toIso8601String(),
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    public static function row(Course $c): array
    {
        $author = $c->currentAuthor;
        $open = $c->openVersion;
        // P9.1: danh sách nạp sẵn `ever_submitted` bằng withExists; trang chi tiết (một khóa) mới truy vấn riêng.
        $flag = $c->getAttribute('ever_submitted');
        $everSubmitted = $flag !== null ? (bool) $flag : CourseVersion::query()->where('course_id', $c->id)->whereNotNull('submitted_at')->exists();
        $due = $author?->due_date !== null ? CarbonImmutable::parse($author->due_date->toDateString()) : null;

        return [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'price' => $c->price,
            'sale_status' => $c->sale_status->value,
            'sale_status_label' => $c->sale_status->label(),
            'author' => $author?->user?->name,
            'due_date' => $author?->due_date?->toDateString(),
            'overdue' => self::overdue($due, $everSubmitted),
            'open_version' => $open === null ? null : ['id' => $open->id, 'version_no' => $open->version_no, 'status' => $open->status->value, 'status_label' => $open->status->label()],
            'published_version_id' => $c->current_version_id,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Course $c): array
    {
        return [
            ...self::row($c),
            'category_id' => $c->category_id,
            'category' => $c->category->name,
            'level_id' => $c->level_id,
            'level' => $c->level->name,
            'learning_mode' => $c->learning_mode->value,
            'learning_mode_label' => $c->learning_mode->label(),
            'original_price' => $c->original_price,
            'access_months' => $c->access_months,
            'extension_price' => $c->extension_price,
            'tag_ids' => $c->tags()->withTrashed()->pluck('tags.id')->values(),
            'tags' => $c->tags()->withTrashed()->pluck('tags.name')->values(),
            'author_id' => $c->currentAuthor?->user_id,
            'cover' => $c->cover_path !== null ? asset('storage/'.$c->cover_path) : null,
        ];
    }
}
