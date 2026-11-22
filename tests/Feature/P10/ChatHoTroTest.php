<?php

use App\Actions\M29\SupportChat;
use App\Enums\M01\RoleCode;
use App\Events\M29\SupportMessagePosted;
use App\Models\M29\SupportMessage;
use App\Models\M29\SupportThread;
use App\Support\DomainRejection;
use Illuminate\Support\Facades\Event;

pest()->group('P10');

beforeEach(function () {
    $this->chat = app(SupportChat::class);
    $this->hv = taiKhoan('hv-chat@edunest.test', RoleCode::HocVien);
    $this->gvu = taiKhoan('gvu-chat@edunest.test', RoleCode::GiaoVu);
});

it('học viên nhắn thì tạo cuộc trò chuyện chờ trả lời, gửi lặp cùng khóa không tạo tin mới', function () {
    Event::fake([SupportMessagePosted::class]);
    $a = $this->chat->send($this->hv, null, '  Em chưa vào học được khóa Hóa 11  ', 'chat-hv-0001');
    $b = $this->chat->send($this->hv, null, 'Em chưa vào học được khóa Hóa 11', 'chat-hv-0001');

    $t = SupportThread::query()->where('user_id', $this->hv->id)->firstOrFail();
    expect($a->id)->toBe($b->id)
        ->and($a->body)->toBe('Em chưa vào học được khóa Hóa 11')
        ->and(SupportMessage::query()->count())->toBe(1)
        ->and($t->status)->toBe(SupportThread::CHO)
        ->and($t->staff_unread)->toBe(1);
    Event::assertDispatchedTimes(SupportMessagePosted::class, 1);
});

it('Giáo vụ trả lời, nhận phụ trách, đếm chưa đọc phía học viên; đóng rồi học viên nhắn lại thì mở lại', function () {
    Event::fake([SupportMessagePosted::class]);
    $this->chat->send($this->hv, null, 'Cho em hỏi về hóa đơn', 'chat-hv-0002');
    $t = SupportThread::query()->where('user_id', $this->hv->id)->firstOrFail();
    $this->chat->reply($this->gvu, $t, 'Chào bạn, mình kiểm tra ngay.', 'chat-gvu-0001');

    $t->refresh();
    expect($t->status)->toBe(SupportThread::DA_TRA_LOI)
        ->and($t->assigned_to_id)->toBe($this->gvu->id)
        ->and($t->user_unread)->toBe(1)
        ->and($t->staff_unread)->toBe(0);

    $this->chat->close($this->gvu, $t);
    expect($t->refresh()->status)->toBe(SupportThread::DONG);
    $this->chat->send($this->hv, null, 'Em hỏi thêm', 'chat-hv-0003');
    expect($t->refresh()->status)->toBe(SupportThread::CHO)
        ->and(SupportThread::query()->count())->toBe(1);
});

it('chỉ Giáo vụ được trả lời và đóng; Giáo vụ không nhắn như học viên; tin rỗng hoặc quá dài bị từ chối', function () {
    $this->chat->send($this->hv, null, 'Xin chào', 'chat-hv-0004');
    $t = SupportThread::query()->firstOrFail();
    $gv = taiKhoan('gv-chat@edunest.test', RoleCode::GiaoVien);
    $khac = taiKhoan('hv2-chat@edunest.test', RoleCode::HocVien);

    $ketQua = function (callable $fn): string {
        try {
            $fn();
        } catch (DomainRejection $e) {
            return $e->status === 403 ? 'forbidden' : 'rejected';
        }

        return 'ok';
    };
    expect($ketQua(fn () => $this->chat->reply($gv, $t, 'Trả lời', 'chat-gv-0001')))->toBe('forbidden')
        ->and($ketQua(fn () => $this->chat->reply($khac, $t, 'Trả lời', 'chat-hv2-0001')))->toBe('forbidden')
        ->and($ketQua(fn () => $this->chat->close($khac, $t)))->toBe('forbidden')
        ->and($ketQua(fn () => $this->chat->send($this->gvu, null, 'Tôi là nhân sự', 'chat-gvu-0002')))->toBe('forbidden')
        ->and($ketQua(fn () => $this->chat->send($this->hv, null, '   ', 'chat-hv-0005')))->toBe('rejected')
        ->and($ketQua(fn () => $this->chat->send($this->hv, null, str_repeat('a', 2001), 'chat-hv-0006')))->toBe('rejected')
        // Khóa yêu cầu của học viên khác không lấy được tin của người khác.
        ->and($ketQua(fn () => $this->chat->send($khac, null, 'Xin chào', 'chat-hv-0004')))->toBe('forbidden');
    expect(SupportChat::canView($khac, $t))->toBeFalse()
        ->and(SupportChat::canView($this->hv, $t))->toBeTrue()
        ->and(SupportChat::canView($this->gvu, $t))->toBeTrue();
});

it('hộp chat JSON: học viên gửi và đọc; trang hộp thư chỉ cho Giáo vụ', function () {
    $this->signIn($this->hv)->postJson(route('account.support.send'), ['body' => 'Em cần hỗ trợ', 'request_key' => 'chat-http-0001'])
        ->assertOk()->assertJsonPath('messages.0.body', 'Em cần hỗ trợ')->assertJsonPath('status', 'CHO');
    $t = SupportThread::query()->firstOrFail();

    $this->signIn($this->gvu)->get(route('academic.support.index', ['thread' => $t->id]))->assertOk()
        ->assertInertia(fn ($p) => $p->component('academic/support/index')->has('threads', 1)->has('messages', 1)->where('active.id', $t->id));
    expect($t->refresh()->staff_unread)->toBe(0);
    $this->signIn($this->gvu)->post(route('academic.support.reply', $t), ['body' => 'Đã kiểm tra giúp bạn.', 'request_key' => 'chat-http-0002'])->assertRedirect();

    $this->signIn($this->hv)->getJson(route('account.support.state'))->assertOk()
        ->assertJsonPath('unread', 1)->assertJsonCount(2, 'messages')->assertJsonPath('messages.1.from_staff', true);
    $this->signIn($this->hv)->postJson(route('account.support.read'))->assertOk();
    expect($t->refresh()->user_unread)->toBe(0);

    $this->signIn($this->hv)->get(route('academic.support.index'))->assertForbidden();
});

it('CR-258 Khách nhắn được khi có họ tên và số điện thoại; nhân viên thấy số; phiên khác không đọc được', function () {
    Event::fake([SupportMessagePosted::class]);
    $this->postJson(route('account.support.send'), ['body' => 'Tôi cần tư vấn cho con lớp 9', 'request_key' => 'chat-khach-0001'])
        ->assertStatus(422)->assertJsonValidationErrors('guest_name');
    $this->postJson(route('account.support.send'), ['body' => 'Tôi cần tư vấn', 'request_key' => 'chat-khach-0002', 'guest_name' => 'Chị Lan', 'guest_phone' => '12345'])
        ->assertStatus(422)->assertJsonValidationErrors('guest_phone');
    $this->postJson(route('account.support.send'), ['body' => 'Tôi cần tư vấn cho con lớp 9', 'request_key' => 'chat-khach-0003',
        'guest_name' => '  Chị   Lan ', 'guest_phone' => '+84 912 345 678'])
        ->assertOk()->assertJsonPath('is_guest', true)->assertJsonPath('guest.phone', '0912345678')
        ->assertJsonPath('messages.0.sender', 'Chị Lan')->assertJsonPath('hotline', config('services.support.hotline'));

    $t = SupportThread::query()->whereNotNull('guest_key')->sole();
    expect($t->user_id)->toBeNull()->and($t->guest_name)->toBe('Chị Lan')->and($t->status)->toBe(SupportThread::CHO);
    $this->chat->reply($this->gvu, $t, 'Chào chị Lan, em gọi lại ngay ạ.', 'chat-khach-gvu1');
    $this->getJson(route('account.support.state'))->assertJsonCount(2, 'messages')->assertJsonPath('unread', 1);
    $this->signIn($this->gvu)->get(route('academic.support.index', ['thread' => $t->id]))
        ->assertInertia(fn ($p) => $p->where('active.phone', '0912345678')->where('active.is_guest', true));

    // Phiên trình duyệt khác (Khách khác) không thấy cuộc trò chuyện này.
    auth()->logout();
    $this->flushSession();
    $this->getJson(route('account.support.state'))->assertJsonPath('thread_id', null)->assertJsonCount(0, 'messages');
});

it('kênh phát của cuộc trò chuyện chỉ cho chủ và Giáo vụ', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-id',
    ]);
    require base_path('routes/channels.php');
    Event::fake([SupportMessagePosted::class]);
    $this->chat->send($this->hv, null, 'Xin chào', 'chat-ch-0001');
    $t = SupportThread::query()->firstOrFail();
    $khac = taiKhoan('hv3-chat@edunest.test', RoleCode::HocVien);
    $auth = fn ($u, string $ch) => $this->signIn($u)->postJson('/broadcasting/auth', ['channel_name' => 'private-'.$ch, 'socket_id' => '1234.5678']);

    $auth($this->hv, 'support.thread.'.$t->id)->assertOk();
    $auth($this->gvu, 'support.thread.'.$t->id)->assertOk();
    $auth($this->gvu, 'support.staff')->assertOk();
    $auth($khac, 'support.thread.'.$t->id)->assertForbidden();
    $auth($this->hv, 'support.staff')->assertForbidden();
});
