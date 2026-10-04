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
            $table->text('footer_description')->nullable();
            $table->string('footer_copyright', 240)->nullable();
            $table->string('footer_tagline', 180)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('site_settings')
            ->whereNotNull('footer_description')
            ->orWhereNotNull('footer_copyright')
            ->orWhereNotNull('footer_tagline')
            ->exists()) {
            throw new RuntimeException('Preserve custom footer text before rolling back this migration.');
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['footer_description', 'footer_copyright', 'footer_tagline']);
        });
    }
};
