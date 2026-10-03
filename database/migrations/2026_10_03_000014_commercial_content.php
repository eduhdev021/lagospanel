<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->string('title', 180);
            $t->text('terms');
            $t->json('items');
            $t->bigInteger('total_minor');
            $t->string('currency', 3)->default('BRL');
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->date('valid_until');
            $t->unsignedSmallInteger('payment_days')->default(7);
            $t->foreignId('invoice_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });
        Schema::create('bulletins', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->string('title', 180);
            $t->string('kind', 20);
            $t->string('state', 20);
            $t->string('severity', 20);
            $t->text('body');
            $t->unsignedInteger('version')->default(1);
            $t->boolean('published')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['published', 'published_at']);
        });
        Schema::create('bulletin_updates', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('bulletin_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->string('state', 20);
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('download_assets', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('title', 180);
            $t->text('description');
            $t->string('path')->unique();
            $t->string('filename');
            $t->unsignedInteger('size');
            $t->string('sha256', 64);
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['quotes', 'bulletins', 'bulletin_updates', 'download_assets'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Rollback bloqueado: preserve propostas, histórico e arquivos privados com um backup compatível.');
            }
        }
        Schema::dropIfExists('download_assets');
        Schema::dropIfExists('bulletin_updates');
        Schema::dropIfExists('bulletins');
        Schema::dropIfExists('quotes');
    }
};
