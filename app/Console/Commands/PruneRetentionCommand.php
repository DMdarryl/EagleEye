<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Issue;
use App\Models\PerformanceTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneRetentionCommand extends Command
{
    protected $signature = 'eye:prune-retention
        {--days=14 : Raw event/trace retention in days}
        {--max-events-per-issue=10 : Latest events to keep per issue}';

    protected $description = 'Prune raw events and traces older than the retention window and trim excess events per issue.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $max = (int) $this->option('max-events-per-issue');

        $cutoff = now()->subDays($days);

        $deletedEvents = Event::where('created_at', '<', $cutoff)->delete();
        $deletedTraces = PerformanceTrace::where('created_at', '<', $cutoff)->delete();

        // Trim excess events beyond the latest N per issue.
        $issues = Issue::all();
        $trimmed = 0;
        foreach ($issues as $issue) {
            $excess = $issue->events()->latest()->skip($max)->take(50)->get();
            foreach ($excess as $event) {
                $event->delete();
                $trimmed++;
            }
        }

        $this->info(sprintf(
            'Pruned %d events and %d traces older than %d days; trimmed %d excess events.',
            $deletedEvents, $deletedTraces, $days, $trimmed
        ));

        return 0;
    }
}
