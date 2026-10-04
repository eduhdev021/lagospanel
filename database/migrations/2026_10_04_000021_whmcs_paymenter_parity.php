<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('tax_id', 32)->nullable();
            $t->string('company_name', 180)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('billing_address', 500)->nullable();
            $t->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('external_source', 40)->nullable();
            $t->string('external_id', 80)->nullable();
            $t->unique(['external_source', 'external_id']);
        });

        Schema::table('products', function (Blueprint $t) {
            $t->string('category', 100)->nullable()->index();
            $t->boolean('allow_upgrade')->default(true);
        });

        Schema::create('service_upgrades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->foreignId('to_product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->bigInteger('delta_minor');
            $t->string('status', 20)->default('pending');
            $t->string('request_key', 64);
            $t->timestamps();
            $t->unique(['user_id', 'request_key']);
        });

        Schema::create('product_addons', function (Blueprint $t) {
            $t->id();
            $t->string('name', 180);
            $t->string('description', 1000)->nullable();
            $t->unsignedBigInteger('price_minor');
            $t->unsignedBigInteger('setup_minor')->default(0);
            $t->string('cycle', 20)->default('monthly');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('service_addons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_addon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 180);
            $t->unsignedBigInteger('price_minor');
            $t->string('cycle', 20);
            $t->string('status', 20)->default('pending');
            $t->date('next_due')->nullable();
            $t->timestamps();
        });

        Schema::create('domain_tlds', function (Blueprint $t) {
            $t->id();
            $t->string('tld', 64)->unique();
            $t->unsignedBigInteger('register_minor');
            $t->unsignedBigInteger('transfer_minor');
            $t->unsignedBigInteger('renew_minor');
            $t->string('registrar', 40)->default('manual');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('domain_registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('domain_tld_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('domain', 190)->unique();
            $t->string('operation_type', 20)->default('register');
            $t->unsignedTinyInteger('years')->default(1);
            $t->unsignedBigInteger('renew_minor');
            $t->string('registrar', 40)->default('manual');
            $t->string('status', 20)->default('pending');
            $t->boolean('transfer_lock')->default(true);
            $t->text('epp_code')->nullable();
            $t->json('nameservers')->nullable();
            $t->date('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('affiliates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('code', 40)->unique();
            $t->unsignedTinyInteger('rate_percent')->default(10);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->unsignedBigInteger('available_minor')->default(0);
            $t->unsignedBigInteger('total_earned_minor')->default(0);
            $t->unsignedBigInteger('total_withdrawn_minor')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('affiliate_commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->string('status', 20)->default('credited');
            $t->timestamps();
        });

        Schema::create('account_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name', 120);
            $t->string('email', 190);
            $t->json('permissions');
            $t->boolean('receive_billing_emails')->default(true);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['owner_id', 'email']);
        });
    }

    public function down(): void
    {
        foreach (['service_upgrades', 'product_addons', 'service_addons', 'domain_tlds', 'domain_registrations', 'affiliates', 'affiliate_commissions', 'account_contacts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Preserve os registros de domínios, upgrades, addons, afiliados e subcontas antes do rollback.');
            }
        }
        Schema::dropIfExists('account_contacts');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('domain_registrations');
        Schema::dropIfExists('domain_tlds');
        Schema::dropIfExists('service_addons');
        Schema::dropIfExists('product_addons');
        Schema::dropIfExists('service_upgrades');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['category', 'allow_upgrade']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['external_source', 'external_id']);
            $t->dropConstrainedForeignId('referred_by_id');
            $t->dropColumn(['tax_id', 'company_name', 'phone', 'billing_address', 'external_source', 'external_id']);
        });
    }
};
