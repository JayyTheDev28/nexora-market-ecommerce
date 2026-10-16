<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\User;
use App\Notifications\ApplicationDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RiderController extends Controller
{
    /**
     * Display a listing of riders and courier applications.
     */
    public function index(Request $request): View
    {
        $sortingCenterId = $request->user()->id;
        $tab = $request->query('tab', 'pending');
        $search = $request->query('q');

        $query = User::where('role', 'courier')
            ->with(['courierProfile', 'address', 'deliveryAreas' => fn ($q) => $q->where('sorting_center_id', $sortingCenterId)])
            ->withCount([
                'activeDeliveries as active_deliveries_count',
                'completedDeliveries as completed_deliveries_count',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhereHas('courierProfile', fn ($cp) => $cp->where('plate_number', 'like', "%{$search}%"));
            });
        }

        match ($tab) {
            'pending' => $query->where('approval_status', 'pending'),
            'active' => $query->where('approval_status', 'approved')->where('account_status', 'active'),
            'inactive' => $query->where('approval_status', 'approved')->where('account_status', '!=', 'active'),
            'disapproved' => $query->where('approval_status', 'disapproved'),
            default => null, // 'all' shows all couriers
        };

        $riders = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'pending' => User::where('role', 'courier')->where('approval_status', 'pending')->count(),
            'active' => User::where('role', 'courier')->where('approval_status', 'approved')->where('account_status', 'active')->count(),
            'inactive' => User::where('role', 'courier')->where('approval_status', 'approved')->where('account_status', '!=', 'active')->count(),
            'disapproved' => User::where('role', 'courier')->where('approval_status', 'disapproved')->count(),
            'all' => User::where('role', 'courier')->count(),
        ];

        // Delivery areas managed by this sorting center
        $deliveryAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->with('courier')
            ->orderBy('name')
            ->get();

        return view('logistics.riders.index', compact('riders', 'counts', 'tab', 'search', 'deliveryAreas'));
    }

    /**
     * Show rider profile details, documents, and workload.
     */
    public function show(Request $request, User $rider): View
    {
        abort_unless($rider->isCourier(), 404, 'User is not a courier.');

        $sortingCenterId = $request->user()->id;

        $rider->load([
            'courierProfile',
            'address',
            'deliveryAreas' => fn ($q) => $q->where('sorting_center_id', $sortingCenterId),
            'activeDeliveries.buyer',
            'activeDeliveries.address',
            'completedDeliveries' => fn ($q) => $q->latest()->limit(10),
        ]);

        $availableAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->orderBy('name')
            ->get();

        return view('logistics.riders.show', compact('rider', 'availableAreas'));
    }

    /**
     * Approve a pending courier registration.
     */
    public function approve(User $rider): RedirectResponse
    {
        abort_unless($rider->isCourier(), 400, 'User is not a courier.');

        if ($rider->isApproved()) {
            return back()->with('info', "Rider {$rider->full_name} is already approved.");
        }

        $rider->update([
            'approval_status' => 'approved',
            'disapproval_reason' => null,
            'account_status' => 'active',
        ]);

        $rider->notify(new ApplicationDecision(approved: true));

        return back()->with('status', "Approved courier {$rider->full_name}. They have been notified via email.");
    }

    /**
     * Disapprove a courier application.
     */
    public function disapprove(Request $request, User $rider): RedirectResponse
    {
        abort_unless($rider->isCourier(), 400, 'User is not a courier.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $rider->update([
            'approval_status' => 'disapproved',
            'disapproval_reason' => $validated['reason'],
        ]);

        $rider->notify(new ApplicationDecision(approved: false, reason: $validated['reason']));

        return back()->with('status', "Disapproved courier application for {$rider->full_name}.");
    }

    /**
     * Activate or deactivate an existing courier account.
     */
    public function toggleStatus(Request $request, User $rider): RedirectResponse
    {
        abort_unless($rider->isCourier(), 400, 'User is not a courier.');
        abort_unless($rider->isApproved(), 400, 'Cannot change account status of an unapproved courier.');

        $validated = $request->validate([
            'action' => ['required', 'in:activate,deactivate'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['action'] === 'deactivate') {
            $activeCount = $rider->activeDeliveries()->count();
            $rider->update([
                'account_status' => 'deactivated',
                'status_reason' => $validated['reason'] ?? 'Deactivated by sorting center.',
            ]);

            $message = "Deactivated rider {$rider->full_name}.";
            if ($activeCount > 0) {
                $message .= " Note: This rider still has {$activeCount} active parcels assigned that may need reassignment.";
            }

            return back()->with('status', $message);
        }

        $rider->update([
            'account_status' => 'active',
            'status_reason' => null,
        ]);

        return back()->with('status', "Activated rider {$rider->full_name}. They are now eligible for parcel assignments.");
    }

    /**
     * Assign delivery areas to a courier.
     */
    public function assignAreas(Request $request, User $rider): RedirectResponse
    {
        abort_unless($rider->isCourier(), 400, 'User is not a courier.');

        // Prevent deactivated or unapproved riders from receiving new assignments
        abort_unless($rider->isApproved(), 422, 'Unapproved riders cannot be assigned delivery areas.');
        abort_unless($rider->isActive(), 422, 'Deactivated riders cannot be assigned delivery areas.');

        $validated = $request->validate([
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'exists:delivery_areas,id'],
        ]);

        $sortingCenterId = $request->user()->id;
        $selectedAreaIds = $validated['area_ids'] ?? [];

        DB::transaction(function () use ($sortingCenterId, $rider, $selectedAreaIds) {
            // Unassign areas owned by this sorting center previously assigned to this rider but not selected
            DeliveryArea::where('sorting_center_id', $sortingCenterId)
                ->where('courier_id', $rider->id)
                ->whereNotIn('id', $selectedAreaIds)
                ->update(['courier_id' => null]);

            // Assign newly selected areas to this rider
            if (! empty($selectedAreaIds)) {
                DeliveryArea::where('sorting_center_id', $sortingCenterId)
                    ->whereIn('id', $selectedAreaIds)
                    ->update(['courier_id' => $rider->id]);
            }
        });

        return back()->with('status', "Updated delivery areas for rider {$rider->full_name}.");
    }

    /**
     * Create a new delivery area for this sorting center.
     */
    public function storeArea(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'courier_id' => ['nullable', 'exists:users,id'],
        ]);

        // If courier assigned, ensure approved and active
        if (! empty($validated['courier_id'])) {
            $courier = User::find($validated['courier_id']);
            abort_unless($courier && $courier->isCourier() && $courier->isApproved() && $courier->isActive(), 422, 'Assigned rider must be an approved and active courier.');
        }

        DeliveryArea::create([
            'sorting_center_id' => $request->user()->id,
            'name' => $validated['name'],
            'courier_id' => $validated['courier_id'] ?? null,
        ]);

        return back()->with('status', "Delivery area '{$validated['name']}' created successfully.");
    }

    /**
     * Delete a delivery area.
     */
    public function destroyArea(Request $request, DeliveryArea $area): RedirectResponse
    {
        abort_unless($area->sorting_center_id === $request->user()->id, 403);

        // Check if orders currently reference this area
        $areaName = $area->name;
        $area->delete();

        return back()->with('status', "Delivery area '{$areaName}' removed.");
    }
}
