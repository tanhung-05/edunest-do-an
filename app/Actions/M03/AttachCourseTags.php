<?php

namespace App\Actions\M03;

use App\Models\M03\Course;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\CourseEnrollments;
use App\Support\M03\CourseLocks;
use App\Support\Tx;

/** NH-62: Giáo vụ đặt tập thẻ của khóa; khóa courses → tags, kiểm thẻ mới còn hiệu lực (CR-09). */
final class AttachCourseTags
{
    /**
     * @param  list<int>  $tagIds
     */
    public function handle(User $actor, Course $course, array $tagIds): Course
    {
        return Tx::run(function () use ($actor, $course, $tagIds) {
            $course = CourseLocks::course($course->id);
            /** @var list<int> $current */
            $current = $course->tags()->withTrashed()->pluck('tags.id')->map(fn ($v) => (int) $v)->sort()->values()->all();
            $wanted = array_values(array_unique(array_map('intval', $tagIds)));
            sort($wanted);
            if ($current === $wanted) {
                return $course;
            }
            if (CourseEnrollments::exists($course->id)) {
                throw new DomainRejection('Khóa đã có ghi danh: chỉ sửa được giá.', 'tag_ids');
            }
            CourseTaxonomy::lockTags(array_values(array_diff($wanted, $current)));
            $course->tags()->sync($wanted);
            Audit::log('COURSE_TAGS', $actor, $course, ['tag_ids' => $current], ['tag_ids' => $wanted]);

            return $course;
        });
    }
}
