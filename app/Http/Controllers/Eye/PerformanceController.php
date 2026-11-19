<?php

namespace App\Http\Controllers\Eye;

use App\Http\Controllers\Controller;
use App\Models\PerformanceTrace;
use App\Models\Project;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $query = PerformanceTrace::with('project')->orderBy('created_at', 'desc');

        if ($project = $request->query('project')) {
            $query->where('project_id', $project);
        }

        $traces = $query->paginate(25)->withQueryString();
        $projects = Project::orderBy('name')->get();

        // Slowest endpoints leaderboard.
        $leaderboard = PerformanceTrace::select('route_name', 'method')
            ->selectRaw('AVG(duration_ms) as avg_duration, COUNT(*) as hits')
            ->whereNotNull('route_name')
            ->groupBy('route_name', 'method')
            ->orderByDesc('avg_duration')
            ->limit(10)
            ->get();

        return view('eye.performance.index', compact(
            'traces', 'projects', 'leaderboard', 'project'
        ));
    }
}
