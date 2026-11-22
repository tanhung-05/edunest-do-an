<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P8 M25 (02e §3): hội thoại AI, tin nhắn, hạn mức theo ngày. Chỉ khung + bộ giả lập, không gọi dịch vụ ngoài. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 10); // BAI_HOC | TU_VAN | SINH_CAU
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->char('guest_key', 64)->nullable();
            $t->foreignId('enrollment_id')->nullable()->constrained('enrollments');
            $t->foreignId('lesson_id')->nullable()->constrained('lessons');
            $t->foreignId('discussion_id')->nullable()->constrained('discussions');
            $t->timestamps();
            $t->index(['user_id', 'kind']);
            $t->index(['guest_key', 'kind']);
        });

        Schema::create('ai_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ai_conversation_id')->constrained('ai_conversations');
            $t->string('role', 4); // USER | AI
            $t->foreignId('reply_to_id')->nullable()->constrained('ai_messages');
            $t->text('content');
            $t->json('meta')->nullable();
            $t->string('request_key', 64)->nullable()->unique();
            $t->string('status', 6)->default('OK'); // OK | LOI
            $t->dateTime('created_at', 3);
            $t->index(['ai_conversation_id', 'id']);
            $t->unique('reply_to_id');
        });

        // BR-MR-16, CR-170: một dòng (chủ thể, ngày giờ VN), tăng dưới khóa dòng khi yêu cầu được ghi nhận để gửi.
        Schema::create('ai_usage_days', function (Blueprint $t) {
            $t->id();
            $t->string('subject', 80);
            $t->date('day');
            $t->unsignedInteger('used')->default(0);
            $t->unique(['subject', 'day']);
        });
    }

    public function down(): void
    {
        foreach (['ai_usage_days', 'ai_messages', 'ai_conversations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
