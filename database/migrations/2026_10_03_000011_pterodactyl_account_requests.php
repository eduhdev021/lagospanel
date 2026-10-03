<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pterodactyl_account_requests', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('connector_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('endpoint');
            $t->string('email');
            $t->string('first_name', 64);
            $t->string('last_name', 64);
            $t->string('username', 32);
            $t->string('external_id', 64)->unique();
            $t->string('status', 20)->default('review');
            $t->uuid('execution_token')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->unsignedInteger('remote_user_id')->nullable();
            $t->timestamps();
            $t->unique(['connector_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pterodactyl_account_requests');
    }
};
