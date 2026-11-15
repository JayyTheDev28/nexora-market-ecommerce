<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\LogisticsAdminRemittance;
use App\Models\LogisticsRemittanceItem;
use App\Models\Order;
use App\Models\SellerPayoutRequest;
use App\Models\SellerSettlementAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CodFinancialService
{
    /**
     * Platform commission rate: 10%
     */
    public const COMMISSION_RATE = 0.1000;

    /**
     * Minimum payout threshold: ₱500.00
     */
    public const MINIMUM_PAYOUT_AMOUNT = 500.00;

    /**
     * Get comprehensive COD financial summary for a Logistics partner.
     */
    public function getLogisticsCodSummary(User $logistics): array
    {
        $logisticsId = $logistics->logisticsCompanyId();

        // Collecting logistics company is delivery_logistics_id (or sorting_center_id if no separate delivery company)
        $deliveredCodOrders = Order::where('payment_method', 'cod')
            ->whereIn('status', ['delivered', 'completed'])
            ->where(function ($q) use ($logisticsId) {
                $q->where('delivery_logistics_id', $logisticsId)
                    ->orWhere(function ($sub) use ($logisticsId) {
                        $sub->whereNull('delivery_logistics_id')
                            ->where('sorting_center_id', $logisticsId);
                    });
            })
            ->with(['codCollection'])
            ->get();

        $totalCodReceived = round((float) $deliveredCodOrders->sum(fn ($o) => (float) $o->total), 2);
        $shippingRetained = round((float) $deliveredCodOrders->sum(fn ($o) => (float) $o->shipping_fee), 2);
        $merchandisePayable = round((float) $deliveredCodOrders->sum(fn ($o) => $o->merchandiseAmount()), 2);

        $awaitingRiderOrders = $deliveredCodOrders->filter(fn ($o) => ! $this->hasVerifiedRiderCodCash($o, $logisticsId));
        $awaitingRiderCodAmount = round((float) $awaitingRiderOrders->sum(fn ($o) => (float) $o->total), 2);

        // Eligible orders for new remittance: Rider cash verified AND not yet in any active/verified Admin remittance batch
        $eligibleOrders = $this->getEligibleOrdersForRemittance($logistics);
        $availableCodTotal = round((float) $eligibleOrders->sum(fn ($o) => (float) $o->total), 2);
        $availableShippingTotal = round((float) $eligibleOrders->sum(fn ($o) => (float) $o->shipping_fee), 2);
        $availableMerchandiseAmount = round((float) $eligibleOrders->sum(fn ($o) => $o->merchandiseAmount()), 2);

        // Remittance batches
        $remittancesQuery = LogisticsAdminRemittance::where('logistics_id', $logisticsId);

        $pendingRemittancesCount = (clone $remittancesQuery)->whereIn('status', ['submitted', 'under_review', 'disputed'])->count();
        $pendingMerchandiseAmount = (float) (clone $remittancesQuery)->whereIn('status', ['submitted', 'under_review', 'disputed'])->sum('merchandise_amount');

        $verifiedRemittancesCount = (clone $remittancesQuery)->where('status', 'verified')->count();
        $verifiedMerchandiseAmount = (float) (clone $remittancesQuery)->where('status', 'verified')->sum('merchandise_amount');
        $verifiedShippingRetained = (float) (clone $remittancesQuery)->where('status', 'verified')->sum('shipping_retained');

        $rejectedRemittancesCount = (clone $remittancesQuery)->where('status', 'rejected')->count();
        $rejectedMerchandiseAmount = (float) (clone $remittancesQuery)->where('status', 'rejected')->sum('merchandise_amount');

        return [
            'totalCodReceived' => $totalCodReceived,
            'shippingRetained' => $shippingRetained,
            'merchandisePayable' => $merchandisePayable,
            'availableMerchandiseAmount' => $availableMerchandiseAmount,
            'availableShippingTotal' => $availableShippingTotal,
            'availableCodTotal' => $availableCodTotal,
            'availableOrdersCount' => $eligibleOrders->count(),
            'awaitingRiderCodAmount' => $awaitingRiderCodAmount,
            'awaitingRiderOrdersCount' => $awaitingRiderOrders->count(),
            'pendingRemittancesCount' => $pendingRemittancesCount,
            'pendingMerchandiseAmount' => $pendingMerchandiseAmount,
            'verifiedRemittancesCount' => $verifiedRemittancesCount,
            'verifiedMerchandiseAmount' => $verifiedMerchandiseAmount,
            'verifiedShippingRetained' => $verifiedShippingRetained,
            'rejectedRemittancesCount' => $rejectedRemittancesCount,
            'rejectedMerchandiseAmount' => $rejectedMerchandiseAmount,
        ];
    }

    /**
     * Determine whether Rider COD cash for an order has been physically/externally verified by Logistics.
     */
    public function hasVerifiedRiderCodCash(Order $order, int $logisticsId): bool
    {
        if (strtolower((string) $order->payment_method) !== 'cod') {
            return false;
        }

        $collection = $order->codCollection;
        if ($collection) {
            return $collection->isFullyVerifiedForAdminRemittance()
                && ((int) ($collection->collecting_logistics_id ?: $logisticsId) === $logisticsId);
        }

        // Legacy fallback ONLY when no CodCollection row exists yet: require an approved/verified CodRemittance
        $query = \App\Models\CodRemittance::where('sorting_center_id', $logisticsId)
            ->whereIn('status', \App\Models\CodRemittance::VERIFIED_STATUSES);

        if ($order->delivery_courier_id) {
            $query->where('courier_id', $order->delivery_courier_id);
        }

        return $query->exists();
    }

    /**
     * Get orders eligible for logistics-to-admin remittance.
     * Delivered, COD, belonging to this collecting logistics company,
     * Rider cash verified, and not in any non-rejected remittance.
     */
    public function getEligibleOrdersForRemittance(User $logistics, ?int $limit = null): Collection
    {
        $logisticsId = $logistics->logisticsCompanyId();

        $query = Order::where('payment_method', 'cod')
            ->whereIn('status', ['delivered', 'completed'])
            ->where(function ($q) use ($logisticsId) {
                $q->where('delivery_logistics_id', $logisticsId)
                    ->orWhere(function ($sub) use ($logisticsId) {
                        $sub->whereNull('delivery_logistics_id')
                            ->where('sorting_center_id', $logisticsId);
                    });
            })
            ->whereDoesntHave('remittanceItem.remittance', function ($q) {
                $q->where('status', '!=', 'rejected');
            })
            ->with(['buyer', 'seller.sellerProfile', 'items.product', 'codCollection'])
            ->latest('delivered_at');

        $orders = $query->get()
            ->filter(fn (Order $order) => $this->hasVerifiedRiderCodCash($order, $logisticsId))
            ->values();

        if ($limit) {
            return $orders->take($limit);
        }

        return $orders;
    }

    /**
     * Submit a new remittance batch from Logistics to Nexora Admin.
     */
    public function createLogisticsAdminRemittance(
        User $logistics,
        array $orderIds,
        array $data,
        UploadedFile|string $proofFile
    ): LogisticsAdminRemittance {
        if (empty($orderIds)) {
            throw ValidationException::withMessages([
                'order_ids' => 'Please select at least one eligible delivered COD order to remit.',
            ]);
        }

        return DB::transaction(function () use ($logistics, $orderIds, $data, $proofFile) {
            $logisticsId = $logistics->logisticsCompanyId();

            // Row-lock selected orders to prevent concurrent multi-batch remittance
            $orders = Order::whereIn('id', $orderIds)
                ->with('codCollection')
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== count(array_unique($orderIds))) {
                throw ValidationException::withMessages([
                    'order_ids' => 'One or more selected orders could not be located.',
                ]);
            }

            // Verify each order eligibility
            $totalCod = 0.00;
            $totalShipping = 0.00;
            $totalSubsidy = 0.00;
            $totalMerchandise = 0.00;

            foreach ($orders as $order) {
                // Must be COD
                if (strtolower($order->payment_method) !== 'cod') {
                    throw ValidationException::withMessages([
                        'order_ids' => "Order #{$order->order_number} is not a Cash on Delivery order.",
                    ]);
                }

                // Must be delivered or completed
                if (! in_array($order->status, ['delivered', 'completed'], true)) {
                    throw ValidationException::withMessages([
                        'order_ids' => "Order #{$order->order_number} has not been delivered yet.",
                    ]);
                }

                // Must belong to this collecting logistics provider
                $custodianId = (int) ($order->delivery_logistics_id ?: $order->sorting_center_id);
                if ($custodianId !== $logisticsId) {
                    throw ValidationException::withMessages([
                        'order_ids' => "Unauthorized: Order #{$order->order_number} is assigned to another collecting carrier.",
                    ]);
                }

                // Must have Rider COD cash verified first
                if (! $this->hasVerifiedRiderCodCash($order, $logisticsId)) {
                    throw ValidationException::withMessages([
                        'order_ids' => "Order #{$order->order_number} cannot be remitted yet: Rider COD cash has not been fully verified by Logistics.",
                    ]);
                }

                // Check for existing active remittance item
                $alreadyInBatch = LogisticsRemittanceItem::where('order_id', $order->id)
                    ->whereHas('remittance', function ($q) {
                        $q->where('status', '!=', 'rejected');
                    })
                    ->exists();

                if ($alreadyInBatch) {
                    throw ValidationException::withMessages([
                        'order_ids' => "Order #{$order->order_number} is already part of an active or settled remittance batch.",
                    ]);
                }

                $shipping = (float) $order->shipping_fee;
                $merchandise = $order->merchandiseAmount();
                $subsidy = (float) ($order->platform_shipping_subsidy ?? $order->shipping_voucher_discount ?? 0);
                $cod = (float) $order->total;

                $totalCod += $cod;
                $totalShipping += $shipping;
                $totalSubsidy += $subsidy;
                $totalMerchandise += $merchandise;
            }

            // Store proof of payment file
            if ($proofFile instanceof UploadedFile) {
                $proofPath = $proofFile->store('remittances/logistics_to_admin', 'public');
            } else {
                $proofPath = $proofFile;
            }

            $refNumber = 'LAR-' . date('Ymd') . '-' . Str::upper(Str::random(6));

            $remittance = LogisticsAdminRemittance::create([
                'logistics_id' => $logisticsId,
                'reference_number' => $refNumber,
                'total_cod_amount' => round($totalCod, 2),
                'shipping_retained' => round($totalShipping, 2),
                'shipping_subsidy_total' => round($totalSubsidy, 2),
                'merchandise_amount' => round($totalMerchandise, 2),
                'transfer_method' => $data['transfer_method'] ?? 'bank_transfer',
                'transfer_reference' => trim((string) ($data['transfer_reference'] ?? '')),
                'proof_path' => $proofPath,
                'notes' => $data['notes'] ?? null,
                'status' => 'submitted',
            ]);

            // Create itemized remittance rows preserving discount & verified COD links
            foreach ($orders as $order) {
                $shipping = (float) $order->shipping_fee;
                $merchandise = $order->merchandiseAmount();
                $cod = (float) $order->total;
                $collection = $order->codCollection;
                $verifiedRemitId = null;
                if ($collection) {
                    $verifiedRemitId = \App\Models\CodRemittanceAllocation::where('cod_collection_id', $collection->id)
                        ->whereIn('status', ['verified', 'partially_verified'])
                        ->latest('id')
                        ->value('cod_remittance_id');
                }

                LogisticsRemittanceItem::create([
                    'logistics_admin_remittance_id' => $remittance->id,
                    'order_id' => $order->id,
                    'cod_remittance_id' => $verifiedRemitId,
                    'cod_collection_id' => $collection?->id,
                    'total_cod_amount' => round($cod, 2),
                    'shipping_component' => round($shipping, 2),
                    'merchandise_component' => round($merchandise, 2),
                    'seller_discount_amount' => round((float) ($order->store_voucher_discount ?? 0), 2),
                    'platform_discount_amount' => round((float) ($order->platform_voucher_discount ?? 0), 2),
                    'platform_shipping_subsidy' => round((float) ($order->platform_shipping_subsidy ?? $order->shipping_voucher_discount ?? 0), 2),
                ]);
            }

            AuditLog::log(
                action: 'logistics_remittance_submitted',
                auditable: $remittance,
                oldValues: null,
                newValues: [
                    'reference_number' => $remittance->reference_number,
                    'total_cod' => $remittance->total_cod_amount,
                    'shipping_retained' => $remittance->shipping_retained,
                    'merchandise_amount' => $remittance->merchandise_amount,
                    'orders_count' => count($orderIds),
                ],
                reason: "Logistics partner submitted COD remittance {$remittance->reference_number}",
                operatorId: $logisticsId
            );

            return $remittance;
        });
    }

    /**
     * Admin verifies actual receipt of merchandise funds from Logistics.
     * Deducts 10% commission and allocates 90% seller earnings per order and seller.
     */
    public function verifyLogisticsAdminRemittance(
        LogisticsAdminRemittance $remittance,
        User $admin,
        ?string $adminNotes = null,
        ?float $actualReceivedAmount = null,
        ?string $verifiedBankReference = null
    ): LogisticsAdminRemittance {
        return DB::transaction(function () use ($remittance, $admin, $adminNotes, $actualReceivedAmount, $verifiedBankReference) {
            $lockedRemittance = LogisticsAdminRemittance::where('id', $remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRemittance->status === 'verified') {
                throw new \DomainException("Remittance #{$lockedRemittance->reference_number} has already been verified.");
            }

            if ($lockedRemittance->status === 'rejected') {
                throw new \DomainException("Cannot verify a rejected remittance.");
            }

            $expectedCents = (int) round((float) $lockedRemittance->merchandise_amount * 100);
            $actualCents = $actualReceivedAmount !== null
                ? (int) round($actualReceivedAmount * 100)
                : $expectedCents;

            if ($actualCents < $expectedCents) {
                throw new \DomainException(
                    'Actual received funds (₱' . number_format($actualCents / 100, 2) . ') are less than expected merchandise proceeds (₱' . number_format($expectedCents / 100, 2) . '). Use Flag / Dispute instead of releasing seller payouts for unreceived cash.'
                );
            }

            $variance = round(($actualCents - $expectedCents) / 100, 2);
            $oldStatus = $lockedRemittance->status;

            $lockedRemittance->update([
                'status' => 'verified',
                'actual_received_amount' => round($actualCents / 100, 2),
                'variance_amount' => $variance,
                'verified_bank_reference' => $verifiedBankReference ?: $lockedRemittance->verified_bank_reference,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'notes' => $adminNotes
                    ? ($lockedRemittance->notes ? $lockedRemittance->notes . "\nAdmin Note: " . $adminNotes : $adminNotes)
                    : $lockedRemittance->notes,
                'rejection_reason' => null,
            ]);

            // Process each itemized order in the remittance
            $items = $lockedRemittance->items()->with('order')->get();

            foreach ($items as $item) {
                $order = $item->order;
                $eligibleMerchandise = (float) $item->merchandise_component;
                $platformCommission = round($eligibleMerchandise * self::COMMISSION_RATE, 2);
                $sellerPayable = round($eligibleMerchandise - $platformCommission, 2);

                // Create or update the traceable SellerSettlementAllocation
                SellerSettlementAllocation::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'seller_id' => $order->seller_id,
                        'logistics_admin_remittance_id' => $lockedRemittance->id,
                        'eligible_merchandise_amount' => $eligibleMerchandise,
                        'commission_rate' => self::COMMISSION_RATE,
                        'platform_commission' => $platformCommission,
                        'seller_payable' => $sellerPayable,
                        'status' => 'available',
                        'settled_at' => now(),
                    ]
                );

                // Settle or update the existing Commission record for historical reconciliation
                Commission::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'seller_id' => $order->seller_id,
                    ],
                    [
                        'order_total' => $eligibleMerchandise,
                        'rate' => self::COMMISSION_RATE,
                        'commission_amount' => $platformCommission,
                        'seller_payout' => $sellerPayable,
                        'status' => 'settled',
                        'settled_at' => now(),
                    ]
                );
            }

            AuditLog::log(
                action: 'logistics_remittance_verified',
                auditable: $lockedRemittance,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'verified',
                    'merchandise_amount' => $lockedRemittance->merchandise_amount,
                    'reviewed_by' => $admin->id,
                ],
                reason: "Administrator verified receipt of merchandise funds for #{$lockedRemittance->reference_number}",
                operatorId: $admin->id
            );

            return $lockedRemittance;
        });
    }

    /**
     * Admin rejects a submitted logistics remittance with a required reason.
     * Releasing the orders to be corrected or re-submitted.
     */
    public function rejectLogisticsAdminRemittance(
        LogisticsAdminRemittance $remittance,
        User $admin,
        string $reason
    ): LogisticsAdminRemittance {
        return DB::transaction(function () use ($remittance, $admin, $reason) {
            $lockedRemittance = LogisticsAdminRemittance::where('id', $remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRemittance->status === 'verified') {
                throw new \DomainException("Cannot reject an already verified remittance.");
            }

            $oldStatus = $lockedRemittance->status;

            $lockedRemittance->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLog::log(
                action: 'logistics_remittance_rejected',
                auditable: $lockedRemittance,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'rejected', 'rejection_reason' => $reason],
                reason: "Administrator rejected remittance: {$reason}",
                operatorId: $admin->id
            );

            return $lockedRemittance;
        });
    }

    /**
     * Admin flags/disputes a submitted logistics remittance due to amount mismatch or proof discrepancy.
     */
    public function disputeLogisticsAdminRemittance(
        LogisticsAdminRemittance $remittance,
        User $admin,
        string $reason,
        ?float $actualReceivedAmount = null
    ): LogisticsAdminRemittance {
        return DB::transaction(function () use ($remittance, $admin, $reason, $actualReceivedAmount) {
            $lockedRemittance = LogisticsAdminRemittance::where('id', $remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRemittance->status === 'verified') {
                throw new \DomainException("Cannot dispute an already verified remittance.");
            }

            $oldStatus = $lockedRemittance->status;
            $expected = (float) $lockedRemittance->merchandise_amount;
            $variance = $actualReceivedAmount !== null ? round($actualReceivedAmount - $expected, 2) : 0.00;

            $lockedRemittance->update([
                'status' => 'disputed',
                'actual_received_amount' => $actualReceivedAmount !== null ? round($actualReceivedAmount, 2) : $lockedRemittance->actual_received_amount,
                'variance_amount' => $variance,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'discrepancy_notes' => $reason,
            ]);

            AuditLog::log(
                action: 'logistics_remittance_disputed',
                auditable: $lockedRemittance,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'disputed',
                    'actual_received_amount' => $lockedRemittance->actual_received_amount,
                    'variance_amount' => $variance,
                    'discrepancy_notes' => $reason,
                ],
                reason: "Administrator flagged discrepancy on remittance: {$reason}",
                operatorId: $admin->id
            );

            return $lockedRemittance;
        });
    }

    /**
     * Calculate comprehensive financial metrics for a Seller:
     * - Gross sales
     * - Pending in-transit earnings
     * - Earnings waiting for logistics remittance
     * - Verified & settled earnings
     * - Net available balance (withdrawable)
     * - Payouts requested & completed
     */
    public function getSellerFinancialOverview(User $seller): array
    {
        $sellerId = $seller->id;

        // 1. All non-cancelled orders for gross sales calculation
        $allOrders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->whereNotIn('status', ['cancelled', 'returned'])
        ->get();

        $grossSales = $allOrders->sum(function ($order) use ($sellerId) {
            $itemsSum = $order->items->where('product.seller_id', $sellerId)->sum(fn ($i) => $i->price * $i->quantity);
            return $itemsSum > 0 ? $itemsSum : (float) $order->subtotal;
        });

        // 2. Verified settled allocations (Funds verified from Logistics to Admin)
        $settledAllocations = SellerSettlementAllocation::where('seller_id', $sellerId)
            ->whereIn('status', ['available', 'paid', 'payout_requested'])
            ->get();

        $verifiedSettledGross = (float) $settledAllocations->sum('eligible_merchandise_amount');
        $platformCommissionDeducted = (float) $settledAllocations->sum('platform_commission');
        $verifiedEarningsPayable = (float) $settledAllocations->sum('seller_payable');

        // 3. Earnings waiting for logistics remittance (delivered but logistics hasn't remitted or admin hasn't verified)
        $waitingOrders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->whereIn('status', ['delivered', 'completed'])
        ->whereDoesntHave('settlementAllocation', function ($q) {
            $q->whereIn('status', ['available', 'paid', 'payout_requested']);
        })
        ->get();

        $earningsWaitingLogistics = (float) $waitingOrders->sum(function ($order) use ($sellerId) {
            $merch = $order->merchandiseAmount();
            return round($merch * (1 - self::COMMISSION_RATE), 2);
        });

        // 4. In-transit / processing earnings (orders not yet delivered)
        $inTransitOrders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->whereNotIn('status', ['delivered', 'completed', 'cancelled', 'returned'])
        ->get();

        $pendingInTransitEarnings = (float) $inTransitOrders->sum(function ($order) use ($sellerId) {
            $merch = $order->merchandiseAmount();
            return round($merch * (1 - self::COMMISSION_RATE), 2);
        });

        // 5. Payout Requests (Pending, Approved, Paid)
        $pendingPayouts = (float) SellerPayoutRequest::where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');

        $completedPayouts = (float) SellerPayoutRequest::where('seller_id', $sellerId)
            ->whereIn('status', ['paid', 'processed'])
            ->sum('amount');

        $totalPayoutsRequested = $pendingPayouts + $completedPayouts;

        // Available balance strictly comes from verified settled earnings minus payouts requested/completed
        $availableBalance = max(0.00, round($verifiedEarningsPayable - $totalPayoutsRequested, 2));

        return [
            'grossSales' => $grossSales,
            'verifiedSettledGross' => $verifiedSettledGross,
            'platformCommissionDeducted' => $platformCommissionDeducted,
            'verifiedEarningsPayable' => $verifiedEarningsPayable,
            'earningsWaitingLogistics' => $earningsWaitingLogistics,
            'pendingInTransitEarnings' => $pendingInTransitEarnings,
            'pendingPayouts' => $pendingPayouts,
            'completedPayouts' => $completedPayouts,
            'totalPayoutsRequested' => $totalPayoutsRequested,
            'availableBalance' => $availableBalance,
        ];
    }

    /**
     * Seller requests a payout from verified available funds.
     */
    public function requestSellerPayout(
        User $seller,
        float $amount,
        string $payoutMethod,
        string $accountName,
        string $accountNumber,
        ?string $notes = null
    ): SellerPayoutRequest {
        if ($amount < self::MINIMUM_PAYOUT_AMOUNT) {
            throw ValidationException::withMessages([
                'amount' => 'The minimum payout withdrawal request is ₱' . number_format(self::MINIMUM_PAYOUT_AMOUNT, 2) . '.',
            ]);
        }

        return DB::transaction(function () use ($seller, $amount, $payoutMethod, $accountName, $accountNumber, $notes) {
            $overview = $this->getSellerFinancialOverview($seller);
            $available = $overview['availableBalance'];

            if ($amount > $available) {
                throw ValidationException::withMessages([
                    'amount' => "Requested withdrawal (₱" . number_format($amount, 2) . ") exceeds your verified available balance (₱" . number_format($available, 2) . ").",
                ]);
            }

            $refNumber = 'SPR-' . date('Ymd') . '-' . Str::upper(Str::random(6));

            $payout = SellerPayoutRequest::create([
                'seller_id' => $seller->id,
                'reference_number' => $refNumber,
                'amount' => round($amount, 2),
                'payout_method' => $payoutMethod,
                'account_name' => trim($accountName),
                'account_number' => trim($accountNumber),
                'status' => 'pending',
                'notes' => $notes,
            ]);

            AuditLog::log(
                action: 'seller_payout_requested',
                auditable: $payout,
                oldValues: null,
                newValues: [
                    'reference_number' => $payout->reference_number,
                    'amount' => $payout->amount,
                    'payout_method' => $payout->payout_method,
                    'seller_id' => $seller->id,
                ],
                reason: "Seller requested payout withdrawal of ₱{$payout->amount}",
                operatorId: $seller->id
            );

            return $payout;
        });
    }

    /**
     * Admin approves a seller payout request (ready for disbursement).
     */
    public function approveSellerPayout(
        SellerPayoutRequest $payout,
        User $admin,
        ?string $adminNotes = null
    ): SellerPayoutRequest {
        return DB::transaction(function () use ($payout, $admin, $adminNotes) {
            $locked = SellerPayoutRequest::where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new \DomainException("Payout request is not pending (current status: {$locked->status}).");
            }

            $oldStatus = $locked->status;

            $locked->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'admin_notes' => $adminNotes ?? $locked->admin_notes,
            ]);

            AuditLog::log(
                action: 'seller_payout_approved',
                auditable: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'approved'],
                reason: "Administrator approved payout request #{$locked->id}",
                operatorId: $admin->id
            );

            return $locked;
        });
    }

    /**
     * Admin records actual payment disbursement with reference and proof, marking as paid.
     */
    public function disburseSellerPayout(
        SellerPayoutRequest $payout,
        User $admin,
        string $transactionReference,
        UploadedFile|string|null $proofFile = null,
        ?string $adminNotes = null
    ): SellerPayoutRequest {
        if (empty(trim($transactionReference))) {
            throw ValidationException::withMessages([
                'transaction_reference' => 'Transaction reference number is required to confirm actual disbursement.',
            ]);
        }

        return DB::transaction(function () use ($payout, $admin, $transactionReference, $proofFile, $adminNotes) {
            $locked = SellerPayoutRequest::where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid()) {
                throw new \DomainException("Payout #{$locked->id} has already been marked as paid.");
            }

            if ($locked->isRejected()) {
                throw new \DomainException("Cannot disburse a rejected payout request.");
            }

            $proofPath = $locked->proof_path;
            if ($proofFile instanceof UploadedFile) {
                $proofPath = $proofFile->store('payouts/proofs', 'public');
            } elseif (is_string($proofFile)) {
                $proofPath = $proofFile;
            }

            $oldStatus = $locked->status;

            $locked->update([
                'status' => 'paid',
                'transaction_reference' => trim($transactionReference),
                'proof_path' => $proofPath,
                'reviewed_by' => $admin->id,
                'admin_notes' => $adminNotes ?? $locked->admin_notes,
                'disbursed_at' => now(),
                'processed_at' => now(),
            ]);

            AuditLog::log(
                action: 'seller_payout_disbursed',
                auditable: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'paid',
                    'transaction_reference' => $transactionReference,
                    'amount' => $locked->amount,
                ],
                reason: "Administrator confirmed payment disbursement for payout #{$locked->id}",
                operatorId: $admin->id
            );

            return $locked;
        });
    }

    /**
     * Admin rejects a seller payout request, restoring the amount back to the seller's available balance.
     */
    public function rejectSellerPayout(
        SellerPayoutRequest $payout,
        User $admin,
        string $reason
    ): SellerPayoutRequest {
        return DB::transaction(function () use ($payout, $admin, $reason) {
            $locked = SellerPayoutRequest::where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid()) {
                throw new \DomainException("Cannot reject an already completed/paid payout.");
            }

            $oldStatus = $locked->status;

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'rejection_reason' => $reason,
                'processed_at' => now(),
            ]);

            AuditLog::log(
                action: 'seller_payout_rejected',
                auditable: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'rejected', 'rejection_reason' => $reason],
                reason: "Administrator rejected payout request: {$reason}",
                operatorId: $admin->id
            );

            return $locked;
        });
    }
}

