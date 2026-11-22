<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P10 M29 (CR-256): chat trực tiếp với nhân viên. Mỗi tài khoản một cuộc trò chuyện (đóng rồi nhắn lại thì mở lại);
 * Giáo vụ trả lời. Đếm chưa đọc hai phía lưu trên dòng cuộc trò chuyện, cập nhật dưới khóa dòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users');
            $t->string('status', 10)->default('CHO'); // CHO (chờ nhân viên) | DA_TRA_LOI | DONG
            $t->foreignId('assigned_to_id')->nullable()->constrained('users');
            $t->unsignedInteger('staff_unread')->default(0);
            $t->unsignedInteger('user_unread')->default(0);
            $t->dateTime('last_message_at', 3)->nullable();
            $t->timestamps();
            $t->index(['status', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_thread_id')->constrained('support_threads');
            $t->foreignId('sender_id')->constrained('users');
            $t->boolean('from_staff');
            $t->text('body');
            $t->string('request_key', 64)->nullable()->unique();
            $t->dateTime('created_at', 3);
            $t->index(['support_thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_threads');
    }
};
