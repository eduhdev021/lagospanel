<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->string('eyebrow', 60);
            $t->string('title', 150);
            $t->text('description');
            $t->string('cta', 40);
            $t->unsignedInteger('version')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_settings');
    }
};
