<?php

use App\Enums\M01\RoleCode;
use App\Enums\M15\NotificationKind;
use App\Jobs\M15\SendWebPush;
use App\Models\M15\PushSubscription;
use App\Notifications\EduNotification;
use Illuminate\Support\Facades\Queue;

pest()->group('P10');

beforeEach(function () {
    // Giá trị giả cho test, không phải khóa thật.
    config(['services.webpush' => ['subject' => 'mailto:test@edunest.test', 'public_key' => 'BPubTest', 'private_key' => 'privTest']]);
});

function dangKyDay(): array
{
    return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => ['p256dh' => 'BKeyTest', 'auth' => 'authTest']];
}

it('CR-262: người dùng bật, bật lại (không trùng) và tắt thông báo đẩy của trình duyệt', function () {
    $u = taiKhoan('day-a@edunest.test', RoleCode::HocVien);
    $this->signIn($u)->get(route('account.notifications.index'))->assertInertia(fn ($p) => $p->where('push_public_key', 'BPubTest'));
    $this->postJson(route('account.push.store'), dangKyDay())->assertOk();
    $this->postJson(route('account.push.store'), dangKyDay())->assertOk();
    expect(PushSubscription::query()->where('user_id', $u->id)->count())->toBe(1);

    $this->postJson(route('account.push.store'), ['endpoint' => 'http://insecure.example/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])
        ->assertUnprocessable();

    $this->postJson(route('account.push.destroy'), ['endpoint' => dangKyDay()['endpoint']])->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

it('CR-262: thông báo mới có đăng ký thì xếp việc gửi đẩy; thông báo trùng hoặc chưa đăng ký thì không', function () {
    Queue::fake();
    $u = taiKhoan('day-b@edunest.test', RoleCode::HocVien);
    $u->notify(new EduNotification(NotificationKind::MuaThanhCong, 'Mua xong', 'Khóa đã mở', '/student/courses', 'day-1'));
    Queue::assertNotPushed(SendWebPush::class);

    $this->signIn($u)->postJson(route('account.push.store'), dangKyDay())->assertOk();
    $u->notify(new EduNotification(NotificationKind::MuaThanhCong, 'Mua xong', 'Khóa đã mở', '/student/courses', 'day-2'));
    Queue::assertPushed(SendWebPush::class, fn ($job) => $job->userId === $u->id && $job->payload['url'] === '/student/courses');

    $u->notify(new EduNotification(NotificationKind::MuaThanhCong, 'Mua xong', 'Khóa đã mở', '/student/courses', 'day-2'));
    Queue::assertPushed(SendWebPush::class, 1);
});

it('CR-262: chưa có khóa VAPID thì ẩn nút bật', function () {
    config(['services.webpush.private_key' => null]);
    $this->signIn(taiKhoan('day-c@edunest.test', RoleCode::HocVien))->get(route('account.notifications.index'))
        ->assertInertia(fn ($p) => $p->where('push_public_key', null));
});
