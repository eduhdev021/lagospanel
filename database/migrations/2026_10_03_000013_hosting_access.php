<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plesk_customer_requests', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('connector_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('endpoint');
            $t->string('email');
            $t->string('name', 60);
            $t->string('login', 60);
            $t->uuid('external_id')->unique();
            $t->text('secret')->nullable();
            $t->string('status', 20)->default('review');
            $t->uuid('execution_token')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->unsignedInteger('remote_id')->nullable();
            $t->timestamps();
            $t->unique(['connector_id', 'user_id']);
            $t->unique(['connector_id', 'remote_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('plesk_customer_requests')->exists()) {
            throw new RuntimeException('Rollback bloqueado: preserve os vínculos e marcadores de envio Plesk.');
        }
        Schema::dropIfExists('plesk_customer_requests');
    }
};
