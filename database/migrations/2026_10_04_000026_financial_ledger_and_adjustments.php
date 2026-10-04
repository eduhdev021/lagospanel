<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->bigInteger('paid_minor')->default(0)->after('total_minor');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('status', 20)->default('captured')->after('currency');
            $table->bigInteger('refunded_minor')->default(0)->after('amount_minor');
            $table->json('provider_payload')->nullable()->after('note');
            $table->timestamp('captured_at')->nullable()->after('created_at');
            $table->index(['invoice_id', 'status']);
        });

        Schema::create('financial_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->string('reference', 190)->unique();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('BRL');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gateway', 40);
            $table->string('reference', 190)->unique();
            $table->string('provider_reference', 190)->nullable()->index();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 20)->default('processed');
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_chargebacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gateway', 40);
            $table->string('reference', 190)->unique();
            $table->string('provider_reference', 190)->nullable()->index();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 20)->default('open');
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payment_chargebacks', 'payment_refunds', 'financial_ledger_entries'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['invoice_id', 'status']);
            $table->dropColumn(['status', 'refunded_minor', 'provider_payload', 'captured_at']);
        });
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('paid_minor'));
    }
};
