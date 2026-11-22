<?php

namespace App\Actions\M03;

use App\Support\DomainRejection;

/** CR-13, BR-TT-79, BR-QH-30: kiểm bộ giá kết quả (L10 CHECK là lưới an toàn cuối). */
final class CoursePricing
{
    public const MAX = 100_000_000_000;

    public static function validate(int $price, ?int $original, ?int $accessMonths, ?int $extension): void
    {
        if ($price <= 0 || $price > self::MAX) {
            throw new DomainRejection('Giá bán phải lớn hơn 0đ.', 'price');
        }
        if ($original !== null && ($original < $price || $original > self::MAX)) {
            throw new DomainRejection('Giá gốc phải lớn hơn hoặc bằng giá bán.', 'original_price');
        }
        if ($extension !== null && $accessMonths === null) {
            throw new DomainRejection('Khóa vĩnh viễn không bán gia hạn.', 'extension_price');
        }
        if ($extension !== null && ($extension <= 0 || $extension > self::MAX)) {
            throw new DomainRejection('Giá gia hạn phải lớn hơn 0đ.', 'extension_price');
        }
    }
}
