<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connectors', function (Blueprint $t) {
            $t->string('driver', 24)->default('json');
            $t->json('settings')->nullable();
        });
        Schema::table('products', fn (Blueprint $t) => $t->json('provisioning')->nullable());
        Schema::table('services', function (Blueprint $t) {
            $t->json('provisioning')->nullable();
            $t->text('provisioning_secret')->nullable();
            $t->string('native_username', 16)->nullable();
            $t->unique(['connector_id', 'native_username']);
        });
        Schema::table('operations', function (Blueprint $t) {
            $t->string('execution_token', 36)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->json('inspection')->nullable();
            $t->text('review_note')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('connectors')->where('driver', '!=', 'json')->exists() || DB::table('services')->whereNotNull('provisioning')->exists()) {
            throw new RuntimeException('Rollback bloqueado: existem vínculos de provisionamento nativo. Preserve os dados e planeje a restauração.');
        }
        Schema::table('operations', fn (Blueprint $t) => $t->dropColumn(['sent_at', 'inspection', 'review_note', 'execution_token']));
        Schema::table('services', function (Blueprint $t) {
            $t->dropUnique(['connector_id', 'native_username']);
            $t->dropColumn(['provisioning', 'provisioning_secret', 'native_username']);
        });
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('provisioning'));
        Schema::table('connectors', fn (Blueprint $t) => $t->dropColumn(['driver', 'settings']));
    }
};
