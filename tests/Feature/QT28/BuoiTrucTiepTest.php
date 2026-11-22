<?php

use App\Actions\M26\LiveSessions;
use App\Enums\M01\RoleCode;
use App\Enums\M15\NotificationKind;
use App\Models\M26\LiveSession;
use App\Models\M26\LiveSessionJoin;
use App\Models\M26\LiveSessionRecording;
use App\Models\User;
use App\Support\DomainRejection;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P8');

function ttGio(string $at): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'Asia/Ho_Chi_Minh')->utc();
}

function ttKetQua(callable $fn): string
{
    try {
        $fn();
    } catch (DomainRejection $e) {
        return $e->status === 403 ? 'forbidden' : 'rejected';
    }

    return 'ok';
}

/** @return array{title: string, description: ?string, starts_at: CarbonImmutable, duration_minutes: int, meeting_url: string} */
function ttDuLieu(string $start = '2026-12-05 19:00', int $minutes = 60, string $url = 'https://meet.google.com/abc-defg-hij'): array
{
    return ['title' => 'Chữa đề chương 1', 'description' => 'Mang theo đề.', 'starts_at' => ttGio($start), 'duration_minutes' => $minutes, 'meeting_url' => $url];
}

function ttNhac(User $u): int
{
    return DB::table('notifications')->where('notifiable_id', $u->id)->where('type', NotificationKind::BuoiTrucTiep->value)->count();
}

beforeEach(function () {
    Storage::fake('local');
    $this->h = new HocTap;
    $this->live = app(LiveSessions::class);
    $this->s = $this->live->schedule($this->h->t, $this->h->course, ttDuLieu(), ttGio('2026-12-01 09:00'));
});

it('BR-MR-20 X1 Giáo viên được giao lên lịch; người khác hoặc giờ đã qua hoặc link không phải https bị từ chối', function () {
    $other = taiKhoan('gv-khac-live@edunest.test', RoleCode::GiaoVien);
    expect($this->s->schedule_rev)->toBe(1)
        ->and(ttKetQua(fn () => $this->live->schedule($other, $this->h->course, ttDuLieu(), ttGio('2026-12-01 09:00'))))->toBe('forbidden')
        ->and(ttKetQua(fn () => $this->live->schedule($this->h->t, $this->h->course, ttDuLieu('2026-11-30 19:00'), ttGio('2026-12-01 09:00'))))->toBe('rejected')
        ->and(ttKetQua(fn () => $this->live->schedule($this->h->t, $this->h->course, ttDuLieu(url: 'http://meet.example.com/x'), ttGio('2026-12-01 09:00'))))->toBe('rejected')
        ->and(LiveSession::query()->count())->toBe(1);
});

it('BR-MR-23 CR-102 BR-MR-24 Vào phòng từ T−10 phút tới hết giờ kết thúc (gồm hai đầu); giữ giờ bấm đầu', function () {
    $join = fn (string $at) => ttKetQua(fn () => $this->live->join($this->h->a, $this->s, $this->h->enrollment, ttGio($at)));
    expect($join('2026-12-05 18:49:59'))->toBe('rejected')
        ->and($join('2026-12-05 18:50:00'))->toBe('ok')
        ->and($join('2026-12-05 20:00:00'))->toBe('ok')
        ->and($join('2026-12-05 20:00:01'))->toBe('rejected');
    $row = LiveSessionJoin::query()->sole();
    expect($row->first_clicked_at->equalTo(ttGio('2026-12-05 18:50:00')))->toBeTrue()
        ->and($this->live->join($this->h->a, $this->s, $this->h->enrollment, ttGio('2026-12-05 19:30')))->toBe('https://meet.google.com/abc-defg-hij');
});

it('E15 E16 E22 X1 Không ghi danh, hết hạn, bị thu hồi không vào phòng được', function () {
    $b = HocTap::student('binh');
    $eb = $this->h->enroll($b);
    $at = ttGio('2026-12-05 19:00');
    $eb->forceFill(['het_han_luc' => ttGio('2026-12-04 00:00')])->save();
    expect(ttKetQua(fn () => $this->live->join($b, $this->s, $eb, $at)))->toBe('forbidden');
    $eb->forceFill(['het_han_luc' => null, 'revoked_at' => now()])->save();
    expect(ttKetQua(fn () => $this->live->join($b, $this->s, $eb, $at)))->toBe('forbidden')
        ->and(ttKetQua(fn () => $this->live->join($b, $this->s, $this->h->enrollment, $at)))->toBe('forbidden')
        ->and(LiveSessionJoin::query()->count())->toBe(0);
});

it('A1 CR-172 Dời giờ hoặc đổi link chỉ trước T−10 phút (tăng mốc nhắc); sửa mô tả tới hết buổi; dời rồi vào phòng theo giờ mới', function () {
    $moved = $this->live->update($this->h->t, $this->s, ttDuLieu('2026-12-05 20:00'), ttGio('2026-12-05 18:00'));
    expect($moved->schedule_rev)->toBe(2);
    expect(ttKetQua(fn () => $this->live->join($this->h->a, $moved, $this->h->enrollment, ttGio('2026-12-05 19:10'))))->toBe('rejected');

    $data = ttDuLieu('2026-12-05 20:00', url: 'https://zoom.us/j/123');
    expect(ttKetQua(fn () => $this->live->update($this->h->t, $moved, $data, ttGio('2026-12-05 19:50'))))->toBe('rejected');
    $desc = [...ttDuLieu('2026-12-05 20:00'), 'description' => 'Đổi mô tả'];
    $kept = $this->live->update($this->h->t, $moved, $desc, ttGio('2026-12-05 20:30'));
    expect($kept->description)->toBe('Đổi mô tả')->and($kept->schedule_rev)->toBe(2)
        ->and(ttKetQua(fn () => $this->live->update($this->h->t, $moved, $desc, ttGio('2026-12-05 21:00'))))->toBe('rejected');
});

it('A2 X2 Hủy trước giờ kết thúc: dừng cấp link; không hủy lại hay sửa; lượt bấm cũ được giữ', function () {
    $this->live->join($this->h->a, $this->s, $this->h->enrollment, ttGio('2026-12-05 18:55'));
    $this->live->cancel($this->h->t, $this->s, ttGio('2026-12-05 19:05'));
    expect(ttKetQua(fn () => $this->live->join($this->h->a, $this->s, $this->h->enrollment, ttGio('2026-12-05 19:06'))))->toBe('rejected')
        ->and(ttKetQua(fn () => $this->live->cancel($this->h->t, $this->s, ttGio('2026-12-05 19:07'))))->toBe('rejected')
        ->and(ttKetQua(fn () => $this->live->update($this->h->t, $this->s, ttDuLieu(), ttGio('2026-12-05 19:07'))))->toBe('rejected')
        ->and(LiveSessionJoin::query()->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'LIVE_SESSION_CANCELLED')->count())->toBe(1);
});

it('A3 CR-171 CR-173 Nhắc một lần trong [T−30, T); chạy lại không nhắc trùng; dời giờ nhắc lại theo lịch mới; người hết hạn không nhận', function () {
    $b = HocTap::student('binh');
    $this->h->enroll($b)->forceFill(['het_han_luc' => ttGio('2026-12-05 18:40')])->save();
    expect($this->live->remind(ttGio('2026-12-05 18:29')))->toBe(0);
    $this->live->remind(ttGio('2026-12-05 18:45'));
    $this->live->remind(ttGio('2026-12-05 18:50'));
    expect(ttNhac($this->h->a))->toBe(1)->and(ttNhac($b))->toBe(0);
    $this->live->remind(ttGio('2026-12-05 19:00'));
    expect(ttNhac($this->h->a))->toBe(1);

    $s2 = $this->live->schedule($this->h->t, $this->h->course, ttDuLieu('2026-12-06 19:00'), ttGio('2026-12-01 09:00'));
    $this->live->remind(ttGio('2026-12-06 18:40'));
    $this->live->update($this->h->t, $s2, ttDuLieu('2026-12-06 21:00'), ttGio('2026-12-06 18:45'));
    $this->live->remind(ttGio('2026-12-06 20:40'));
    expect(ttNhac($this->h->a))->toBe(3);
    $this->live->cancel($this->h->t, $this->live->schedule($this->h->t, $this->h->course, ttDuLieu('2026-12-07 19:00'), ttGio('2026-12-01 09:00')), ttGio('2026-12-07 18:00'));
    $this->live->remind(ttGio('2026-12-07 18:45'));
    expect(ttNhac($this->h->a))->toBe(3);
});

it('A5 A6 CR-174 E15 Tải bản ghi chỉ sau giờ kết thúc; thay giữ tệp cũ có dấu vết; tệp cũ bị từ chối; người hết hạn không xem', function () {
    $f = fn (string $n) => UploadedFile::fake()->create($n, 100, 'video/mp4');
    expect(ttKetQua(fn () => $this->live->uploadRecording($this->h->t, $this->s, $f('som.mp4'), ttGio('2026-12-05 19:30'))))->toBe('rejected');
    $r1 = $this->live->uploadRecording($this->h->t, $this->s, $f('ban-1.mp4'), ttGio('2026-12-05 20:01'));
    $r2 = $this->live->uploadRecording($this->h->t, $this->s, $f('ban-2.mp4'), ttGio('2026-12-05 21:00'));
    $old = LiveSessionRecording::query()->find($r1->id);
    expect($old->current_for_session_id)->toBeNull()->and($old->replaced_by_id)->toBe($this->h->t->id)
        ->and($r2->current_for_session_id)->toBe($this->s->id)
        ->and(ttKetQua(fn () => $this->live->recording($this->h->a, $r1, $this->h->enrollment, ttGio('2026-12-05 21:05'))))->toBe('forbidden')
        ->and(ttKetQua(fn () => $this->live->recording($this->h->a, $r2, $this->h->enrollment, ttGio('2026-12-05 21:05'))))->toBe('ok');
    $this->h->enrollment->forceFill(['het_han_luc' => ttGio('2026-12-05 22:00')])->save();
    expect(ttKetQua(fn () => $this->live->recording($this->h->a, $r2, $this->h->enrollment, ttGio('2026-12-05 22:00'))))->toBe('forbidden');
});

it('MH Trang lịch học viên và trang Giáo viên hiển thị; tuyến vào phòng chuyển ra link ngoài', function () {
    $this->travelTo(ttGio('2026-12-05 18:55'));
    $this->h->signIn($this)->get(route('student.live.index'))->assertInertia(fn ($p) => $p->component('student/live')
        ->has('sessions', 1)->where('sessions.0.state', 'VAO_DUOC'));
    $this->h->signIn($this)->post(route('student.live.join', $this->s))->assertRedirect('https://meet.google.com/abc-defg-hij');
    $this->signIn($this->h->t)->get(route('teacher.live-sessions.index'))->assertInertia(fn ($p) => $p->component('teacher/live/index')
        ->has('sessions', 1)->has('sessions.0.joins', 1));
});
