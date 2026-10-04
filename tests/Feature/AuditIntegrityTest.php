<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Services\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_are_correlated_and_hash_chained(): void
    {
        Audit::record('test.created', 'test:1', ['safe' => true]);
        Audit::record('test.updated', 'test:1', ['safe' => true]);

        $events = AuditEvent::orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertNotEmpty($events[0]->request_id);
        $this->assertNull($events[0]->previous_hash);
        $this->assertSame($events[0]->hash, $events[1]->previous_hash);
        $this->assertSame(0, Artisan::call('lagos:audit:verify'));
    }

    public function test_verifier_rejects_tampered_context(): void
    {
        Audit::record('test.created', 'test:1', ['safe' => true]);
        AuditEvent::query()->first()->update(['context' => ['safe' => false]]);

        $this->assertSame(1, Artisan::call('lagos:audit:verify'));
    }
}
