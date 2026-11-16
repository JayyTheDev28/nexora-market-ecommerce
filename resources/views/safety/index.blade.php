@extends('layouts.app')
@section('title', 'Safety Reports & Appeals')
@section('content')
<div class="max-w-5xl mx-auto p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-on-surface">Safety Reports &amp; Account Appeals</h1>
            <p class="text-sm text-on-surface-variant">Submit a report to the Admin Safety Center or appeal an active account restriction.</p>
        </div>

        @if (session('status'))
            <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="p-3 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('safety-reports.store') }}" enctype="multipart/form-data" class="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant/60 space-y-4">
            @csrf
            <h2 class="text-base font-bold">Submit New Report</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1">Category</label>
                    <select name="category" required class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                        @foreach (\App\Models\SafetyReport::CATEGORIES as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Title / Subject</label>
                    <input type="text" name="title" required placeholder="Brief summary of the issue" class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Description</label>
                <textarea name="description" rows="3" required placeholder="Provide details, order/tracking numbers, or misconduct description..." class="w-full px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Evidence Screenshots (optional)</label>
                <input type="file" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="text-xs">
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-on-primary text-sm font-semibold">Submit Report</button>
        </form>

        <div class="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant/60 space-y-3">
            <h2 class="text-base font-bold">My Submitted Reports</h2>
            @forelse ($reports as $report)
                <div class="p-4 rounded-xl border border-outline-variant/50 space-y-1">
                    <div class="flex items-center justify-between">
                        <strong class="text-sm">{{ $report->reference_number }} — {{ $report->title }}</strong>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">{{ ucwords(str_replace('_', ' ', $report->status)) }}</span>
                    </div>
                    <p class="text-xs text-on-surface-variant">{{ $report->category_label }} &bull; {{ $report->created_at->toDayDateTimeString() }}</p>
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
            <div class="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant/60 space-y-3">
                <h2 class="text-base font-bold">Account Restrictions &amp; Appeals</h2>
                @foreach ($restrictions as $res)
                    <div class="p-4 rounded-xl border border-outline-variant/60 space-y-2">
                        <div class="flex items-center justify-between">
                            <strong class="text-sm">{{ $res->type_label }}</strong>
                            <span class="text-xs font-semibold">{{ ucfirst($res->status) }}</span>
                        </div>
                        <p class="text-xs text-on-surface-variant">Reason: {{ $res->reason }}</p>
                        @if ($res->status === 'active')
                            <form method="POST" action="{{ route('restrictions.appeal', $res) }}" class="flex gap-2 pt-1">
                                @csrf
                                <input type="text" name="reason" required placeholder="Explain why this restriction should be lifted..." class="flex-1 px-3 py-1.5 rounded-xl border border-outline-variant text-xs bg-surface">
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-semibold">Submit Appeal</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
</div>
@endsection
