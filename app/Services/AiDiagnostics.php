<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\AiTurn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiDiagnostics
{
    public function heartbeat(string $connection, string $queues): void
    {
        if ($connection !== 'database' || ! in_array((string) config('queue.connections.database.queue', 'default'), array_map('trim', explode(',', $queues)), true) || ! Schema::hasTable('runtime_signals')) {
            return;
        }
        DB::table('runtime_signals')->updateOrInsert(['name' => 'ai-database-worker'], ['seen_at' => now()]);
    }

    public function report(): array
    {
        $s = AiSetting::find(1);
        $seen = Schema::hasTable('runtime_signals') ? DB::table('runtime_signals')->where('name', 'ai-database-worker')->value('seen_at') : null;
        $last = AiTurn::where('status', 'failed')->latest('id')->first(['failure_code']);

        return ['enabled' => (bool) $s?->active, 'model' => $s?->model, 'token_configured' => (bool) $s?->token, 'web_token_configured' => (bool) $s?->web_token, 'worker_seen_at' => $seen, 'worker_recent' => $seen && Carbon::parse($seen)->gt(now()->subSeconds(120)), 'queue_name' => config('queue.connections.database.queue', 'default'), 'retry_after' => config('queue.connections.database.retry_after'), 'paused' => is_file(storage_path('framework/down')) || is_file(storage_path('framework/panel-update-pause')), 'queued' => AiTurn::where('status', 'queued')->count(), 'processing' => AiTurn::where('status', 'processing')->count(), 'last_failure' => $last ? AiFailure::message($last->failure_code) : null, 'probe_status' => $s?->probe_version === $s?->version ? $s?->probe_status : null, 'probe_checked_at' => $s?->probe_checked_at?->toIso8601String()];
    }
}
