<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** P8 M27 (02e §5): bảng tin cộng đồng — bài, bình luận, thích, báo cáo; ẩn/hiện có lý do (CR-100, CR-135, CR-175..177). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('topic', 12);
            $t->foreignId('course_id')->nullable()->constrained('courses');
            $t->string('title', 200);
            $t->text('body');
            $t->string('image_path', 500)->nullable();
            $t->string('status', 6)->default('HIEN');
            $t->string('hidden_reason', 500)->nullable();
            $t->dateTime('hidden_at')->nullable();
            $t->foreignId('hidden_by_id')->nullable()->constrained('users');
            $t->unsignedInteger('like_count')->default(0);
            $t->dateTime('edited_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'id']);
            $t->index(['user_id', 'created_at']);
        });
        DB::statement("ALTER TABLE community_posts ADD CONSTRAINT community_posts_status_chk CHECK (status IN ('HIEN','DA_AN'))");

        Schema::create('community_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('community_post_id')->constrained('community_posts');
            $t->foreignId('user_id')->constrained('users');
            $t->text('body');
            $t->string('status', 6)->default('HIEN');
            $t->string('hidden_reason', 500)->nullable();
            $t->dateTime('hidden_at')->nullable();
            $t->foreignId('hidden_by_id')->nullable()->constrained('users');
            $t->timestamps();
            $t->index(['community_post_id', 'id']);
        });
        DB::statement("ALTER TABLE community_comments ADD CONSTRAINT community_comments_status_chk CHECK (status IN ('HIEN','DA_AN'))");

        Schema::create('community_likes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('community_post_id')->constrained('community_posts');
            $t->foreignId('user_id')->constrained('users');
            $t->dateTime('created_at');
            $t->unique(['community_post_id', 'user_id']);
        });

        Schema::create('community_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('community_post_id')->constrained('community_posts');
            $t->foreignId('user_id')->constrained('users');
            $t->string('reason', 500);
            $t->dateTime('created_at');
            $t->unique(['community_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['community_reports', 'community_likes', 'community_comments', 'community_posts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
