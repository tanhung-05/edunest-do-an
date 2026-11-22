<?php

use App\Models\User;

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-id',
    ]);
    require base_path('routes/channels.php');
});

it('cho phép người dùng nghe kênh thông báo riêng của chính mình', function () {
    $user = User::factory()->create();

    $this->signIn($user);

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-App.Models.User.'.$user->id,
        'socket_id' => '1234.5678',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['auth']);
});

it('từ chối người dùng nghe kênh riêng của người khác', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $this->signIn($user1);

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-App.Models.User.'.$user2->id,
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});

it('từ chối khách chưa đăng nhập nghe kênh riêng', function () {
    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-App.Models.User.1',
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});
