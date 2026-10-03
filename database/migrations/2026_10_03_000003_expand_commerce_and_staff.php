<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedInteger('max_per_user')->nullable();
            $t->boolean('allow_quantity')->default(true);
        });
        Schema::create('product_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('required')->default(true);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('option_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->bigInteger('recurring_minor')->default(0);
            $t->bigInteger('setup_minor')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->json('value_ids');
            $t->unsignedInteger('quantity');
            $t->string('signature', 64);
            $t->timestamps();
            $t->unique(['user_id', 'signature']);
        });
        Schema::table('coupons', function (Blueprint $t) {
            $t->string('kind')->default('percent');
            $t->bigInteger('fixed_minor')->default(0);
            $t->unsignedInteger('per_user_limit')->nullable();
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->timestamp('expires_at')->nullable()->index();
        });
        Schema::table('services', function (Blueprint $t) {
            $t->json('configuration')->nullable();
        });
        Schema::create('stock_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->string('status')->default('held');
            $t->timestamps();
            $t->unique(['invoice_id', 'product_id']);
        });
        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('held');
            $t->timestamps();
            $t->unique(['coupon_id', 'invoice_id']);
        });
        Schema::create('staff_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->json('permissions');
            $t->timestamps();
        });
        Schema::table('users', fn (Blueprint $t) => $t->foreignId('staff_role_id')->nullable()->constrained('staff_roles')->restrictOnDelete());
        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('category')->default('Geral');
            $t->longText('body');
            $t->boolean('published')->default(false);
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('api_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('token_hash', 64)->unique();
            $t->string('password_fingerprint', 64);
            $t->json('scopes');
            $t->timestamp('expires_at');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('articles');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('staff_role_id'));
        Schema::dropIfExists('staff_roles');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('stock_reservations');
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('configuration'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('expires_at'));
        Schema::table('coupons', fn (Blueprint $t) => $t->dropColumn(['kind', 'fixed_minor', 'per_user_limit']));
        foreach (['cart_items', 'option_values', 'product_options'] as $n) {
            Schema::dropIfExists($n);
        }Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['max_per_user', 'allow_quantity']));
    }
};
