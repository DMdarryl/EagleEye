<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EyeIngestionTest extends TestCase
{
    use RefreshDatabase;
    public function test_same_fingerprint_merges_into_one_issue(): void
    {
        $project = Project::create(['name' => 'Test', 'dsn_key' => 'test-key-1', 'environment' => 'testing']);

        $payload = [
            'exception_class' => 'App\\Exceptions\\InvoiceFailed',
            'message' => 'Invoice #8821 failed',
            'level' => 'error',
            'stacktrace' => [
                ['file' => app_path('Services/Invoice.php'), 'line' => 42, 'in_app' => true, 'function' => 'charge'],
            ],
        ];

        $response1 = $this->postJson('/api/eye/ingest', $payload, ['X-Watcher-Key' => 'test-key-1']);
        $response2 = $this->postJson('/api/eye/ingest', array_merge($payload, ['message' => 'Invoice #8822 failed']), ['X-Watcher-Key' => 'test-key-1']);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $this->assertDatabaseCount('issues', 1);
        $issue = Issue::first();
        $this->assertSame(2, $issue->occurrence_count);
        $this->assertDatabaseCount('events', 2);
        $this->assertSame(2, $issue->events()->count());
    }

    public function test_rate_limit_allows_ten_per_minute(): void
    {
        $client = new \App\Watchers\SentinelClient();

        $method = new \ReflectionMethod($client, 'rateLimit');
        $method->setAccessible(true);

        $results = [];
        for ($i = 0; $i < 11; $i++) {
            $results[] = $method->invoke($client, 'loop-error-1');
        }

        $this->assertSame(array_fill(0, 10, true), array_slice($results, 0, 10));
        $this->assertFalse($results[10]);
    }

    public function test_rejects_invalid_key(): void
    {
        $this->postJson('/api/eye/ingest', ['exception_class' => 'X', 'message' => 'y'], ['X-Watcher-Key' => 'bad'])->assertStatus(401);
    }
}
