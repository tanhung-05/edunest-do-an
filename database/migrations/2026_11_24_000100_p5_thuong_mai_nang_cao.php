<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| P5 (02b §1–§2): giỏ, mã giảm giá, khuyến mãi, mã kích hoạt, hóa đơn, đánh giá, thông báo, cảnh báo.
| Thêm FK dự trù trên bảng L (orders.coupon_id, order_items.promotion_id, enrollments.activation_code_id).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->unsignedBigInteger('price');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->foreignId('created_by_id')->nullable()->constrained('users');
            $t->foreignId('updated_by_id')->nullable()->constrained('users');
            $t->dateTime('notified_at')->nullable();
            $t->timestamps();
            $t->index(['course_id', 'starts_at']);
            $t->index(['notified_at', 'starts_at']);
        });
        DB::statement('ALTER TABLE course_promotions ADD CONSTRAINT course_promotions_range_chk CHECK (ends_on > starts_on)');

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('discount_type', 10);
            $t->unsignedBigInteger('value');
            $t->unsignedBigInteger('max_discount')->nullable();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->unsignedInteger('total_limit');
            $t->unsignedInteger('per_user_limit')->default(1);
            $t->string('scope_type', 10)->default('TOAN_BO');
            $t->foreignId('scope_category_id')->nullable()->constrained('categories');
            $t->foreignId('scope_course_id')->nullable()->constrained('courses');
            $t->unsignedBigInteger('min_order')->default(0);
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by_id')->nullable()->constrained('users');
            $t->foreignId('updated_by_id')->nullable()->constrained('users');
            $t->timestamps();
        });
        DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_value_chk CHECK (value > 0 AND (discount_type <> 'PHAN_TRAM' OR (value <= 100 AND max_discount IS NOT NULL)))");

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained('coupons');
            $t->foreignId('order_id')->constrained('orders');
            $t->foreignId('user_id')->constrained('users');
            $t->string('status', 8)->default('GIU_CHO');
            $t->unsignedBigInteger('discount_amount');
            $t->dateTime('order_expires_at');
            $t->dateTime('held_at');
            $t->dateTime('used_at')->nullable();
            $t->dateTime('released_at')->nullable();
            $t->timestamps();
            $t->unique(['coupon_id', 'order_id']);
            $t->index(['coupon_id', 'status']);
            $t->index(['coupon_id', 'user_id', 'status']);
        });

        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users');
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained('carts');
            $t->foreignId('course_id')->constrained('courses');
            $t->dateTime('added_at');
            $t->unique(['cart_id', 'course_id']);
        });
        Schema::create('wishlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('course_id')->constrained('courses');
            $t->dateTime('created_at');
            $t->unique(['user_id', 'course_id']);
            $t->index('course_id');
        });

        Schema::create('activation_code_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained('courses');
            $t->unsignedSmallInteger('quantity');
            $t->dateTime('redeem_until');
            $t->unsignedSmallInteger('access_months')->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by_id')->constrained('users');
            $t->timestamps();
        });
        Schema::create('activation_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('activation_code_batches');
            $t->char('code', 12)->unique();
            $t->string('status', 10)->default('CHUA_DUNG');
            $t->foreignId('redeemed_by_id')->nullable()->constrained('users');
            $t->dateTime('redeemed_at')->nullable();
            $t->foreignId('enrollment_id')->nullable()->constrained('enrollments');
            $t->dateTime('voided_at')->nullable();
            $t->foreignId('voided_by_id')->nullable()->constrained('users');
            $t->string('void_reason', 500)->nullable();
            $t->timestamps();
            $t->index(['batch_id', 'status']);
        });
        Schema::create('activation_attempt_windows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users');
            $t->dateTime('window_started_at')->nullable();
            $t->unsignedTinyInteger('failures')->default(0);
            $t->dateTime('locked_until')->nullable();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained('orders');
            $t->string('invoice_number', 20)->unique();
            $t->dateTime('issued_at');
            $t->unsignedBigInteger('amount');
            $t->string('pdf_path', 255)->nullable();
            $t->string('pdf_status', 10)->default('DANG_TAO');
            $t->unsignedTinyInteger('pdf_attempts')->default(0);
            $t->timestamps();
        });

        Schema::create('course_ratings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('course_id')->constrained('courses');
            $t->foreignId('enrollment_id')->constrained('enrollments');
            $t->unsignedTinyInteger('stars');
            $t->text('comment')->nullable();
            $t->string('status', 6)->default('HIEN');
            $t->string('hidden_reason', 500)->nullable();
            $t->dateTime('hidden_at')->nullable();
            $t->foreignId('hidden_by_id')->nullable()->constrained('users');
            $t->text('reply')->nullable();
            $t->foreignId('replied_by_id')->nullable()->constrained('users');
            $t->dateTime('replied_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'course_id']);
            $t->index(['course_id', 'status']);
        });
        DB::statement('ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_stars_chk CHECK (stars BETWEEN 1 AND 5)');

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->string('dedupe_key', 150)->nullable()->unique();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('security_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->json('ip_list');
            $t->dateTime('detected_at');
            $t->index(['user_id', 'detected_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->json('notification_mutes')->nullable();
        });
        Schema::table('user_devices', function (Blueprint $t) {
            $t->string('user_agent', 255)->nullable();
            $t->string('last_ip', 45)->nullable();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->foreign('coupon_id')->references('id')->on('coupons');
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->foreign('promotion_id')->references('id')->on('course_promotions');
        });
        Schema::table('enrollments', function (Blueprint $t) {
            $t->foreign('activation_code_id')->references('id')->on('activation_codes');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $t) => $t->dropForeign(['activation_code_id']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropForeign(['promotion_id']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropForeign(['coupon_id']));
        Schema::table('user_devices', fn (Blueprint $t) => $t->dropColumn(['user_agent', 'last_ip']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notification_mutes'));
        foreach (['security_alerts', 'notifications', 'course_ratings', 'invoices', 'activation_attempt_windows', 'activation_codes',
            'activation_code_batches', 'wishlists', 'cart_items', 'carts', 'coupon_redemptions', 'coupons', 'course_promotions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
