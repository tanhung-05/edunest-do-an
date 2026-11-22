<?php

use App\Enums\M01\RoleCode;
use Database\Seeders\GiangVienDemoSeeder;
use Illuminate\Support\Facades\Storage;

pest()->group('P10');

it('22/11: Khách xem trang Giới thiệu thấy đội ngũ giảng viên có ảnh; seeder chạy lại không tạo trùng', function () {
    Storage::fake('public');
    taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $this->seed(GiangVienDemoSeeder::class);
    $this->seed(GiangVienDemoSeeder::class);

    $this->get(route('public.about'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('public/about')
            ->has('teachers', 6)
            ->where('stats.teachers', 6)
            ->where('teachers.0.name', 'Trần Minh Hóa')
            ->where('teachers.0.photo', fn ($url) => str_contains((string) $url, 'teacher-photos/tran-minh-hoa.jpg'))
            ->whereNot('teachers.1.achievements', null));

    Storage::disk('public')->assertExists('teacher-photos/vo-duc-thanh.jpg');
});
