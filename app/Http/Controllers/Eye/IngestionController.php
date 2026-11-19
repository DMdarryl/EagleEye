<?php

namespace App\Http\Controllers\Eye;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class IngestionController extends Controller
{
    public const MAX_PAYLOAD_BYTES = 262_144; // 256 KB

    public function store(Request $request)
    {
        $key = $request->header('X-Watcher-Key');
        if (! $key) {
            return response()->json(['error' => 'Missing X-Watcher-Key'], 401);
        }

        $project = Project::where('dsn_key', $key)->first();
        if (! $project) {
            return response()->json(['error' => 'Invalid key'], 401);
        }

        if ($request->headers->get('content-length') > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        $data = $request->validate([
            'exception_class' => 'required|string',
            'message' => 'required|string',
            'level' => 'sometimes|string',
            'fingerprint' => 'sometimes|string',
            'stacktrace' => 'sometimes|array',
            'breadcrumbs' => 'sometimes|array',
            'user_context' => 'sometimes|array',
            'payload' => 'sometimes|array',
            'release' => 'sometimes|string',
            'environment' => 'sometimes|string',
        ]);

        $fingerprint = $data['fingerprint'] ?? $this->fingerprint(
            $data['exception_class'], $data['message'], $data['stacktrace'] ?? []
        );

        $issue = Issue::where('project_id', $project->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        $isNew = ! $issue;
        $wasResolved = $issue && $issue->status === 'resolved';

        if ($isNew) {
            $issue = Issue::create([
                'project_id' => $project->id,
                'fingerprint' => $fingerprint,
                'title' => $data['exception_class'].': '.$data['message'],
                'culprit' => $this->culprit($data['stacktrace'] ?? []),
                'level' => $data['level'] ?? 'error',
                'status' => 'unresolved',
                'occurrence_count' => 1,
                'affected_users_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        } else {
            $issue->increment('occurrence_count');
            $issue->update(['last_seen_at' => now()]);
            if ($wasResolved) {
                $issue->update(['status' => 'unresolved']);
            }
        }

        $this->capEvents($issue);

        Event::create([
            'issue_id' => $issue->id,
            'payload_json' => json_encode($data['payload'] ?? []),
            'user_context_json' => json_encode($data['user_context'] ?? []),
            'breadcrumbs_json' => json_encode($data['breadcrumbs'] ?? []),
            'stacktrace_json' => json_encode($data['stacktrace'] ?? []),
            'release' => $data['release'] ?? null,
            'environment' => $data['environment'] ?? $project->environment,
        ]);

        if ($isNew || $wasResolved) {
            $this->alert($project, $issue, $isNew);
        }

        return response()->json(['id' => $issue->id, 'fingerprint' => $fingerprint], 201);
    }

    protected function fingerprint(string $class, string $message, array $stacktrace): string
    {
        $normalized = preg_replace(
            '/\b\d{4,}\b|[0-9a-f]{8,}|(\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2})/',
            '*',
            $message
        );

        $culprit = $this->culprit($stacktrace);

        return hash('sha256', $class.'|'.$culprit.'|'.$normalized);
    }

    protected function culprit(array $stacktrace): ?string
    {
        foreach ($stacktrace as $frame) {
            if (! empty($frame['in_app'])) {
                return ($frame['file'] ?? '').':'.($frame['line'] ?? '?');
            }
        }
        if (isset($stacktrace[0]['file'])) {
            return $stacktrace[0]['file'].':'.($stacktrace[0]['line'] ?? '?');
        }
        return null;
    }

    protected function capEvents(Issue $issue): void
    {
        $excess = $issue->events()->latest()->skip(10)->take(50)->get();
        foreach ($excess as $event) {
            $event->delete();
        }
    }

    protected function alert(Project $project, Issue $issue, bool $isNew): void
    {
        $text = $isNew
            ? "🐌 *New issue* [{$project->name}]\n*{$issue->title}*\n{$issue->culprit}"
            : "🔄 *Issue regressed* [{$project->name}]\n*{$issue->title}*";

        if ($project->discord_webhook_url) {
            Http::timeout(2)->post($project->discord_webhook_url, [
                'embeds' => [[
                    'title' => $isNew ? 'New EagleEye issue' : 'Issue regressed',
                    'description' => "*{$issue->title}*\n{$issue->culprit}",
                    'color' => $isNew ? 0xff9900 : 0x0099ff,
                    'fields' => [
                        ['name' => 'Project', 'value' => $project->name, 'inline' => true],
                        ['name' => 'Environment', 'value' => $project->environment, 'inline' => true],
                        ['name' => 'Occurrences', 'value' => (string) $issue->occurrence_count, 'inline' => true],
                    ],
                ]],
            ])->throw();
        }

        if ($project->telegram_bot_token && $project->telegram_chat_id) {
            Http::timeout(2)->post(
                "https://api.telegram.org/bot{$project->telegram_bot_token}/sendMessage",
                ['chat_id' => $project->telegram_chat_id, 'text' => $text, 'parse_mode' => 'Markdown']
            )->throw();
        }
    }
}
