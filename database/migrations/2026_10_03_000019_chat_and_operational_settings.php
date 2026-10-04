<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_threads', function (Blueprint $t) {
            $t->text('title')->nullable();
            $t->uuid('creation_key')->nullable();
            $t->timestamp('consented_at')->nullable();
            $t->unique(['user_id', 'creation_key']);
        });
        Schema::create('operational_settings', function (Blueprint $t) {
            $t->string('section', 30)->primary();
            $t->unsignedInteger('version')->default(0);
            $t->longText('values')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('operational_settings')->exists()) {
            throw new RuntimeException('Preserve as configurações antes do rollback.');
        }Schema::dropIfExists('operational_settings');
        Schema::table('ai_threads', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'creation_key']);
            $t->dropColumn(['title', 'creation_key', 'consented_at']);
        });
    }
};
