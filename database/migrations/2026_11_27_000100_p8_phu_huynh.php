<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P8 M23 (02e §1a): mã mời phụ huynh, liên kết phụ huynh–học viên, nhật ký báo cáo tuần. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_invites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('users');
            $t->string('code', 12)->unique();
            $t->dateTime('issued_at', 3);
            $t->dateTime('expires_at', 3);
            $t->dateTime('used_at', 3)->nullable();
            $t->foreignId('used_by_id')->nullable()->constrained('users');
            $t->index(['student_id', 'issued_at']);
        });

        Schema::create('guardian_student', function (Blueprint $t) {
            $t->id();
            $t->foreignId('guardian_id')->constrained('users');
            $t->foreignId('student_id')->constrained('users');
            $t->foreignId('guardian_invite_id')->constrained('guardian_invites');
            $t->dateTime('linked_at', 3);
            $t->dateTime('removed_at', 3)->nullable();
            $t->foreignId('removed_by_id')->nullable()->constrained('users');
            $t->unique(['guardian_id', 'student_id']);
            $t->index(['student_id', 'removed_at']);
        });

        // CR-234: mỗi (cặp, tuần) gửi tối đa một lần.
        Schema::create('guardian_weekly_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('guardian_id')->constrained('users');
            $t->foreignId('student_id')->constrained('users');
            $t->date('week_start');
            $t->dateTime('sent_at', 3);
            $t->unique(['guardian_id', 'student_id', 'week_start'], 'guardian_weekly_unique');
        });
    }

    public function down(): void
    {
        foreach (['guardian_weekly_reports', 'guardian_student', 'guardian_invites'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
