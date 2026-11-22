<?php

use App\Actions\M12\ManageCart;
use App\Actions\M12\SetCoursePromotion;
use App\Enums\M07\InvoicePdfStatus;
use App\Enums\M15\NotificationKind;
use App\Jobs\M07\GenerateInvoice;
use App\Models\M07\Invoice;
use App\Support\M15\Notifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MuaHang as MH;
use Tests\Support\ThuongMai as TM;

pest()->group('P5');

beforeEach(function () {
    Storage::fake('local');
    $this->d = TM::d13();
    $this->a = $this->d['a'];
    $this->c = $this->d['lan'];
    $this->k1 = $this->d['k1'];
});

afterEach(fn () => $this->travelBack());

it('TV-13 TV-11 Đơn hoàn tất: một hóa đơn số HD-năm, PDF sẵn sàng, chuông mua thành công; tải được', function () {
    $order = MH::dat($this->a, $this->k1);
    MH::xacNhan(MH::khoiTao($this->a, $order));
    MH::chayCap();
    $inv = Invoice::query()->sole();
    expect($inv->invoice_number)->toBe('HD-2026-000001')->and($inv->pdf_status)->toBe(InvoicePdfStatus::SanSang)
        ->and($this->a->notifications()->where('type', NotificationKind::MuaThanhCong->value)->count())->toBe(1);
    (new GenerateInvoice($order->id))->handle();
    expect(Invoice::query()->count())->toBe(1);
    $this->signIn($this->a)->get(route('student.orders.invoice', $order))->assertOk();
    $this->signIn($this->d['b'])->get(route('student.orders.invoice', $order))->assertForbidden();
});

it('M15 Khóa chống trùng: cùng khóa chỉ một thông báo; tắt loại được phép tắt thì không gửi', function () {
    Notifier::send($this->a, NotificationKind::KhuyenMai, 'x', 'y', null, 'k1');
    Notifier::send($this->a, NotificationKind::KhuyenMai, 'x', 'y', null, 'k1');
    expect($this->a->notifications()->count())->toBe(1);
    $this->a->forceFill(['notification_mutes' => [NotificationKind::KhuyenMai->value]])->save();
    Notifier::send($this->a->fresh(), NotificationKind::KhuyenMai, 'x', 'y', null, 'k2');
    expect($this->a->notifications()->count())->toBe(1);
    $this->signIn($this->a)->getJson(route('account.notifications.unread'))->assertOk()->assertJsonPath('count', 1);
    $this->post(route('account.notifications.read-all'))->assertRedirect();
    expect($this->a->unreadNotifications()->count())->toBe(0);
});

it('TV-10 Nhắc hết hạn 7 ngày và 1 ngày, chạy lại không gửi trùng', function () {
    MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addDays(7)]);
    Artisan::call('edunest:commerce', ['task' => 'reminders']);
    Artisan::call('edunest:commerce', ['task' => 'reminders']);
    expect($this->a->notifications()->where('type', NotificationKind::SapHetHan->value)->count())->toBe(1);
});

it('TV-19 Khóa yêu thích bắt đầu khuyến mãi: báo một lần', function () {
    app(ManageCart::class)->toggleWishlist($this->a, $this->k1);
    app(SetCoursePromotion::class)->handle($this->c, $this->k1, ['price' => 99000, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-25']);
    Artisan::call('edunest:commerce', ['task' => 'promotions']);
    Artisan::call('edunest:commerce', ['task' => 'promotions']);
    expect($this->a->notifications()->where('type', NotificationKind::KhuyenMai->value)->count())->toBe(1);
});
