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
            $table->mediumText('favicon_content')->nullable();
            $table->string('favicon_mime', 40)->nullable();
            $table->mediumText('og_image_content')->nullable();
            $table->string('og_image_mime', 40)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('footer_explore_title', 60)->nullable();
            $table->string('footer_info_title', 60)->nullable();
            $table->json('footer_links')->nullable();
        });
    }

    public function down(): void
    {
        $customized = DB::table('site_settings')
            ->whereNotNull('favicon_content')
            ->orWhereNotNull('og_image_content')
            ->orWhereNotNull('meta_description')
            ->orWhereNotNull('brand_color')
            ->orWhereNotNull('accent_color')
            ->orWhereNotNull('footer_explore_title')
            ->orWhereNotNull('footer_info_title')
            ->orWhereNotNull('footer_links')
            ->exists();

        if ($customized) {
            throw new RuntimeException('Preserve customized identity, appearance and footer settings before rollback.');
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'favicon_content',
                'favicon_mime',
                'og_image_content',
                'og_image_mime',
                'meta_description',
                'brand_color',
                'accent_color',
                'footer_explore_title',
                'footer_info_title',
                'footer_links',
            ]);
        });
    }
};
