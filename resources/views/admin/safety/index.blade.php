@extends('layouts.app')
@section('title', 'Safety & Reports Center — Admin Portal')
@section('content')
<div class="w-full min-h-screen bg-surface flex flex-col lg:flex-row">
    <x-admin-nav active="safety" />
    <main class="flex-1 p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-on-surface">Safety &amp; Reports Center</h1>
                    <p class="text-sm text-on-surface-variant">Centralized case management across Buyers, Sellers, Riders, Transfer Couriers, and Logistics Partners.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach (['all' => 'All', 'new' => 'New', 'under_investigation' => 'Under Investigation', 'awaiting_evidence' => 'Awaiting Evidence', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'rejected' => 'Rejected', 'closed' => 'Closed'] as $key => $label)
                    <a href="{{ route('admin.safety.index', array_merge(request()->except('page'), ['status' => $key])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-semibold border {{ $status === $key ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface border-outline-variant' }}">
                        {{ $label }} ({{ $counts[$key] ?? 0 }})
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.safety.index') }}" class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60 grid grid-cols-1 md:grid-cols-4 gap-3">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search reference, title, reporter..." class="px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                <select name="category" class="px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                    <option value="">All Categories</option>
                    @foreach (\App\Models\SafetyReport::CATEGORIES as $k => $v)
                        <option value="{{ $k }}" {{ $category === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
                <select name="priority" class="px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                    <option value="">All Priorities</option>
                    @foreach (\App\Models\SafetyReport::PRIORITIES as $p)
                        <option value="{{ $p }}" {{ $priority === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary text-sm font-semibold">Filter</button>
            </form>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/60 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-on-surface-variant text-xs uppercase">
                        <tr>
                            <th class="text-left px-4 py-3">Reference</th>
                            <th class="text-left px-4 py-3">Reporter</th>
                            <th class="text-left px-4 py-3">Category</th>
                            <th class="text-left px-4 py-3">Subject</th>
                            <th class="text-left px-4 py-3">Priority</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-right px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse ($reports as $report)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $report->reference_number }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $report->reporter?->full_name ?? 'Unknown' }}</div>
                                    <div class="text-xs text-on-surface-variant">{{ ucfirst($report->reporter_role) }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $report->category_label }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $report->title }}</div>
                                    @if ($report->reportedUser)
                                        <div class="text-xs text-on-surface-variant">Reported: {{ $report->reportedUser->full_name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-surface-container-high">{{ ucfirst($report->priority) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">{{ ucwords(str_replace('_', ' ', $report->status)) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.safety.show', $report) }}" class="text-primary font-semibold hover:underline">Review Case</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-on-surface-variant">No safety reports match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $reports->links() }}
    </main>
</div>
@endsection
