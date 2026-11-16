<?php

namespace App\Services;

class AdminPermissionService
{
    public const PERMISSIONS = [
        'view_financials' => 'View financial monitoring & reconciliation',
        'verify_remittances' => 'Verify Logistics-to-Admin COD remittances',
        'approve_payouts' => 'Approve Seller payout requests',
        'disburse_payouts' => 'Record Seller payout disbursement',
        'review_reports' => 'Review & resolve Safety & Reports Center cases',
        'manage_users' => 'Suspend, restrict, and review user appeals',
        'manage_shipping_policies' => 'Approve or reject shipping rate policies',
        'manage_logistics' => 'Monitor logistics & escalate parcel incidents',
        'manage_security' => 'Manage platform settings, 2FA, and system health',
    ];
}
