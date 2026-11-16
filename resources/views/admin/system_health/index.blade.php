@extends('layouts.app')
@section('title', 'System Health Monitoring — Admin Portal')
@section('content')
<div class="w-full min-h-screen bg-surface flex flex-col lg:flex-row">
    <x-admin-nav active="system-health" />
    <main class="flex-1 p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-on-surface">System Health Monitoring</h1>
                <p class="text-sm text-on-surface-variant">Database health, queue backlog, failed job retries, and sanitized application error logs.</p>
            </div>

            @if (session('status'))
                <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60">
                    <div class="text-xs text-on-surface-variant">Database Connectivity</div>
                    <div class="text-lg font-bold mt-1 {{ $dbHealthy ? 'text-emerald-700' : 'text-red-700' }}">{{ $dbHealthy ? 'Healthy' : 'Error' }}</div>
                </div>
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60">
                    <div class="text-xs text-on-surface-variant">Pending Queue Jobs</div>
                    <div class="text-lg font-bold mt-1">{{ $pendingJobsCount }}</div>
                </div>
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60">
                    <div class="text-xs text-on-surface-variant">Failed Jobs</div>
                    <div class="text-lg font-bold mt-1 {{ $failedJobsCount > 0 ? 'text-red-700' : '' }}">{{ $failedJobsCount }}</div>
                </div>
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60">
                    <div class="text-xs text-on-surface-variant">Latest Audit Entry</div>
                    <div class="text-sm font-bold mt-1">{{ $lastAuditActivity?->diffForHumans() ?? 'None' }}</div>
                </div>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/60 overflow-x-auto">
                <div class="p-4 border-b border-outline-variant/40 font-bold text-base">Failed Background Jobs</div>
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th class="text-left px-4 py-3">UUID</th>
                            <th class="text-left px-4 py-3">Queue</th>
                            <th class="text-left px-4 py-3">Failed At</th>
                            <th class="text-right px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse ($failedJobs as $job)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $job->uuid }}</td>
                                <td class="px-4 py-3">{{ $job->queue }}</td>
                                <td class="px-4 py-3 text-xs">{{ $job->failed_at }}</td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.system-health.retry', $job->uuid) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 rounded-lg bg-primary text-on-primary text-xs font-semibold">Retry</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-on-surface-variant">No failed background jobs.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60 space-y-2">
                <h2 class="text-base font-bold">Recent Sanitized Application Exceptions</h2>
                @forelse ($recentExceptions as $line)
                    <div class="font-mono text-xs bg-surface-container-low p-2 rounded-lg overflow-x-auto">{{ $line }}</div>
                @empty
                    <p class="text-sm text-on-surface-variant">No recent application errors found in log.</p>
                @endforelse
            </div>
    </main>
</div>
@endsection
