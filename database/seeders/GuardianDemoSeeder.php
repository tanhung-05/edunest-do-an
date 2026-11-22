<?php

namespace Database\Seeders;

use App\Actions\M23\GuardianLinks;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\M23\GuardianLink;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dữ liệu mẫu P8 M23 (QT-25): tài khoản Phụ huynh `phu.huynh@edunest.test` liên kết với `an.nguyen` qua mã mời thật;
 * `dang-hoc.p4` có một mã mời còn hạn để thử đăng ký Phụ huynh. Chạy lại không tạo trùng.
 */
final class GuardianDemoSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'an.nguyen@edunest.test')->first();
        if ($student === null || GuardianLink::query()->where('student_id', $student->id)->exists()) {
            return;
        }
        $guardian = User::query()->where('email', 'phu.huynh@edunest.test')->first();
        if ($guardian === null) {
            $guardian = new User(['name' => 'Nguyễn Văn Phụ', 'email' => 'phu.huynh@edunest.test', 'password' => Hash::make(DemoSeeder::PASSWORD)]);
            $guardian->status = AccountStatus::HoatDong;
            $guardian->email_verified_at = now();
            $guardian->save();
            $guardian->roles()->syncWithoutDetaching([Role::idFor(RoleCode::PhuHuynh) => ['assigned_at' => now()]]);
        }
        $links = app(GuardianLinks::class);
        $links->link($guardian, $links->issue($student)->code);

        $other = User::query()->where('email', 'dang-hoc.p4@edunest.test')->first();
        if ($other !== null) {
            $links->issue($other);
        }
    }
}
