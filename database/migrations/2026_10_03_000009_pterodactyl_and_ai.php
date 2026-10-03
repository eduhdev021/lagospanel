<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pterodactyl_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('connector_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('remote_user_id');
            $t->timestamps();
            $t->unique(['connector_id', 'user_id']);
            $t->unique(['connector_id', 'remote_user_id']);
        });
        Schema::create('ai_settings', function (Blueprint $t) {
            $t->id();
            $t->string('endpoint')->default('https://ollama.com');
            $t->text('token')->nullable();
            $t->string('model', 180)->nullable();
            $t->json('models')->nullable();
            $t->timestamp('models_checked_at')->nullable();
            $t->boolean('active')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('ai_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('ai_turns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ai_thread_id')->constrained()->cascadeOnDelete();
            $t->uuid('request_key');
            $t->text('user_text');
            $t->text('assistant_text')->nullable();
            $t->string('status', 20)->default('queued')->index();
            $t->unsignedInteger('settings_version');
            $t->string('model', 180);
            $t->uuid('execution_token')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamps();
            $t->unique(['ai_thread_id', 'request_key']);
        });
        Schema::create('ai_daily_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->unsignedInteger('requests')->default(0);
            $t->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        if (DB::table('connectors')->where('driver', 'pterodactyl')->exists() || DB::table('pterodactyl_accounts')->exists() || DB::table('ai_turns')->exists() || DB::table('ai_settings')->exists()) {
            throw new RuntimeException('Preserve vínculos nativos, configurações e conversas antes do rollback.');
        }
        foreach (['ai_daily_usages', 'ai_turns', 'ai_threads', 'ai_settings', 'pterodactyl_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
