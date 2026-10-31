<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\SellerWarning;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->query('role');
        $status = $request->query('status');
        $approval = $request->query('approval');
        $search = $request->query('q');

        $query = User::query()->with(['sellerProfile', 'sortingCenterProfile', 'courierProfile', 'address']);

        if ($role) {
            $query->where('role', $role);
        }

        if ($status) {
            $query->where('account_status', $status);
        }

        if ($approval) {
            $query->where('approval_status', $approval);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhereHas('sellerProfile', fn ($sq) => $sq->where('business_name', 'like', "%{$search}%"))
                    ->orWhereHas('sortingCenterProfile', fn ($sq) => $sq->where('business_name', 'like', "%{$search}%"));
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'total' => User::count(),
            'active' => User::where('account_status', 'active')->count(),
            'suspended' => User::where('account_status', 'suspended')->count(),
            'deactivated' => User::where('account_status', 'deactivated')->count(),
            'buyers' => User::where('role', 'buyer')->count(),
            'sellers' => User::where('role', 'seller')->count(),
            'sortingCenters' => User::where('role', 'sorting_center')->count(),
            'couriers' => User::where('role', 'courier')->count(),
        ];

        return view('admin.users.index', compact('users', 'role', 'status', 'approval', 'search', 'stats'));
    }

    public function show(User $user): View
    {
        $user->load([
            'sellerProfile',
            'sortingCenterProfile',
            'courierProfile',
            'address',
            'addresses',
            'products' => fn ($q) => $q->latest()->limit(5),
        ]);

        $warnings = [];
        if ($user->isSeller()) {
            $warnings = SellerWarning::where('seller_id', $user->id)->with('issuedBy', 'product')->latest()->get();
        }

        $auditTrail = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->with('operator')
            ->latest('created_at')
            ->limit(10)
            ->get();

        $metrics = [];
        if ($user->isBuyer()) {
            $metrics['totalOrders'] = Order::where('buyer_id', $user->id)->count();
            $metrics['totalSpent'] = Order::where('buyer_id', $user->id)->where('status', 'completed')->sum('total');
        } elseif ($user->isSeller()) {
            $metrics['totalProducts'] = $user->products()->count();
            $metrics['activeProducts'] = $user->products()->where('status', 'active')->count();
            $metrics['totalSales'] = Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $user->id))
                ->where('status', 'completed')
                ->sum('total');
        }

        return view('admin.users.show', compact('user', 'warnings', 'auditTrail', 'metrics'));
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['status' => 'You cannot suspend or deactivate your own administrator account.']);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'deactivated'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $validated['status'];
        $reason = $validated['reason'] ?? null;

        if (in_array($newStatus, ['suspended', 'deactivated'], true) && empty($reason)) {
            return back()->withErrors(['reason' => 'A valid reason is required when suspending or deactivating an account.']);
        }

        $oldStatus = $user->account_status;

        if ($oldStatus === $newStatus) {
            return back()->with('status', 'Account status was already ' . $newStatus);
        }

        $user->update([
            'account_status' => $newStatus,
            'status_reason' => $reason,
        ]);

        AuditLog::log(
            action: 'user_status_changed',
            auditable: $user,
            oldValues: ['account_status' => $oldStatus],
            newValues: ['account_status' => $newStatus, 'status_reason' => $reason],
            reason: $reason ?? "Account status set to {$newStatus} by administrator."
        );

        try {
            $user->notify(new AccountStatusChangedNotification($newStatus, $reason));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to send status notification to user: " . $e->getMessage());
        }

        return back()->with('status', "Account status for {$user->full_name} updated to {$newStatus}.");
    }
}
