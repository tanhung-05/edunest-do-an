<?php

namespace App\Actions\M02;

use App\Enums\M02\TaxonomyKind;
use Illuminate\Support\Facades\DB;

/**
 * Đếm liên kết đang giữ nhãn (kể cả nhãn đã xóa mềm): khóa học theo danh mục/cấp độ,
 * course_tag và question_tag cho thẻ (BR-HT-22, CR-09).
 */
final class TaxonomyUsage
{
    /** @return array<string, int> tên liên kết → số lượng */
    public static function links(TaxonomyKind $kind, int $id): array
    {
        return match ($kind) {
            TaxonomyKind::Category => ['khóa học' => DB::table('courses')->where('category_id', $id)->count()],
            TaxonomyKind::Level => ['khóa học' => DB::table('courses')->where('level_id', $id)->count()],
            TaxonomyKind::Tag => [
                'khóa học' => DB::table('course_tag')->where('tag_id', $id)->count(),
                'câu hỏi' => DB::table('question_tag')->where('tag_id', $id)->count(),
            ],
        };
    }
}
