<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    public const RETURN_STAGE_REQUESTED = 'requested';
    public const RETURN_STAGE_APPROVED_AWAITING_RETURN = 'approved_awaiting_return';
    public const RETURN_STAGE_IN_RETURN_TRANSIT = 'in_return_transit';
    public const RETURN_STAGE_RECEIVED_FOR_INSPECTION = 'received_for_inspection';
    public const RETURN_STAGE_INSPECTED = 'inspected';
    public const RETURN_STAGE_REFUND_AUTHORIZED = 'refund_authorized';
    public const RETURN_STAGE_REFUND_RECORDED = 'refund_recorded';
    public const RETURN_STAGE_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'raised_by',
        'reason',
        'details',
        'status',
        'seller_status',
        'return_stage',
        'seller_response',
        'seller_response_at',
        'return_instructions',
        'return_tracking_number',
        'return_received_at',
        'return_received_by',
        'inspection_condition',
        'inspection_notes',
        'inspected_at',
        'inspected_by',
        'restock_approved',
        'restocked_at',
        'refund_amount',
        'refund_authorized_at',
        'refund_authorized_by',
        'refund_method',
        'refund_reference',
        'refund_recorded_at',
        'refund_recorded_by',
        'refund_notes',
        'resolution_note',
        'media_paths',
    ];

    protected function casts(): array
    {
        return [
            'media_paths' => 'array',
            'seller_response_at' => 'datetime',
            'return_received_at' => 'datetime',
            'inspected_at' => 'datetime',
            'restock_approved' => 'boolean',
            'restocked_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refund_authorized_at' => 'datetime',
            'refund_recorded_at' => 'datetime',
        ];
    }

    public function returnReceivedBy()
    {
        return $this->belongsTo(User::class, 'return_received_by');
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function refundAuthorizedBy()
    {
        return $this->belongsTo(User::class, 'refund_authorized_by');
    }

    public function refundRecordedBy()
    {
        return $this->belongsTo(User::class, 'refund_recorded_by');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}
