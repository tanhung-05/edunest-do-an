<?php

namespace Tests\Support;

use App\Actions\M03\ChangeSaleStatus;
use App\Enums\M01\RoleCode;
use App\Enums\M05\LessonType;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Bộ dữ liệu D6 của QT-06: K1, K2 (Hóa, T1 "Thầy Minh"), K3 (Vật lý, T2) đang bán; K4 công bố chưa bán;
 * K5 "Hóa 10" ngừng bán; K6 bản nháp. K1 có 12 bài video, tổng 190 phút, bài 1 xem thử, bài 3 có tài nguyên.
 */
final class CongKhai
{
    /** @return array<string, mixed> */
    public static function d6(): array
    {
        Storage::fake('local');
        $lan = self::nv('lan.pham@edunest.test', RoleCode::GiaoVu);
        $minh = self::nv('minh.gv@edunest.test', RoleCode::GiaoVu);
        $t1 = self::nv('thay.minh@edunest.test', RoleCode::GiaoVien, 'Thầy Minh');
        $t2 = self::nv('co.lan@edunest.test', RoleCode::GiaoVien, 'Cô Hạnh');
        $tn = Category::query()->firstOrCreate(['parent_id' => null, 'name' => 'Khoa học tự nhiên'], ['slug' => 'khoa-hoc-tu-nhien', 'position' => 1]);
        $hoa = Category::query()->firstOrCreate(['parent_id' => $tn->id, 'name' => 'Hóa học'], ['slug' => 'hoa-hoc', 'position' => 1]);
        $ly = Category::query()->firstOrCreate(['parent_id' => $tn->id, 'name' => 'Vật lý'], ['slug' => 'vat-ly', 'position' => 2]);
        $coBan = Level::query()->firstOrCreate(['name' => 'Cơ bản'], ['position' => 1]);
        $nangCao = Level::query()->firstOrCreate(['name' => 'Nâng cao'], ['position' => 2]);

        $k1 = self::khoa($lan, $t1, $minh, ['title' => 'Hóa 12 nền tảng', 'category_id' => $hoa->id, 'level_id' => $coBan->id, 'price' => 499000, 'original_price' => 699000], '2026-11-01', true, true);
        $k2 = self::khoa($lan, $t1, $minh, ['title' => 'Hóa 12 nâng cao', 'category_id' => $hoa->id, 'level_id' => $nangCao->id, 'price' => 799000], '2026-11-10', true);
        $k3 = self::khoa($lan, $t2, $minh, ['title' => 'Vật lý 12', 'category_id' => $ly->id, 'level_id' => $coBan->id, 'price' => 399000, 'original_price' => 399000], '2026-11-05', true);
        $k4 = self::khoa($lan, $t1, $minh, ['title' => 'Hóa 11 chuyên đề', 'category_id' => $hoa->id, 'level_id' => $nangCao->id, 'price' => 599000], '2026-11-08', false);
        $k5 = self::khoa($lan, $t1, $minh, ['title' => 'Hóa 10', 'category_id' => $hoa->id, 'level_id' => $nangCao->id, 'price' => 299000], '2026-10-20', true);
        app(ChangeSaleStatus::class)->stop($lan, $k5);
        $k6 = KhoaHoc::giao($lan, $t1, ['title' => 'Hóa 9 ôn thi', 'category_id' => $hoa->id, 'level_id' => $nangCao->id]);

        return ['lan' => $lan, 'minh' => $minh, 't1' => $t1, 't2' => $t2, 'hoa' => $hoa, 'ly' => $ly, 'tn' => $tn, 'coBan' => $coBan, 'nangCao' => $nangCao,
            'k1' => $k1->fresh(), 'k2' => $k2->fresh(), 'k3' => $k3->fresh(), 'k4' => $k4->fresh(), 'k5' => $k5->fresh(), 'k6' => $k6->fresh()];
    }

    public static function nv(string $email, RoleCode $role, ?string $name = null): User
    {
        $u = User::query()->where('email', $email)->first() ?? taiKhoan($email, $role);
        if ($name !== null) {
            $u->forceFill(['name' => $name])->save();
        }

        return $u;
    }

    /**
     * Khóa đã công bố bản 1 (bài 1 "Mở đầu" xem thử), có thể bật bán. K1 dựng đủ 12 bài video theo D6.
     *
     * @param  array<string, mixed>  $over
     */
    public static function khoa(User $lan, User $author, User $duyet, array $over, string $publishedOn, bool $ban, bool $k1 = false): Course
    {
        $k = KhoaHoc::giao($lan, $author, $over);
        $v = KhoaHoc::banMo($k);
        $s = KhoaHoc::chuong($v);
        if ($k1) {
            $mins = [10, 12, 17, 17, 17, 17, 17, 17, 17, 17, 17, 15];
            foreach ($mins as $i => $m) {
                $path = 'lessons/video/k1-'.($i + 1).'.mp4';
                Storage::disk('local')->put($path, str_repeat('v', 2048));
                $l = KhoaHoc::bai($s, $i === 0 ? 'Mở đầu' : 'Bài '.($i + 1), LessonType::Video, [
                    'video_path' => $path, 'video_duration_ms' => $m * 60000, 'is_preview' => $i === 0,
                    'objectives' => 'Mục tiêu bài '.($i + 1),
                ]);
                if ($i === 2) {
                    Storage::disk('local')->put('lessons/resources/de3.pdf', '%PDF-1.4');
                    LessonResource::query()->create(['lesson_id' => $l->id, 'title' => 'Đề bài 3', 'file_path' => 'lessons/resources/de3.pdf', 'original_name' => 'de3.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 8, 'position' => 1]);
                }
            }
        } else {
            KhoaHoc::bai($s, 'Mở đầu', LessonType::BaiDoc, ['is_preview' => true]);
            KhoaHoc::bai($s, 'Bài 2');
        }
        KhoaHoc::guiDuyet($v, $author);
        KhoaHoc::duyet($v, $duyet);
        Carbon::setTestNow(Carbon::parse($publishedOn.' 09:00:00', 'Asia/Ho_Chi_Minh'));
        KhoaHoc::congBo($v, $lan);
        $k->refresh()->forceFill(['created_at' => now()])->save();
        Carbon::setTestNow();
        if ($ban) {
            app(ChangeSaleStatus::class)->start($lan, $k->fresh() ?? $k);
        }

        return $k->fresh() ?? $k;
    }

    public static function bai(Course $k, string $title): Lesson
    {
        return Lesson::query()->where('course_version_id', $k->current_version_id)->where('title', $title)->sole();
    }
}
