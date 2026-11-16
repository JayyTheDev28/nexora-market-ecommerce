<?php

namespace App\Services;

use App\Models\AccountRestriction;
use App\Models\AuditLog;
use App\Models\RestrictionAppeal;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\DB;

class AccountEnforcementService
{
    public function applyRestriction(
        User $targetUser,
        User $admin,
        string $restrictionType,
        string $reason,
        ?int $durationDays = null,
        ?int $safetyReportId = null
    ): AccountRestriction {
        return DB::transaction(function () use ($targetUser, $admin, $restrictionType, $reason, $durationDays, $safetyReportId) {
            $restriction = AccountRestriction::create([
                'user_id' => $targetUser->id,
                'restriction_type' => $restrictionType,
                'reason' => trim($reason),
                'safety_report_id' => $safetyReportId,
                'issued_by' => $admin->id,
                'starts_at' => now(),
                'expires_at' => $durationDays ? now()->addDays($durationDays) : null,
                'status' => 'active',
            ]);

            $oldAccountStatus = $targetUser->account_status;
            if ($restrictionType === 'suspension') {
                $targetUser->update([
                    'account_status' => 'suspended',
                    'status_reason' => trim($reason),
                ]);
                $targetUser->tokens()->delete();
                $targetUser->notify(new AccountStatusChangedNotification('suspended', trim($reason)));
            } else {
                $targetUser->notify(new SystemNotification(
                    title: "Account Restriction Applied: {$restriction->type_label}",
                    message: "Reason: {$reason}" . ($restriction->expires_at ? " (Until {$restriction->expires_at->toDateString()})" : ''),
                    actionUrl: null,
                    category: 'account_restriction'
                ));
            }

            AuditLog::log(
                action: 'account_restriction_applied',
                auditable: $targetUser,
                oldValues: ['account_status' => $oldAccountStatus],
                newValues: [
                    'restriction_id' => $restriction->id,
                    'restriction_type' => $restrictionType,
                    'expires_at' => $restriction->expires_at?->toIso8601String(),
                    'account_status' => $targetUser->account_status,
                ],
                reason: $reason,
                operatorId: $admin->id
            );

            return $restriction->fresh();
        });
    }

    public function liftRestriction(
        AccountRestriction $restriction,
        User $admin,
        string $liftReason
    ): AccountRestriction {
        return DB::transaction(function () use ($restriction, $admin, $liftReason) {
            $restriction->update([
                'status' => 'lifted',
                'lifted_at' => now(),
                'lifted_by' => $admin->id,
                'lift_reason' => trim($liftReason),
            ]);

            $targetUser = $restriction->user;
            if ($restriction->restriction_type === 'suspension' && $targetUser && $targetUser->account_status === 'suspended') {
                $hasOtherActiveSuspension = $targetUser->accountRestrictions()
                    ->where('id', '!=', $restriction->id)
                    ->where('restriction_type', 'suspension')
                    ->where('status', 'active')
                    ->exists();

                if (! $hasOtherActiveSuspension) {
                    $targetUser->update([
                        'account_status' => 'active',
                        'status_reason' => null,
                    ]);
                    $targetUser->notify(new AccountStatusChangedNotification('active', $liftReason));
                }
            } elseif ($targetUser) {
                $targetUser->notify(new SystemNotification(
                    title: "Restriction Lifted: {$restriction->type_label}",
                    message: "Your restriction has been lifted. Note: {$liftReason}",
                    actionUrl: null,
                    category: 'account_restriction'
                ));
            }

            AuditLog::log(
                action: 'account_restriction_lifted',
                auditable: $targetUser ?? $restriction,
                oldValues: ['restriction_status' => 'active'],
                newValues: ['restriction_id' => $restriction->id, 'restriction_status' => 'lifted'],
                reason: $liftReason,
                operatorId: $admin->id
            );

            return $restriction->fresh();
        });
    }

    public function submitAppeal(AccountRestriction $restriction, User $user, string $reason): RestrictionAppeal
    {
        abort_unless((int) $restriction->user_id === (int) $user->id, 403);

        $appeal = RestrictionAppeal::create([
            'account_restriction_id' => $restriction->id,
            'user_id' => $user->id,
            'reason' => trim($reason),
            'status' => 'pending',
        ]);

        AuditLog::log(
            action: 'restriction_appeal_submitted',
            auditable: $appeal,
            oldValues: null,
            newValues: ['restriction_id' => $restriction->id, 'status' => 'pending'],
            reason: $reason,
            operatorId: $user->id
        );

        return $appeal;
    }

    public function reviewAppeal(
        RestrictionAppeal $appeal,
        User $admin,
        string $decision,
        string $adminNotes
    ): RestrictionAppeal {
        return DB::transaction(function () use ($appeal, $admin, $decision, $adminNotes) {
            $oldStatus = $appeal->status;

            $appeal->update([
                'status' => $decision,
                'reviewed_by' => $admin->id,
                'admin_notes' => trim($adminNotes),
                'reviewed_at' => now(),
            ]);

            if ($decision === 'approved' && $appeal->restriction && $appeal->restriction->status === 'active') {
                $this->liftRestriction($appeal->restriction, $admin, "Appeal approved: {$adminNotes}");
            }

            if ($appeal->user) {
                $appeal->user->notify(new SystemNotification(
                    title: 'Restriction Appeal ' . ucfirst($decision),
                    message: "Your appeal for {$appeal->restriction?->type_label} was {$decision}. Note: {$adminNotes}",
                    actionUrl: null,
                    category: 'account_restriction'
                ));
            }

            AuditLog::log(
                action: 'restriction_appeal_reviewed',
                auditable: $appeal,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $decision],
                reason: $adminNotes,
                operatorId: $admin->id
            );

            return $appeal->fresh();
        });
    }
}
