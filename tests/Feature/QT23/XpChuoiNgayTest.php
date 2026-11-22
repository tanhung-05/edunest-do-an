<?php

use App\Actions\M09\MarkLessonCompleted;
use App\Actions\M21\BadgeAdmin;
use App\Actions\M21\XpLedger;
use App\Enums\M01\RoleCode;
use App\Enums\M21\XpSourceType;
use App\Listeners\RecordLearningXp;
use App\Models\M21\Badge;
use App\Models\M21\BadgeAward;
use App\Models\M21\LearningDay;
use App\Models\M21\StudentStat;
use App\Models\M21\XpSource;
use App\Models\M21\XpTransaction;
use App\Models\User;
use App\Queries\M21\Achievements;
use App\Support\DomainRejection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P7');

function vn(string $at): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'Asia/Ho_Chi_Minh')->utc();
}

/** Ghi một nguồn hoàn thành bài giả lập (giữ chuỗi, 2 XP theo lesson_key). */
function bai(User $u, string $at, string $key = 'L'): void
{
    app(XpLedger::class)->record(XpSourceType::HoanThanhBai, 'tay:'.$u->id.':'.$at.':'.$key, $u->id, vn($at), ['course_id' => 1, 'lesson_key' => $key.$at]);
}

function huyHieu(User $u, string $code): bool
{
    return BadgeAward::query()->where('user_id', $u->id)->whereHas('badge', fn ($q) => $q->where('code', $code))->exists();
}

beforeEach(function () {
    $this->u = HocTap::student('xp');
    $this->l = app(XpLedger::class);
});

it('BR-XP-28/30/32 Công thức: ngưỡng cấp, hệ số theo cấp, làm tròn nửa lên, thưởng phiên', function () {
    expect(XpLedger::threshold(2))->toBe(100)->and(XpLedger::threshold(3))->toBe(220)->and(XpLedger::levelFor(99))->toBe(1)
        ->and(XpLedger::levelFor(220))->toBe(3)->and(XpLedger::factorPct(5))->toBe(100)->and(XpLedger::factorPct(6))->toBe(90)
        ->and(XpLedger::factorPct(41))->toBe(20)->and(XpLedger::factorPct(99))->toBe(20)
        ->and(XpLedger::scale(5, 90))->toBe(5)->and(XpLedger::scale(5, 70))->toBe(4)->and(XpLedger::scale(10, 85))->toBe(9)
        ->and(XpLedger::sessionBonus(1))->toBe(2)->and(XpLedger::sessionBonus(10))->toBe(4)->and(XpLedger::sessionBonus(26))->toBe(10)
        ->and(XpLedger::questionBase('KHO', 1, false))->toBe(12)->and(XpLedger::questionBase('DE', 2, false))->toBe(6)
        ->and(XpLedger::questionBase('TRUNG_BINH', 1, true))->toBe(5)->and(XpLedger::questionBase('KHO', null, false))->toBe(0);
});

it('BR-HT-02 E40 BR-XP-49 Một nguồn xử lý nhiều lần chỉ ghi một lần; cấp không giảm', function () {
    $payload = ['status' => 'HOAN_THANH', 'factor_pct' => 100, 'question_count' => 1, 'correct' => 1,
        'questions' => [['position' => 1, 'difficulty' => 'KHO', 'correct_on_try' => 1, 'hint_used' => false]]];
    $this->l->record(XpSourceType::PhienLuyenTap, '9001', $this->u->id, now(), $payload);
    $this->l->record(XpSourceType::PhienLuyenTap, '9001', $this->u->id, now(), $payload);
    $source = XpSource::query()->sole();
    $source->forceFill(['processed_at' => null])->save();
    $this->l->process($source->id);
    expect(XpTransaction::query()->count())->toBe(2)->and(StudentStat::query()->find($this->u->id)->total_xp)->toBe(14)
        ->and(StudentStat::query()->find($this->u->id)->correct_answers)->toBe(1);
});

it('BR-XP-38 CR-159 Ranh giới ngày theo giờ Việt Nam: 23:59 ngày 18 và 00:01 ngày 19 là hai ngày', function () {
    bai($this->u, '2026-11-18 23:59:00');
    bai($this->u, '2026-11-19 00:01:00');
    expect(LearningDay::query()->where('user_id', $this->u->id)->orderBy('day')->pluck('day')->map->toDateString()->all())->toBe(['2026-11-18', '2026-11-19']);
    // Xử lý lại muộn vẫn dùng ngày gốc.
    $this->travelTo(vn('2026-11-25 10:00:00'));
    bai($this->u, '2026-11-18 23:59:00');
    expect(LearningDay::query()->count())->toBe(2);
});

it('BR-XP-41/42/72 CR-158 Hai chuỗi 15–17 và 19–21 đã nhận mốc 3, bù ngày 18 → thêm đúng một mốc 7 và một huy hiệu', function () {
    foreach ([15, 16, 17, 19, 20, 21] as $d) {
        bai($this->u, "2026-11-$d 09:00:00");
    }
    expect(XpTransaction::query()->where('rule', 'CHUOI_3')->count())->toBe(2)->and(huyHieu($this->u, 'CHUOI_7'))->toBeFalse();
    bai($this->u, '2026-11-18 21:00:00');
    expect(XpTransaction::query()->where('rule', 'CHUOI_3')->count())->toBe(2)
        ->and(XpTransaction::query()->where('rule', 'CHUOI_7')->count())->toBe(1)
        ->and(huyHieu($this->u, 'CHUOI_7'))->toBeTrue()
        ->and(StudentStat::query()->find($this->u->id)->longest_streak)->toBe(7)
        ->and(StudentStat::query()->find($this->u->id)->total_xp)->toBe(7 * 2 + 2 * 10 + 20);
    bai($this->u, '2026-11-18 22:00:00', 'K');
    expect(XpTransaction::query()->where('rule', 'CHUOI_7')->count())->toBe(1);
});

it('A2 BR-XP-39 Chuỗi hiện tại giữ tới hết hôm nay nếu hôm qua có học; bỏ trọn một ngày thì về 0', function () {
    bai($this->u, '2026-11-17 08:00:00');
    bai($this->u, '2026-11-18 08:00:00');
    expect(XpLedger::currentStreak($this->u->id, vn('2026-11-19 23:30:00')))->toBe(2)
        ->and(XpLedger::currentStreak($this->u->id, vn('2026-11-20 00:30:00')))->toBe(0);
});

it('E41 CR-87 Phiên HET_MANG chỉ XP câu, không giữ chuỗi nhưng đếm câu đúng; game không giữ chuỗi', function () {
    $this->l->record(XpSourceType::PhienLuyenTap, '77', $this->u->id, now(), ['status' => 'HET_MANG', 'factor_pct' => 100, 'question_count' => 5, 'correct' => 1,
        'questions' => [['position' => 1, 'difficulty' => 'DE', 'correct_on_try' => 1, 'hint_used' => false]]]);
    $this->l->record(XpSourceType::PhienGame, '78', $this->u->id, now(), ['status' => 'HOAN_THANH', 'factor_pct' => 90, 'card_count' => 7]);
    expect(LearningDay::query()->count())->toBe(0)->and(XpTransaction::query()->pluck('amount', 'rule')->all())->toBe(['CAU' => 8, 'THUONG_GAME' => 4])
        ->and(StudentStat::query()->find($this->u->id)->correct_answers)->toBe(1);
});

it('SK-08 E44 BR-XP-34/36 Hoàn thành bài 2 XP một lần theo lesson_key; học lại không phát thêm nhưng vẫn ghi ngày', function () {
    Storage::fake('local');
    $h = new HocTap;
    $this->travelTo(vn('2026-11-18 09:00:00'));
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);
    expect(XpTransaction::query()->where('user_id', $h->a->id)->where('rule', 'BAI')->sum('amount'))->toEqual(2)
        ->and(LearningDay::query()->where('user_id', $h->a->id)->count())->toBe(1);
    // Học lại: nguồn mới (lần hoàn thành khác) cùng lesson_key vào ngày khác.
    $this->travelTo(vn('2026-11-19 09:00:00'));
    $progress = $h->mark($h->read);
    app(RecordLearningXp::class)->recordLesson($progress->fresh()->forceFill(['id' => $progress->id + 100000]));
    expect(XpTransaction::query()->where('user_id', $h->a->id)->where('rule', 'BAI')->count())->toBe(1);
});

it('SK-10 BR-XP-35/43 Hoàn thành khóa 50 XP một lần và huy hiệu "Hoàn thành khóa đầu tiên"', function () {
    Storage::fake('local');
    $h = new HocTap;
    $h->mark($h->video);
    $h->submit(3);
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);
    $this->artisan('learning:recover-completions')->assertSuccessful();
    $this->artisan('learning:recover-completions')->assertSuccessful();
    expect(XpTransaction::query()->where('user_id', $h->a->id)->where('rule', 'KHOA')->sum('amount'))->toEqual(50)
        ->and(huyHieu($h->a, 'KHOA_DAU_TIEN'))->toBeTrue();
});

it('A3 E39 BR-XP-45 Huy hiệu Top 10 kỳ thi chỉ cho hạng chính thức ≤ 10; BR-XP-46 100 câu đúng', function () {
    $other = HocTap::student('hang11');
    $this->l->record(XpSourceType::KyThi, '5:'.$this->u->id, $this->u->id, now(), ['exam_event_id' => 5, 'rank' => 10]);
    $this->l->record(XpSourceType::KyThi, '5:'.$other->id, $other->id, now(), ['exam_event_id' => 5, 'rank' => 11]);
    expect(huyHieu($this->u, 'TOP_10_KY_THI'))->toBeTrue()->and(huyHieu($other, 'TOP_10_KY_THI'))->toBeFalse()
        ->and(XpTransaction::query()->count())->toBe(0);
    $this->l->record(XpSourceType::PhienLuyenTap, '500', $this->u->id, now(), ['status' => 'HET_MANG', 'factor_pct' => 100, 'question_count' => 100,
        'correct' => 100, 'questions' => []]);
    expect(huyHieu($this->u, 'CAU_DUNG_100'))->toBeTrue();
});

it('BR-XP-48 CR-157 Bảng xếp hạng tuần: chỉ học viên, hạng 1,2,2,4, biệt danh / tên đồng ý / Ẩn danh', function () {
    $this->travelTo(vn('2026-11-18 10:00:00')); // thứ Tư
    $b = HocTap::student('b');
    $c = HocTap::student('c');
    $d = HocTap::student('d');
    $staff = taiKhoan('gv-xp@edunest.test', RoleCode::GiaoVien);
    $this->u->forceFill(['nickname' => 'Sao Băng'])->save();
    $b->forceFill(['show_name_on_leaderboard' => true])->save();
    $give = function (User $u, int $xp, string $at) {
        app(XpLedger::class)->record(XpSourceType::PhienGame, 'lb:'.$u->id.':'.$at, $u->id, vn($at), ['status' => 'HOAN_THANH', 'factor_pct' => 100, 'card_count' => $xp * 5 / 2]);
    };
    $give($this->u, 10, '2026-11-17 08:00:00');
    $give($b, 8, '2026-11-16 00:00:00');   // thứ Hai 00:00 VN thuộc tuần
    $give($c, 8, '2026-11-18 08:00:00');
    $give($d, 6, '2026-11-15 23:59:00');   // Chủ nhật tuần trước
    $give($d, 4, '2026-11-18 09:00:00');
    $give($staff, 10, '2026-11-18 09:00:00');
    $board = Achievements::leaderboard($c, 'week');
    expect(array_map(fn ($r) => [$r['rank'], $r['xp'], $r['name']], $board['rows']))->toBe([
        [1, 10, 'Sao Băng'], [2, 8, $b->name], [2, 8, $c->name], [4, 4, 'Ẩn danh'],
    ])->and($board['me']['rank'])->toBe(2)->and($board['from'])->toBe('2026-11-16');
    $month = collect(Achievements::leaderboard($c, 'month')['rows']);
    expect($month->firstWhere('name', 'Ẩn danh'))->toMatchArray(['rank' => 1, 'xp' => 10])->and($month->count())->toBe(4);
});

it('X1 BR-XP-73 TV-14 xử lý lại nguồn chưa xong và bổ sung hoàn thành bài bị lỡ', function () {
    Storage::fake('local');
    $h = new HocTap;
    $progress = $h->mark($h->read);
    DB::table('xp_sources')->insert(['user_id' => $this->u->id, 'type' => 'HOAN_THANH_BAI', 'source_key' => 'treo', 'occurred_at' => now()->subMinutes(5),
        'payload' => json_encode(['course_id' => 1, 'lesson_key' => 'X']), 'attempts' => 0, 'created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5)]);
    $this->artisan('edunest:practice')->assertSuccessful();
    expect(XpSource::query()->whereNull('processed_at')->count())->toBe(0)
        ->and(XpSource::query()->where('source_key', (string) $progress->id)->exists())->toBeTrue()
        ->and(StudentStat::query()->find($this->u->id)->total_xp)->toBe(2);
});

it('BR-XP-71 CR-232 Quản trị sửa huy hiệu giữ định danh và lượt đã trao; học viên không tự cộng XP (X2)', function () {
    $admin = taiKhoan('qt-xp@edunest.test', RoleCode::QuanTri);
    $badge = Badge::query()->where('code', 'CHUOI_7')->firstOrFail();
    app(BadgeAdmin::class)->save($admin, ['name' => 'Bền bỉ 7 ngày', 'description' => null, 'rule' => 'CHUOI_NGAY', 'threshold' => 7, 'is_active' => true], $badge);
    expect($badge->fresh()->code)->toBe('CHUOI_7')->and($badge->fresh()->name)->toBe('Bền bỉ 7 ngày')
        ->and(fn () => app(BadgeAdmin::class)->save($this->u, ['name' => 'x', 'description' => null, 'rule' => 'CAU_DUNG', 'threshold' => 1, 'is_active' => true]))
        ->toThrow(DomainRejection::class);
    $this->signIn($admin)->get(route('admin.badges.index'))->assertInertia(fn ($p) => $p->component('admin/badges/index')->has('badges', 4));
    $this->signIn($this->u)->get(route('student.achievements.index'))->assertInertia(fn ($p) => $p->component('student/achievements')->where('summary.total_xp', 0));
});
