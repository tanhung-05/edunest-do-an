<?php

namespace Tests\Support;

use App\Actions\M12\SaveCoupon;
use App\Models\M03\Course;
use App\Models\M12\Coupon;
use App\Models\User;

/** Dữ liệu nền D13 (QT-13) và tiện ích P5. Giờ mặc định 10:00 20/11/2026 giờ Việt Nam. */
final class ThuongMai
{
    /** @return array<string, mixed> */
    public static function d13(): array
    {
        $d = MuaHang::d7();
        MuaHang::luc('2026-11-20 10:00:00');
        foreach (['k1', 'k2', 'k3'] as $k) {
            $d[$k]->forceFill(['access_months' => 12])->save();
        }

        return $d;
    }

    /** @param array<string, mixed> $over */
    public static function ma(User $giaoVu, array $over = []): Coupon
    {
        return app(SaveCoupon::class)->handle($giaoVu, $over + [
            'code' => 'HOA12', 'discount_type' => 'PHAN_TRAM', 'value' => 20, 'max_discount' => 100000,
            'starts_on' => '2026-11-15', 'ends_on' => '2026-11-30', 'total_limit' => 100, 'per_user_limit' => 1,
            'scope_type' => 'TOAN_BO', 'scope_category_id' => null, 'scope_course_id' => null, 'min_order' => 300000, 'note' => null,
        ]);
    }

    public static function gia(Course $course, int $price): Course
    {
        $course->forceFill(['price' => $price])->save();

        return $course->fresh() ?? $course;
    }
}
