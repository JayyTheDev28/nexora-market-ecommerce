<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CourierProfile;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\ApplicationDecision;
use App\Notifications\RiderActivationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RiderController extends Controller
{
    /**
     * Display a listing of riders and courier applications for this sorting center.
     */
    public function index(Request $request): View
    {
        $sortingCenterId = $request->user()->id;
        $tab = $request->query('tab', 'active');
        $category = $request->query('category', 'all'); // all | riders | couriers
        $search = $request->query('q');

        // Tenant isolation: Only riders & transfer couriers belonging to this logistics company
        $query = User::whereIn('role', ['rider', 'courier'])
            ->when($category === 'riders', fn ($q) => $q->where('role', 'rider'))
            ->when($category === 'couriers', fn ($q) => $q->where('role', 'courier'))
            ->whereHas('courierProfile', fn ($cp) => $cp->where('sorting_center_id', $sortingCenterId)->orWhere('logistics_company_id', $sortingCenterId))
            ->with([
                'courierProfile.assignedHub',
                'courierProfile.logisticsCompany.logisticsProfile',
                'address',
                'assignedZones.barangays',
                'assignedZones.hub',
                'deliveryAreas' => fn ($q) => $q->where('sorting_center_id', $sortingCenterId),
            ])
            ->withCount([
                'activeDeliveries as active_deliveries_count',
                'completedDeliveries as completed_deliveries_count',
                'assignedManifests as active_manifests_count' => fn ($q) => $q->whereIn('status', \App\Models\TransferManifest::ACTIVE_STATUSES),
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhereHas('courierProfile', fn ($cp) => $cp->where('plate_number', 'like', "%{$search}%")->orWhere('license_number', 'like', "%{$search}%"));
            });
        }

        match ($tab) {
            'pending_activation' => $query->where('account_status', 'pending_activation'),
            'active' => $query->where('approval_status', 'approved')->where('account_status', 'active'),
            'inactive' => $query->where('approval_status', 'approved')->where('account_status', 'deactivated'),
            'pending' => $query->where('approval_status', 'pending'),
            'disapproved' => $query->where('approval_status', 'disapproved'),
            default => null, // 'all' shows all couriers for this center
        };

        $riders = $query->latest()->latest('id')->paginate(15)->withQueryString();

        $baseCount = fn () => User::whereIn('role', ['rider', 'courier'])
            ->when($category === 'riders', fn ($q) => $q->where('role', 'rider'))
            ->when($category === 'couriers', fn ($q) => $q->where('role', 'courier'))
            ->whereHas('courierProfile', fn ($cp) => $cp->where('sorting_center_id', $sortingCenterId)->orWhere('logistics_company_id', $sortingCenterId));

        $categoryCounts = [
            'all' => User::whereIn('role', ['rider', 'courier'])->whereHas('courierProfile', fn ($cp) => $cp->where('sorting_center_id', $sortingCenterId)->orWhere('logistics_company_id', $sortingCenterId))->count(),
            'riders' => User::where('role', 'rider')->whereHas('courierProfile', fn ($cp) => $cp->where('sorting_center_id', $sortingCenterId)->orWhere('logistics_company_id', $sortingCenterId))->count(),
            'couriers' => User::where('role', 'courier')->whereHas('courierProfile', fn ($cp) => $cp->where('sorting_center_id', $sortingCenterId)->orWhere('logistics_company_id', $sortingCenterId))->count(),
        ];

        $counts = [
            'pending_activation' => (clone $baseCount())->where('account_status', 'pending_activation')->count(),
            'active' => (clone $baseCount())->where('approval_status', 'approved')->where('account_status', 'active')->count(),
            'inactive' => (clone $baseCount())->where('approval_status', 'approved')->where('account_status', 'deactivated')->count(),
            'pending' => (clone $baseCount())->where('approval_status', 'pending')->count(),
            'disapproved' => (clone $baseCount())->where('approval_status', 'disapproved')->count(),
            'all' => (clone $baseCount())->count(),
        ];

        // Coverage zones managed by this logistics company
        $zones = Zone::where('logistics_company_id', $sortingCenterId)
            ->with(['hub', 'riders', 'barangays'])
            ->withCount(['riders', 'barangays'])
            ->orderBy('name')
            ->get();

        // Delivery areas managed by this sorting center (legacy)
        $deliveryAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->with('courier')
            ->orderBy('name')
            ->get();

        return view('logistics.riders.index', compact('riders', 'counts', 'categoryCounts', 'category', 'tab', 'search', 'deliveryAreas', 'zones'));
    }

    /**
     * Show the Add Rider or Add Transfer Courier form.
     */
    public function create(Request $request): View
    {
        $personnelType = $request->query('type') === 'courier' ? 'courier' : 'rider';
        $hubs = \App\Models\Hub::where('logistics_company_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('logistics.riders.create', compact('personnelType', 'hubs'));
    }

    /**
     * Show the Create Transfer Courier form.
     */
    public function createCourier(Request $request): View
    {
        $personnelType = 'courier';
        $hubs = \App\Models\Hub::where('logistics_company_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('logistics.riders.create', compact('personnelType', 'hubs'));
    }

    /**
     * Store a newly created Transfer Courier and dispatch activation email.
     */
    public function storeCourier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s]+$/'],
            'birthday' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,contact_number'],
            'license_number' => ['required', 'string', 'regex:/^[A-Z]\d{2}-\d{2}-\d{6}$/i', 'unique:courier_profiles,license_number'],
            'license_expiry_date' => ['required', 'date', 'after_or_equal:today'],
            'license_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'assigned_hub_id' => ['nullable', 'integer', 'exists:hubs,id'],
            'account_status' => ['nullable', 'string', 'in:pending_activation,active'],
        ]);

        if (! empty($validated['assigned_hub_id'])) {
            \App\Models\Hub::where('id', $validated['assigned_hub_id'])
                ->where('logistics_company_id', $request->user()->id)
                ->firstOrFail();
        }

        $licensePath = $request->file('license_file')->store('documents/courier/licenses', 'public');
        $activationToken = Str::random(64);

        $courier = DB::transaction(function () use ($validated, $licensePath, $activationToken, $request) {
            $user = User::create([
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'sex' => 'other',
                'birthday' => $validated['birthday'],
                'age' => \Carbon\Carbon::parse($validated['birthday'])->age,
                'email' => strtolower(trim($validated['email'])),
                'contact_number' => trim($validated['contact_number']),
                'password' => Hash::make(Str::random(32)),
                'role' => 'courier',
                'approval_status' => 'approved',
                'account_status' => 'pending_activation',
                'email_verified_at' => null,
                'valid_id_path' => $licensePath,
                'activation_token' => $activationToken,
                'activation_token_expires_at' => now()->addHours(24),
            ]);

            CourierProfile::create([
                'user_id' => $user->id,
                'sorting_center_id' => $request->user()->id,
                'logistics_company_id' => $request->user()->id,
                'assigned_hub_id' => $validated['assigned_hub_id'] ?? null,
                'vehicle_type' => null,
                'plate_number' => null,
                'license_number' => strtoupper(trim($validated['license_number'])),
                'license_expiry_date' => $validated['license_expiry_date'],
                'license_path' => $licensePath,
                'or_cr_path' => null,
            ]);

            return $user;
        });

        try {
            $courier->notify(new RiderActivationNotification($activationToken));
        } catch (\Throwable $e) {
            Log::error('Failed to send transfer courier activation notification: ' . $e->getMessage());
        }

        return redirect()->route('logistics.riders.index', ['tab' => 'pending_activation', 'category' => 'couriers'])
            ->with('status', "Transfer Courier {$courier->full_name} registered successfully. An activation email has been sent to {$courier->email}.");
    }

    /**
     * Store a newly created rider and dispatch activation email.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s]+$/'],
            'birthday' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,contact_number'],
            'license_number' => ['required', 'string', 'regex:/^[A-Z]\d{2}-\d{2}-\d{6}$/i', 'unique:courier_profiles,license_number'],
            'license_expiry_date' => ['required', 'date', 'after_or_equal:today'],
            'vehicle_type' => ['required', 'string', 'in:Motorcycle,Van,Sedan,Truck,Bicycle'],
            'plate_number' => ['required', 'string', 'max:20', 'unique:courier_profiles,plate_number'],
            'license_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'or_cr_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'first_name.regex' => 'First name must contain letters and spaces only.',
            'last_name.regex' => 'Last name must contain letters and spaces only.',
            'birthday.before_or_equal' => 'Rider must be at least 18 years old as of today.',
            'contact_number.regex' => 'Mobile number must start with 09 and be exactly 11 digits (e.g. 09170000001).',
            'contact_number.unique' => 'This mobile number is already registered in the system.',
            'license_number.regex' => 'Driver\'s License No. must match Philippine format (e.g., N01-23-456789).',
            'license_number.unique' => 'This driver\'s license number is already registered.',
            'license_expiry_date.after_or_equal' => 'License expiry date must be today or a future date.',
            'plate_number.unique' => 'This plate number is already registered in the system.',
            'license_file.required' => 'The Driver\'s License document upload is required.',
            'license_file.mimes' => 'Driver\'s License must be a file of type: JPG, PNG, or PDF.',
            'license_file.max' => 'Driver\'s License file size must not exceed 5 MB.',
            'or_cr_file.required' => 'The Vehicle OR/CR document upload is required.',
            'or_cr_file.mimes' => 'Vehicle OR/CR must be a file of type: JPG, PNG, or PDF.',
            'or_cr_file.max' => 'Vehicle OR/CR file size must not exceed 5 MB.',
        ]);

        $licensePath = $request->file('license_file')->store('documents/courier/licenses', 'public');
        $orCrPath = $request->file('or_cr_file')->store('documents/courier/or_cr', 'public');

        $activationToken = Str::random(64);

        $rider = DB::transaction(function () use ($validated, $licensePath, $orCrPath, $activationToken, $request) {
            $user = User::create([
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'sex' => 'other',
                'birthday' => $validated['birthday'],
                'age' => \Carbon\Carbon::parse($validated['birthday'])->age,
                'email' => strtolower(trim($validated['email'])),
                'contact_number' => trim($validated['contact_number']),
                'password' => Hash::make(Str::random(32)),
                'role' => 'rider',
                'approval_status' => 'approved',
                'account_status' => 'pending_activation',
                'email_verified_at' => null,
                'valid_id_path' => $licensePath,
                'activation_token' => $activationToken,
                'activation_token_expires_at' => now()->addHours(24),
            ]);

            CourierProfile::create([
                'user_id' => $user->id,
                'sorting_center_id' => $request->user()->id,
                'logistics_company_id' => $request->user()->id,
                'vehicle_type' => $validated['vehicle_type'],
                'plate_number' => strtoupper(trim($validated['plate_number'])),
                'license_number' => strtoupper(trim($validated['license_number'])),
                'license_expiry_date' => $validated['license_expiry_date'],
                'license_path' => $licensePath,
                'or_cr_path' => $orCrPath,
            ]);

            return $user;
        });

        try {
            $rider->notify(new RiderActivationNotification($activationToken));
        } catch (\Throwable $e) {
            Log::error('Failed to send rider activation notification: ' . $e->getMessage());
        }

        return redirect()->route('logistics.riders.index', ['tab' => 'pending_activation'])
            ->with('status', "Rider {$rider->full_name} registered successfully. An activation email with a 24-hour setup link has been sent to {$rider->email}.");
    }

    /**
     * Resend account activation link to a rider.
     */
    public function resendActivation(Request $request, User $rider): RedirectResponse
    {
        $this->authorizeRiderBelongsToCenter($request, $rider);
        abort_unless($rider->account_status === 'pending_activation', 422, 'This rider is not pending activation.');

        $token = Str::random(64);
        $rider->update([
            'activation_token' => $token,
            'activation_token_expires_at' => now()->addHours(24),
        ]);

        try {
            $rider->notify(new RiderActivationNotification($token));
        } catch (\Throwable $e) {
            Log::error('Failed to resend rider activation notification: ' . $e->getMessage());
        }

        return back()->with('status', "A new activation email has been sent to {$rider->email} (expires in 24 hours).");
    }

    /**
     * Show rider profile details, documents, and workload.
     */
    public function show(Request $request, User $rider): View
    {
        $this->authorizeRiderBelongsToCenter($request, $rider, 404);

        $sortingCenterId = $request->user()->id;

        $rider->load([
            'courierProfile.assignedHub',
            'courierProfile.logisticsCompany.logisticsProfile',
            'courierProfile.sortingCenter.logisticsProfile',
            'address',
            'assignedZones.barangays',
            'assignedZones.hub',
            'deliveryAreas' => fn ($q) => $q->where('sorting_center_id', $sortingCenterId),
            'activeDeliveries.buyer',
            'activeDeliveries.address',
            'completedDeliveries' => fn ($q) => $q->latest('delivered_at')->latest('id')->limit(10),
        ]);

        $availableZones = Zone::where('logistics_company_id', $sortingCenterId)
            ->where('is_active', true)
            ->when(
                $rider->courierProfile?->assigned_hub_id,
                fn ($q, $hubId) => $q->where(function ($hq) use ($hubId) {
                    $hq->where('hub_id', $hubId)->orWhereNull('hub_id');
                })
            )
            ->with(['hub', 'barangays', 'riders'])
            ->orderBy('name')
            ->get();

        $availableAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->orderBy('name')
            ->get();

        return view('logistics.riders.show', compact('rider', 'availableAreas', 'availableZones'));
    }

    /**
     * Approve a pending courier registration.
     */
    public function approve(Request $request, User $rider): RedirectResponse
    {
        $this->authorizeRiderBelongsToCenter($request, $rider);

        if (! $rider->courierProfile) {
            $rider->courierProfile()->create([
                'sorting_center_id' => $request->user()->id,
                'vehicle_type' => 'Motorcycle',
                'plate_number' => 'APP-' . $rider->id,
            ]);
        }

        if ($rider->isApproved()) {
            return back()->with('info', "Rider {$rider->full_name} is already approved.");
        }

        $rider->update([
            'approval_status' => 'approved',
            'disapproval_reason' => null,
            'account_status' => 'active',
        ]);

        AuditLog::log(
            action: 'rider_approval',
            auditable: $rider,
            oldValues: ['approval_status' => 'pending'],
            newValues: ['approval_status' => 'approved', 'account_status' => 'active'],
            reason: 'Logistics center approved rider application',
            operatorId: $request->user()->id
        );

        $rider->notify(new ApplicationDecision(approved: true));

        return back()->with('status', "Approved courier {$rider->full_name}. They have been notified via email.");
    }

    /**
     * Disapprove a courier application.
     */
    public function disapprove(Request $request, User $rider): RedirectResponse
    {
        $this->authorizeRiderBelongsToCenter($request, $rider);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $rider->update([
            'approval_status' => 'disapproved',
            'disapproval_reason' => $validated['reason'],
        ]);

        AuditLog::log(
            action: 'rider_disapproval',
            auditable: $rider,
            oldValues: ['approval_status' => 'pending'],
            newValues: ['approval_status' => 'disapproved'],
            reason: $validated['reason'],
            operatorId: $request->user()->id
        );

        $rider->notify(new ApplicationDecision(approved: false, reason: $validated['reason']));

        return back()->with('status', "Disapproved courier application for {$rider->full_name}.");
    }

    /**
     * Activate or deactivate an existing courier account.
     */
    public function toggleStatus(Request $request, User $rider): RedirectResponse
    {
        $this->authorizeRiderBelongsToCenter($request, $rider);
        abort_unless($rider->isApproved(), 400, 'Cannot change account status of an unapproved courier.');

        $validated = $request->validate([
            'action' => ['required', 'in:activate,deactivate'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['action'] === 'deactivate') {
            $oldStatus = $rider->account_status;

            // 1. Pending jobs not yet picked up return to Unassigned queue
            Order::where('pickup_courier_id', $rider->id)
                ->whereIn('status', ['ready_for_pickup', 'assigned'])
                ->update([
                    'pickup_courier_id' => null,
                    'status' => 'ready_for_pickup',
                ]);

            Order::where('delivery_courier_id', $rider->id)
                ->where('status', 'assigned_to_rider')
                ->update([
                    'delivery_courier_id' => null,
                    'status' => 'at_hub',
                ]);

            // Note: Parcels already Picked Up (picked_up / out_for_delivery) remain assigned to that rider.
            $activeCount = $rider->activeDeliveries()->count();

            $rider->update([
                'account_status' => 'deactivated',
                'status_reason' => $validated['reason'] ?? 'Deactivated by sorting center.',
            ]);

            if ($rider->courierProfile) {
                $rider->courierProfile->update(['is_online' => false]);
            }

            // Audit Trail
            AuditLog::log(
                action: 'rider_suspension',
                auditable: $rider,
                oldValues: ['account_status' => $oldStatus],
                newValues: ['account_status' => 'deactivated'],
                reason: $validated['reason'] ?? 'Suspended by logistics sorting center.',
                operatorId: $request->user()->id
            );

            $message = "Deactivated rider {$rider->full_name}. Pending unpicked jobs returned to the Unassigned queue.";
            if ($activeCount > 0) {
                $message .= " Note: This rider still has {$activeCount} picked-up parcels assigned.";
            }

            return back()->with('status', $message);
        }

        $oldStatus = $rider->account_status;
        $rider->update([
            'account_status' => 'active',
            'status_reason' => null,
        ]);

        AuditLog::log(
            action: 'rider_activation',
            auditable: $rider,
            oldValues: ['account_status' => $oldStatus],
            newValues: ['account_status' => 'active'],
            reason: 'Activated by logistics sorting center.',
            operatorId: $request->user()->id
        );

        return back()->with('status', "Activated rider {$rider->full_name}. They are now eligible for parcel assignments.");
    }

    /**
     * Assign coverage zones (and legacy delivery areas) to a courier.
     */
    public function assignZones(Request $request, User $rider): RedirectResponse
    {
        return $this->assignAreas($request, $rider);
    }

    public function assignAreas(Request $request, User $rider): RedirectResponse
    {
        $this->authorizeRiderBelongsToCenter($request, $rider);

        abort_unless($rider->isRider(), 422, 'Only delivery riders may be assigned a delivery zone.');
        // Prevent deactivated, pending activation, or unapproved riders from receiving new assignments
        abort_unless($rider->isApproved(), 422, 'Unapproved riders cannot be assigned delivery coverage.');
        abort_unless($rider->isActive(), 422, 'Only active riders can be assigned delivery coverage.');

        $validated = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:zones,id'],
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'exists:delivery_areas,id'],
        ]);

        if ($request->has('zone_ids') && count($validated['zone_ids'] ?? []) > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'zone_id' => 'Each rider may only be assigned to a single delivery zone.',
            ]);
        }

        $sortingCenterId = $request->user()->id;
        $hasZoneInput = $request->has('zone_id') || $request->has('zone_ids');
        $targetZoneId = $validated['zone_id'] ?? ($validated['zone_ids'][0] ?? null);
        $targetZoneId = $targetZoneId ? (int) $targetZoneId : null;

        if ($hasZoneInput && $targetZoneId !== null) {
            $zone = Zone::where('id', $targetZoneId)
                ->where('logistics_company_id', $sortingCenterId)
                ->where('is_active', true)
                ->first();

            if (! $zone) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'zone_id' => 'Selected delivery zone does not belong to your logistics company or is inactive.',
                ]);
            }

            $assignedHubId = $rider->courierProfile?->assigned_hub_id;
            if ($assignedHubId && $zone->hub_id && (int) $zone->hub_id !== (int) $assignedHubId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'zone_id' => 'Selected delivery zone must belong to the rider\'s assigned hub.',
                ]);
            }
        }

        $conflictingOrders = collect();
        if ($hasZoneInput) {
            $conflictingOrders = Order::where('delivery_courier_id', $rider->id)
                ->whereIn('status', ['assigned_to_rider', 'out_for_delivery'])
                ->when($targetZoneId !== null, fn ($q) => $q->where(function ($zq) use ($targetZoneId) {
                    $zq->whereNull('delivery_zone_id')->orWhere('delivery_zone_id', '!=', $targetZoneId);
                }))
                ->pluck('order_number');
        }

        DB::transaction(function () use ($sortingCenterId, $rider, $validated, $request, $hasZoneInput, $targetZoneId) {
            // 1. Coverage Zone (strictly 1 zone per rider; replaces any previous assignment)
            if ($hasZoneInput) {
                if ($targetZoneId !== null) {
                    $rider->assignedZones()->sync([$targetZoneId]);
                    $zone = Zone::find($targetZoneId);
                    if ($zone?->hub_id && $rider->courierProfile && $rider->courierProfile->assigned_hub_id === null) {
                        $rider->courierProfile->update(['assigned_hub_id' => $zone->hub_id]);
                    }
                } else {
                    $rider->assignedZones()->detach();
                }
            }

            // 2. Legacy DeliveryArea assignment (if area_ids provided)
            if ($request->has('area_ids')) {
                $selectedAreaIds = $validated['area_ids'] ?? [];
                DeliveryArea::where('sorting_center_id', $sortingCenterId)
                    ->where('courier_id', $rider->id)
                    ->whereNotIn('id', $selectedAreaIds)
                    ->update(['courier_id' => null]);

                if (! empty($selectedAreaIds)) {
                    DeliveryArea::where('sorting_center_id', $sortingCenterId)
                        ->whereIn('id', $selectedAreaIds)
                        ->update(['courier_id' => $rider->id]);
                }
            }
        });

        $message = "Updated delivery coverage assignment for rider {$rider->full_name}.";
        if ($conflictingOrders->isNotEmpty()) {
            $message .= ' Note: Existing active parcel assignments were preserved (' . $conflictingOrders->implode(', ') . ') — review coverage conflicts.';
        }

        return back()->with('status', $message);
    }

    /**
     * Authorize that the rider belongs to the authenticated logistics sorting center.
     */
    protected function authorizeRiderBelongsToCenter(Request $request, User $rider, int $notFoundStatus = 400): void
    {
        abort_unless($rider->isRider() || $rider->isCourier(), $notFoundStatus, 'User is not a rider or transfer courier.');

        $assignedCenter = $rider->courierProfile?->logistics_company_id ?? $rider->courierProfile?->sorting_center_id;
        if ($assignedCenter !== null && (int) $assignedCenter !== (int) $request->user()->id) {
            abort(403, 'Unauthorized access to this personnel.');
        }

        // Auto-link legacy unassigned courier profile if applicable
        if ($rider->courierProfile && $rider->courierProfile->sorting_center_id === null) {
            $rider->courierProfile->update([
                'sorting_center_id' => $request->user()->id,
                'logistics_company_id' => $request->user()->id,
            ]);
        }
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
            abort_unless($courier && $courier->isRider() && $courier->isApproved() && $courier->isActive(), 422, 'Assigned rider must be an approved and active delivery rider.');
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

        $areaName = $area->name;
        $area->delete();

        return back()->with('status', "Delivery area '{$areaName}' removed.");
    }

    /**
     * Module R1: Re-verify Rider Vehicle Update.
     */
    public function verifyVehicle(Request $request, User $rider): RedirectResponse
    {
        $this->authorizeRiderBelongsToCenter($request, $rider);
        $profile = $rider->courierProfile;
        abort_unless($profile, 404, 'Rider profile not found.');

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $decision = $validated['decision'];

        if ($decision === 'approve') {
            $oldPlate = $profile->plate_number;
            $newPlate = $profile->pending_plate_number ?: $profile->plate_number;

            $profile->update([
                'vehicle_type' => $profile->pending_vehicle_type ?: $profile->vehicle_type,
                'plate_number' => $newPlate,
                'or_cr_path' => $profile->pending_or_cr_path ?: $profile->or_cr_path,
                'pending_vehicle_type' => null,
                'pending_plate_number' => null,
                'pending_or_cr_path' => null,
                'vehicle_verification_status' => 'verified',
            ]);

            AuditLog::log(
                action: 'vehicle_re_verification_approved',
                auditable: $profile,
                oldValues: ['plate_number' => $oldPlate],
                newValues: ['plate_number' => $newPlate],
                reason: $validated['reason'] ?? 'Vehicle update approved by logistics operator',
                operatorId: $request->user()->id
            );

            return back()->with('status', "Vehicle update approved for rider {$rider->full_name}.");
        }

        $profile->update([
            'vehicle_verification_status' => 'rejected',
        ]);

        AuditLog::log(
            action: 'vehicle_re_verification_rejected',
            auditable: $profile,
            oldValues: ['pending_plate_number' => $profile->pending_plate_number],
            newValues: ['vehicle_verification_status' => 'rejected'],
            reason: $validated['reason'] ?? 'Vehicle documents rejected',
            operatorId: $request->user()->id
        );

        return back()->with('status', "Vehicle update rejected for rider {$rider->full_name}.");
    }

    /**
     * Authorized Logistics Order Status Override with mandatory AuditLog.
     */
    public function overrideStatus(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->sorting_center_id === $request->user()->id, 403, 'Unauthorized order override.');

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        app(\App\Services\ShipmentStateMachineService::class)->logisticsOverride(
            $order,
            $validated['status'],
            $request->user(),
            $validated['reason']
        );

        return back()->with('status', "Order {$order->order_number} manually transitioned to {$validated['status']} with audit log.");
    }
}
