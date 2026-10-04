<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->longText('avatar_content')->nullable();
            $t->string('avatar_mime', 30)->nullable();
            $t->string('avatar_source', 20)->nullable();
            $t->timestamp('avatar_updated_at')->nullable();
        });
        Schema::table('ai_turns', fn (Blueprint $t) => $t->string('failure_code', 40)->nullable());
        Schema::table('ai_settings', function (Blueprint $t) {
            $t->string('probe_status', 40)->nullable();
            $t->timestamp('probe_checked_at')->nullable();
            $t->unsignedInteger('probe_version')->nullable();
        });
        Schema::create('runtime_signals', function (Blueprint $t) {
            $t->string('name', 80)->primary();
            $t->timestamp('seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_signals');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['avatar_content', 'avatar_mime', 'avatar_source', 'avatar_updated_at']));
        Schema::table('ai_turns', fn (Blueprint $t) => $t->dropColumn('failure_code'));
        Schema::table('ai_settings', fn (Blueprint $t) => $t->dropColumn(['probe_status', 'probe_checked_at', 'probe_version']));
    }
};
