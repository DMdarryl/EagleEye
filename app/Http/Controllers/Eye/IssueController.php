<?php

namespace App\Http\Controllers\Eye;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        $query = Issue::with('project')
            ->orderBy('last_seen_at', 'desc');

        if ($project = $request->query('project')) {
            $query->where('project_id', $project);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($environment = $request->query('environment')) {
            $query->whereHas('project', fn ($q) => $q->where('environment', $environment));
        }

        $sort = $request->query('sort', 'last_seen');
        $query->orderBy($sort === 'occurrences' ? 'occurrence_count' : 'last_seen_at', 'desc');

        $issues = $query->paginate(25)->withQueryString();

        $projects = Project::orderBy('name')->get();
        $statuses = ['unresolved', 'resolved', 'ignored'];
        $environments = Project::distinct()->pluck('environment');

        return view('eye.issues.index', compact(
            'issues', 'projects', 'statuses', 'environments',
            'project', 'status', 'environment', 'sort'
        ));
    }

    public function show(Issue $issue)
    {
        $issue->load(['project', 'events' => function ($q) {
            $q->latest()->limit(10);
        }]);

        $latest = $issue->events()->latest()->first();
        $stacktrace = $latest ? $latest->stacktrace() : [];
        $breadcrumbs = $latest ? $latest->breadcrumbs() : [];
        $userContext = $latest ? $latest->userContext() : [];
        $payload = $latest ? $latest->payload() : [];

        return view('eye.issues.show', compact(
            'issue', 'stacktrace', 'breadcrumbs', 'userContext', 'payload'
        ));
    }

    public function update(Request $request, Issue $issue)
    {
        $request->validate(['status' => 'required|in:unresolved,resolved,ignored']);

        $issue->update(['status' => $request->status]);

        if ($request->status === 'resolved') {
            $issue->update(['resolved_in_release' => $request->query('release') ?: null]);
        }

        return back()->with('ok', "Issue marked as {$request->status}.");
    }

    public function destroy(Issue $issue)
    {
        $issue->delete();

        return redirect()->route('eye.issues.index')->with('ok', 'Issue deleted.');
    }

    public function copyCurl(Issue $issue)
    {
        $latest = $issue->events()->latest()->first();
        $payload = $latest ? $latest->payload() : [];
        $dsn = $issue->project->dsn_key;

        $curl = "curl -X POST https://your-eagle-eye.domain/api/eye/ingest \\\n  -H 'X-Watcher-Key: {$dsn}' \\\n  -H 'Content-Type: application/json' \\\n  -d '".json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."'";

        return response()->json(['curl' => $curl]);
    }
}
