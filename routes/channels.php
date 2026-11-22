<?php

use App\Actions\M29\SupportChat;
use App\Models\M29\SupportThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// M15: chuông thông báo của chính người dùng.
Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id) {
    return (string) $user->id === (string) $id;
});

// P10 M29: cuộc trò chuyện hỗ trợ — chủ cuộc trò chuyện hoặc Giáo vụ đang hiệu lực.
Broadcast::channel('support.thread.{thread}', function (User $user, SupportThread $thread) {
    return SupportChat::canView($user, $thread);
});

// P10 M29: hộp thư chung của nhân sự hỗ trợ.
Broadcast::channel('support.staff', function (User $user) {
    return SupportChat::isAgent($user);
});
