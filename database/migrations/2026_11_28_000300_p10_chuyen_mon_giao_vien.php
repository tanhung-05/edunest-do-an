<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CR-259 (UAT 22/11): chuyên môn giáo viên = danh sách danh mục môn phụ trách, dùng để gợi ý và cảnh báo khi phân công soạn. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->json('subject_category_ids')->nullable()->after('achievements');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn('subject_category_ids');
        });
    }
};
