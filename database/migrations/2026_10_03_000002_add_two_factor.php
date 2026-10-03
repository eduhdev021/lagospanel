<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('totp_secret')->nullable();
            $t->bigInteger('totp_last_step')->default(-1);
            $t->text('recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['totp_secret', 'totp_last_step', 'recovery_codes']));
    }
};
