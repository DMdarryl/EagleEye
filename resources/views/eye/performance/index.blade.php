@extends('eye.layouts.app')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-semibold text-slate-100">Performance &amp; bottlenecks</h2>
        <p class="text-sm text-slate-400">Slow traces and N+1 warnings captured from monitored apps.</p>
    </div>

    <a href="{{ route('eye.performance.index') }}" class="px-3 py-2 text-sm font-medium rounded-md text-slate-300 bg-slate-800 hover:bg-slate-700">Reset filters</a>
</div>

<form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div>
        <label class="block text-xs font-medium text-slate-400 mb-1 uppercase tracking-wider">Project</label>
        <select name="project" class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm focus:outline-none focus:border-emerald-500">
            <option value="">All projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" {{ old('project', request('project')) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<!-- Slowest endpoints leaderboard -->
<div class="mb-8 overflow-hidden rounded-lg border border-slate-700 bg-slate-900/50">
    <div class="px-6 py-4 border-b border-slate-800">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Slowest endpoints (avg duration)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Route</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Method</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">Avg ms</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">Hits</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($leaderboard as $row)
                    <tr>
                        <td class="px-6 py-4 text-sm text-slate-300 mono truncate">{{ $row->route_name }}</td>
                        <td class="px-6 py-4 text-sm text-slate-400 mono">{{ $row->method }}</td>
                        <td class="px-6 py-4 text-right text-sm font-mono text-slate-300">{{ number_format($row->avg_duration, 1) }}</td>
                        <td class="px-6 py-4 text-right text-sm text-slate-400">{{ $row->hits }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">No performance traces yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Recent traces -->
<div class="overflow-hidden rounded-lg border border-slate-700 bg-slate-900/50">
    <div class="px-6 py-4 border-b border-slate-800">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Recent traces</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Route</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Method</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">Duration</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">Queries</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">When</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($traces as $trace)
                    <tr>
                        <td class="px-6 py-4 text-sm text-slate-300 mono truncate">{{ $trace->route_name ?: '—' }}</td>
                        <td class="px-6 py-4 text-sm text-slate-400 mono">{{ $trace->method ?: '—' }}</td>
                        <td class="px-6 py-4 text-right text-sm font-mono text-slate-300">{{ $trace->duration_ms }}ms</td>
                        <td class="px-6 py-4 text-right text-sm text-slate-400">{{ $trace->query_count }}</td>
                        <td class="px-6 py-4 text-sm text-slate-500">{{ $trace->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">No traces yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $traces->links() }}
@endsection
