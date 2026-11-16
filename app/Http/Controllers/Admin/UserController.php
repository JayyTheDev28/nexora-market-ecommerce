<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountRestriction;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RestrictionAppeal;
use App\Models\SellerWarning;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use App\Services\AccountEnforcementService;
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

        $query = User::query()->with(['sellerProfile', 'sortingCenterProfile', 'logisticsProfile', 'courierProfile', 'address']);

        if ($role) {
            if ($role === 'sorting_center' || $role === 'logistics') {
                $query->whereIn('role', ['logistics', 'sorting_center']);
            } elseif ($role === 'courier' || $role === 'rider') {
                $query->whereIn('role', ['rider', 'courier']);
            } else {
                $query->where('role', $role);
            }
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
            'sortingCenters' => User::whereIn('role', ['logistics', 'sorting_center'])->count(),
            'couriers' => User::whereIn('role', ['rider', 'courier'])->count(),
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
            'accountRestrictions.issuer',
            'accountRestrictions.appeals',
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

    public function restrict(Request $request, User $user, AccountEnforcementService $enforcement): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('manage_users'), 403);
        abort_if($user->id === $request->user()->id, 422, 'Cannot restrict your own administrator account.');

        $validated = $request->validate([
            'restriction_type' => ['required', Rule::in(array_keys(AccountRestriction::TYPES))],
            'reason' => ['required', 'string', 'max:1000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $restriction = $enforcement->applyRestriction(
            $user,
            $request->user(),
            $validated['restriction_type'],
            $validated['reason'],
            $validated['duration_days'] ?? null
        );

        return back()->with('status', "{$restriction->type_label} applied to {$user->full_name}.");
    }

    public function liftRestriction(Request $request, AccountRestriction $restriction, AccountEnforcementService $enforcement): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('manage_users'), 403);

        $validated = $request->validate([
            'lift_reason' => ['required', 'string', 'max:500'],
        ]);

        $enforcement->liftRestriction($restriction, $request->user(), $validated['lift_reason']);

        return back()->with('status', 'Restriction lifted and logged.');
    }

    public function reviewAppeal(Request $request, RestrictionAppeal $appeal, AccountEnforcementService $enforcement): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('manage_users'), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'under_review'])],
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $enforcement->reviewAppeal($appeal, $request->user(), $validated['decision'], $validated['admin_notes']);

        return back()->with('status', "Appeal #{$appeal->id} marked as " . ucfirst(str_replace('_', ' ', $validated['decision'])) . '.');
    }
}
