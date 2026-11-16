@extends('layouts.app')
@section('title', 'Safety Reports & Appeals')
@section('content')
@php
    $authUser = auth()->user();
    $hasSidebar = $authUser && ($authUser->isSeller() || $authUser->isSortingCenter() || $authUser->isAdmin());
    $prefillCategory = old('category', request('category'));
    $prefillTitle = old('title', request('title'));
    $prefillReportedUserId = old('reported_user_id', request('reported_user_id'));
    $prefillProductId = old('product_id', request('product_id'));
    $prefillOrderId = old('order_id', request('order_id'));
    $prefillSubjectType = old('subject_type', request('subject_type'));
    $prefillSubjectId = old('subject_id', request('subject_id'));
@endphp

<div class="w-full min-h-screen bg-surface">
    @if ($hasSidebar)
        <div class="flex flex-col lg:flex-row w-full">
            @if ($authUser->isSeller())
                <x-seller-nav active="safety" />
            @elseif ($authUser->isSortingCenter())
                <x-logistics-nav active="safety" />
            @elseif ($authUser->isAdmin())
                <x-admin-nav active="safety" />
            @endif
            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
                <div class="max-w-5xl mx-auto space-y-6">
    @else
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 md:py-6 space-y-6">
            @if ($authUser && $authUser->isBuyer())
                <x-buyer-nav active="safety" />
            @endif
            <div class="max-w-5xl space-y-6">
    @endif

                <div>
                    <h1 class="text-2xl font-bold text-on-surface">Safety Reports &amp; Account Appeals</h1>
                    <p class="text-sm text-on-surface-variant">Submit a report to the Admin Safety Center, track investigation updates, or appeal an active account restriction.</p>
                </div>

                @if (session('status'))
                    <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="p-3 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('safety-reports.store') }}" enctype="multipart/form-data" class="bg-surface-container-lowest p-5 sm:p-6 rounded-2xl border border-outline-variant/60 space-y-4">
                    @csrf
                    @if ($prefillReportedUserId)
                        <input type="hidden" name="reported_user_id" value="{{ $prefillReportedUserId }}">
                    @endif
                    @if ($prefillProductId)
                        <input type="hidden" name="product_id" value="{{ $prefillProductId }}">
                    @endif
                    @if ($prefillOrderId)
                        <input type="hidden" name="order_id" value="{{ $prefillOrderId }}">
                    @endif
                    @if ($prefillSubjectType)
                        <input type="hidden" name="subject_type" value="{{ $prefillSubjectType }}">
                    @endif
                    @if ($prefillSubjectId)
                        <input type="hidden" name="subject_id" value="{{ $prefillSubjectId }}">
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold">Submit New Report</h2>
                        @if ($prefillProductId || $prefillReportedUserId || $prefillOrderId)
                            <span class="px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-800 text-xs font-semibold border border-amber-500/20">
                                Context linked ({{ $prefillProductId ? 'Product #' . $prefillProductId : ($prefillOrderId ? 'Order #' . $prefillOrderId : 'User #' . $prefillReportedUserId) }})
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1">Category</label>
                            <select name="category" required class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                                @foreach (\App\Models\SafetyReport::CATEGORIES as $k => $v)
                                    <option value="{{ $k }}" @selected($prefillCategory === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Title / Subject</label>
                            <input type="text" name="title" required value="{{ $prefillTitle }}" placeholder="Brief summary of the issue" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Description</label>
                        <textarea name="description" rows="3" required placeholder="Provide details, order/tracking numbers, or misconduct description..." class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Evidence Screenshots / Documents (optional, JPG/PNG/WEBP/PDF up to 5MB)</label>
                        <input type="file" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="text-xs w-full sm:w-auto">
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-on-primary text-sm font-semibold hover:bg-primary/90 transition-colors cursor-pointer">Submit Report</button>
                </form>

                <div class="bg-surface-container-lowest p-5 sm:p-6 rounded-2xl border border-outline-variant/60 space-y-3">
                    <h2 class="text-base font-bold">My Submitted Reports</h2>
                    @forelse ($reports as $report)
                        <div class="p-4 rounded-xl border border-outline-variant/50 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <strong class="text-sm">{{ $report->reference_number }} — {{ $report->title }}</strong>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">{{ ucwords(str_replace('_', ' ', $report->status)) }}</span>
                            </div>
                            <p class="text-xs text-on-surface-variant">{{ $report->category_label }} &bull; {{ $report->created_at->toDayDateTimeString() }}</p>
                            <p class="text-xs text-on-surface">{{ $report->description }}</p>
                            @if ($report->publicNotes->isNotEmpty())
                                <div class="mt-2 pt-2 border-t border-outline-variant/40 space-y-1.5">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Admin Responses</p>
                                    @foreach ($report->publicNotes as $note)
                                        <div class="p-2.5 rounded-lg bg-surface-container-low text-xs">
                                            <span class="font-semibold text-on-surface">{{ $note->author?->full_name ?? 'Admin Safety Team' }}:</span>
                                            <span class="text-on-surface-variant">{{ $note->note }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if ($report->resolution_summary)
                                <p class="text-xs text-emerald-700 pt-1"><strong>Resolution:</strong> {{ $report->resolution_summary }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-on-surface-variant">You have not submitted any safety reports.</p>
                    @endforelse
                    {{ $reports->links() }}
                </div>

                @if ($restrictions->isNotEmpty())
                    <div class="bg-surface-container-lowest p-5 sm:p-6 rounded-2xl border border-outline-variant/60 space-y-3">
                        <h2 class="text-base font-bold">Account Restrictions &amp; Appeals</h2>
                        @foreach ($restrictions as $res)
                            <div class="p-4 rounded-xl border border-outline-variant/60 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <strong class="text-sm">{{ $res->type_label }}</strong>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-800">{{ ucfirst($res->status) }}</span>
                                </div>
                                <p class="text-xs text-on-surface-variant">Reason: {{ $res->reason }}</p>
                                @if ($res->appeals->isNotEmpty())
                                    <div class="space-y-1 pt-1">
                                        @foreach ($res->appeals as $appeal)
                                            <p class="text-xs text-on-surface-variant">
                                                <strong>Appeal ({{ ucfirst($appeal->status) }}):</strong> {{ $appeal->reason }}
                                                @if ($appeal->admin_notes)
                                                    — <em>Admin: {{ $appeal->admin_notes }}</em>
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($res->status === 'active')
                                    <form method="POST" action="{{ route('restrictions.appeal', $res) }}" class="flex flex-col sm:flex-row gap-2 pt-1">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Explain why this restriction should be lifted..." class="flex-1 px-3 py-1.5 rounded-xl border border-outline-variant text-xs bg-surface">
                                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-semibold cursor-pointer">Submit Appeal</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

    @if ($hasSidebar)
                </div>
            </div>
        </div>
    @else
            </div>
        </div>
    @endif
</div>
@endsection
