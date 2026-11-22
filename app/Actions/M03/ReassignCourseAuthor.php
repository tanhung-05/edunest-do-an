<?php

namespace App\Actions\M03;

use App\Enums\M01\RoleCode;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\CourseLocks;
use App\Support\Tx;

/** QT-03 A2 (NH-63): giao lại; người cũ mất quyền soạn ngay, bản nháp giữ nguyên, lịch sử giữ dòng cũ. */
final class ReassignCourseAuthor
{
    public function handle(User $actor, Course $course, int $authorId, ?string $dueDate = null): Course
    {
        return Tx::run(function () use ($actor, $course, $authorId, $dueDate) {
            $course = CourseLocks::course($course->id);
            $author = User::query()->whereKey($authorId)->first();
            if ($author === null || ! $author->hasRole(RoleCode::GiaoVien) || ! $author->isActive()) {
                throw new DomainRejection('Người soạn phải là tài khoản Giáo viên đang hoạt động.', 'author_id');
            }
            /** @var CourseAuthor|null $current */
            $current = CourseAuthor::query()->where('course_id', $course->id)->whereNotNull('current_key')->first();
            if ($current !== null && $current->user_id === $author->id) {
                throw new DomainRejection('Người này đang là người soạn của khóa.', 'author_id');
            }
            $current?->forceFill(['ended_at' => now()])->save();
            CourseAuthor::query()->create([
                'course_id' => $course->id,
                'user_id' => $author->id,
                'due_date' => $dueDate ?? $current?->due_date?->toDateString(),
                'assigned_by_id' => $actor->id,
                'assigned_at' => now(),
            ]);
            Audit::log('COURSE_REASSIGN', $actor, $course, ['author_id' => $current?->user_id], ['author_id' => $author->id]);

            return $course;
        });
    }
}
