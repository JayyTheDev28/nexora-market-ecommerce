@extends('layouts.app')
@section('title', 'Safety Case ' . $safetyReport->reference_number . ' — Admin Portal')
@section('content')
<div class="w-full min-h-screen bg-surface flex flex-col lg:flex-row">
    <x-admin-nav active="safety" />
    <main class="flex-1 p-6 lg:p-8 max-w-6xl mx-auto w-full space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <a href="{{ route('admin.safety.index') }}" class="text-xs font-semibold text-primary hover:underline">&larr; Back to Safety Center</a>
                    <h1 class="text-2xl font-bold text-on-surface mt-1">Case {{ $safetyReport->reference_number }}</h1>
                    <p class="text-sm text-on-surface-variant">{{ $safetyReport->category_label }} &bull; Submitted {{ $safetyReport->created_at->toDayDateTimeString() }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-primary/10 text-primary">
                    {{ ucwords(str_replace('_', ' ', $safetyReport->status)) }}
                </span>
            </div>

            @if (session('status'))
                <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">{{ $errors->first() }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant/60 space-y-3">
                        <h2 class="text-lg font-bold">{{ $safetyReport->title }}</h2>
                        <p class="text-sm text-on-surface whitespace-pre-line">{{ $safetyReport->description }}</p>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-outline-variant/40 text-xs">
                            <div><span class="text-on-surface-variant">Reporter:</span> <strong>{{ $safetyReport->reporter?->full_name }}</strong> ({{ $safetyReport->reporter_role }})</div>
                            <div><span class="text-on-surface-variant">Reported User:</span> <strong>{{ $safetyReport->reportedUser?->full_name ?? 'N/A' }}</strong></div>
                            <div><span class="text-on-surface-variant">Order:</span> <strong>{{ $safetyReport->order?->order_number ?? 'N/A' }}</strong></div>
                            <div><span class="text-on-surface-variant">Product:</span> <strong>{{ $safetyReport->product?->name ?? 'N/A' }}</strong></div>
                        </div>

                        @if (! empty($safetyReport->evidence_paths))
                            <div class="pt-4 border-t border-outline-variant/40">
                                <div class="text-xs font-bold uppercase text-on-surface-variant mb-2">Private Evidence Files</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($safetyReport->evidence_paths as $idx => $path)
                                        <a href="{{ route('admin.safety.evidence', [$safetyReport, $idx]) }}" target="_blank"
                                           class="px-3 py-1.5 rounded-xl bg-surface-container-low border border-outline-variant text-xs font-semibold text-primary hover:underline">
                                            View Evidence #{{ $idx + 1 }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant/60 space-y-4">
                        <h3 class="text-base font-bold">Investigation Notes &amp; Updates</h3>
                        <form method="POST" action="{{ route('admin.safety.notes', $safetyReport) }}" class="space-y-3">
                            @csrf
                            <textarea name="note" rows="3" required placeholder="Add investigation note or reporter update..." class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface"></textarea>
                            <div class="flex items-center justify-between">
                                <label class="inline-flex items-center gap-2 text-xs">
                                    <input type="checkbox" name="is_internal" value="1" checked>
                                    <span>Internal Admin-only note (uncheck to notify reporter)</span>
                                </label>
                                <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold">Add Note</button>
                            </div>
                        </form>

                        <div class="space-y-3 pt-2">
                            @forelse ($safetyReport->notes as $note)
                                <div class="p-3 rounded-xl border {{ $note->is_internal ? 'bg-amber-50/50 border-amber-200' : 'bg-surface-container-low border-outline-variant/60' }}">
                                    <div class="flex items-center justify-between text-xs text-on-surface-variant mb-1">
                                        <span><strong>{{ $note->author?->full_name ?? 'Admin' }}</strong> &bull; {{ $note->is_internal ? 'Internal Private Note' : 'Public Update' }}</span>
                                        <span>{{ $note->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm">{{ $note->note }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-on-surface-variant">No investigation notes yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <form method="POST" action="{{ route('admin.safety.update', $safetyReport) }}" class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60 space-y-3">
                        @csrf
                        <h3 class="text-base font-bold">Case Decision</h3>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                                @foreach (\App\Models\SafetyReport::STATUSES as $st)
                                    <option value="{{ $st }}" {{ $safetyReport->status === $st ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $st)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Priority</label>
                            <select name="priority" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                                @foreach (\App\Models\SafetyReport::PRIORITIES as $p)
                                    <option value="{{ $p }}" {{ $safetyReport->priority === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Assigned Investigator</label>
                            <select name="assigned_admin_id" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                                <option value="">Unassigned</option>
                                @foreach ($admins as $adm)
                                    <option value="{{ $adm->id }}" {{ (int) $safetyReport->assigned_admin_id === (int) $adm->id ? 'selected' : '' }}>{{ $adm->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Resolution Summary</label>
                            <textarea name="resolution_summary" rows="2" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">{{ $safetyReport->resolution_summary }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Closure Reason (required if Closed)</label>
                            <textarea name="closed_reason" rows="2" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">{{ $safetyReport->closed_reason }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Public Message to Reporter (optional)</label>
                            <input type="text" name="public_message" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                        </div>
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-primary text-on-primary text-sm font-semibold">Save Case Update</button>
                    </form>

                    @if ($safetyReport->reportedUser)
                        <form method="POST" action="{{ route('admin.safety.restrict', $safetyReport) }}" class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60 space-y-3">
                            @csrf
                            <h3 class="text-base font-bold text-red-700">Apply Account Restriction</h3>
                            <p class="text-xs text-on-surface-variant">Target: {{ $safetyReport->reportedUser->full_name }}</p>
                            <select name="restriction_type" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                                @foreach (\App\Models\AccountRestriction::TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="duration_days" min="1" max="365" placeholder="Duration in days (optional)" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                            <textarea name="reason" rows="2" required placeholder="Documented enforcement reason..." class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface"></textarea>
                            <button type="submit" class="w-full py-2 rounded-xl bg-red-600 text-white text-sm font-semibold">Apply Restriction</button>
                        </form>
                    @endif
                </div>
            </div>
    </main>
</div>
@endsection
