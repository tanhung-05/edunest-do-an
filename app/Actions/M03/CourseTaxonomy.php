<?php

namespace App\Actions\M03;

use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use App\Support\DomainRejection;

/** Khóa và kiểm hiệu lực nhãn khi gắn mới (05 §1: categories cha → con → levels → tags). */
final class CourseTaxonomy
{
    public static function lockAndCheck(?int $categoryId, ?int $levelId): void
    {
        if ($categoryId !== null) {
            /** @var Category|null $cat */
            $cat = Category::query()->withTrashed()->whereKey($categoryId)->first();
            if ($cat !== null && $cat->parent_id !== null) {
                Category::query()->withTrashed()->whereKey($cat->parent_id)->sharedLock()->first();
            }
            $cat = Category::query()->withTrashed()->whereKey($categoryId)->sharedLock()->first();
            if ($cat === null || $cat->trashed()) {
                throw new DomainRejection('Danh mục không còn hiệu lực.', 'category_id');
            }
            // 02 L10: danh mục cấp 2, hoặc cấp 1 không có con.
            if ($cat->parent_id === null && Category::query()->where('parent_id', $cat->id)->exists()) {
                throw new DomainRejection('Hãy chọn danh mục con.', 'category_id');
            }
        }
        if ($levelId !== null) {
            $level = Level::query()->withTrashed()->whereKey($levelId)->sharedLock()->first();
            if ($level === null || $level->trashed()) {
                throw new DomainRejection('Cấp độ không còn hiệu lực.', 'level_id');
            }
        }
    }

    /**
     * @param  list<int>  $tagIds
     */
    public static function lockTags(array $tagIds, string $field = 'tag_ids'): void
    {
        $ids = array_values(array_unique($tagIds));
        sort($ids);
        if ($ids === []) {
            return;
        }
        $alive = Tag::query()->whereIn('id', $ids)->orderBy('id')->sharedLock()->pluck('id')->all();
        if (count($alive) !== count($ids)) {
            throw new DomainRejection('Có thẻ không còn hiệu lực.', $field);
        }
    }
}
