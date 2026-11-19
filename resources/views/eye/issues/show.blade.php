@extends('eye.layouts.app')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h2 class="text-lg font-semibold text-slate-100">{{ $issue->title }}</h2>
        <p class="text-xs text-slate-500 mono">{{ $issue->project->name }} · {{ $issue->project->environment }} · fingerprint: {{ substr($issue->fingerprint, 0, 16) }}...</p>
    </div>

    <div class="flex gap-2">
        <form action="{{ route('eye.issues.update', $issue) }}" method="POST" class="inline" onsubmit="return confirm('Mark this issue resolved?')">
            @method('PATCH')
            @csrf
            <input type="hidden" name="status" value="resolved">
            <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded bg-emerald-900/50 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-900">Resolve</button>
        </form>

        <form action="{{ route('eye.issues.update', $issue) }}" method="POST" class="inline" onsubmit="return confirm('Ignore this issue?')">
            @method('PATCH')
            @csrf
            <input type="hidden" name="status" value="ignored">
            <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700">Ignore</button>
        </form>

        <form action="{{ route('eye.issues.destroy', $issue) }}" method="POST" class="inline" onsubmit="return confirm('Delete this issue and its events?')">
            @method('DELETE')
            @csrf
            <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded bg-rose-900/50 text-rose-400 border border-rose-500/30 hover:bg-rose-900">Delete</button>
        </form>

        <button class="px-3 py-1.5 text-xs font-medium rounded bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700" onclick="copyCurl({{ $issue->id }})">
            Copy cURL
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Stack trace -->
    <div class="lg:col-span-8 space-y-4">
        <div class="flex items-center gap-2 mb-2">
            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Stack trace</h3>
            <span class="ml-auto text-[10px] text-slate-500 mono">Click a frame to inspect source</span>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-700 bg-slate-900/50">
            <div class="divide-y divide-slate-800">
                @forelse ($stacktrace as $index => $frame)
                    <div class="frame-row p-3 text-sm group {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}" onclick="toggleFrame(this)">
                        <div class="flex items-start gap-3">
                            <span class="text-xs text-slate-500 mono w-5">{{ $index }}</span>
                            <div class="flex-1">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="text-xs font-medium {{ $frame['in_app'] ? 'text-emerald-400' : 'text-slate-500' }}">{{ $frame['in_app'] ? 'in-app' : 'vendor' }}</span>
                                    <span class="text-xs text-slate-600">{{ $frame['class'] ?: '' }}{{ $frame['class'] ? '::' : '' }}{{ $frame['function'] }}()</span>
                                </div>
                                <div class="text-xs mono text-slate-400 truncate">{{ $frame['file'] ?: '?' }}:{{ $frame['line'] ?: '?' }}</div>
                            </div>
                        </div>
                        <div class="frame-source hidden mt-2 pt-2 border-t border-slate-800" id="source-{{ $index }}">
                            <pre class="mono text-xs overflow-x-auto"><code class="language-php">{{ collect($frame['context'] ?? [])->map(fn ($line) => ($line['highlight'] ? '▶ ' : '  ').str_pad($line['line'], 4).' | '.e($line['code']))->implode("\n") }}</code></pre>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-slate-500 text-sm">No stack trace captured.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Breadcrumbs + context -->
    <div class="lg:col-span-4 space-y-4">
        <div class="overflow-hidden rounded-lg border border-slate-700 bg-slate-900/50">
            <div class="px-4 py-3 border-b border-slate-800 bg-slate-900/30">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Breadcrumbs ({{ count($breadcrumbs) }})</h3>
            </div>
            <div class="divide-y divide-slate-800 p-3 space-y-3">
                @forelse (array_reverse($breadcrumbs) as $crumb)
                    <div class="text-xs">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="badge bg-slate-800 text-slate-400 border border-slate-700">{{ $crumb['type'] }}</span>
                            <span class="text-[10px] text-slate-500 mono">{{ date('H:i:s.u', (isset($crumb['timestamp']) ? $crumb['timestamp'] : 0)) }}</span>
                        </div>
                        <div class="text-sm text-slate-300 mono truncate">
                            @if (isset($crumb['query']))
                                {{ e($crumb['query']) }} @if (isset($crumb['ms']))<span class="text-slate-500">({{ $crumb['ms'] }}ms)</span>@endif
                            @elseif (isset($crumb['level']))
                                [{{ $crumb['level'] }}] {{ e($crumb['message'] ?? '') }}
                            @else
                                {{ json_encode($crumb, JSON_UNESCAPED_SLASHES) }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-slate-500 text-xs">No breadcrumbs.</div>
                @endforelse
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-700 bg-slate-900/50">
            <div class="px-4 py-3 border-b border-slate-800 bg-slate-900/30">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">User &amp; request context</h3>
            </div>
            <div class="p-3 space-y-3">
                <div class="text-xs">
                    <div class="text-slate-500 uppercase text-[10px] tracking-wider mb-1">User</div>
                    <div class="text-sm text-slate-300 mono">{{ $userContext['id'] ?: '—' }}</div>
                    <div class="text-sm text-slate-400 mono truncate">{{ $userContext['email'] ?: '—' }}</div>
                </div>
                <div class="text-xs">
                    <div class="text-slate-500 uppercase text-[10px] tracking-wider mb-1">Route / Controller</div>
                    <div class="text-sm text-slate-300 mono truncate">{{ $payload['route'] ?: '—' }}</div>
                    <div class="text-sm text-slate-400 mono truncate">{{ $payload['controller'] ?: '—' }}</div>
                </div>
                <div class="text-xs">
                    <div class="text-slate-500 uppercase text-[10px] tracking-wider mb-1">PHP / Laravel</div>
                    <div class="text-sm text-slate-300 mono">{{ $payload['php_version'] ?: '—' }} / {{ $payload['laravel_version'] ?: '—' }}</div>
                </div>
                <div class="text-xs">
                    <div class="text-slate-500 uppercase text-[10px] tracking-wider mb-1">Release</div>
                    <div class="text-sm text-slate-300 mono">{{ $issue->resolved_in_release ?: $payload['release'] ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>
hljs.highlightAll();

function toggleFrame(row) {
    const source = document.getElementById('source-' + row.dataset.index);
    const isActive = row.classList.contains('active');
    document.querySelectorAll('.frame-row.active').forEach(r => {
        r.classList.remove('active');
        const s = document.getElementById('source-' + r.dataset.index);
        if (s) s.classList.add('hidden');
    });
    if (!isActive) {
        row.classList.add('active');
        source.classList.remove('hidden');
        hljs.highlightElement(source.querySelector('code'));
    }
}

async function copyCurl(issueId) {
    const res = await fetch('/eye/issues/' + issueId + '/copy-curl', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    });
    const data = await res.json();
    await navigator.clipboard.writeText(data.curl);
    alert('cURL command copied to clipboard.');
}
</script>
@endpush

@endsection
