<?php

use App\Models\M01\UserDevice;
use App\Support\M09\LearningFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P4');

beforeEach(function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
});

it('AC-17-01 Đăng nhập cấp cookie, phát cùng trình duyệt và chặn cookie khác', function () {
    $h = $this->h;
    $login = dangNhap($h->a->email)->assertRedirect();
    $login->assertCookie(LearningFile::DEVICE);
    $cookie = $login->getCookie(LearningFile::DEVICE)->getValue();
    expect($cookie)->toHaveLength(64);
    $h->signIn($this, cookie: $cookie);
    $url = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['video_url'];
    $this->get($url)->assertOk();
    $this->withCookie(LearningFile::DEVICE, str_repeat('a', 64))->getJson($url)->assertForbidden();
});

it('AC-17-02 Hai tab cùng cookie và phiên dùng chung URL ký', function () {
    $h = $this->h;
    $h->signIn($this);
    $url = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['video_url'];
    $this->get($url, ['Range' => 'bytes=10-19'])->assertStatus(206);
    $this->get($url, ['Range' => 'bytes=20-29'])->assertStatus(206);
    expect(UserDevice::query()->where('user_id', $h->a->id)->count())->toBe(1);
});
