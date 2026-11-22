<?php

namespace App\Queries\M05;

use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;

/** CR-196: so bản đang xét với bản đang công bố theo khóa bài: thêm, bỏ, đổi nội dung (dấu vân tay), đổi tên. */
final class VersionDiff
{
    /** @return array{base: ?int, added: list<string>, removed: list<string>, changed: list<string>, renamed: list<array{from: string, to: string}>}|null */
    public static function against(CourseVersion $version): ?array
    {
        $currentId = $version->course()->value('current_version_id');
        if ($currentId === null || $currentId === $version->id) {
            return null;
        }
        $base = CourseVersion::query()->whereKey($currentId)->first();
        if ($base === null) {
            return null;
        }
        $old = Lesson::query()->where('course_version_id', $base->id)->whereNull('removed_at')->orderBy('position')->get()->keyBy('lesson_key');
        $new = Lesson::query()->where('course_version_id', $version->id)->whereNull('removed_at')->orderBy('position')->get()->keyBy('lesson_key');
        $out = ['base' => $base->version_no, 'added' => [], 'removed' => [], 'changed' => [], 'renamed' => []];
        foreach ($new as $key => $lesson) {
            $prev = $old->get($key);
            if ($prev === null) {
                $out['added'][] = $lesson->title;

                continue;
            }
            if ($prev->content_fingerprint !== $lesson->content_fingerprint) {
                $out['changed'][] = $lesson->title;
            }
            if ($prev->title !== $lesson->title) {
                $out['renamed'][] = ['from' => $prev->title, 'to' => $lesson->title];
            }
        }
        foreach ($old as $key => $lesson) {
            if (! $new->has($key)) {
                $out['removed'][] = $lesson->title;
            }
        }

        return $out;
    }
}
