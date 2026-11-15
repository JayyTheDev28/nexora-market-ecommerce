@extends('layouts.app')

@section('title', 'Admin Review — Inter-Company Shipping Split Policies')

@section('content')
<div class="w-full min-h-screen bg-surface py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-on-surface">Shipping Revenue-Sharing Policy Governance</h1>
                <p class="text-xs text-on-surface-variant mt-1">Review and approve multi-carrier revenue split contracts before they activate on inter-company transfers.</p>
            </div>
            <a href="{{ route('admin.logistics-remittances.index') }}" class="text-xs font-bold text-primary hover:underline">← Back to Admin Remittances</a>
        </div>

        @if (session('status'))
            <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 text-xs text-primary font-semibold">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs p-5 overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                        <th class="pb-3">Policy</th>
                        <th class="pb-3">Scope</th>
                        <th class="pb-3">Split (O / T / D)</th>
                        <th class="pb-3">Contract Ref</th>
                        <th class="pb-3">Status</th>
                        <th class="pb-3">Review Status</th>
                        <th class="pb-3 text-right">Admin Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @foreach($policies as $p)
                        <tr>
                            <td class="py-3 font-semibold">{{ $p->name }} <span class="text-[10px] font-mono text-outline">v{{ $p->version }}</span></td>
                            <td class="py-3">{{ $p->logisticsCompany?->full_name ?? 'Platform Default' }}</td>
                            <td class="py-3">{{ $p->origin_logistics_share_pct }}% / {{ $p->transfer_carrier_share_pct }}% / {{ $p->delivery_logistics_share_pct }}%</td>
                            <td class="py-3 font-mono">{{ $p->contract_reference ?? '—' }}</td>
                            <td class="py-3 uppercase font-bold">{{ $p->status }}</td>
                            <td class="py-3 uppercase font-bold">{{ $p->review_status }}</td>
                            <td class="py-3 text-right space-x-2">
                                @if($p->review_status !== 'approved')
                                    <form method="POST" action="{{ route('admin.shipping-policies.approve', $p) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="contract_reference" value="{{ $p->contract_reference ?: 'CONTRACT-' . $p->id }}" class="px-2 py-1 rounded border border-outline-variant text-[11px] w-32" required>
                                        <input type="hidden" name="activate" value="1">
                                        <button type="submit" class="px-3 py-1 rounded-xl bg-emerald-600 text-white font-bold cursor-pointer">Approve & Activate</button>
                                    </form>
                                @else
                                    <span class="text-emerald-700 font-semibold">Approved {{ $p->reviewed_at?->format('M d, Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
