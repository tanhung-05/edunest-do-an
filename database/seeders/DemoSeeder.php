<?php

namespace Database\Seeders;

use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seed demo P0 (09 §3 P0.5), chạy lặp an toàn. Mật khẩu chung: "matkhau123".
 * Nhân sự chưa có 2FA → lần đăng nhập đầu phải thiết lập (A6).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'matkhau123';

    public function run(): void
    {
        $admin = $this->user('quang@edunest.test', 'Lê Quang', [RoleCode::QuanTri]);
        $this->user('dung@edunest.test', 'Phạm Dũng', [RoleCode::QuanTri]);
        $this->user('lan.pham@edunest.test', 'Phạm Thị Lan', [RoleCode::GiaoVu]);
        $this->user('minh@edunest.test', 'Ngô Minh', [RoleCode::GiaoVu]);
        $hoa = $this->user('hoa.tran@edunest.test', 'Trần Minh Hóa', [RoleCode::GiaoVien]);
        $binh = $this->user('binh.gv@edunest.test', 'Đỗ Văn Bình', [RoleCode::GiaoVien]);
        $this->user('an.nguyen@edunest.test', 'Nguyễn Văn An', [RoleCode::HocVien]);
        $this->user('chi.le@edunest.test', 'Lê Thị Chi', [RoleCode::HocVien]);

        TeacherProfile::query()->firstOrCreate(['user_id' => $hoa->id], [
            'bio' => '12 năm dạy Hóa học THPT, luyện thi đại học.',
            'achievements' => 'Giải ba Hóa học quốc gia; giáo viên dạy giỏi cấp thành phố.',
            'created_by_id' => $admin->id,
        ]);
        TeacherProfile::query()->firstOrCreate(['user_id' => $binh->id], [
            'bio' => '5 năm dạy Vật lý.',
            'created_by_id' => $admin->id,
        ]);

        $tree = [
            'Khoa học tự nhiên' => ['Hóa học', 'Vật lý', 'Sinh học'],
            'Khoa học xã hội' => ['Lịch sử', 'Địa lý'],
            'Ngoại ngữ' => ['Tiếng Anh'],
        ];
        foreach (array_keys($tree) as $i => $rootName) {
            $root = Category::query()->firstOrCreate(
                ['name' => $rootName, 'parent_id' => null],
                ['slug' => Str::slug($rootName), 'position' => $i],
            );
            foreach ($tree[$rootName] as $j => $child) {
                Category::query()->firstOrCreate(
                    ['name' => $child, 'parent_id' => $root->id],
                    ['slug' => Str::slug($child), 'position' => $j],
                );
            }
        }
        foreach (['Cơ bản', 'Nâng cao', 'Luyện thi'] as $i => $name) {
            Level::query()->firstOrCreate(['name' => $name], ['position' => $i]);
        }
        foreach (['Este', 'Dao động cơ', 'Ngữ pháp', 'THPT quốc gia'] as $name) {
            Tag::query()->firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);
        }
    }

    /** @param list<RoleCode> $roles */
    private function user(string $email, string $name, array $roles): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD)]);
            $user->email_verified_at = now();
            $user->save();
        }
        foreach ($roles as $role) {
            $user->roles()->syncWithoutDetaching([Role::idFor($role) => ['assigned_at' => now()]]);
        }

        return $user;
    }
}
