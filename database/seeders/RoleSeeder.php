<?php

namespace Database\Seeders;

use App\Enums\M01\RoleCode;
use App\Models\M11\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Dữ liệu khởi tạo idempotent: 5 vai (02 L02) và giá trị cấu hình mặc định (02 L36). */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        foreach (RoleCode::cases() as $role) {
            DB::table('roles')->upsert(
                [['code' => $role->value, 'name' => $role->label(), 'created_at' => $now, 'updated_at' => $now]],
                ['code'],
                ['name', 'updated_at'],
            );
        }
        foreach (Setting::DEFAULTS as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
