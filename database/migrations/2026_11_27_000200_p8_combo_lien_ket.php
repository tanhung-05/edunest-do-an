<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P8 M24 (02e §2): combo, khóa trong combo, cộng tác viên, hoa hồng; `order_items.bundle_id`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->string('slug', 220)->unique();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('price');
            $t->boolean('deduct_owned')->default(true);
            $t->boolean('accepts_orders')->default(true);
            $t->foreignId('created_by_id')->constrained('users');
            $t->foreignId('updated_by_id')->constrained('users');
            $t->timestamps();
        });

        Schema::create('bundle_course', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bundle_id')->constrained('bundles');
            $t->foreignId('course_id')->constrained('courses');
            $t->unsignedSmallInteger('position');
            $t->unique(['bundle_id', 'course_id']);
        });

        Schema::table('order_items', function (Blueprint $t) {
            // Cột đã có từ P3 (01 dự trù); P8 chỉ thêm FK.
            $t->foreign('bundle_id')->references('id')->on('bundles');
        });

        Schema::create('affiliates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users');
            $t->string('code', 32)->unique();
            $t->unsignedSmallInteger('rate_bp');
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by_id')->constrained('users');
            $t->foreignId('updated_by_id')->constrained('users');
            $t->timestamps();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->foreign('affiliate_id')->references('id')->on('affiliates');
        });

        Schema::create('affiliate_commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained('orders');
            $t->foreignId('affiliate_id')->constrained('affiliates');
            $t->unsignedBigInteger('base_amount');
            $t->unsignedSmallInteger('rate_bp');
            $t->unsignedBigInteger('amount');
            $t->string('status', 10)->default('CHO_DUYET');
            $t->dateTime('recorded_at', 3);
            $t->dateTime('paid_at', 3)->nullable();
            $t->foreignId('paid_by_id')->nullable()->constrained('users');
            $t->dateTime('cancelled_at', 3)->nullable();
            $t->string('cancel_reason', 200)->nullable();
            $t->index(['affiliate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
        Schema::table('orders', fn (Blueprint $t) => $t->dropForeign(['affiliate_id']));
        Schema::dropIfExists('affiliates');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropForeign(['bundle_id']));
        Schema::dropIfExists('bundle_course');
        Schema::dropIfExists('bundles');
    }
};
