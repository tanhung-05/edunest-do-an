<?php

namespace App\Actions\M03;

use App\Models\M03\Course;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;

/** CR-67: Giáo vụ đặt hoặc bỏ một khóa tiên quyết; không tự trỏ, không tạo vòng. */
final class SetCoursePrerequisite
{
    public function handle(User $actor, Course $course, ?int $prerequisiteId): Course
    {
        return Tx::run(function () use ($actor, $course, $prerequisiteId) {
            OrderLocks::academic(User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail());
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($prerequisiteId !== null) {
                if ($prerequisiteId === $course->id || ! Course::query()->whereKey($prerequisiteId)->exists()) {
                    throw new DomainRejection('Khóa tiên quyết không hợp lệ.', 'prerequisite_course_id');
                }
                $seen = [$course->id => true];
                for ($id = $prerequisiteId; $id !== null; $id = Course::query()->whereKey($id)->value('prerequisite_course_id')) {
                    if (isset($seen[$id])) {
                        throw new DomainRejection('Chọn khóa này sẽ tạo vòng tiên quyết.', 'prerequisite_course_id');
                    }
                    $seen[$id] = true;
                }
            }
            $old = $course->prerequisite_course_id;
            $course->forceFill(['prerequisite_course_id' => $prerequisiteId])->save();
            Audit::log('COURSE_PREREQUISITE', $actor, $course, ['prerequisite_course_id' => $old], ['prerequisite_course_id' => $prerequisiteId]);

            return $course;
        });
    }
}
