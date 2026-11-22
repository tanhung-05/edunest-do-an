<?php

/*
| QT-06 Khám phá khóa và học thử — nhóm P2 của 07a. Dữ liệu D6 ở Tests\Support\CongKhai.
| Ca ghi danh và đơn dùng dữ liệu thật ở P3.
*/

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M05\CreateDraftVersion;
use App\Enums\M01\RoleCode;
use App\Enums\M05\LessonType;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use App\Models\M07\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\Support\CongKhai;
use Tests\Support\KhoaHoc;
use Tests\Support\MuaHang as MH;

pest()->group('P2');

beforeEach(function () {
    $this->d = CongKhai::d6();
});

afterEach(fn () => Carbon::setTestNow());

function goiYCongKhai(string $q): array
{
    return collect(test()->getJson(route('public.courses.suggestions', ['q' => $q]))->assertOk()->json('items'))->pluck('title')->all();
}

function danhMucCongKhai(array $query = []): array
{
    return collect(test()->get(route('public.courses.index', $query))->assertOk()->viewData('page')['props']['courses']['data'])->pluck('title')->all();
}

function trangCongKhai(string $route, mixed $params = []): array
{
    return test()->get(route($route, $params))->assertOk()->viewData('page')['props'];
}

it('AC-06-01 Trang chủ chỉ có K1–K3; gợi ý "hó" có K1, K2; trang K1 đúng giá, thời hạn, 12 bài, 190 phút; bài 1 phát được; Mua ngay không tạo gì', function () {
    $home = trangCongKhai('home');
    expect(collect($home['featured'])->pluck('title')->sort()->values()->all())->toBe(['Hóa 12 nâng cao', 'Hóa 12 nền tảng', 'Vật lý 12'])
        ->and(goiYCongKhai('hó'))->toBe(['Hóa 12 nâng cao', 'Hóa 12 nền tảng']);
    $p = trangCongKhai('public.courses.show', $this->d['k1']->slug);
    expect($p['course']['price'])->toBe(499000)->and($p['course']['access_months'])->toBe(12)
        ->and($p['course']['lesson_count'])->toBe(12)->and($p['course']['total_minutes'])->toBe(190)->and($p['cta']['buy'])->toBeTrue();
    $bai1 = CongKhai::bai($this->d['k1'], 'Mở đầu');
    $url = trangCongKhai('public.lessons.preview', [$this->d['k1']->slug, $bai1->id])['lesson']['video_url'];
    $this->get($url)->assertOk();
    $this->signIn(taiKhoan('a@edunest.test', RoleCode::HocVien))->get(route('public.courses.buy', $this->d['k1']->slug))
        ->assertOk();
    expect(Order::query()->count())->toBe(0);
});

it('AC-06-02 Gõ "h" không có gợi ý; "hó" gợi ý K1 và K2', function () {
    expect(goiYCongKhai('h'))->toBe([])->and(goiYCongKhai('hó'))->toBe(['Hóa 12 nâng cao', 'Hóa 12 nền tảng']);
});

it('AC-06-03 Gõ "zzz": tập rỗng thật', function () {
    expect(goiYCongKhai('zzz'))->toBe([])->and(danhMucCongKhai(['q' => 'zzz']))->toBe([]);
});

it('AC-06-04 Danh mục chỉ K1, K2, K3', function () {
    expect(danhMucCongKhai())->toEqualCanonicalizing(['Hóa 12 nền tảng', 'Hóa 12 nâng cao', 'Vật lý 12']);
});

it('AC-06-05 Đúng 12 khóa: một trang; khóa thứ 13: trang 2 có 1 khóa', function () {
    foreach (range(1, 9) as $i) {
        CongKhai::khoa($this->d['lan'], $this->d['t2'], $this->d['minh'], ['title' => "Lý {$i}", 'category_id' => $this->d['ly']->id], '2026-11-02', true);
    }
    $p = trangCongKhai('public.courses.index');
    expect($p['courses']['data'])->toHaveCount(12)->and($p['courses']['last_page'])->toBe(1);
    CongKhai::khoa($this->d['lan'], $this->d['t2'], $this->d['minh'], ['title' => 'Lý 13', 'category_id' => $this->d['ly']->id], '2026-11-02', true);
    expect(trangCongKhai('public.courses.index')['courses']['last_page'])->toBe(2)
        ->and(trangCongKhai('public.courses.index', ['page' => 2])['courses']['data'])->toHaveCount(1);
});

it('AC-06-06 Lọc Hóa học + Nâng cao + T1: chỉ K2', function () {
    expect(danhMucCongKhai(['category' => $this->d['hoa']->id, 'level' => $this->d['nangCao']->id, 'teacher' => $this->d['t1']->id]))->toBe(['Hóa 12 nâng cao']);
});

it('AC-06-07 Khoảng giá gồm hai đầu', function () {
    expect(danhMucCongKhai(['price_min' => 400000, 'price_max' => 800000]))->toEqualCanonicalizing(['Hóa 12 nền tảng', 'Hóa 12 nâng cao'])
        ->and(danhMucCongKhai(['price_min' => 399000, 'price_max' => 499000]))->toEqualCanonicalizing(['Hóa 12 nền tảng', 'Vật lý 12']);
});

it('AC-06-08 Bán chạy tính ghi danh đúng biên 30 ngày', function () {
    MH::luc('10:00:00');
    foreach ([['k1', 29, 3], ['k1', 30, 1], ['k1', 31, 3], ['k2', 29, 6], ['k3', 29, 3]] as [$key, $days, $count]) {
        foreach (User::factory()->count($count)->create() as $user) {
            MH::ghiDanh($user, $this->d[$key], ['granted_at' => now()->subDays($days)]);
        }
    }
    expect(danhMucCongKhai(['sort' => 'ban_chay']))->toBe(['Hóa 12 nâng cao', 'Hóa 12 nền tảng', 'Vật lý 12']);
})->group('P3');

it('AC-06-09 Sắp xếp mới nhất và giá tăng dần', function () {
    expect(danhMucCongKhai(['sort' => 'moi']))->toBe(['Hóa 12 nâng cao', 'Vật lý 12', 'Hóa 12 nền tảng'])
        ->and(danhMucCongKhai(['sort' => 'gia_tang']))->toBe(['Vật lý 12', 'Hóa 12 nền tảng', 'Hóa 12 nâng cao']);
});

it('AC-06-10 Trang chủ đếm mỗi học viên một lần', function () {
    [$a, $b, $e, $h, $i, $f] = User::factory()->count(6)->create()->all();
    foreach ([[$a, 'k1'], [$a, 'k2'], [$b, 'k1'], [$e, 'k2'], [$h, 'k3'], [$h, 'k1'], [$i, 'k3']] as [$user, $key]) {
        MH::ghiDanh($user, $this->d[$key]);
    }
    expect(trangCongKhai('home')['stats'])->toMatchArray(['courses' => 3, 'students' => 5]);
    MH::ghiDanh($a, $this->d['k3']);
    expect(trangCongKhai('home')['stats']['students'])->toBe(5);
    MH::ghiDanh($f, $this->d['k3']);
    expect(trangCongKhai('home')['stats']['students'])->toBe(6);
})->group('P3');

it('AC-06-11 Khóa nổi bật: K2, K3, K1', function () {
    expect(collect(trangCongKhai('home')['featured'])->pluck('title')->all())->toBe(['Hóa 12 nâng cao', 'Vật lý 12', 'Hóa 12 nền tảng']);
});

it('AC-06-12 Đề cương 12 bài; bài 1 có xem thử; bài 2 chỉ tên và 12 phút', function () {
    $p = trangCongKhai('public.courses.show', $this->d['k1']->slug);
    $bai = collect($p['outline'])->flatMap(fn ($s) => $s['lessons']);
    expect($bai)->toHaveCount(12)->and($bai[0]['preview_url'])->not->toBeNull()
        ->and($bai[1])->toBe(['id' => $bai[1]['id'], 'number' => 2, 'title' => 'Bài 2', 'type_label' => 'Video', 'minutes' => 12, 'is_preview' => false, 'preview_url' => null]);
    $raw = $this->get(route('public.courses.show', $this->d['k1']->slug))->getContent();
    expect($raw)->not->toContain('k1-2.mp4')->not->toContain('Mục tiêu bài 2');
});

it('AC-06-13 Bản 2 nháp thêm bài 13 không lộ trên trang K1', function () {
    $v2 = app(CreateDraftVersion::class)->handle($this->d['t1'], $this->d['k1'])['version'];
    KhoaHoc::bai($v2->sections()->first(), 'Bài 13');
    $p = trangCongKhai('public.courses.show', $this->d['k1']->slug);
    expect(collect($p['outline'])->flatMap(fn ($s) => $s['lessons'])->pluck('title'))->toHaveCount(12)->not->toContain('Bài 13');
});

it('AC-06-14/15 K4 (chưa bán) và K6 (nháp) trả 404 và vắng mặt ở mọi nơi công khai', function () {
    foreach (['k4', 'k6'] as $k) {
        $this->get(route('public.courses.show', $this->d[$k]->slug))->assertNotFound();
        $this->signIn(taiKhoan("b-{$k}@edunest.test", RoleCode::HocVien))->get(route('public.courses.show', $this->d[$k]->slug))->assertNotFound()
            ->assertDontSee($this->d[$k]->title);
        auth()->logout();
    }
    $sitemap = $this->get(route('public.sitemap'))->assertOk()->getContent();
    $teacher = trangCongKhai('public.teachers.show', $this->d['t1']->id);
    foreach (['k4', 'k6'] as $k) {
        expect(collect(trangCongKhai('home')['featured'])->pluck('id'))->not->toContain($this->d[$k]->id)
            ->and(goiYCongKhai('Hóa'))->not->toContain($this->d[$k]->title)
            ->and(collect($teacher['courses'])->pluck('id'))->not->toContain($this->d[$k]->id)
            ->and($sitemap)->not->toContain($this->d[$k]->slug);
    }
});

it('AC-05-15/AC-06-16 K5 ngừng bán: trang mở được có nhãn, không Mua ngay, bài 1 xem được; vắng ở gợi ý, danh mục, sitemap, trang GV', function () {
    $k5 = $this->d['k5'];
    $p = trangCongKhai('public.courses.show', $k5->slug);
    expect($p['course']['sale_status_label'])->toBe('Ngừng bán')->and($p['cta']['buy'])->toBeFalse();
    $this->get(route('public.lessons.preview', [$k5->slug, CongKhai::bai($k5, 'Mở đầu')->id]))->assertOk();
    $this->get(route('public.courses.buy', $k5->slug));
    expect(goiYCongKhai('Hóa 10'))->toBe([])->and(danhMucCongKhai())->not->toContain('Hóa 10')
        ->and($this->get(route('public.sitemap'))->getContent())->not->toContain($k5->slug)
        ->and(collect(trangCongKhai('public.teachers.show', $this->d['t1']->id)['courses'])->pluck('id'))->not->toContain($k5->id);
});

it('AC-06-17 Khóa ngừng bán vẫn trả CTA Vào học cho ghi danh còn hạn', function () {
    $user = taiKhoan('a@edunest.test', RoleCode::HocVien);
    MH::ghiDanh($user, $this->d['k5']);
    $this->signIn($user);
    $props = trangCongKhai('public.courses.show', $this->d['k5']->slug);
    expect($props['cta']['label'])->toBe('Vào học')->and($props['cta']['buy'])->toBeFalse()
        ->and($props['course']['student_count'])->toBe(1);
})->group('P3');
it('AC-06-18 Người có đơn chờ được mở đúng đơn đang chờ', function () {
    $a = taiKhoan('a@edunest.test', RoleCode::HocVien);
    $b = taiKhoan('b@edunest.test', RoleCode::HocVien);
    $course = $this->d['k1'];
    MH::luc('09:50:00');
    $order = MH::dat($b, $course);
    MH::luc('10:00:00');
    $this->signIn($a);
    expect(trangCongKhai('public.courses.show', $course->slug)['cta']['label'])->toBe('Mua ngay');
    $this->signIn($b);
    expect(trangCongKhai('public.courses.show', $course->slug)['cta']['label'])->toBe('Tiếp tục thanh toán');
    $this->get(route('public.courses.buy', $course->slug))->assertRedirect(route('student.orders.show', $order));
    expect(Order::query()->count())->toBe(1);
})->group('P3');
it('AC-06-20 Ghi danh bị thu hồi trả CTA Liên hệ giáo vụ', function () {
    $user = taiKhoan('a@edunest.test', RoleCode::HocVien);
    MH::ghiDanh($user, $this->d['k1'], ['revoked_at' => now()]);
    $this->signIn($user);
    $cta = trangCongKhai('public.courses.show', $this->d['k1']->slug)['cta'];
    expect($cta['label'])->toBe('Liên hệ giáo vụ')->and($cta['buy'])->toBeFalse();
})->group('P3');

it('AC-06-22 URL ký dùng được ở phiên khác trong 15 phút', function () {
    Carbon::setTestNow('2026-11-18 03:00:00');
    $url = trangCongKhai('public.lessons.preview', [$this->d['k1']->slug, CongKhai::bai($this->d['k1'], 'Mở đầu')->id])['lesson']['video_url'];
    Carbon::setTestNow('2026-11-18 03:05:00');
    $this->flushSession();
    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

it('AC-06-23 Bài 3 (trả phí): 403, không lộ nội dung hay tệp', function () {
    $b3 = CongKhai::bai($this->d['k1'], 'Bài 3');
    $r = $this->get(route('public.lessons.preview', [$this->d['k1']->slug, $b3->id]))->assertForbidden();
    expect($r->getContent())->not->toContain('de3.pdf')->not->toContain('k1-3.mp4')->not->toContain('Mục tiêu bài 3');
});

it('AC-06-24 (phần P2) Tài nguyên bài 3 qua URL đoán: 403', function () {
    $b3 = CongKhai::bai($this->d['k1'], 'Bài 3');
    $r = LessonResource::query()->where('lesson_id', $b3->id)->sole();
    $url = URL::temporarySignedRoute('public.lessons.preview.resources.show', now()->addMinutes(15), ['slug' => $this->d['k1']->slug, 'lesson' => $b3->id, 'resource' => $r->id]);
    $this->get($url)->assertForbidden();
    $this->get(URL::temporarySignedRoute('public.lessons.preview.video', now()->addMinutes(15), ['slug' => $this->d['k1']->slug, 'lesson' => $b3->id]))->assertForbidden();
});

it('AC-06-25 URL hết hạn sau 15 phút; tải lại trang nhận URL mới', function () {
    $bai1 = CongKhai::bai($this->d['k1'], 'Mở đầu');
    Carbon::setTestNow('2026-11-18 03:00:00');
    $url = trangCongKhai('public.lessons.preview', [$this->d['k1']->slug, $bai1->id])['lesson']['video_url'];
    Carbon::setTestNow('2026-11-18 03:14:59');
    $this->get($url)->assertOk();
    Carbon::setTestNow('2026-11-18 03:15:01');
    $this->get($url)->assertForbidden();
    Carbon::setTestNow('2026-11-18 03:15:30');
    $moi = trangCongKhai('public.lessons.preview', [$this->d['k1']->slug, $bai1->id])['lesson']['video_url'];
    $this->get($moi)->assertOk();
});

it('AC-06-26 Trang T1: hồ sơ và đúng K1, K2', function () {
    expect(collect(trangCongKhai('public.teachers.show', $this->d['t1']->id)['courses'])->pluck('title')->sort()->values()->all())->toBe(['Hóa 12 nâng cao', 'Hóa 12 nền tảng']);
});

it('AC-06-27 Khách bấm Mua ngay: sang đăng nhập', function () {
    $this->get(route('public.courses.buy', $this->d['k1']->slug))->assertRedirect(route('login'));
});

it('AC-06-28 Học viên, Giáo vụ, Quản trị thấy như Khách; bài 3 vẫn 403', function () {
    $b1 = CongKhai::bai($this->d['k1'], 'Mở đầu');
    $b3 = CongKhai::bai($this->d['k1'], 'Bài 3');
    foreach ([taiKhoan('a2@edunest.test', RoleCode::HocVien), $this->d['lan'], taiKhoan('d@edunest.test', RoleCode::QuanTri)] as $u) {
        $this->signIn($u);
        expect(collect(trangCongKhai('home')['featured'])->pluck('title'))->toHaveCount(3)->and(danhMucCongKhai())->toHaveCount(3);
        $this->get(route('public.courses.show', $this->d['k1']->slug))->assertOk();
        $this->get(route('public.courses.show', $this->d['k4']->slug))->assertNotFound();
        $this->get(route('public.lessons.preview', [$this->d['k1']->slug, $b1->id]))->assertOk();
        $this->get(route('public.lessons.preview', [$this->d['k1']->slug, $b3->id]))->assertForbidden();
    }
});

it('AC-06-30 Sitemap có K1–K3, không K4–K6; trang K1 có tiêu đề, mô tả, ảnh chia sẻ trong mã nguồn', function () {
    $this->d['k1']->forceFill(['cover_path' => 'course-covers/k1.png'])->save();
    $xml = $this->get(route('public.sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    foreach (['k1', 'k2', 'k3'] as $k) {
        expect($xml)->toContain(route('public.courses.show', $this->d[$k]->slug));
    }
    foreach (['k4', 'k5', 'k6'] as $k) {
        expect($xml)->not->toContain('/courses/'.$this->d[$k]->slug.'<');
    }
    $html = $this->get(route('public.courses.show', $this->d['k1']->slug))->getContent();
    expect($html)->toContain('<title>Hóa 12 nền tảng')->toContain('name="description"')->toContain('og:image')->toContain('course-covers/k1.png')
        ->toContain('/courses/hoa-12-nen-tang');
});

it('AC-06-35 Giá gốc chỉ gạch khi lớn hơn giá bán', function () {
    expect(trangCongKhai('public.courses.show', $this->d['k1']->slug)['course']['original_price'])->toBe(699000)
        ->and(trangCongKhai('public.courses.show', $this->d['k3']->slug)['course']['original_price'])->toBeNull();
});

it('AC-06-36 Gợi ý tối đa 8; khóa ngừng bán rời gợi ý; khớp tên giáo viên', function () {
    $t3 = CongKhai::nv('t3@edunest.test', RoleCode::GiaoVien, 'Cô Thu');
    $toan = [];
    foreach (range(10, 18) as $i) {
        $toan[$i] = CongKhai::khoa($this->d['lan'], $t3, $this->d['minh'], ['title' => "Toán {$i}", 'category_id' => $this->d['ly']->id], '2026-11-03', true);
    }
    expect(goiYCongKhai('toán'))->toHaveCount(8);
    app(ChangeSaleStatus::class)->stop($this->d['lan'], $toan[18]);
    expect(goiYCongKhai('toán'))->toHaveCount(8)->not->toContain('Toán 18')
        ->and(goiYCongKhai('minh'))->toEqualCanonicalizing(['Hóa 12 nền tảng', 'Hóa 12 nâng cao']);
});

it('AC-06-37 Lọc danh mục cấp 1 gồm cả con', function () {
    expect(danhMucCongKhai(['category' => $this->d['tn']->id]))->toEqualCanonicalizing(['Hóa 12 nền tảng', 'Hóa 12 nâng cao', 'Vật lý 12']);
});

it('AC-06-38 Giá giảm dần', function () {
    expect(danhMucCongKhai(['sort' => 'gia_giam']))->toBe(['Hóa 12 nâng cao', 'Hóa 12 nền tảng', 'Vật lý 12']);
});

it('P2.2 Video xem thử hỗ trợ Range (206)', function () {
    $url = trangCongKhai('public.lessons.preview', [$this->d['k1']->slug, CongKhai::bai($this->d['k1'], 'Mở đầu')->id])['lesson']['video_url'];
    $this->get($url, ['Range' => 'bytes=0-99'])->assertStatus(206);
});

it('P2.2 Gỡ cờ xem thử ở bản mới công bố: URL cũ bị từ chối', function () {
    $k1 = $this->d['k1'];
    $url = trangCongKhai('public.lessons.preview', [$k1->slug, CongKhai::bai($k1, 'Mở đầu')->id])['lesson']['video_url'];
    $v2 = app(CreateDraftVersion::class)->handle($this->d['t1'], $k1)['version'];
    Lesson::query()->where('course_version_id', $v2->id)->where('title', 'Mở đầu')->update(['is_preview' => false]);
    KhoaHoc::guiDuyet($v2, $this->d['t1']);
    KhoaHoc::duyet($v2, $this->d['minh']);
    KhoaHoc::congBo($v2, $this->d['lan']);
    $this->get($url)->assertForbidden();
    expect(LessonType::Video->value)->toBe('VIDEO');
});
