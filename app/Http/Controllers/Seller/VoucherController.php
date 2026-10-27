<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $seller = $request->user();

        $vouchers = Voucher::where('seller_id', $seller->id)
            ->withCount(['userClaims as claims_count', 'userClaims as used_count' => fn ($q) => $q->whereNotNull('used_at')])
            ->latest()
            ->get();

        $stats = [
            'total_vouchers' => $vouchers->count(),
            'active_vouchers' => $vouchers->where('is_active', true)->filter(fn ($v) => ! $v->isExpired())->count(),
            'total_claims' => $vouchers->sum('claims_count'),
            'total_used' => $vouchers->sum('used_count'),
        ];

        return view('seller.vouchers.index', compact('vouchers', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $seller = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_num', 'unique:vouchers,code'],
            'type' => ['required', 'in:flat,percent'],
            'value' => ['required', 'numeric', 'min:1'],
            'min_spend' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:1'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validated['type'] === 'percent' && (float) $validated['value'] > 100) {
            return back()->withErrors(['value' => 'Percentage discount cannot exceed 100%.']);
        }

        Voucher::create([
            'seller_id' => $seller->id,
            'code' => strtoupper($validated['code']),
            'type' => $validated['type'],
            'voucher_scope' => 'store',
            'value' => $validated['value'],
            'min_spend' => $validated['min_spend'],
            'max_discount' => $validated['max_discount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'is_active' => true,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return redirect()->route('seller.vouchers.index')
            ->with('status', "Voucher '{$validated['code']}' created successfully.");
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'min_spend' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:1'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $voucher->update([
            'min_spend' => $validated['min_spend'],
            'max_discount' => $validated['max_discount'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('seller.vouchers.index')
            ->with('status', "Voucher '{$voucher->code}' was updated.");
    }

    public function toggle(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);

        $voucher->update(['is_active' => ! $voucher->is_active]);

        $state = $voucher->is_active ? 'active' : 'inactive';
        return redirect()->route('seller.vouchers.index')
            ->with('status', "Voucher '{$voucher->code}' is now {$state}.");
    }

    public function claims(Request $request, Voucher $voucher): View
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);

        $claims = $voucher->userClaims()->with(['user', 'order'])->latest('claimed_at')->paginate(20);

        return view('seller.vouchers.claims', compact('voucher', 'claims'));
    }
}
