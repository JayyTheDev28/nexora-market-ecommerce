@extends('layouts.app')
@section('title', 'Fraud & Risk Alerts — Admin Portal')
@section('content')
<div class="w-full min-h-screen bg-surface flex flex-col lg:flex-row">
    <x-admin-nav active="fraud-alerts" />
    <main class="flex-1 p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-on-surface">Fraud &amp; Suspicious Activity Detection</h1>
                    <p class="text-sm text-on-surface-variant">Rules-based anomaly alerts for Admin review. Alerts never auto-suspend accounts without human verification.</p>
                </div>
                <form method="POST" action="{{ route('admin.fraud-alerts.scan') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary text-on-primary text-sm font-semibold">Run Rules Scan</button>
                </form>
            </div>

            @if (session('status'))
                <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap gap-2">
                @foreach (['all' => 'All', 'open' => 'Open', 'investigating' => 'Investigating', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label)
                    <a href="{{ route('admin.fraud-alerts.index', ['status' => $key, 'severity' => $severity]) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-semibold border {{ $status === $key ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest border-outline-variant' }}">
                        {{ $label }} ({{ $counts[$key] ?? 0 }})
                    </a>
                @endforeach
            </div>

            <div class="space-y-4">
                @forelse ($alerts as $alert)
                    <div class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-mono text-xs px-2 py-0.5 rounded bg-surface-container-high font-semibold">{{ $alert->rule_code }}</span>
                                <span class="ml-2 text-base font-bold">{{ $alert->title }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="px-2.5 py-0.5 rounded-full font-bold {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : ($alert->severity === 'high' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') }}">{{ strtoupper($alert->severity) }}</span>
                                <span class="px-2.5 py-0.5 rounded-full bg-surface-container-high font-semibold">{{ ucfirst($alert->status) }}</span>
                            </div>
                        </div>
                        <div class="text-xs text-on-surface-variant">
                            Detected: {{ $alert->detected_at?->toDayDateTimeString() }}
                            @if ($alert->user) &bull; User: <strong>{{ $alert->user->full_name }}</strong> ({{ $alert->user->role }}) @endif
                            &bull; Evidence: <code class="bg-surface-container-low px-1.5 py-0.5 rounded">{{ json_encode($alert->supporting_data) }}</code>
                        </div>
                        <form method="POST" action="{{ route('admin.fraud-alerts.review', $alert) }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 pt-2 border-t border-outline-variant/40">
                            @csrf
                            <select name="status" class="px-3 py-2 rounded-xl border border-outline-variant text-xs bg-surface">
                                @foreach (\App\Models\RiskAlert::STATUSES as $st)
                                    <option value="{{ $st }}" {{ $alert->status === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="decision_notes" value="{{ $alert->decision_notes }}" required placeholder="Document investigation decision..." class="md:col-span-2 px-3 py-2 rounded-xl border border-outline-variant text-xs bg-surface">
                            <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold">Save Review</button>
                        </form>
                    </div>
                @empty
                    <div class="bg-surface-container-lowest p-8 rounded-2xl border border-outline-variant/60 text-center text-sm text-on-surface-variant">
                        No risk alerts found. Click "Run Rules Scan" to evaluate live database records.
                    </div>
                @endforelse
            </div>

            {{ $alerts->links() }}
    </main>
</div>
@endsection
