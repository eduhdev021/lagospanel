<?php

namespace Tests\Feature;

use App\Services\PanelUpdateSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelUpdateSourceTest extends TestCase
{
    private function fake(array $runs): void
    {
        $url = 'https://api.github.com/repos/'.PanelUpdateSource::REPOSITORY;
        Http::preventStrayRequests();
        Http::fake([$url => Http::response(['full_name' => PanelUpdateSource::REPOSITORY, 'private' => false, 'archived' => false]), $url.'/commits/main' => Http::response(['sha' => str_repeat('a', 40)]), $url.'/actions/runs*' => Http::response(['workflow_runs' => $runs])]);
    }

    private function runData(): array
    {
        return ['id' => 1, 'head_sha' => str_repeat('a', 40), 'head_branch' => 'main', 'event' => 'push', 'path' => '.github/workflows/tests.yml', 'head_repository' => ['full_name' => PanelUpdateSource::REPOSITORY], 'status' => 'completed', 'conclusion' => 'success'];
    }

    public function test_accepts_exact_successful_push_workflow(): void
    {
        $this->fake([$this->runData()]);
        $this->assertSame(str_repeat('a', 40), app(PanelUpdateSource::class)->latest());
    }

    public function test_rejects_pull_request_workflow(): void
    {
        $run = $this->runData();
        $run['event'] = 'pull_request';
        $this->fake([$run]);
        $this->expectException(\RuntimeException::class);
        app(PanelUpdateSource::class)->latest();
    }

    public function test_newer_failed_run_invalidates_previous_success(): void
    {
        $bad = $this->runData();
        $bad['id'] = 2;
        $bad['conclusion'] = 'failure';
        $this->fake([$this->runData(), $bad]);
        $this->expectException(\RuntimeException::class);
        app(PanelUpdateSource::class)->latest();
    }
}
