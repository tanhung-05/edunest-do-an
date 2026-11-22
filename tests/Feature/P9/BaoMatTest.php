<?php

use App\Enums\M01\RoleCode;
use App\Models\User;
use App\Support\M09\SafeFileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

pest()->group('P9');

function bmThongBao(int $userId, string $url): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id, 'type' => 'test', 'notifiable_type' => User::class, 'notifiable_id' => $userId,
        'data' => json_encode(['title' => 'Thử', 'body' => '', 'url' => $url]), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

it('P9.2 Mọi phản hồi web có tiêu đề bảo mật', function () {
    $this->get(route('home'))->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('P9.2 Bấm thông báo chỉ chuyển trong ứng dụng, link ngoài quay lại trang trước', function () {
    $u = taiKhoan('bm-tb@edunest.test', RoleCode::HocVien);
    $this->signIn($u);
    $inside = bmThongBao($u->id, '/student/orders');
    $this->post(route('account.notifications.read', $inside))->assertRedirect('/student/orders');
    $outside = bmThongBao($u->id, 'https://evil.example/phish');
    $this->from('/notifications')->post(route('account.notifications.read', $outside))->assertRedirect('/notifications');
    $protocolRelative = bmThongBao($u->id, '//evil.example/x');
    $this->from('/notifications')->post(route('account.notifications.read', $protocolRelative))->assertRedirect('/notifications');
});

it('P9.2 Tài liệu bổ sung loại nguy hiểm được tải về, loại an toàn mở ngay', function () {
    $path = tempnam(sys_get_temp_dir(), 'bm');
    file_put_contents($path, '<script>alert(1)</script>');
    $html = SafeFileResponse::make($path, 'text/html', 'trang.html');
    expect($html->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($html->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($html->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    $svg = SafeFileResponse::make($path, 'image/svg+xml', 'hinh.svg');
    expect($svg->headers->get('Content-Disposition'))->toStartWith('attachment');
    $pdf = SafeFileResponse::make($path, 'application/pdf', 'tai-lieu.pdf');
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->headers->get('Content-Disposition'))->toStartWith('inline');
    @unlink($path);
});
