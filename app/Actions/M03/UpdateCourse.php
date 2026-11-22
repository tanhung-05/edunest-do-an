<?php

namespace App\Actions\M03;

use App\Enums\M03\LearningMode;
use App\Models\M03\Course;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\CourseEnrollments;
use App\Support\M03\CourseLocks;
use App\Support\Tx;

/**
 * QT-03 A7 (NH-62, CR-21): sửa trường khóa. Khi đã có ghi danh chỉ sửa giá (UpdateCoursePricing).
 * Giá, thẻ, ảnh bìa đi qua Action riêng.
 */
final class UpdateCourse
{
    /**
     * @param  array{title?: string, category_id?: int, level_id?: int, learning_mode?: LearningMode, access_months?: int|null, due_date?: string|null}  $data
     */
    public function handle(User $actor, Course $course, array $data): Course
    {
        return Tx::run(function () use ($actor, $course, $data) {
            $course = CourseLocks::course($course->id);
            $old = $course->only(array_keys($data));
            $changes = array_filter($data, fn ($v, $k) => $k !== 'due_date' && self::differs($course->getAttribute($k), $v), ARRAY_FILTER_USE_BOTH);
            if ($changes !== [] && CourseEnrollments::exists($course->id)) {
                throw new DomainRejection('Khóa đã có ghi danh: chỉ sửa được giá.', 'general');
            }
            CourseTaxonomy::lockAndCheck(
                array_key_exists('category_id', $changes) ? $data['category_id'] ?? null : null,
                array_key_exists('level_id', $changes) ? $data['level_id'] ?? null : null,
            );
            if (array_key_exists('access_months', $changes) && $changes['access_months'] === null && $course->extension_price !== null) {
                throw new DomainRejection('Khóa vĩnh viễn không bán gia hạn: hãy bỏ giá gia hạn trước.', 'access_months');
            }
            if (isset($changes['title'])) {
                $changes['title'] = trim($changes['title']);
            }
            $course->forceFill($changes)->save();
            if (array_key_exists('due_date', $data)) {
                $course->currentAuthor()->update(['due_date' => $data['due_date']]);
            }
            if ($changes !== []) {
                Audit::log('COURSE_UPDATE', $actor, $course, self::plain($old, array_keys($changes)), self::plain($changes, array_keys($changes)));
            }

            return $course;
        });
    }

    private static function differs(mixed $current, mixed $new): bool
    {
        $c = $current instanceof \BackedEnum ? $current->value : $current;
        $n = $new instanceof \BackedEnum ? $new->value : $new;

        return (string) $c !== (string) $n || ($c === null) !== ($n === null);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function plain(array $values, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $v = $values[$k] ?? null;
            $out[$k] = $v instanceof \BackedEnum ? $v->value : $v;
        }

        return $out;
    }
}
