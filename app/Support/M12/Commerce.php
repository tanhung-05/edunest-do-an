<?php

namespace App\Support\M12;

use App\Models\M03\Course;
use App\Models\M12\CoursePromotion;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Tiện ích tiền và ngày dùng chung cho QT-13/14 (02b §0, ADR-003, CR-58/121/122/124).
 */
final class Commerce
{
    public const TZ = 'Asia/Ho_Chi_Minh';

    /** CR-122: bỏ khoảng trắng hai đầu, so không phân biệt hoa thường, lưu chữ hoa. */
    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /** 00:00 giờ Việt Nam của ngày `Y-m-d`, đổi sang UTC. */
    public static function vnDayStart(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::TZ)->startOfDay()->utc();
    }

    /** Cận trên loại trừ: 00:00 VN ngày kế ngày cuối (gồm trọn ngày cuối, CR-58/121). */
    public static function vnDayEndExclusive(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::TZ)->startOfDay()->addDay()->utc();
    }

    /**
     * Giá hiệu lực của khóa tại thời điểm: đợt khuyến mãi đang chạy, nếu không thì giá bán (BR-TT-61).
     *
     * @return array{0: int, 1: ?CoursePromotion}
     */
    public static function effectivePrice(Course $course, ?CarbonImmutable $at = null): array
    {
        if ($at === null && $course->relationLoaded(self::PRIMED)) {
            // Chỉ trang hiển thị nạp sẵn (primePromotions); luồng đặt mua luôn truyền `$at` và đọc lại dưới khóa.
            $promotion = $course->getRelation(self::PRIMED);
        } else {
            $at ??= now();
            $promotion = CoursePromotion::query()->where('course_id', $course->id)
                ->where('starts_at', '<=', $at)->where('ends_at', '>', $at)->orderBy('id')->first();
        }

        return $promotion !== null && $promotion->price < $course->price ? [$promotion->price, $promotion] : [$course->price, null];
    }

    public const PRIMED = 'primedPromotion';

    /**
     * P9.1: nạp sẵn đợt khuyến mãi đang chạy cho danh sách khóa bằng một truy vấn, cùng luật với `effectivePrice`
     * (đợt có id nhỏ nhất đang hiệu lực). Chỉ cho trang hiển thị.
     *
     * @param  iterable<Course>  $courses
     */
    public static function primePromotions(iterable $courses): void
    {
        $list = [];
        foreach ($courses as $course) {
            $list[] = $course;
        }
        if ($list === []) {
            return;
        }
        $at = now();
        $active = CoursePromotion::query()->whereIn('course_id', array_map(fn (Course $c) => $c->id, $list))
            ->where('starts_at', '<=', $at)->where('ends_at', '>', $at)->orderBy('id')->get();
        $first = [];
        foreach ($active as $promotion) {
            $first[$promotion->course_id] ??= $promotion;
        }
        foreach ($list as $course) {
            $course->setRelation(self::PRIMED, $first[$course->id] ?? null);
        }
    }

    /**
     * CR-124: chia tổng giảm theo tỷ lệ giá cơ sở, làm tròn xuống, đồng dư cho phần lẻ lớn nhất, hòa theo thứ tự.
     * Tích tiền × giá có thể vượt PHP_INT_MAX nên dùng BigInteger.
     *
     * @param  array<int, int>  $bases  khóa → giá cơ sở (đã xếp theo thứ tự dòng)
     * @return array<int, int>
     */
    public static function allocate(int $total, array $bases): array
    {
        $sum = array_sum($bases);
        $result = array_fill_keys(array_keys($bases), 0);
        if ($sum === 0 || $total === 0) {
            return $result;
        }
        $total = min($total, $sum);
        $remainders = [];
        $order = 0;
        foreach ($bases as $key => $base) {
            $product = BigInteger::of($total)->multipliedBy($base);
            $quotient = $product->dividedBy($sum, RoundingMode::Down);
            $result[$key] = $quotient->toInt();
            $remainders[] = [$key, $product->mod($sum)->toInt(), $order++];
        }
        $left = $total - array_sum($result);
        usort($remainders, fn ($a, $b) => $b[1] <=> $a[1] ?: $a[2] <=> $b[2]);
        foreach ($remainders as [$key]) {
            if ($left <= 0) {
                break;
            }
            if ($result[$key] < $bases[$key]) {
                $result[$key]++;
                $left--;
            }
        }

        return $result;
    }

    /** Hiển thị VND: 499.000đ. */
    public static function vnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }
}
