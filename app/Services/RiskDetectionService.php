<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CodRemittance;
use App\Models\DeliveryAttempt;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\RiskAlert;
use App\Models\SellerPayoutRequest;
use App\Models\SellerWarning;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RiskDetectionService
{
    /**
     * Run rules-based anomaly checks against authoritative database records.
     * Never automatically suspends accounts; generates reviewable RiskAlerts only.
     */
    public function runRulesScan(?User $actor = null): array
    {
        $created = [];

        // Rule 1: Repeated COD remittance shortages per rider (>= 2 remittances with shortage_amount > 0)
        $shortageRiders = CodRemittance::where('shortage_amount', '>', 0)
            ->select('courier_id', DB::raw('COUNT(*) as shortage_count'), DB::raw('SUM(shortage_amount) as total_shortage'))
            ->groupBy('courier_id')
            ->having('shortage_count', '>=', 2)
            ->get();

        foreach ($shortageRiders as $row) {
            $created[] = $this->upsertOpenAlert(
                ruleCode: 'REPEATED_COD_SHORTAGE',
                title: 'Repeated COD remittance shortages detected',
                severity: 'high',
                entityType: User::class,
                entityId: (int) $row->courier_id,
                userId: (int) $row->courier_id,
                supportingData: [
                    'shortage_count' => (int) $row->shortage_count,
                    'total_shortage' => round((float) $row->total_shortage, 2),
                ]
            );
        }

        // Rule 2: Duplicate transfer/payment reference usage in SellerPayoutRequest
        $dupPayoutRefs = SellerPayoutRequest::whereNotNull('transaction_reference')
            ->where('transaction_reference', '!=', '')
            ->select('transaction_reference', DB::raw('COUNT(*) as ref_count'))
            ->groupBy('transaction_reference')
            ->having('ref_count', '>', 1)
            ->get();

        foreach ($dupPayoutRefs as $row) {
            $first = SellerPayoutRequest::where('transaction_reference', $row->transaction_reference)->first();
            $created[] = $this->upsertOpenAlert(
                ruleCode: 'DUPLICATE_PAYOUT_REFERENCE',
                title: "Duplicate payout reference: {$row->transaction_reference}",
                severity: 'critical',
                entityType: SellerPayoutRequest::class,
                entityId: $first?->id,
                userId: $first?->seller_id,
                supportingData: [
                    'transaction_reference' => $row->transaction_reference,
                    'occurrences' => (int) $row->ref_count,
                ]
            );
        }

        // Rule 3: Excessive failed delivery attempts (>= 5 in last 14 days by same rider)
        $failedByRider = DeliveryAttempt::where('result', 'failed')
            ->where('attempted_at', '>=', now()->subDays(14))
            ->select('courier_id', DB::raw('COUNT(*) as failed_count'))
            ->groupBy('courier_id')
            ->having('failed_count', '>=', 5)
            ->get();

        foreach ($failedByRider as $row) {
            $created[] = $this->upsertOpenAlert(
                ruleCode: 'EXCESSIVE_FAILED_DELIVERIES',
                title: 'High failed delivery attempt volume (last 14 days)',
                severity: 'medium',
                entityType: User::class,
                entityId: (int) $row->courier_id,
                userId: (int) $row->courier_id,
                supportingData: [
                    'failed_attempts_14d' => (int) $row->failed_count,
                ]
            );
        }

        // Rule 4: Excessive buyer cancellations (>= 4 in last 30 days)
        $buyerCancels = Order::where('status', 'cancelled')
            ->where('updated_at', '>=', now()->subDays(30))
            ->select('buyer_id', DB::raw('COUNT(*) as cancel_count'))
            ->groupBy('buyer_id')
            ->having('cancel_count', '>=', 4)
            ->get();

        foreach ($buyerCancels as $row) {
            $created[] = $this->upsertOpenAlert(
                ruleCode: 'EXCESSIVE_BUYER_CANCELLATIONS',
                title: 'Excessive order cancellations by buyer (30d)',
                severity: 'medium',
                entityType: User::class,
                entityId: (int) $row->buyer_id,
                userId: (int) $row->buyer_id,
                supportingData: ['cancellations_30d' => (int) $row->cancel_count]
            );
        }

        // Rule 5: Repeated seller compliance warnings (>= 3 warnings)
        $warnedSellers = SellerWarning::select('seller_id', DB::raw('COUNT(*) as warning_count'))
            ->groupBy('seller_id')
            ->having('warning_count', '>=', 3)
            ->get();

        foreach ($warnedSellers as $row) {
            $created[] = $this->upsertOpenAlert(
                ruleCode: 'REPEATED_SELLER_VIOLATIONS',
                title: 'Seller accumulated 3+ compliance warnings',
                severity: 'high',
                entityType: User::class,
                entityId: (int) $row->seller_id,
                userId: (int) $row->seller_id,
                supportingData: ['warnings_count' => (int) $row->warning_count]
            );
        }

        // Rule 6: Frequent wrong-hub or missing parcel incidents per hub/company (>= 3 in 30d)
        $incidentHotspots = ParcelIncident::whereIn('incident_type', ['wrong_hub', 'missing', 'damaged'])
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('hub_id')
            ->select('hub_id', 'incident_type', DB::raw('COUNT(*) as incident_count'))
            ->groupBy('hub_id', 'incident_type')
            ->having('incident_count', '>=', 3)
            ->get();

        foreach ($incidentHotspots as $row) {
            $created[] = $this->upsertOpenAlert(
                ruleCode: 'PARCEL_INCIDENT_CLUSTER',
                title: 'Elevated ' . str_replace('_', ' ', $row->incident_type) . ' incidents at hub',
                severity: $row->incident_type === 'missing' ? 'high' : 'medium',
                entityType: \App\Models\Hub::class,
                entityId: (int) $row->hub_id,
                userId: null,
                supportingData: [
                    'hub_id' => (int) $row->hub_id,
                    'incident_type' => $row->incident_type,
                    'count_30d' => (int) $row->incident_count,
                ]
            );
        }

        // Rule 7: Unusual dispute/refund frequency per buyer (>= 3 disputes in 30d)
        $buyerDisputes = Dispute::where('created_at', '>=', now()->subDays(30))
            ->select('raised_by', DB::raw('COUNT(*) as dispute_count'))
            ->groupBy('raised_by')
            ->having('dispute_count', '>=', 3)
            ->get();

        foreach ($buyerDisputes as $row) {
            $created[] = $this->upsertOpenAlert(
                ruleCode: 'UNUSUAL_REFUND_DISPUTE_FREQUENCY',
                title: 'Unusual dispute/refund frequency (30d)',
                severity: 'medium',
                entityType: User::class,
                entityId: (int) $row->raised_by,
                userId: (int) $row->raised_by,
                supportingData: ['disputes_30d' => (int) $row->dispute_count]
            );
        }

        $newlyCreated = array_values(array_filter($created));

        if ($actor && count($newlyCreated) > 0) {
            AuditLog::log(
                action: 'risk_rules_scan_executed',
                auditable: $actor,
                oldValues: null,
                newValues: ['alerts_created' => count($newlyCreated)],
                reason: 'Admin executed rules-based fraud & risk anomaly scan',
                operatorId: $actor->id
            );
        }

        return $newlyCreated;
    }

    private function upsertOpenAlert(
        string $ruleCode,
        string $title,
        string $severity,
        string $entityType,
        ?int $entityId,
        ?int $userId,
        array $supportingData
    ): ?RiskAlert {
        $existing = RiskAlert::where('rule_code', $ruleCode)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereIn('status', ['open', 'investigating', 'escalated'])
            ->first();

        if ($existing) {
            $existing->update(['supporting_data' => $supportingData]);
            return null;
        }

        return RiskAlert::create([
            'rule_code' => $ruleCode,
            'title' => $title,
            'severity' => $severity,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'supporting_data' => $supportingData,
            'status' => 'open',
            'detected_at' => now(),
        ]);
    }
}
