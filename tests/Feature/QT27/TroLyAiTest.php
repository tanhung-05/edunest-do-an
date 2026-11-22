<?php

use App\Actions\M04\ConfidentialBanks;
use App\Actions\M04\QuestionBankActions;
use App\Actions\M25\AiAssistant;
use App\Actions\M25\AiQuestions;
use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Enums\M04\QuestionSource;
use App\Enums\M04\QuestionStatus;
use App\Enums\M17\DiscussionStatus;
use App\Models\M04\Question;
use App\Models\M11\Setting;
use App\Models\M17\Discussion;
use App\Models\M25\AiConversation;
use App\Models\M25\AiMessage;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\KhoaHoc;

pest()->group('P8');

const AI_BAI = '<p>Phản ứng oxi hóa khử là phản ứng có sự chuyển electron giữa các chất. Chất khử nhường electron, chất oxi hóa nhận electron trong phản ứng.</p>';

function aiKetQua(callable $fn): string
{
    try {
        $fn();
    } catch (DomainRejection $e) {
        return $e->status === 403 ? 'forbidden' : 'rejected';
    }

    return 'ok';
}

function aiDaDung(User $u): int
{
    return AiAssistant::usedToday('u:'.$u->id);
}

function aiCo(bool $on): void
{
    Setting::query()->updateOrCreate(['key' => 'ai.enabled'], ['value' => $on]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->h = new HocTap;
    $this->h->read->forceFill(['content' => AI_BAI])->save();
    $this->ai = app(AiAssistant::class);
    $this->hoi = fn (string $q, string $key, ?User $u = null) => $this->ai->askLesson($u ?? $this->h->a, $this->h->enrollment, $this->h->read, $q, $key);
});

it('AC-27 bước 6–8, A2 BR-MR-12/13 Trả lời theo nội dung bài; ngoài bài báo không có căn cứ; mỗi yêu cầu 1 lượt; gửi lặp cùng khóa không tính thêm', function () {
    $r1 = ($this->hoi)('Phản ứng oxi hóa khử là gì?', 'khoa-yeu-cau-0001');
    expect($r1->status)->toBe('OK')->and($r1->meta['found'])->toBeTrue()->and($r1->content)->toContain('chuyển electron')
        ->and(aiDaDung($this->h->a))->toBe(1);

    $again = ($this->hoi)('Phản ứng oxi hóa khử là gì?', 'khoa-yeu-cau-0001');
    expect($again->id)->toBe($r1->id)->and(aiDaDung($this->h->a))->toBe(1)
        ->and(AiMessage::query()->where('role', 'USER')->count())->toBe(1);

    $r2 = ($this->hoi)('Định luật Newton thứ hai phát biểu thế nào?', 'khoa-yeu-cau-0002');
    expect($r2->meta['found'])->toBeFalse()->and($r2->content)->toContain('không có trong bài')->and(aiDaDung($this->h->a))->toBe(2)
        ->and(AiConversation::query()->count())->toBe(1);
});

it('CR-170 X2 X3 X5 AI tắt: 0 lượt; hết lượt bị chặn; nhà cung cấp lỗi giữ 1 lượt, xử lý lại cùng khóa không tính thêm', function () {
    aiCo(false);
    expect(aiKetQua(fn () => ($this->hoi)('Phản ứng oxi hóa khử?', 'khoa-tat-00001')))->toBe('rejected')
        ->and(aiDaDung($this->h->a))->toBe(0)->and(AiMessage::query()->count())->toBe(0);

    aiCo(true);
    $loi = ($this->hoi)('Phản ứng oxi hóa khử #loi-ai', 'khoa-loi-00001');
    expect($loi->status)->toBe('LOI')->and(aiDaDung($this->h->a))->toBe(1);
    $lai = ($this->hoi)('Phản ứng oxi hóa khử #loi-ai', 'khoa-loi-00001');
    expect($lai->id)->toBe($loi->id)->and(aiDaDung($this->h->a))->toBe(1);

    Setting::query()->updateOrCreate(['key' => 'ai.daily_limit_user'], ['value' => 2]);
    ($this->hoi)('Chất khử nhường electron?', 'khoa-han-00001');
    expect(aiDaDung($this->h->a))->toBe(2)
        ->and(aiKetQua(fn () => ($this->hoi)('Chất oxi hóa nhận electron?', 'khoa-han-00002')))->toBe('rejected')
        ->and(aiDaDung($this->h->a))->toBe(2);
});

it('E15 E16 E22 X1 Không ghi danh, hết hạn, bị thu hồi, khóa yêu cầu của người khác đều bị chặn, không tính lượt', function () {
    $b = HocTap::student('binh');
    expect(aiKetQua(fn () => $this->ai->askLesson($b, $this->h->enrollment, $this->h->read, 'Phản ứng oxi hóa?', 'khoa-khac-0001')))->toBe('forbidden');

    $eb = $this->h->enroll($b);
    ($this->hoi)('Phản ứng oxi hóa khử?', 'khoa-cua-an-01');
    expect(aiKetQua(fn () => $this->ai->askLesson($b, $eb, $this->h->read, 'Phản ứng oxi hóa?', 'khoa-cua-an-01')))->toBe('forbidden');

    $this->h->enrollment->forceFill(['het_han_luc' => now()->subDay()])->save();
    expect(aiKetQua(fn () => ($this->hoi)('Chất khử là gì?', 'khoa-het-han-1')))->not->toBe('ok');
    $this->h->enrollment->forceFill(['het_han_luc' => null, 'revoked_at' => now()])->save();
    expect(aiKetQua(fn () => ($this->hoi)('Chất khử là gì?', 'khoa-thu-hoi-1')))->not->toBe('ok')
        ->and(aiDaDung($this->h->a))->toBe(1)->and(aiDaDung($b))->toBe(0);
});

it('BR-MR-14 bước 9–10 A4 Chuyển giáo viên tạo đúng một câu hỏi hỏi đáp CHUA_TRA_LOI; chuyển lại trả câu cũ; người khác bị chặn', function () {
    ($this->hoi)('Định luật Newton thứ hai?', 'khoa-chuyen-001');
    $conv = AiConversation::query()->sole();
    $d1 = $this->ai->handOff($this->h->a, $conv);
    $d2 = $this->ai->handOff($this->h->a, $conv->fresh());
    expect($d2->id)->toBe($d1->id)->and($d1->status)->toBe(DiscussionStatus::ChuaTraLoi)
        ->and($d1->body)->toBe('Định luật Newton thứ hai?')->and($d1->lesson_id)->toBe($this->h->read->id)
        ->and(Discussion::query()->count())->toBe(1)->and($conv->fresh()->discussion_id)->toBe($d1->id)
        ->and(aiKetQua(fn () => $this->ai->handOff(HocTap::student('binh'), $conv->fresh())))->toBe('forbidden');
});

it('MH trang hỏi AI trong bài: chủ hội thoại xem lịch sử, gửi qua tuyến; học viên chưa ghi danh bị chặn', function () {
    $url = route('student.lessons.ai.show', ['course' => $this->h->course->id, 'lesson' => $this->h->read->id]);
    $this->h->signIn($this)->post(route('student.lessons.ai.ask', ['course' => $this->h->course->id, 'lesson' => $this->h->read->id]), [
        'question' => 'Phản ứng oxi hóa khử là gì?', 'request_key' => 'khoa-tuyen-0001',
    ])->assertRedirect();
    $this->h->signIn($this)->get($url)->assertInertia(fn ($p) => $p->component('student/lessons/ai')
        ->has('messages', 2)->where('remaining', 19)->where('can_ask', true));
    session()->forget(LearningFile::DEVICE);
    session()->save();
    $this->h->signIn($this, HocTap::student('binh'))->get($url)->assertForbidden();
});

it('QT-27c E7 E14 A5 A6 CR-257 Khách: chỉ gợi ý khóa đang bán kèm thẻ khóa; không giới hạn lượt; lịch sử theo phiên', function () {
    $this->h->course->forceFill(['title' => 'Hóa học 12 ôn thi', 'sale_status' => SaleStatus::DangBan])->save();
    $this->post(route('public.advisor.ask'), ['question' => 'Em muốn ôn hóa học lớp 12', 'request_key' => 'khach-tu-van-01'])->assertRedirect();
    $reply = AiMessage::query()->where('role', 'AI')->sole();
    expect($reply->meta['courses'])->toHaveCount(1)->and($reply->meta['courses'][0]['url'])->toBe(route('public.courses.show', $this->h->course->slug))
        ->and(AiConversation::query()->sole()->user_id)->toBeNull()
        ->and($reply->meta['courses'][0]['title'])->toBe('Hóa học 12 ôn thi')->and($reply->meta['courses'][0])->toHaveKeys(['cover', 'price', 'teacher'])
        // CR-257: tư vấn không tính lượt theo ngày.
        ->and(AiAssistant::usedToday('ip:'.hash('sha256', '127.0.0.1')))->toBe(0);
    $this->get(route('public.advisor.show'))->assertInertia(fn ($p) => $p->has('messages', 2)->missing('remaining')->has('hotline'));

    $this->h->course->forceFill(['sale_status' => SaleStatus::NgungBan])->save();
    $this->post(route('public.advisor.ask'), ['question' => 'Còn khóa hóa học nào không?', 'request_key' => 'khach-tu-van-02']);
    $last = AiMessage::query()->where('role', 'AI')->orderByDesc('id')->first();
    expect($last->meta['courses'])->toBe([])->and($last->content)->toContain('chưa có khóa');

    // Lời chào không bị báo "chưa tìm thấy": AI chào lại và gợi ý khóa nổi bật.
    $this->h->course->forceFill(['sale_status' => SaleStatus::DangBan])->save();
    $this->postJson(route('public.advisor.ask'), ['question' => 'Xin chào', 'request_key' => 'khach-tu-van-03'])->assertOk()
        ->assertJsonPath('messages.5.content', fn (string $c) => str_contains($c, 'Chào bạn'))
        ->assertJsonCount(1, 'messages.5.courses');

    // Phiên khác cùng IP không đọc được lịch sử (A5).
    $this->flushSession();
    $this->get(route('public.advisor.show'))->assertInertia(fn ($p) => $p->has('messages', 0));
});

it('QT-27a CH-5 CH-6 CH-7 CR-169 Gợi ý câu nháp AI; duyệt; bỏ nháp; Dùng lại chuẩn bị chặn; khôi phục về nháp', function () {
    $bank = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Ngân hàng AI', 0);
    $qs = app(AiQuestions::class)->generate($this->h->t, $bank, $this->h->read, 2);
    expect($qs)->toHaveCount(2)->and($qs[0]->status)->toBe(QuestionStatus::Nhap)->and($qs[0]->source)->toBe(QuestionSource::Ai)
        ->and($qs[0]->options()->pluck('content')->all())->toBe(['Đúng', 'Sai']);

    $act = app(AiQuestions::class);
    $ok = $act->approve($this->h->t, $qs[0]);
    expect($ok->status)->toBe(QuestionStatus::DangDung)->and($ok->ai_first_approved_at)->not->toBeNull();

    $bo = $act->discard($this->h->t, $qs[1]);
    expect($bo->status)->toBe(QuestionStatus::NgungDung)
        ->and(aiKetQua(fn () => app(QuestionBankActions::class)->reactivate($this->h->t, $bo)))->toBe('rejected')
        ->and($act->restore($this->h->t, $bo)->status)->toBe(QuestionStatus::Nhap)
        ->and(aiKetQua(fn () => $act->approve(taiKhoan('gv-khac@edunest.test', RoleCode::GiaoVien), $qs[1]->fresh())))->toBe('forbidden')
        ->and(aiKetQua(fn () => $act->restore($this->h->t, $ok->fresh())))->toBe('rejected');
});

it('E38 X2 Ngân hàng bảo mật, bài khóa khác, AI tắt đều không tạo câu', function () {
    $bank = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Bảo mật', 0);
    app(ConfidentialBanks::class)->set($this->h->t, $bank, true);
    $gen = fn ($b, $l) => aiKetQua(fn () => app(AiQuestions::class)->generate($this->h->t, $b, $l, 2));
    expect($gen($bank->fresh(), $this->h->read))->toBe('rejected');

    $thuong = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Thường', 0);
    $khac = KhoaHoc::giao($this->h->g, $this->h->t);
    $baiKhac = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo($khac)), 'Bài khóa khác');
    expect($gen($thuong, $baiKhac))->toBe('rejected');
    aiCo(false);
    expect($gen($thuong, $this->h->read))->toBe('rejected')
        ->and(Question::query()->where('source', QuestionSource::Ai->value)->count())->toBe(0);
});

it('A7 Quản trị bật/tắt AI có nhật ký; vai khác bị chặn', function () {
    $qt = taiKhoan('qt-ai@edunest.test', RoleCode::QuanTri);
    $this->ai->setEnabled($qt, false);
    expect(Setting::valueFor('ai.enabled'))->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'AI_ENABLED_SET')->count())->toBe(1)
        ->and(aiKetQua(fn () => $this->ai->setEnabled($this->h->t, true)))->toBe('forbidden');
});
