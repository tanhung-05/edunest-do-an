<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| P3 (09 §6): orders → order_items → payments → payment_events → enrollments → FK order_items.enrollment_id.
| Cột/ràng buộc theo 02 L28–L32; cột dự trù P5/P8 nullable.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('public_code', 16)->unique();
            $t->foreignId('user_id')->constrained('users');
            $t->string('status', 15)->default('CHO_THANH_TOAN');
            $t->string('source', 10)->default('MUA_NGAY');
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('discount_total')->default(0);
            $t->unsignedBigInteger('total');
            $t->unsignedBigInteger('coupon_id')->nullable();
            $t->unsignedBigInteger('affiliate_id')->nullable();
            $t->dateTime('expires_at');
            $t->string('status_before_cxl', 15)->nullable();
            $t->string('cancel_reason', 20)->nullable();
            $t->dateTime('cancelled_at')->nullable();
            $t->dateTime('expired_at')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->dateTime('first_completed_at')->nullable();
            $t->unsignedSmallInteger('affiliate_rate_bp_snapshot')->nullable();
            $t->boolean('affiliate_commission_eligible')->nullable();
            $t->dateTime('grant_stopped_at')->nullable();
            $t->foreignId('grant_stopped_by_id')->nullable()->constrained('users');
            $t->string('grant_stop_reason', 500)->nullable();
            $t->unsignedBigInteger('grant_refund_payment_id')->nullable();
            $t->char('grant_ticket', 26)->nullable();
            $t->dateTime('grant_ticket_at')->nullable();
            $t->unsignedBigInteger('grant_job_id')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['status', 'expires_at']);
            $t->index(['status', 'updated_at']);
        });
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_total_chk CHECK (total <= subtotal)");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_before_cxl_chk CHECK (status_before_cxl IS NULL OR status_before_cxl IN ('HET_HAN','DA_HUY'))");

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders');
            $t->foreignId('course_id')->constrained('courses');
            $t->string('kind', 10)->default('MUA_KHOA');
            $t->string('status', 10)->default('CHUA_CAP');
            $t->string('course_title', 200);
            $t->unsignedBigInteger('list_price');
            $t->unsignedBigInteger('promotion_id')->nullable();
            $t->unsignedBigInteger('effective_price');
            $t->unsignedBigInteger('allocated_discount')->default(0);
            $t->unsignedBigInteger('final_amount');
            $t->unsignedSmallInteger('access_months')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->unsignedBigInteger('bundle_id')->nullable();
            $t->unsignedBigInteger('enrollment_id')->nullable();
            $t->dateTime('expiry_before')->nullable();
            $t->dateTime('expiry_after')->nullable();
            $t->dateTime('granted_at')->nullable();
            $t->unsignedTinyInteger('grant_attempts')->default(0);
            $t->dateTime('grant_last_attempt_at', 3)->nullable();
            $t->dateTime('grant_next_attempt_at', 3)->nullable();
            $t->char('grant_run_token', 26)->nullable();
            $t->dateTime('grant_run_expires_at', 3)->nullable();
            $t->string('grant_last_error', 500)->nullable();
            $t->string('grant_block_code', 32)->nullable();
            $t->timestamps();
            $t->unique(['order_id', 'course_id']);
            $t->index(['course_id', 'status']);
            $t->index('enrollment_id');
        });
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_final_chk CHECK (final_amount = effective_price - allocated_discount)');
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_kind_chk CHECK (kind = 'MUA_KHOA' OR access_months IS NOT NULL)");

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders');
            $t->string('transaction_code', 40)->unique();
            $t->unsignedBigInteger('requested_amount');
            $t->unsignedBigInteger('received_amount')->nullable();
            $t->string('channel', 12)->default('MO_PHONG');
            $t->string('txn_status', 12)->default('KHOI_TAO');
            $t->string('reconcile_status', 12)->nullable();
            $t->dateTime('terms_accepted_at');
            $t->boolean('is_late')->default(false);
            $t->dateTime('succeeded_at')->nullable();
            $t->dateTime('failed_at')->nullable();
            $t->dateTime('reconciled_at')->nullable();
            $t->foreignId('accepted_late_by_id')->nullable()->constrained('users');
            $t->dateTime('accepted_late_at')->nullable();
            $t->dateTime('refunded_at')->nullable();
            $t->foreignId('refunded_by_id')->nullable()->constrained('users');
            $t->string('refund_reason', 500)->nullable();
            $t->tinyInteger('valid_key')->nullable()->storedAs("IF(reconcile_status = 'HOP_LE', 1, NULL)");
            $t->timestamps();
            $t->unique(['order_id', 'valid_key']);
            $t->index(['order_id', 'txn_status']);
            $t->index(['reconcile_status', 'txn_status', 'order_id']);
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_reconcile_chk CHECK (reconcile_status IS NULL OR txn_status = 'THANH_CONG')");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_refund_chk CHECK (reconcile_status IS NULL OR reconcile_status <> 'DA_HOAN_LAI' OR refund_reason IS NOT NULL)");

        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->nullable()->constrained('payments');
            $t->string('transaction_code', 40);
            $t->string('result', 12);
            $t->boolean('signature_valid');
            $t->binary('payload');
            $t->string('signature', 255)->nullable();
            $t->dateTime('received_at');
            $t->string('outcome', 20);
            $t->tinyInteger('accepted_key')->nullable()->storedAs("IF(outcome = 'DA_GHI', 1, NULL)");
            $t->unique(['transaction_code', 'result', 'accepted_key']);
            $t->index('payment_id');
        });
        DB::statement('ALTER TABLE payment_events MODIFY payload MEDIUMBLOB NOT NULL');

        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('course_id')->constrained('courses');
            $t->foreignId('course_version_id')->constrained('course_versions');
            $t->string('learning_status', 12)->default('DANG_HOC');
            $t->dateTime('het_han_luc')->nullable();
            $t->dateTime('revoked_at', 3)->nullable();
            $t->foreignId('revoked_by_id')->nullable()->constrained('users');
            $t->string('revoked_reason', 500)->nullable();
            $t->string('source', 15)->default('DON_HANG');
            $t->foreignId('order_item_id')->nullable()->constrained('order_items');
            $t->unsignedBigInteger('activation_code_id')->nullable();
            $t->dateTime('granted_at');
            $t->dateTime('started_at')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'course_id']);
            $t->index(['course_id', 'learning_status']);
            $t->index('het_han_luc');
            $t->index(['user_id', 'revoked_at']);
            $t->index(['learning_status', 'id']);
            $t->index(['course_id', 'granted_at']);
        });
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_completed_chk CHECK (learning_status <> 'HOAN_THANH' OR completed_at IS NOT NULL)");

        Schema::table('order_items', function (Blueprint $t) {
            $t->foreign('enrollment_id')->references('id')->on('enrollments');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropForeign(['enrollment_id']);
        });
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
