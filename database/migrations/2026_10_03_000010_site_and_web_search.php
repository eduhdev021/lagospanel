<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $t) {
            $t->boolean('web_enabled')->default(false);
            $t->text('web_token')->nullable();
        });
        Schema::table('ai_turns', function (Blueprint $t) {
            $t->boolean('web_requested')->default(false);
            $t->text('web_query')->nullable();
            $t->text('web_sources')->nullable();
            $t->string('web_status', 24)->nullable();
        });
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('url');
            $t->string('support_email')->nullable();
            $t->boolean('registration_enabled')->default(true);
            $t->mediumText('logo_content')->nullable();
            $t->string('logo_mime', 30)->nullable();
            $t->string('mailer', 16)->default('inherit');
            $t->string('smtp_host')->nullable();
            $t->unsignedInteger('smtp_port')->default(587);
            $t->string('smtp_scheme', 8)->default('smtp');
            $t->string('smtp_username')->nullable();
            $t->text('smtp_password')->nullable();
            $t->string('mail_from_address')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('site_settings')->exists() || DB::table('ai_settings')->where('web_enabled', true)->orWhereNotNull('web_token')->exists() || DB::table('ai_turns')->where('web_requested', true)->exists()) {
            throw new RuntimeException('Preserve configurações e pesquisas antes do rollback.');
        }
        Schema::dropIfExists('site_settings');
        Schema::table('ai_turns', fn (Blueprint $t) => $t->dropColumn(['web_requested', 'web_query', 'web_sources', 'web_status']));
        Schema::table('ai_settings', fn (Blueprint $t) => $t->dropColumn(['web_enabled', 'web_token']));
    }
};
