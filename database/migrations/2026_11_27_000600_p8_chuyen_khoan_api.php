<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8 M28 (02e §6): liên kết mã ngân hàng–khoản–đơn và thứ tự nguồn (CR-178), bản X4 chờ tra soát (CR-179),
 * điều khoản chuyển khoản của đơn. Token API Sanctum gắn thiết bị ở migration `000700`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dateTime('transfer_terms_accepted_at')->nullable()->after('expires_at');
        });

        Schema::table('payments', function (Blueprint $t) {
            // CR-178: thứ tự giao dịch của nguồn đối soát trong đơn; chỉ lần thứ nhất được xét HOP_LE.
            $t->unsignedInteger('source_seq')->nullable()->after('channel');
            $t->unique(['order_id', 'source_seq']);
        });

        Schema::create('bank_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('bank_code', 40)->unique();
            $t->foreignId('order_id')->constrained('orders');
            $t->foreignId('payment_id')->unique()->constrained('payments');
            $t->unsignedInteger('source_seq');
            $t->string('memo', 140);
            $t->unsignedBigInteger('amount');
            $t->dateTime('received_at');
            $t->timestamps();
        });

        Schema::create('bank_transfer_reviews', function (Blueprint $t) {
            $t->id();
            $t->string('bank_code', 40);
            $t->unsignedBigInteger('amount')->nullable();
            $t->string('memo_masked', 140);
            $t->string('reason', 255);
            $t->dateTime('received_at');
            $t->string('status', 12)->default('CHO_TRA_SOAT');
            $t->timestamps();
            $t->unique(['bank_code', 'status']);
            $t->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_reviews');
        Schema::dropIfExists('bank_transactions');
        Schema::table('payments', function (Blueprint $t) {
            $t->dropUnique(['order_id', 'source_seq']);
            $t->dropColumn('source_seq');
        });
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('transfer_terms_accepted_at'));
    }
};
