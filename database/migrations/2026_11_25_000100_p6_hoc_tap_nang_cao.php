<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** P6 (02c §1): hỏi đáp, ghi chú, lượt xem trang khóa, kỳ thi thử, chuyển phiên bản. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->char('lesson_key', 26);
            $t->foreignId('lesson_id')->constrained('lessons');
            $t->foreignId('user_id')->constrained('users');
            $t->text('body');
            $t->unsignedInteger('video_second')->nullable();
            $t->string('status', 14)->default('CHUA_TRA_LOI');
            $t->string('status_before_hide', 14)->nullable();
            $t->string('hidden_reason', 500)->nullable();
            $t->foreignId('hidden_by_id')->nullable()->constrained('users');
            $t->dateTime('hidden_at')->nullable();
            $t->unsignedBigInteger('accepted_reply_id')->nullable();
            $t->unsignedInteger('follower_count')->default(0);
            $t->timestamps();
            $t->index(['course_id', 'lesson_key', 'status']);
            $t->index(['course_id', 'status', 'created_at']);
        });
        Schema::create('discussion_replies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('discussion_id')->constrained('discussions');
            $t->foreignId('user_id')->constrained('users');
            $t->text('body');
            $t->timestamps();
        });
        Schema::table('discussions', fn (Blueprint $t) => $t->foreign('accepted_reply_id')->references('id')->on('discussion_replies'));
        Schema::create('discussion_follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('discussion_id')->constrained('discussions');
            $t->foreignId('user_id')->constrained('users');
            $t->dateTime('created_at');
            $t->unique(['discussion_id', 'user_id']);
        });
        Schema::create('lesson_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('course_id')->constrained('courses');
            $t->char('lesson_key', 26);
            $t->foreignId('lesson_id')->constrained('lessons');
            $t->unsignedInteger('video_second')->nullable();
            $t->text('body');
            $t->timestamps();
            $t->index(['user_id', 'course_id', 'lesson_key']);
        });
        Schema::create('course_page_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->char('session_hash', 64);
            $t->date('view_date');
            $t->dateTime('created_at');
            $t->unique(['session_hash', 'view_date', 'course_id']);
            $t->index(['view_date', 'course_id']);
        });

        Schema::table('quizzes', function (Blueprint $t) {
            $t->dateTime('locked_at')->nullable()->after('answers_open_at');
        });
        Schema::create('exam_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quiz_id')->constrained('quizzes');
            $t->string('title', 200);
            $t->foreignId('created_by_id')->constrained('users');
            $t->string('status', 12)->default('NHAP');
            $t->dateTime('opens_at', 3);
            $t->dateTime('closes_at', 3);
            $t->unsignedSmallInteger('duration_minutes');
            $t->string('audience', 10)->default('CONG_KHAI');
            $t->boolean('requires_registration')->default(false);
            $t->dateTime('publish_at', 3)->nullable();
            $t->dateTime('scheduled_at', 3)->nullable();
            $t->dateTime('opened_at', 3)->nullable();
            $t->dateTime('closed_at', 3)->nullable();
            $t->dateTime('published_at', 3)->nullable();
            $t->dateTime('reminded_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'opens_at']);
            $t->index(['status', 'closes_at']);
            $t->index('quiz_id');
        });
        DB::statement('ALTER TABLE exam_events ADD CONSTRAINT exam_events_window CHECK (closes_at > opens_at AND duration_minutes BETWEEN 1 AND 600)');
        Schema::create('exam_event_courses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_event_id')->constrained('exam_events');
            $t->foreignId('course_id')->constrained('courses');
            $t->unique(['exam_event_id', 'course_id']);
        });
        Schema::create('exam_registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_event_id')->constrained('exam_events');
            $t->foreignId('user_id')->constrained('users');
            $t->string('status', 10)->default('DA_DANG_KY');
            $t->timestamps();
            $t->unique(['exam_event_id', 'user_id']);
        });
        Schema::create('exam_registration_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_registration_id')->constrained('exam_registrations');
            $t->string('action', 14);
            $t->foreignId('actor_id')->constrained('users');
            $t->dateTime('at', 3);
        });
        Schema::create('exam_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_event_id')->constrained('exam_events');
            $t->foreignId('user_id')->constrained('users');
            $t->boolean('consent');
            $t->dateTime('recorded_at', 3);
            $t->unique(['exam_event_id', 'user_id']);
        });
        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->dateTime('event_at', 3)->nullable()->after('closed_at');
            $t->foreign('exam_event_id')->references('id')->on('exam_events');
            $t->foreign('exam_registration_id')->references('id')->on('exam_registrations');
            $t->index(['exam_event_id', 'status']);
        });
        Schema::create('exam_tab_leaves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quiz_attempt_id')->constrained('quiz_attempts');
            $t->dateTime('left_at', 3);
            $t->unsignedInteger('duration_ms')->default(0);
            $t->index('quiz_attempt_id');
        });
        Schema::create('exam_rankings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_event_id')->constrained('exam_events');
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('quiz_attempt_id')->constrained('quiz_attempts');
            $t->unsignedInteger('rank');
            $t->decimal('score_ratio', 12, 8);
            $t->unsignedBigInteger('duration_ms');
            $t->dateTime('event_at', 3);
            $t->boolean('show_name')->default(false);
            $t->unique(['exam_event_id', 'user_id']);
            $t->index(['exam_event_id', 'rank']);
        });

        Schema::create('enrollment_version_switches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->constrained('enrollments');
            $t->foreignId('from_version_id')->constrained('course_versions');
            $t->foreignId('to_version_id')->constrained('course_versions');
            $t->unsignedInteger('kept_lessons')->default(0);
            $t->dateTime('switched_at', 3);
            $t->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->dropForeign(['exam_event_id']);
            $t->dropForeign(['exam_registration_id']);
            $t->dropIndex(['exam_event_id', 'status']);
            $t->dropColumn('event_at');
        });
        Schema::table('discussions', fn (Blueprint $t) => $t->dropForeign(['accepted_reply_id']));
        foreach (['enrollment_version_switches', 'exam_rankings', 'exam_tab_leaves', 'exam_consents', 'exam_registration_logs',
            'exam_registrations', 'exam_event_courses', 'exam_events', 'course_page_views', 'lesson_notes', 'discussion_follows',
            'discussion_replies', 'discussions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('quizzes', fn (Blueprint $t) => $t->dropColumn('locked_at'));
    }
};
