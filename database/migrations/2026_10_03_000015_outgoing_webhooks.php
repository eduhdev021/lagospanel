<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('name', 100);
            $t->string('url', 1000);
            $t->text('secret');
            $t->json('events');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('webhook_endpoint_id')->constrained()->restrictOnDelete();
            $t->foreignId('audit_event_id')->constrained()->restrictOnDelete();
            $t->uuid('event_id')->index();
            $t->text('payload');
            $t->string('event_type', 80);
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->unsignedInteger('max_attempts')->default(5);
            $t->unsignedInteger('retry_base')->default(0);
            $t->uuid('execution_token')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->unique(['webhook_endpoint_id', 'audit_event_id'], 'webhook_delivery_event_unique');
            $t->index(['status', 'next_attempt_at']);
        });
        Schema::create('webhook_attempts', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('webhook_delivery_id')->constrained()->restrictOnDelete();
            $t->uuid('execution_token')->unique();
            $t->unsignedInteger('number');
            $t->string('outcome', 30)->default('processing');
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['webhook_endpoints', 'webhook_deliveries', 'webhook_attempts'] as $t) {
            if (DB::table($t)->exists()) {
                throw new RuntimeException('Preserve destinos, eventos e tentativas antes de rollback.');
            }
        }Schema::dropIfExists('webhook_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
