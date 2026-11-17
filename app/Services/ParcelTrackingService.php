<?php

namespace App\Services;

use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\TransferManifestItem;

class ParcelTrackingService
{
    /**
     * Build unified, buyer-safe parcel tracking timeline from real database milestones only.
     * Never invents fake scans or exposes internal notes, staff names, payouts, or liabilities.
     */
    public function buildBuyerTrackingSummary(Order $order): array
    {
        $order->loadMissing([
            'statusHistory',
            'originHub',
            'destinationHub',
            'currentHub',
            'reroutedFromHub',
            'transfers.senderHub',
            'transfers.receiverHub',
            'manifestItems.manifest.originHub',
            'manifestItems.manifest.destinationHub',
            'incidents',
            'proofOfDelivery',
        ]);

        $milestones = [];

        foreach ($order->statusHistory->sortBy([['changed_at', 'asc'], ['id', 'asc']]) as $history) {
            if (! $history->changed_at) {
                continue;
            }
            $milestones[] = [
                'timestamp' => $history->changed_at->toIso8601String(),
                'label' => $this->formatStatusLabel((string) $history->status),
                'hub' => null,
                'detail' => $this->sanitizeStatusNote($history->note, (string) $history->status),
                'type' => 'order_status',
            ];
        }

        // Real hub transfer & manifest leg milestones (departed, arrived, received)
        foreach ($order->manifestItems as $item) {
            $manifest = $item->manifest;
            if (! $manifest) {
                continue;
            }
            $origin = $manifest->originHub?->name ?? 'Origin Hub';
            $dest = $manifest->destinationHub?->name ?? 'Destination Hub';

            if ($item->loaded_at) {
                $milestones[] = [
                    'timestamp' => $item->loaded_at->toIso8601String(),
                    'label' => 'Loaded for Hub Transfer',
                    'hub' => $origin,
                    'detail' => "Parcel loaded on transfer {$manifest->manifest_number} at {$origin}.",
                    'type' => 'manifest_loaded',
                ];
            }

            if ($manifest->dispatched_at) {
                $milestones[] = [
                    'timestamp' => $manifest->dispatched_at->toIso8601String(),
                    'label' => "Departed {$origin}",
                    'hub' => $origin,
                    'detail' => "In transit from {$origin} to {$dest}.",
                    'type' => 'manifest_departed',
                ];
            }

            if ($manifest->arrived_at) {
                $milestones[] = [
                    'timestamp' => $manifest->arrived_at->toIso8601String(),
                    'label' => "Arrived at {$dest}",
                    'hub' => $dest,
                    'detail' => "Transfer vehicle arrived at {$dest}.",
                    'type' => 'manifest_arrived',
                ];
            }

            if ($item->receiving_status === TransferManifestItem::RECEIVING_RECEIVED && $item->received_at) {
                $milestones[] = [
                    'timestamp' => $item->received_at->toIso8601String(),
                    'label' => "Received at {$dest}",
                    'hub' => $dest,
                    'detail' => "Parcel verified at {$dest}.",
                    'type' => 'manifest_received',
                ];
            }
        }

        // Non-manifest HubTransfer legs (e.g. direct transfers or wrong-hub reroute legs)
        foreach ($order->transfers as $transfer) {
            if ($transfer->manifest_id !== null) {
                continue;
            }
            $fromHub = $transfer->senderHub?->name ?? 'Origin Hub';
            $toHub = $transfer->receiverHub?->name ?? 'Destination Hub';

            if ($transfer->dispatched_at) {
                $milestones[] = [
                    'timestamp' => $transfer->dispatched_at->toIso8601String(),
                    'label' => "Transfer Dispatched from {$fromHub}",
                    'hub' => $fromHub,
                    'detail' => "En route from {$fromHub} to {$toHub}.",
                    'type' => 'hub_transfer_dispatched',
                ];
            }
            if ($transfer->received_at && $transfer->status === 'received') {
                $milestones[] = [
                    'timestamp' => $transfer->received_at->toIso8601String(),
                    'label' => "Received at {$toHub}",
                    'hub' => $toHub,
                    'detail' => "Arrived and received at {$toHub}.",
                    'type' => 'hub_transfer_received',
                ];
            }
        }

        // Sanitized buyer-safe exception milestones (no internal notes, staff names, or financial amounts)
        $latestIncident = $order->incidents->sortByDesc('id')->first();
        $buyerExceptions = [];
        foreach ($order->incidents->sortBy('id') as $incident) {
            $safe = $incident->toBuyerSafeArray();
            $buyerExceptions[] = $safe;

            if ($incident->reported_at) {
                $milestones[] = [
                    'timestamp' => $incident->reported_at->toIso8601String(),
                    'label' => $safe['badge'],
                    'hub' => $incident->hub?->name,
                    'detail' => $safe['message'],
                    'type' => 'exception_notice',
                ];
            }
            if ($incident->found_at) {
                $milestones[] = [
                    'timestamp' => $incident->found_at->toIso8601String(),
                    'label' => 'Parcel Located',
                    'hub' => null,
                    'detail' => 'Parcel Located — preparing next hub handling step.',
                    'type' => 'exception_located',
                ];
            }
            if ($incident->resolved_at && $incident->isResolved()) {
                $milestones[] = [
                    'timestamp' => $incident->resolved_at->toIso8601String(),
                    'label' => $safe['badge'],
                    'hub' => null,
                    'detail' => $safe['message'],
                    'type' => 'exception_resolved',
                ];
            }
        }

        usort($milestones, fn ($a, $b) => strcmp((string) $a['timestamp'], (string) $b['timestamp']));

        $latestSafe = $latestIncident ? $latestIncident->toBuyerSafeArray() : null;
        $hasActiveException = $order->incidents->contains(fn (ParcelIncident $inc) => $inc->isUnresolved());

        return [
            'current_status' => $order->status,
            'current_status_label' => $this->formatStatusLabel((string) $order->status),
            'has_active_exception' => $hasActiveException,
            'exception_badge' => $latestSafe['badge'] ?? null,
            'exception_message' => $latestSafe['message'] ?? null,
            'exception_reference' => $latestSafe['reference'] ?? null,
            'delivery_verified' => $order->delivered_at !== null || $order->proofOfDelivery !== null,
            'reroute_count' => (int) ($order->reroute_count ?? 0),
            'exceptions' => $buyerExceptions,
            'milestones' => array_values($milestones),
        ];
    }

    protected function formatStatusLabel(string $status): string
    {
        return match ($status) {
            'placed' => 'Order Placed',
            'confirmed' => 'Seller Confirmed',
            'preparing' => 'Preparing Parcel',
            'ready_for_pickup' => 'Ready for Pickup',
            'picked_up' => 'Picked Up by Logistics',
            'received_at_hub' => 'Received at Sorting Hub',
            'in_transit_hub' => 'In Transit Between Hubs',
            'at_sorting_center' => 'At Destination Hub',
            'sorted' => 'Sorted for Delivery',
            'assigned_to_rider' => 'Assigned to Delivery Rider',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    public function sanitizeStatusNote(?string $note, string $status): ?string
    {
        if (! $note) {
            return null;
        }

        $lower = strtolower($note);

        // Never leak internal staff/operator incident notes, damage descriptions, or internal incident IDs to buyers
        if (str_contains($note, 'INC-')
            || str_contains($lower, 'quarantine')
            || str_contains($lower, 'damaged parcel')
            || str_contains($lower, 'missing parcel')
            || str_contains($lower, 'discrepancy')
            || str_contains($lower, 'post-receipt')) {
            return 'Parcel is undergoing logistics quality inspection at sorting hub.';
        }

        if (str_contains($lower, 'reroute')) {
            return 'Parcel rerouted toward the designated sorting hub.';
        }

        return $note;
    }
}
