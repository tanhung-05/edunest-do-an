<?php

namespace Database\Seeders;

use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Đội ngũ giảng viên mẫu cho trang Giới thiệu (22/11): ảnh chân dung cho 2 giáo viên sẵn có, thêm 4 giáo viên.
 * Ảnh gốc ở database/seeders/anh/giang-vien (Unsplash License), chép sang đĩa public `teacher-photos/`.
 * Chạy lại không tạo trùng, không đè ảnh giáo viên đã tự tải lên.
 */
final class GiangVienDemoSeeder extends Seeder
{
    /** @var list<array{email: string, name: string, photo: string, bio: string, achievements: string}> */
    private const DOI_NGU = [
        [
            'email' => 'hoa.tran@edunest.test', 'name' => 'Trần Minh Hóa', 'photo' => 'tran-minh-hoa',
            'bio' => '12 năm dạy Hóa học THPT, luyện thi đại học.',
            'achievements' => 'Giải ba Hóa học quốc gia; giáo viên dạy giỏi cấp thành phố.',
        ],
        [
            'email' => 'binh.gv@edunest.test', 'name' => 'Đỗ Văn Bình', 'photo' => 'do-van-binh',
            'bio' => '5 năm dạy Vật lý, chuyên phần dao động và sóng.',
            'achievements' => 'Thạc sĩ Vật lý lý thuyết; hướng dẫn đội tuyển học sinh giỏi cấp tỉnh.',
        ],
        [
            'email' => 'thu.ha@edunest.test', 'name' => 'Nguyễn Thu Hà', 'photo' => 'nguyen-thu-ha',
            'bio' => '9 năm dạy Tiếng Anh, luyện IELTS và tiếng Anh thi THPT.',
            'achievements' => 'IELTS 8.5; chứng chỉ giảng dạy CELTA.',
        ],
        [
            'email' => 'hoang.nam@edunest.test', 'name' => 'Lê Hoàng Nam', 'photo' => 'le-hoang-nam',
            'bio' => '7 năm dạy Sinh học, phụ trách phần di truyền và tiến hóa.',
            'achievements' => 'Thạc sĩ Sinh học; giải nhì giáo viên dạy giỏi cấp tỉnh.',
        ],
        [
            'email' => 'ngoc.mai@edunest.test', 'name' => 'Phạm Ngọc Mai', 'photo' => 'pham-ngoc-mai',
            'bio' => '6 năm dạy Địa lý, chuyên kỹ năng biểu đồ và Atlat.',
            'achievements' => 'Tác giả bộ đề luyện thi Địa lý THPT dùng tại nhiều trường.',
        ],
        [
            'email' => 'duc.thanh@edunest.test', 'name' => 'Võ Đức Thành', 'photo' => 'vo-duc-thanh',
            'bio' => '20 năm dạy Lịch sử, nguyên tổ trưởng chuyên môn.',
            'achievements' => 'Nhà giáo ưu tú; đồng tác giả tài liệu ôn thi Lịch sử cấp sở.',
        ],
    ];

    public function run(): void
    {
        // Hồ sơ giáo viên cần người tạo (Quản trị); DemoSeeder chạy trước tạo sẵn tài khoản này.
        $admin = User::query()->where('email', 'quang@edunest.test')->first();
        if ($admin === null) {
            return;
        }

        foreach (self::DOI_NGU as $gv) {
            $user = User::query()->where('email', $gv['email'])->first();
            if ($user === null) {
                $user = new User(['name' => $gv['name'], 'email' => $gv['email'], 'password' => Hash::make(DemoSeeder::PASSWORD)]);
                $user->email_verified_at = now();
                $user->save();
            }
            $user->roles()->syncWithoutDetaching([Role::idFor(RoleCode::GiaoVien) => ['assigned_at' => now()]]);

            $profile = TeacherProfile::query()->firstOrCreate(['user_id' => $user->id], [
                'bio' => $gv['bio'],
                'achievements' => $gv['achievements'],
                'created_by_id' => $admin->id,
            ]);

            // Hồ sơ cũ chỉ có một dòng giới thiệu (Đỗ Văn Bình): bổ sung cho đủ hai ý.
            if ($profile->achievements === null) {
                $profile->bio = $gv['bio'];
                $profile->achievements = $gv['achievements'];
                $profile->save();
            }

            $source = database_path('seeders/anh/giang-vien/'.$gv['photo'].'.jpg');
            if ($profile->photo_path === null && is_file($source)) {
                $path = 'teacher-photos/'.$gv['photo'].'.jpg';
                Storage::disk('public')->put($path, (string) file_get_contents($source));
                $profile->photo_path = $path;
                $profile->save();
            }
        }
    }
}
