<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->uuid('request_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->char('previous_hash', 64)->nullable()->index();
            $table->char('hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('audit_events', 'hash') && Schema::hasColumn('audit_events', 'previous_hash')) {
            Schema::table('audit_events', function (Blueprint $table): void {
                $table->dropUnique(['hash']);
                $table->dropIndex(['previous_hash']);
                $table->dropColumn(['request_id', 'ip_address', 'user_agent', 'occurred_at', 'previous_hash', 'hash']);
            });
        }
    }
};
