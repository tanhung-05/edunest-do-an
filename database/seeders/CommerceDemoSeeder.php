<?php

namespace Database\Seeders;

use App\Actions\M13\ActivationCodes;
use App\Enums\M03\SaleStatus;
use App\Enums\M12\CouponScope;
use App\Enums\M12\DiscountType;
use App\Enums\M13\ActivationCodeStatus;
use App\Models\M03\Course;
use App\Models\M12\Coupon;
use App\Models\M12\CoursePromotion;
use App\Models\M13\ActivationCode;
use App\Models\M13\ActivationCodeBatch;
use App\Models\User;
use App\Support\M12\Commerce;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu P5 (QT-13/14): một đợt khuyến mãi, hai mã giảm giá, một lô mã kích hoạt. Ghi thẳng bản ghi vì tài khoản
 * Giáo vụ mẫu chưa bật 2FA nên chưa có vai hiệu lực; chạy lại không tạo trùng.
 */
final class CommerceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lan = User::query()->where('email', 'lan.pham@edunest.test')->first();
        $courses = Course::query()->where('sale_status', SaleStatus::DangBan)->whereNotNull('current_version_id')
            ->orderBy('id')->get()->values();
        if ($lan === null || $courses->count() < 2) {
            return;
        }
        $today = now()->setTimezone(Commerce::TZ);

        $promoCourse = $courses->last();
        if ($promoCourse !== null && $promoCourse->price >= 2000 && ! CoursePromotion::query()->where('course_id', $promoCourse->id)->exists()) {
            $from = $today->format('Y-m-d');
            $to = $today->addDays(10)->format('Y-m-d');
            CoursePromotion::query()->create([
                'course_id' => $promoCourse->id, 'price' => intdiv($promoCourse->price * 7, 10 * 1000) * 1000,
                'starts_on' => $from, 'ends_on' => $to, 'starts_at' => Commerce::vnDayStart($from), 'ends_at' => Commerce::vnDayEndExclusive($to),
                'created_by_id' => $lan->id, 'updated_by_id' => $lan->id,
            ]);
        }

        $from = $today->subDay()->format('Y-m-d');
        $to = $today->addDays(30)->format('Y-m-d');
        foreach ([
            ['code' => 'CHAOMUNG20', 'discount_type' => DiscountType::PhanTram, 'value' => 20, 'max_discount' => 150000, 'min_order' => 0, 'note' => 'Mã chào mừng học viên mới'],
            ['code' => 'GIAM50K', 'discount_type' => DiscountType::SoTien, 'value' => 50000, 'max_discount' => null, 'min_order' => 300000, 'note' => 'Giảm 50.000đ cho đơn từ 300.000đ'],
        ] as $coupon) {
            Coupon::query()->firstOrCreate(['code' => $coupon['code']], $coupon + [
                'starts_on' => $from, 'ends_on' => $to, 'starts_at' => Commerce::vnDayStart($from), 'ends_at' => Commerce::vnDayEndExclusive($to),
                'total_limit' => 100, 'per_user_limit' => 1, 'scope_type' => CouponScope::ToanBo, 'created_by_id' => $lan->id,
            ]);
        }

        if (! ActivationCodeBatch::query()->exists()) {
            $course = $courses[1];
            $batch = ActivationCodeBatch::query()->create([
                'course_id' => $course->id, 'quantity' => 20, 'redeem_until' => $today->addMonths(3)->endOfDay()->utc(),
                'access_months' => $course->access_months, 'note' => 'Tặng kèm sách tóm tắt công thức bản in', 'created_by_id' => $lan->id,
            ]);
            $codes = [];
            while (count($codes) < 20) {
                $codes[ActivationCodes::generate()] = true;
            }
            foreach (array_keys($codes) as $code) {
                ActivationCode::query()->create(['batch_id' => $batch->id, 'code' => $code, 'status' => ActivationCodeStatus::ChuaDung]);
            }
        }
    }
}
