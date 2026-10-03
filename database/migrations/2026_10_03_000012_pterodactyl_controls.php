<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pterodactyl_controls', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->uuid('request_key');
            $t->string('signal', 10);
            $t->string('status', 20)->default('processing');
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'request_key']);
            $t->index(['service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pterodactyl_controls');
    }
};
