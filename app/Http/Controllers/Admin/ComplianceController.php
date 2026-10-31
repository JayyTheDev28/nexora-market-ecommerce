<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerWarning;
use App\Models\User;
use App\Notifications\SellerWarningNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $violationType = $request->query('violation_type');
        $sellerId = $request->query('seller_id');
        $search = $request->query('q');

        $query = SellerWarning::query()->with(['seller.sellerProfile', 'issuedBy', 'product.category']);

        if ($violationType) {
            $query->where('violation_type', $violationType);
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('details', 'like', "%{$search}%")
                    ->orWhereHas('seller', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('sellerProfile', fn ($pq) => $pq->where('business_name', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        $warnings = $query->latest()->paginate(15)->withQueryString();

        $sellers = User::where('role', 'seller')->with('sellerProfile')->orderBy('first_name')->get();
        $categories = Category::orderBy('name')->get();

        $stats = [
            'totalWarnings' => SellerWarning::count(),
            'categoryMismatches' => SellerWarning::where('violation_type', 'category_mismatch')->count(),
            'prohibitedItems' => SellerWarning::where('violation_type', 'prohibited_item')->count(),
            'warnedSellersCount' => SellerWarning::distinct('seller_id')->count('seller_id'),
        ];

        return view('admin.compliance.index', compact('warnings', 'sellers', 'categories', 'stats', 'violationType', 'sellerId', 'search'));
    }

    public function storeWarning(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'violation_type' => ['required', 'string', 'max:50'],
            'details' => ['required', 'string', 'max:2000'],
            'archive_product' => ['nullable', 'boolean'],
        ]);

        $seller = User::findOrFail($validated['seller_id']);

        $warning = SellerWarning::create([
            'seller_id' => $seller->id,
            'issued_by' => auth()->id(),
            'product_id' => $validated['product_id'] ?? null,
            'violation_type' => $validated['violation_type'],
            'details' => $validated['details'],
        ]);

        if (! empty($validated['archive_product']) && ! empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
            if ($product && $product->status !== 'archived') {
                $oldStatus = $product->status;
                $product->update(['status' => 'archived']);

                AuditLog::log(
                    action: 'product_archived_compliance',
                    auditable: $product,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'archived'],
                    reason: "Product archived due to compliance violation: {$validated['violation_type']}"
                );
            }
        }

        AuditLog::log(
            action: 'seller_warning_issued',
            auditable: $warning,
            oldValues: null,
            newValues: $warning->toArray(),
            reason: "Issued compliance warning ({$validated['violation_type']}) to seller {$seller->full_name}"
        );

        try {
            $seller->notify(new SellerWarningNotification($warning));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to notify seller of warning: " . $e->getMessage());
        }

        return back()->with('status', "Warning issued to {$seller->full_name}. Notification dispatched.");
    }

    public function toggleProductStatus(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $oldStatus = $product->status;
        $newStatus = $oldStatus === 'active' ? 'archived' : 'active';

        $product->update(['status' => $newStatus]);

        AuditLog::log(
            action: 'product_status_changed_admin',
            auditable: $product,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
            reason: $validated['reason']
        );

        $actionText = $newStatus === 'active' ? 'reactivated' : 'archived';

        return back()->with('status', "Product '{$product->name}' has been {$actionText}.");
    }
}
