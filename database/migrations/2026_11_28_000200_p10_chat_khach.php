<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P10 M29 (CR-258): Khách chưa đăng nhập (thường là phụ huynh) cũng nhắn được nhân viên. Cuộc trò chuyện của Khách gắn
 * khóa ngẫu nhiên trong phiên trình duyệt, kèm họ tên và số điện thoại để nhân viên gọi lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->change();
            $t->char('guest_key', 64)->nullable()->unique()->after('user_id');
            $t->string('guest_name', 80)->nullable()->after('guest_key');
            $t->string('guest_phone', 20)->nullable()->after('guest_name');
        });
        Schema::table('support_messages', function (Blueprint $t) {
            $t->foreignId('sender_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $t) {
            $t->dropUnique(['guest_key']);
            $t->dropColumn(['guest_key', 'guest_name', 'guest_phone']);
        });
    }
};
