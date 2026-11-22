<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| P1 (09 §2): audit_logs → courses → course_authors → course_versions → FK courses.current_version_id
| → course_tag → course_version_editors → course_sections → lessons → lesson_resources
| → course_reviews → review_comments → question_banks → questions → question_options → question_tag
| → quizzes → quiz_questions. Cột/ràng buộc theo 02 L08–L24, L35.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('action', 60);
            $t->string('actor_type', 15);
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('subject_type', 60);
            $t->unsignedBigInteger('subject_id');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('reason', 1000)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->dateTime('created_at');
            $t->index(['subject_type', 'subject_id', 'created_at']);
            $t->index(['actor_id', 'created_at']);
            $t->index(['action', 'created_at']);
        });

        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->string('slug', 220)->unique();
            $t->foreignId('category_id')->constrained('categories');
            $t->foreignId('level_id')->constrained('levels');
            $t->string('learning_mode', 10);
            $t->unsignedBigInteger('price');
            $t->unsignedBigInteger('original_price')->nullable();
            $t->unsignedSmallInteger('access_months')->nullable();
            $t->unsignedBigInteger('extension_price')->nullable();
            $t->string('sale_status', 10)->default('CHUA_BAN');
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->string('cover_path', 255)->nullable();
            $t->foreignId('prerequisite_course_id')->nullable()->constrained('courses');
            $t->decimal('rating_avg', 3, 2)->nullable();
            $t->unsignedInteger('rating_count')->default(0);
            $t->dateTime('last_published_at')->nullable();
            $t->foreignId('created_by_id')->constrained('users');
            $t->timestamps();
            $t->index(['sale_status', 'category_id']);
            $t->index(['sale_status', 'level_id']);
            $t->index(['sale_status', 'price']);
            $t->index(['sale_status', 'last_published_at']);
            $t->fullText('title');
        });
        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_price_chk CHECK (price > 0 AND (original_price IS NULL OR original_price >= price))');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_extension_chk CHECK (extension_price IS NULL OR access_months IS NOT NULL)');

        Schema::create('course_authors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->foreignId('user_id')->constrained('users');
            $t->date('due_date')->nullable();
            $t->foreignId('assigned_by_id')->constrained('users');
            $t->dateTime('assigned_at');
            $t->dateTime('ended_at')->nullable();
            $t->tinyInteger('current_key')->nullable()->storedAs('IF(ended_at IS NULL,1,NULL)');
            $t->timestamps();
            $t->unique(['course_id', 'current_key']);
            $t->index(['user_id', 'current_key']);
        });

        Schema::create('course_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->unsignedSmallInteger('version_no');
            $t->string('status', 15)->default('NHAP');
            $t->foreignId('source_version_id')->nullable()->constrained('course_versions');
            $t->string('summary', 500)->nullable();
            $t->text('description')->nullable();
            $t->text('objectives')->nullable();
            $t->string('seo_title', 200)->nullable();
            $t->string('seo_description', 300)->nullable();
            $t->dateTime('submitted_at')->nullable();
            $t->foreignId('submitted_by_id')->nullable()->constrained('users');
            $t->dateTime('approved_at')->nullable();
            $t->dateTime('published_at')->nullable();
            $t->foreignId('published_by_id')->nullable()->constrained('users');
            $t->dateTime('superseded_at')->nullable();
            $t->tinyInteger('published_key')->nullable()->storedAs("IF(status='DA_CONG_BO',1,NULL)");
            $t->tinyInteger('open_draft_key')->nullable()->storedAs("IF(status IN ('NHAP','TRA_LAI','CHO_DUYET','DA_DUYET'),1,NULL)");
            $t->timestamps();
            $t->unique(['course_id', 'version_no']);
            $t->unique(['course_id', 'published_key']);
            $t->unique(['course_id', 'open_draft_key']);
            $t->index(['status', 'submitted_at']);
        });
        Schema::table('courses', function (Blueprint $t) {
            $t->foreign('current_version_id')->references('id')->on('course_versions');
        });

        Schema::create('course_tag', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->foreignId('tag_id')->constrained('tags');
            $t->unique(['course_id', 'tag_id']);
            $t->index('tag_id');
        });

        Schema::create('course_version_editors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_version_id')->constrained('course_versions');
            $t->foreignId('user_id')->constrained('users');
            $t->dateTime('first_edited_at');
            $t->unique(['course_version_id', 'user_id']);
        });

        Schema::create('course_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_version_id')->constrained('course_versions');
            $t->string('title', 200);
            $t->unsignedSmallInteger('position');
            $t->dateTime('removed_at')->nullable();
            $t->timestamps();
            $t->index(['course_version_id', 'position']);
        });

        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_section_id')->constrained('course_sections');
            $t->foreignId('course_version_id')->constrained('course_versions');
            $t->char('lesson_key', 26);
            $t->string('title', 200);
            $t->text('objectives')->nullable();
            $t->string('type', 10);
            $t->longText('content')->nullable();
            $t->string('video_path', 255)->nullable();
            $t->unsignedInteger('video_duration_ms')->nullable();
            $t->string('pdf_path', 255)->nullable();
            $t->boolean('is_required')->default(false);
            $t->boolean('is_preview')->default(false);
            $t->boolean('is_final_test')->default(false);
            $t->unsignedSmallInteger('position');
            $t->char('content_fingerprint', 64)->nullable();
            $t->dateTime('removed_at')->nullable();
            $t->tinyInteger('alive_key')->nullable()->storedAs('IF(removed_at IS NULL,1,NULL)');
            $t->tinyInteger('final_key')->nullable()->storedAs('IF(is_final_test AND removed_at IS NULL,1,NULL)');
            $t->timestamps();
            $t->unique(['course_version_id', 'lesson_key', 'alive_key']);
            $t->unique(['course_version_id', 'final_key']);
            $t->index(['course_section_id', 'position']);
            $t->index(['course_version_id', 'is_preview']);
        });
        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_final_chk CHECK (is_final_test = 0 OR (type = 'QUIZ' AND is_required = 1))");

        Schema::create('lesson_resources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lesson_id')->constrained('lessons');
            $t->string('title', 200);
            $t->string('file_path', 255);
            $t->string('original_name', 255);
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->unsignedSmallInteger('position');
            $t->dateTime('removed_at')->nullable();
            $t->timestamps();
            $t->index(['lesson_id', 'position']);
        });

        Schema::create('course_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_version_id')->constrained('course_versions');
            $t->foreignId('reviewer_id')->constrained('users');
            $t->string('result', 10);
            $t->text('reason')->nullable();
            $t->dateTime('created_at');
            $t->index(['course_version_id', 'created_at']);
        });

        Schema::create('review_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_review_id')->constrained('course_reviews');
            $t->foreignId('lesson_id')->nullable()->constrained('lessons');
            $t->text('comment');
            $t->dateTime('created_at');
        });

        Schema::create('question_banks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->string('name', 150);
            $t->boolean('is_confidential')->default(false);
            $t->foreignId('created_by_id')->constrained('users');
            $t->timestamps();
            $t->unique(['course_id', 'name']);
        });

        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_bank_id')->constrained('question_banks');
            $t->string('type', 20);
            $t->string('difficulty', 12)->default('TRUNG_BINH');
            $t->text('stem');
            $t->string('image_path', 255)->nullable();
            $t->decimal('points', 6, 2)->unsigned()->default(1);
            $t->text('hint')->nullable();
            $t->text('explanation')->nullable();
            $t->string('status', 12)->default('DANG_DUNG');
            $t->string('source', 10)->default('GIAO_VIEN');
            $t->dateTime('ai_first_approved_at')->nullable();
            $t->foreignId('created_by_id')->constrained('users');
            $t->foreignId('updated_by_id')->constrained('users');
            $t->timestamps();
            $t->index(['question_bank_id', 'status', 'type', 'difficulty']);
            $t->fullText('stem');
        });
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_ai_chk CHECK (status <> 'NHAP' OR source = 'AI')");

        Schema::create('question_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained('questions');
            $t->text('content');
            $t->boolean('is_correct')->default(false);
            $t->unsignedSmallInteger('position');
            $t->string('match_key', 40)->nullable();
            $t->timestamps();
            $t->index(['question_id', 'position']);
        });

        Schema::create('question_tag', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained('questions');
            $t->foreignId('tag_id')->constrained('tags');
            $t->unique(['question_id', 'tag_id']);
            $t->index('tag_id');
        });

        Schema::create('quizzes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_version_id')->nullable()->constrained('course_versions');
            $t->foreignId('lesson_id')->nullable()->unique()->constrained('lessons');
            $t->string('kind', 10)->default('BAI_HOC');
            $t->unsignedTinyInteger('pass_percent')->nullable();
            $t->unsignedSmallInteger('duration_minutes')->nullable();
            $t->unsignedTinyInteger('max_attempts')->nullable();
            $t->foreignId('question_bank_id')->nullable()->constrained('question_banks');
            $t->dateTime('answers_open_at')->nullable();
            $t->string('title', 200)->nullable();
            $t->foreignId('owner_teacher_id')->nullable()->constrained('users');
            $t->timestamps();
        });
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_lesson_chk CHECK (kind <> 'BAI_HOC' OR (lesson_id IS NOT NULL AND course_version_id IS NOT NULL AND (pass_percent IS NULL OR pass_percent BETWEEN 1 AND 100) AND (duration_minutes IS NULL OR duration_minutes BETWEEN 1 AND 180) AND (max_attempts IS NULL OR max_attempts BETWEEN 1 AND 10)))");

        Schema::create('quiz_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quiz_id')->constrained('quizzes');
            $t->foreignId('question_id')->constrained('questions');
            $t->unsignedSmallInteger('position');
            $t->decimal('points', 6, 2)->unsigned()->nullable();
            $t->json('snapshot')->nullable();
            $t->dateTime('snapshotted_at')->nullable();
            $t->dateTime('removed_at')->nullable();
            $t->tinyInteger('alive_key')->nullable()->storedAs('IF(removed_at IS NULL,1,NULL)');
            $t->timestamps();
            $t->unique(['quiz_id', 'question_id', 'alive_key']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $t) => $t->dropForeign(['current_version_id']));
        foreach (['quiz_questions', 'quizzes', 'question_tag', 'question_options', 'questions', 'question_banks',
            'review_comments', 'course_reviews', 'lesson_resources', 'lessons', 'course_sections',
            'course_version_editors', 'course_tag', 'course_versions', 'course_authors', 'courses', 'audit_logs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
