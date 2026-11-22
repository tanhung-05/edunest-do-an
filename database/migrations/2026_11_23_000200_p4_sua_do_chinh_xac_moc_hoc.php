<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rà P4 #9: "Tiếp tục học" chọn bài xem gần nhất theo last_viewed_at, nên lưu tới mili giây như last_beat_at
 * (QT-09 bước 8/A1, BR-QZ-25). Model LessonProgress đã định dạng ngày 'Y-m-d H:i:s.v'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->dateTime('last_viewed_at', 3)->nullable()->change();
            $table->dateTime('completed_at', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->dateTime('last_viewed_at')->nullable()->change();
            $table->dateTime('completed_at')->nullable()->change();
        });
    }
};
