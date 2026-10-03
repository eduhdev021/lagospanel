<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->string('priority', 16)->default('normal');
            $t->unsignedSmallInteger('sla_hours')->default(24);
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('response_due_at')->nullable()->index();
            $t->timestamp('first_responded_at')->nullable();
            $t->timestamp('closed_at')->nullable();
        });
        Schema::table('ticket_replies', function (Blueprint $t) {
            $t->boolean('is_internal')->default(false);
            $t->boolean('is_staff')->default(false);
        });
        Schema::create('ticket_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ticket_reply_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('filename', 180);
            $t->string('mime', 80);
            $t->unsignedInteger('size');
            $t->string('sha256', 64);
            $t->boolean('is_internal')->default(false);
            $t->longText('payload');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('ticket_attachments')->exists() || DB::table('ticket_replies')->where('is_internal', true)->exists()) {
            throw new RuntimeException('Rollback bloqueado: há anexos ou notas internas. Restaure um backup validado com plano de preservação de dados; não remova as proteções de privacidade.');
        }
        Schema::dropIfExists('ticket_attachments');
        Schema::table('ticket_replies', fn (Blueprint $t) => $t->dropColumn(['is_internal', 'is_staff']));
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropIndex(['response_due_at']);
            $t->dropConstrainedForeignId('assigned_to');
            $t->dropColumn(['priority', 'sla_hours', 'response_due_at', 'first_responded_at', 'closed_at']);
        });
    }
};
