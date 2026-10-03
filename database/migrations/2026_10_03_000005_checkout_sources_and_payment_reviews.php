<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('source')->default('single');
            $t->string('coupon_code', 40)->default('');
        });
        Schema::create('payment_reviews', function (Blueprint $t) {
            $t->id();
            $t->string('fingerprint', 64)->unique();
            $t->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('provider_invoice_ref');
            $t->string('gateway');
            $t->string('reference', 160);
            $t->bigInteger('amount_minor');
            $t->string('currency', 12);
            $t->string('status')->default('open');
            $t->text('reason');
            $t->text('resolution')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reviews');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['source', 'coupon_code']));
    }
};
