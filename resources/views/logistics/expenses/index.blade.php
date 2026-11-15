@extends('layouts.app')

@section('title', 'Logistics Operating Expenses — Logistics Operations')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="expenses" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Operating Expenses</span>
                    </div>
                    <a href="{{ route('logistics.reports.index', ['tab' => 'profitability']) }}" class="text-xs text-primary font-bold hover:underline">View Profitability Report →</a>
                </div>

                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 text-xs text-primary font-semibold">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">This Month Expenses</span>
                        <span class="text-2xl font-extrabold text-rose-700 mt-1 block">₱{{ number_format($monthRecorded, 2) }}</span>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Total Recorded Expenses</span>
                        <span class="text-2xl font-extrabold text-on-surface mt-1 block">₱{{ number_format($totalRecorded, 2) }}</span>
                    </div>
                </div>

                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="text-sm font-bold mb-3">Record Operating Expense</h2>
                    <form method="POST" action="{{ route('logistics.expenses.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold mb-1">Category *</label>
                            <select name="category" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold mb-1">Description *</label>
                            <input type="text" name="description" required placeholder="e.g. Van diesel fill-up Calamba → Sta. Cruz trip" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Amount (₱) *</label>
                            <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Expense Date *</label>
                            <input type="date" name="expense_date" value="{{ now()->toDateString() }}" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Hub (Optional)</label>
                            <select name="hub_id" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                <option value="">Company-Wide Overhead</option>
                                @foreach($hubs as $hub)
                                    <option value="{{ $hub->id }}">{{ $hub->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Receipt Ref #</label>
                            <input type="text" name="reference_number" placeholder="OR / Invoice #" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full py-2 rounded-xl bg-primary text-on-primary font-bold cursor-pointer">Record Expense</button>
                        </div>
                    </form>
                </div>

                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                    <th class="pb-3">Expense #</th>
                                    <th class="pb-3">Date</th>
                                    <th class="pb-3">Category & Hub</th>
                                    <th class="pb-3">Description</th>
                                    <th class="pb-3 text-right">Amount</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($expenses as $exp)
                                    <tr>
                                        <td class="py-3 font-mono font-bold text-primary">{{ $exp->expense_number }}</td>
                                        <td class="py-3">{{ $exp->expense_date?->format('M d, Y') }}</td>
                                        <td class="py-3">
                                            <span class="font-bold">{{ $categories[$exp->category] ?? $exp->category }}</span>
                                            <span class="block text-[10px] text-outline">{{ $exp->hub?->name ?? 'Company-Wide' }}</span>
                                        </td>
                                        <td class="py-3">{{ $exp->description }}</td>
                                        <td class="py-3 text-right font-extrabold">₱{{ number_format($exp->amount, 2) }}</td>
                                        <td class="py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $exp->status === 'recorded' ? 'bg-emerald-500/10 text-emerald-800' : 'bg-rose-500/10 text-rose-800' }}">{{ $exp->status }}</span></td>
                                        <td class="py-3 text-right">
                                            @if($exp->status === 'recorded')
                                                <form method="POST" action="{{ route('logistics.expenses.void', $exp) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-500/10 text-rose-700 font-bold text-[11px] cursor-pointer">Void</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="py-8 text-center text-on-surface-variant">No expenses recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="mt-4">{{ $expenses->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
