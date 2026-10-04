<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_preferences', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->unsignedInteger('id')->primary();
            $t->boolean('require_two_factor')->nullable();
            $t->unsignedInteger('version')->default(0);
            $t->timestamp('updater_heartbeat_at')->nullable();
            $t->timestamps();
        });
        Schema::create('panel_updates', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('source_sha', 40);
            $t->string('target_sha', 40);
            $t->string('status', 20)->default('checked');
            $t->string('phase', 40)->nullable();
            $t->json('events')->nullable();
            $t->string('backup_path')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('panel_updates')->exists() || DB::table('admin_preferences')->whereNotNull('require_two_factor')->exists()) {
            throw new RuntimeException('Preserve configurações e histórico antes do rollback.');
        }Schema::dropIfExists('panel_updates');
        Schema::dropIfExists('admin_preferences');
    }
};
