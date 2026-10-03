<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->boolean('is_admin')->default(false);
            $t->boolean('password_reset_required')->default(false);
            $t->bigInteger('balance_minor')->default(0);
        });
        Schema::create('connectors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('endpoint');
            $t->text('token');
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->bigInteger('price_minor');
            $t->bigInteger('setup_minor')->default(0);
            $t->string('cycle')->default('monthly');
            $t->boolean('active')->default(true);
            $t->unsignedInteger('stock')->nullable();
            $t->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->unsignedTinyInteger('percent');
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('uses')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->uuid('request_key');
            $t->string('fingerprint', 64);
            $t->bigInteger('total_minor');
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['user_id', 'request_key']);
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('connector_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('status')->default('pending');
            $t->string('cycle');
            $t->bigInteger('price_minor');
            $t->date('next_due')->nullable();
            $t->unsignedTinyInteger('billing_anchor')->nullable();
            $t->string('remote_id')->nullable();
            $t->boolean('auto_renew')->default(true);
            $t->timestamp('cancellation_requested_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->timestamps();
            $t->index(['status', 'next_due']);
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('type')->default('order');
            $t->string('status')->default('unpaid');
            $t->string('currency', 3)->default('BRL');
            $t->bigInteger('total_minor');
            $t->json('snapshot');
            $t->date('due_date');
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('renewal_service_id')->nullable()->constrained('services')->restrictOnDelete();
            $t->date('period_start')->nullable();
            $t->timestamps();
            $t->unique(['renewal_service_id', 'period_start']);
            $t->index(['status', 'due_date']);
        });
        Schema::create('invoice_service', function (Blueprint $t) {
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->primary(['invoice_id', 'service_id']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->string('gateway', 40);
            $t->string('reference', 160);
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->unique(['gateway', 'reference']);
        });
        Schema::create('wallet_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('reference', 160)->unique();
            $t->bigInteger('amount_minor');
            $t->bigInteger('balance_after_minor');
            $t->string('description');
            $t->timestamps();
        });
        Schema::create('operations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->string('action');
            $t->string('status')->default('pending');
            $t->string('reference', 160)->unique();
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('subject');
            $t->text('body');
            $t->string('status')->default('open');
            $t->string('department')->default('support');
            $t->timestamps();
        });
        Schema::create('ticket_replies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event');
            $t->string('subject')->nullable();
            $t->json('context')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'ticket_replies', 'tickets', 'operations', 'wallet_entries', 'payments', 'invoice_service', 'invoices', 'services', 'orders', 'coupons', 'products', 'connectors'] as $n) {
            Schema::dropIfExists($n);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['legacy_id', 'is_admin', 'password_reset_required', 'balance_minor']));
    }
};
