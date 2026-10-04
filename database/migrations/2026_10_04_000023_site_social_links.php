<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('social_links')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('site_settings')->whereNotNull('social_links')->exists()) {
            throw new RuntimeException('Preserve configured social links before rolling back this migration.');
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
