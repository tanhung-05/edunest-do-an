<?php

namespace App\Enums\M02;

use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use Illuminate\Database\Eloquent\Model;

/** QT-02 — ba loại nhãn nền; giá trị dùng trong URL /admin/taxonomies/{kind}. */
enum TaxonomyKind: string
{
    case Category = 'categories';
    case Level = 'levels';
    case Tag = 'tags';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Danh mục',
            self::Level => 'Cấp độ',
            self::Tag => 'Thẻ',
        };
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Category => Category::class,
            self::Level => Level::class,
            self::Tag => Tag::class,
        };
    }

    public function hasSlug(): bool
    {
        return $this !== self::Level;
    }
}
