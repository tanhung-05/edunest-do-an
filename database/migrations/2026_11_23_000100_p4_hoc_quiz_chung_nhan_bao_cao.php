<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->char('cookie_hash', 64);
            $table->string('browser', 100)->nullable();
            $table->string('os', 100)->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_active_at');
            $table->dateTime('revoked_at')->nullable();
            $table->char('remember_token_hash', 64)->nullable();
            $table->unique(['user_id', 'cookie_hash']);
            $table->index(['user_id', 'revoked_at', 'last_active_at']);
        });
        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreignId('device_id')->nullable()->constrained('user_devices')->nullOnDelete();
        });
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained();
            $table->foreignId('enrollment_id')->nullable()->constrained();
            $table->unsignedBigInteger('exam_registration_id')->nullable();
            $table->unsignedBigInteger('exam_event_id')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 10)->default('DANG_LAM');
            $table->string('close_reason', 12)->nullable();
            $table->dateTime('started_at', 3);
            $table->dateTime('deadline_at', 3);
            $table->dateTime('closed_at', 3)->nullable();
            $table->dateTime('finalized_at', 3)->nullable();
            $table->decimal('score_earned', 16, 4)->unsigned()->nullable();
            $table->decimal('score_max', 16, 4)->unsigned()->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedInteger('official_rank')->nullable();
            $table->tinyInteger('open_key')->storedAs("CASE WHEN status = 'DANG_LAM' THEN 1 ELSE NULL END");
            $table->unique(['enrollment_id', 'quiz_id', 'open_key'], 'quiz_attempts_one_open');
            $table->unique(['enrollment_id', 'quiz_id', 'attempt_no']);
            $table->unique(['user_id', 'exam_event_id']);
            $table->index(['status', 'deadline_at']);
            $table->index(['user_id', 'status']);
        });
        DB::statement("ALTER TABLE quiz_attempts
            ADD CONSTRAINT quiz_attempts_target CHECK ((enrollment_id IS NULL) <> (exam_event_id IS NULL)),
            ADD CONSTRAINT quiz_attempts_registration CHECK (exam_registration_id IS NULL OR exam_event_id IS NOT NULL),
            ADD CONSTRAINT quiz_attempts_reason CHECK (status = 'DANG_LAM' OR close_reason IS NOT NULL)");

        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained();
            $table->foreignId('quiz_question_id')->constrained();
            $table->json('answer')->nullable();
            $table->dateTime('received_at', 3);
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('parts_correct')->nullable();
            $table->unsignedSmallInteger('parts_total')->nullable();
            $table->decimal('points_awarded', 6, 2)->nullable();
            $table->unique(['quiz_attempt_id', 'quiz_question_id']);
        });
        Schema::create('quiz_attempt_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained();
            $table->foreignId('quiz_id')->constrained();
            $table->unsignedTinyInteger('extra_attempts')->default(1);
            $table->string('reason', 500);
            $table->foreignId('granted_by_id')->constrained('users');
            $table->dateTime('created_at');
            $table->index(['enrollment_id', 'quiz_id']);
        });
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained();
            $table->foreignId('lesson_id')->constrained();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->json('watched_segments')->nullable();
            $table->unsignedInteger('valid_watch_ms')->default(0);
            $table->unsignedInteger('last_position_ms')->nullable();
            $table->dateTime('last_beat_at', 3)->nullable();
            $table->dateTime('last_viewed_at')->nullable();
            $table->unique(['enrollment_id', 'lesson_id']);
            $table->index(['enrollment_id', 'completed_at']);
        });
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained();
            $table->string('certificate_number', 13)->unique();
            $table->string('holder_name', 120);
            $table->string('course_title', 200);
            $table->date('issued_on');
            $table->string('status', 12)->default('HIEU_LUC');
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by_id')->nullable()->constrained('users');
            $table->string('revoked_reason', 500)->nullable();
            $table->string('pdf_status', 10)->default('CHO_TAO');
            $table->string('pdf_path')->nullable();
            $table->dateTime('pdf_generated_at')->nullable();
            $table->char('pdf_job_token', 36)->nullable();
            $table->string('completion_event_id', 48)->unique();
            $table->dateTime('completion_event_published_at')->nullable();
            $table->char('completion_event_claim_token', 26)->nullable();
            $table->dateTime('completion_event_claim_until')->nullable();
            $table->index(['completion_event_published_at', 'completion_event_claim_until'], 'certificates_completion_pending');
            $table->index(['pdf_status', 'status']);
        });
        Schema::create('report_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('metric', 60);
            $table->string('scope_type', 30);
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->date('metric_date');
            $table->json('value');
            $table->dateTime('computed_at');
            $table->unique(['metric', 'scope_type', 'scope_id', 'metric_date'], 'report_metrics_scope_date');
        });
    }

    public function down(): void
    {
        foreach (['report_metrics', 'certificates', 'lesson_progress', 'quiz_attempt_grants', 'attempt_answers', 'quiz_attempts'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('device_id');
        });
        Schema::dropIfExists('user_devices');
    }
};
