<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 40);
            $table->string('reference', 160);
            $table->string('status', 30)->default('active');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('BRL');
            $table->text('pix_copia_e_cola');
            $table->string('location', 500)->nullable();
            $table->string('provider_reference', 160)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'reference']);
            $table->index(['invoice_id', 'gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_charges');
    }
};
