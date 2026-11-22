<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P8 M26 (02e §4): buổi học trực tiếp, lượt bấm vào phòng đầu tiên, bản ghi do Giáo viên tải (CR-171..174). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->dateTime('starts_at');
            $t->unsignedSmallInteger('duration_minutes');
            $t->string('meeting_url', 500);
            // CR-171/172: dời giờ tạo lịch nhắc mới (tăng số), sửa mô tả/link giữ định danh nhắc.
            $t->unsignedInteger('schedule_rev')->default(1);
            $t->dateTime('cancelled_at')->nullable();
            $t->foreignId('cancelled_by_id')->nullable()->constrained('users');
            $t->foreignId('created_by_id')->constrained('users');
            $t->foreignId('updated_by_id')->constrained('users');
            $t->timestamps();
            $t->index(['course_id', 'starts_at']);
            $t->index(['cancelled_at', 'starts_at']);
        });

        Schema::create('live_session_joins', function (Blueprint $t) {
            $t->id();
            $t->foreignId('live_session_id')->constrained('live_sessions');
            $t->foreignId('user_id')->constrained('users');
            $t->dateTime('first_clicked_at', 3);
            $t->unique(['live_session_id', 'user_id']);
        });

        Schema::create('live_session_recordings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('live_session_id')->constrained('live_sessions');
            // CR-174: một tệp hiện hành mỗi buổi (cột bằng live_session_id khi hiện hành, NULL khi đã bị thay).
            $t->unsignedBigInteger('current_for_session_id')->nullable()->unique();
            $t->string('file_path', 500);
            $t->string('original_name', 255);
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->foreignId('uploaded_by_id')->constrained('users');
            $t->dateTime('replaced_at')->nullable();
            $t->foreignId('replaced_by_id')->nullable()->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['live_session_recordings', 'live_session_joins', 'live_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
