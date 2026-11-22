<?php

namespace Database\Seeders;

use App\Enums\M03\SaleStatus;
use App\Models\M03\Course;
use App\Models\M24\Affiliate;
use App\Models\M24\Bundle;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu P8 M24 (QT-26): một combo gồm các khóa đang bán (giá bằng 80% tổng giá niêm yết) và hồ sơ cộng tác viên
 * của `chi.le` với mã `chi-le`, tỷ lệ 10%. Ghi thẳng bản ghi phía Giáo vụ vì tài khoản nhân sự mẫu chưa bật 2FA (như ExamDemoSeeder). Chạy lại không tạo trùng.
 */
final class BundleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $academic = User::query()->where('email', 'lan.pham@edunest.test')->first();
        if ($academic === null || Bundle::query()->exists()) {
            return;
        }
        $courses = Course::query()->where('sale_status', SaleStatus::DangBan)->whereNotNull('current_version_id')->orderBy('id')->limit(3)->get();
        if ($courses->count() >= 2) {
            $bundle = Bundle::query()->create(['title' => 'Combo Hóa học trọn bộ', 'slug' => 'combo-hoa-hoc-tron-bo',
                'description' => 'Học liền mạch các khóa Hóa với giá ưu đãi.', 'price' => (int) floor($courses->sum('price') * 0.8 / 1000) * 1000,
                'deduct_owned' => true, 'accepts_orders' => true, 'created_by_id' => $academic->id, 'updated_by_id' => $academic->id]);
            $bundle->courses()->sync($courses->values()->mapWithKeys(fn (Course $c, int $i) => [$c->id => ['position' => $i]])->all());
        }
        $chi = User::query()->where('email', 'chi.le@edunest.test')->first();
        if ($chi !== null && ! Affiliate::query()->where('user_id', $chi->id)->exists()) {
            Affiliate::query()->create(['user_id' => $chi->id, 'code' => 'chi-le', 'rate_bp' => 1000, 'is_active' => true,
                'created_by_id' => $academic->id, 'updated_by_id' => $academic->id]);
        }
    }
}
