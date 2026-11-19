<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>EagleEye — Laravel Error Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f1f5f9; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .frame-row { cursor: pointer; }
        .frame-row:hover { background: #1e293b; }
        .frame-row.active { background: #334155; border-left: 4px solid #60a5fa; }
        .hljs { background: #0f172a !important; }
        .badge { @apply inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium leading-4; }
    </style>
</head>
<body class="min-h-screen">

<nav class="border-b border-slate-700 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="font-semibold text-slate-100 tracking-tight">EagleEye</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('eye.issues.index') }}" class="px-3 py-2 text-sm font-medium rounded-md text-slate-300 hover:text-white hover:bg-slate-800">Issues</a>
                <a href="{{ route('eye.performance.index') }}" class="px-3 py-2 text-sm font-medium rounded-md text-slate-300 hover:text-white hover:bg-slate-800">Performance</a>
                <a href="{{ route('eye.issues.index') . '?status=unresolved' }}" class="px-3 py-2 text-sm font-medium rounded-md text-emerald-400 hover:text-emerald-300 hover:bg-slate-800">Unresolved</a>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    @if (session('ok'))
        <div class="mb-6 p-4 rounded-lg border border-emerald-500/30 bg-emerald-900/20 text-emerald-300 text-sm">{{ session('ok') }}</div>
    @endif

    @yield('content')
</main>

</body>
</html>
