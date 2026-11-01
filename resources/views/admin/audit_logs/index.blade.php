@extends('layouts.app')

@section('title', 'System Audit Trail — Admin Portal')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ expandedId: null }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-admin-nav active="audit-logs" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Immutable System Audit Trail</h1>
                <p class="font-body-md text-sm text-on-surface-variant">
                    Read-only, append-only log of all critical administrative actions, rate revisions, user status overrides, and financial arbitrations.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/10 text-emerald-700 font-semibold text-xs border border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Append-Only Integrity Enforced</span>
                </span>
            </div>
        </div>

        {{-- Filters & Search Row --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-xs">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="flex flex-col md:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Search reason, action, or IP address..."
                           class="w-full pl-9 pr-4 py-2 bg-surface-container rounded-xl text-xs text-on-surface placeholder:text-outline border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div class="w-full md:w-56">
                    <select name="action" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Action Types</option>
                        @foreach($actions as $act)
                            <option value="{{ $act }}" {{ $action === $act ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $act)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full md:w-48">
                    <select name="operator_id" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Operators</option>
                        @foreach($operators as $op)
                            <option value="{{ $op->id }}" {{ (string)$operatorId === (string)$op->id ? 'selected' : '' }}>
                                {{ $op->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <button type="submit" class="w-full md:w-auto px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        Filter
                    </button>
                    @if($search || $action || $operatorId || $startDate || $endDate)
                        <a href="{{ route('admin.audit-logs.index') }}" class="w-full md:w-auto px-3 py-2 rounded-xl border border-outline-variant text-on-surface-variant text-xs font-semibold hover:bg-surface-container transition-all text-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Audit Log Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            @if($logs->isEmpty())
                <div class="p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-[48px] text-outline mb-3">history</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">No audit logs recorded</h3>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">Actions performed by operators will appear here automatically.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">Timestamp</th>
                                <th class="px-5 py-3.5">Operator</th>
                                <th class="px-5 py-3.5">Action Event</th>
                                <th class="px-5 py-3.5">Target Resource</th>
                                <th class="px-5 py-3.5">Reason / Justification</th>
                                <th class="px-5 py-3.5">IP Address</th>
                                <th class="px-5 py-3.5 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($logs as $log)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <span class="font-bold text-on-surface block">{{ $log->created_at->format('M d, Y') }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $log->created_at->format('h:i:s A') }} ({{ $log->created_at->diffForHumans() }})</span>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <span class="font-semibold text-on-surface block">{{ $log->operator?->full_name ?? 'System Automated' }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $log->operator?->roleLabel() ?? 'Service' }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-primary/10 text-primary capitalize font-mono">
                                            {{ str_replace('_', ' ', $log->action) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap font-mono text-[11px] text-on-surface">
                                        {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-on-surface-variant text-xs max-w-xs block truncate" title="{{ $log->reason }}">
                                            {{ $log->reason ?? 'Standard administrative operation' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap font-mono text-[11px] text-outline">
                                        {{ $log->ip_address ?? '127.0.0.1' }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        @if($log->old_values || $log->new_values)
                                            <button type="button"
                                                    @click="expandedId = (expandedId === {{ $log->id }} ? null : {{ $log->id }})"
                                                    class="inline-flex items-center gap-1 text-[11px] text-primary font-semibold hover:underline">
                                                <span x-text="expandedId === {{ $log->id }} ? 'Hide' : 'Changes'"></span>
                                                <span class="material-symbols-outlined text-[14px]" :class="expandedId === {{ $log->id }} ? 'rotate-180' : ''">expand_more</span>
                                            </button>
                                        @else
                                            <span class="text-outline text-[10px]">None</span>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Expandable Row for Old vs New Value Diffs --}}
                                @if($log->old_values || $log->new_values)
                                    <tr x-show="expandedId === {{ $log->id }}" x-cloak class="bg-surface-container-low">
                                        <td colspan="7" class="px-5 py-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider block mb-1">Previous Values:</span>
                                                    <pre class="bg-surface-container-lowest p-3 rounded-xl border border-outline-variant text-[11px] font-mono text-on-surface overflow-x-auto">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                                <div>
                                                    <span class="text-[10px] text-primary font-bold uppercase tracking-wider block mb-1">New Values:</span>
                                                    <pre class="bg-surface-container-lowest p-3 rounded-xl border border-outline-variant text-[11px] font-mono text-on-surface overflow-x-auto">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($logs->hasPages())
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                        {{ $logs->links() }}
                    </div>
                @endif
            @endif
        </div>

                </div>
        </div>
    </div>
</div>

@endsection
