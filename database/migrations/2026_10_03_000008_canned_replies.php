<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canned_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('department', 20)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('canned_replies')->exists()) {
            throw new RuntimeException('Rollback bloqueado: preserve/exporte os modelos antes de remover a biblioteca.');
        }
        Schema::dropIfExists('canned_replies');
    }
};
