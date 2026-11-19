@extends('eye.layouts.app')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-semibold text-slate-100">Issues</h2>
        <p class="text-sm text-slate-400">Grouped by fingerprint — duplicate occurrences merge into one issue.</p>
    </div>

    <a href="{{ route('eye.issues.index') }}" class="px-3 py-2 text-sm font-medium rounded-md text-slate-300 bg-slate-800 hover:bg-slate-700">Reset filters</a>
</div>

<form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="col-span-1 md:col-span-2">
        <label class="block text-xs font-medium text-slate-400 mb-1 uppercase tracking-wider">Project</label>
        <select name="project" class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm focus:outline-none focus:border-emerald-500">
            <option value="">All projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" {{ old('project', request('project')) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-400 mb-1 uppercase tracking-wider">Status</label>
        <select name="status" class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm focus:outline-none focus:border-emerald-500">
            <option value="">All statuses</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" {{ old('status', request('status')) == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-400 mb-1 uppercase tracking-wider">Environment</label>
        <select name="environment" class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm focus:outline-none focus:border-emerald-500">
            <option value="">All environments</option>
            @foreach ($environments as $e)
                <option value="{{ $e }}" {{ old('environment', request('environment')) == $e ? 'selected' : '' }}>{{ $e }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end">
        <button class="w-full bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium rounded px-4 py-2">Filter</button>
    </div>
</form>

<div class="overflow-hidden rounded-lg border border-slate-700">
    <table class="min-w-full divide-y divide-slate-700">
        <thead class="bg-slate-900">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Issue</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Project</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Env</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Occurrences</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Last seen</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-slate-400 uppercase tracking-wider"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 bg-slate-900/50">
            @forelse ($issues as $issue)
                <tr class="group hover:bg-slate-800/50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-slate-100 mono">{{ $issue->title }}</div>
                        <div class="text-xs text-slate-500 mono">{{ $issue->culprit }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-300">{{ $issue->project->name }}</td>
                    <td class="px-6 py-4 text-sm text-slate-400">{{ $issue->project->environment }}</td>
                    <td class="px-6 py-4">
                        <span class="badge {{ $issue->status === 'unresolved' ? 'bg-orange-900/50 text-orange-400 border border-orange-500/30' : ($issue->status === 'resolved' ? 'bg-emerald-900/50 text-emerald-400 border border-emerald-500/30' : 'bg-slate-900/50 text-slate-400 border border-slate-700') }}">
                            {{ ucfirst($issue->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-mono text-slate-300">{{ $issue->occurrence_count }}</td>
                    <td class="px-6 py-4 text-sm text-slate-400">{{ $issue->last_seen_at?->diffForHumans() }}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('eye.issues.show', $issue) }}" class="text-sm text-emerald-400 hover:text-emerald-300 underline">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">No issues found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $issues->links() }}
@endsection
