<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** P7 (02d §1): luyện tập, sổ XP, chuỗi ngày, huy hiệu, thẻ ghi nhớ, mini game. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->string('title', 200);
            $t->unsignedSmallInteger('question_count');
            $t->string('draw_mode', 12)->default('NGAU_NHIEN');
            $t->unsignedSmallInteger('seconds_per_question')->default(30);
            $t->foreignId('created_by_id')->constrained('users');
            $t->foreignId('updated_by_id')->constrained('users');
            $t->dateTime('archived_at')->nullable();
            $t->timestamps();
            $t->index(['course_id', 'archived_at']);
        });

        Schema::create('activity_question_bank', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained('activities');
            $t->foreignId('question_bank_id')->constrained('question_banks');
            $t->unique(['activity_id', 'question_bank_id']);
        });

        Schema::create('activity_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained('activities');
            $t->foreignId('enrollment_id')->constrained('enrollments');
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('course_id')->constrained('courses');
            $t->string('mode', 10);
            $t->string('status', 12)->default('DANG_LAM');
            $t->string('end_reason', 16)->nullable();
            $t->decimal('level_factor', 3, 2);
            $t->unsignedTinyInteger('lives')->nullable();
            $t->unsignedSmallInteger('question_count');
            $t->unsignedSmallInteger('current_position')->default(1);
            $t->unsignedSmallInteger('seconds_per_question')->nullable();
            $t->dateTime('question_deadline_at', 3)->nullable();
            $t->dateTime('last_activity_at', 3);
            $t->dateTime('started_at', 3);
            $t->dateTime('ended_at', 3)->nullable();
            $t->string('open_key', 40)->nullable()->unique();
            $t->timestamps();
            $t->index(['user_id', 'course_id', 'status']);
            $t->index(['status', 'last_activity_at']);
            $t->index(['status', 'question_deadline_at']);
        });

        Schema::create('activity_session_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_session_id')->constrained('activity_sessions');
            $t->unsignedSmallInteger('position');
            $t->foreignId('question_id')->constrained('questions');
            $t->json('snapshot');
            $t->unsignedTinyInteger('tries')->default(0);
            $t->boolean('hint_used')->default(false);
            $t->string('result', 10)->nullable();
            $t->unsignedTinyInteger('correct_on_try')->nullable();
            $t->json('answer')->nullable();
            $t->json('request_keys')->nullable();
            $t->dateTime('opened_at', 3)->nullable();
            $t->dateTime('deadline_at', 3)->nullable();
            $t->dateTime('finished_at', 3)->nullable();
            $t->unique(['activity_session_id', 'position']);
            $t->index(['question_id', 'result']);
        });

        Schema::create('xp_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('type', 16);
            $t->string('source_key', 80);
            $t->dateTime('occurred_at', 3);
            $t->json('payload');
            $t->dateTime('processed_at', 3)->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamps();
            $t->unique(['type', 'source_key']);
            $t->index(['processed_at', 'id']);
            $t->index(['user_id', 'type']);
        });

        Schema::create('xp_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('xp_source_id')->constrained('xp_sources');
            $t->string('rule', 14);
            $t->unsignedInteger('amount');
            $t->string('unique_key', 120)->unique();
            $t->date('streak_start')->nullable();
            $t->dateTime('occurred_at', 3);
            $t->timestamp('created_at')->nullable();
            $t->index(['occurred_at', 'user_id']);
            $t->index(['user_id', 'occurred_at']);
        });

        Schema::create('student_stats', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users');
            $t->unsignedInteger('total_xp')->default(0);
            $t->unsignedInteger('level')->default(1);
            $t->unsignedInteger('correct_answers')->default(0);
            $t->unsignedInteger('longest_streak')->default(0);
            $t->timestamps();
        });

        Schema::create('learning_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->date('day');
            $t->foreignId('xp_source_id')->constrained('xp_sources');
            $t->unique(['user_id', 'day']);
        });

        Schema::create('badges', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 120);
            $t->string('description', 500)->nullable();
            $t->string('rule', 16);
            $t->unsignedInteger('threshold');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('badge_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('badge_id')->constrained('badges');
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('xp_source_id')->nullable()->constrained('xp_sources');
            $t->dateTime('awarded_at', 3);
            $t->unique(['badge_id', 'user_id']);
        });

        Schema::create('flashcard_sets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->foreignId('course_section_id')->nullable()->constrained('course_sections');
            $t->string('title', 200);
            $t->string('description', 1000)->nullable();
            $t->foreignId('created_by_id')->constrained('users');
            $t->foreignId('updated_by_id')->constrained('users');
            $t->dateTime('archived_at')->nullable();
            $t->timestamps();
            $t->index(['course_id', 'archived_at']);
        });

        Schema::create('flashcards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flashcard_set_id')->constrained('flashcard_sets');
            $t->unsignedInteger('position');
            $t->string('front', 500);
            $t->string('back', 1000);
            $t->string('image_path')->nullable();
            $t->string('speech_lang', 10)->nullable();
            $t->unsignedInteger('back_version')->default(1);
            $t->dateTime('archived_at')->nullable();
            $t->timestamps();
            $t->index(['flashcard_set_id', 'archived_at', 'position']);
        });

        Schema::create('flashcard_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flashcard_id')->constrained('flashcards');
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('course_id')->constrained('courses');
            $t->decimal('ease', 4, 2)->default(2.5);
            $t->decimal('interval_days', 9, 3)->nullable();
            $t->unsignedInteger('reps')->default(0);
            $t->date('due_on')->nullable();
            $t->unsignedInteger('back_version')->default(1);
            $t->dateTime('last_reviewed_at', 3)->nullable();
            $t->timestamps();
            $t->unique(['flashcard_id', 'user_id']);
            $t->index(['user_id', 'due_on']);
        });

        Schema::create('flashcard_review_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flashcard_review_id')->constrained('flashcard_reviews');
            $t->string('request_id', 40)->unique();
            $t->string('grade', 4);
            $t->decimal('ease_before', 4, 2);
            $t->decimal('ease_after', 4, 2);
            $t->decimal('interval_before', 9, 3)->nullable();
            $t->decimal('interval_after', 9, 3);
            $t->date('due_on');
            $t->dateTime('reviewed_at', 3);
        });

        Schema::create('game_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flashcard_set_id')->constrained('flashcard_sets');
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('enrollment_id')->constrained('enrollments');
            $t->foreignId('course_id')->constrained('courses');
            $t->string('kind', 10);
            $t->string('status', 12)->default('DANG_LAM');
            $t->string('end_reason', 16)->nullable();
            $t->decimal('level_factor', 3, 2);
            $t->unsignedSmallInteger('card_count');
            $t->unsignedSmallInteger('current_position')->default(1);
            $t->dateTime('last_activity_at', 3);
            $t->dateTime('started_at', 3);
            $t->dateTime('ended_at', 3)->nullable();
            $t->string('open_key', 40)->nullable()->unique();
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['status', 'last_activity_at']);
        });

        Schema::create('game_session_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('game_session_id')->constrained('game_sessions');
            $t->unsignedSmallInteger('position');
            $t->foreignId('flashcard_id')->constrained('flashcards');
            $t->json('snapshot');
            $t->string('result', 10)->nullable();
            $t->string('answer', 500)->nullable();
            $t->string('request_id', 40)->nullable();
            $t->dateTime('answered_at', 3)->nullable();
            $t->unique(['game_session_id', 'position']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('nickname', 30)->nullable();
            $t->boolean('show_name_on_leaderboard')->default(false);
        });

        $now = now();
        DB::table('badges')->insert([
            ['code' => 'KHOA_DAU_TIEN', 'name' => 'Hoàn thành khóa đầu tiên', 'description' => 'Hoàn thành trọn vẹn khóa học đầu tiên.', 'rule' => 'KHOA_HOAN_THANH', 'threshold' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CHUOI_7', 'name' => 'Chuỗi 7 ngày', 'description' => 'Học liên tục 7 ngày.', 'rule' => 'CHUOI_NGAY', 'threshold' => 7, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TOP_10_KY_THI', 'name' => 'Top 10 kỳ thi', 'description' => 'Đạt hạng chính thức từ 1 đến 10 trong một kỳ thi thử.', 'rule' => 'TOP_KY_THI', 'threshold' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CAU_DUNG_100', 'name' => '100 câu đúng', 'description' => 'Trả lời đúng 100 câu luyện tập.', 'rule' => 'CAU_DUNG', 'threshold' => 100, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['nickname', 'show_name_on_leaderboard']));
        foreach (['game_session_cards', 'game_sessions', 'flashcard_review_logs', 'flashcard_reviews', 'flashcards', 'flashcard_sets',
            'badge_user', 'badges', 'learning_days', 'student_stats', 'xp_transactions', 'xp_sources', 'activity_session_questions',
            'activity_sessions', 'activity_question_bank', 'activities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
