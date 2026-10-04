<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_providers', function (Blueprint $t) {
            $t->string('provider', 20)->primary();
            $t->boolean('enabled')->default(false);
            $t->string('client_id')->nullable();
            $t->text('client_secret')->nullable();
            $t->unsignedInteger('version')->default(0);
            $t->timestamps();
        });
        Schema::create('social_identities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 20);
            $t->string('client_hash', 64);
            $t->string('subject', 191);
            $t->timestamps();
            $t->unique(['provider', 'client_hash', 'subject'], 'social_subject_unique');
            $t->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        if (DB::table('social_identities')->exists()) {
            throw new RuntimeException('Preserve os vínculos sociais antes do rollback.');
        }Schema::dropIfExists('social_identities');
        Schema::dropIfExists('social_providers');
    }
};
