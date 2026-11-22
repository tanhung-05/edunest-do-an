<?php

namespace App\Support\M03;

use App\Models\M03\Course;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * URL ảnh bìa khóa học: ảnh giáo viên tải lên; nếu chưa có thì ảnh minh họa thật theo môn học
 * (ảnh Unsplash License trong public/images/stock, xem NGUON.md). Giao diện v5.
 */
final class CourseCoverUrl
{
    /** Từ khóa trong tên danh mục (không dấu) → ảnh. */
    private const BY_SUBJECT = [
        'hoa' => ['hoa-ong-nghiem', 'hoa-pipette'],
        'anh' => ['anh-sach', 'anh-sach-2'],
        'sinh' => ['sinh-kinh-hien-vi', 'sinh-te-bao'],
        'dia' => ['dia-qua-cau', 'dia-qua-cau-2'],
        'su' => ['su-sach-co', 'su-trang-sach'],
        'toan' => ['toan-bang'],
        'vat ly' => ['toan-bang', 'hoc-dem'],
        'van' => ['viet-sach'],
    ];

    private const GENERAL = ['hoc-laptop', 'nhom-macbook', 'thu-vien', 'viet-sach', 'doc-sach-ngoai-troi'];

    public static function for(Course $course, ?string $categoryName = null): string
    {
        if ($course->cover_path !== null) {
            return Storage::disk('public')->url($course->cover_path);
        }

        return self::forSubject($categoryName ?? $course->category->name, $course->id);
    }

    /** Ảnh minh họa thật cho một môn (tên danh mục); $seed chọn ảnh trong nhóm cùng môn. */
    public static function forSubject(string $categoryName, int $seed = 0): string
    {
        $name = Str::of($categoryName)->ascii()->lower()->value();
        $pool = self::GENERAL;
        foreach (self::BY_SUBJECT as $key => $files) {
            if (preg_match('/(^|\s)'.$key.'(\s|$)/', $name) === 1) {
                $pool = $files;
                break;
            }
        }

        return asset('images/stock/'.$pool[$seed % count($pool)].'.jpg');
    }
}
