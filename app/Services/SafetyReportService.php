<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SafetyReport;
use App\Models\SafetyReportNote;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SafetyReportService
{
    public function submitReport(User $reporter, array $data, array $evidenceFiles = []): SafetyReport
    {
        return DB::transaction(function () use ($reporter, $data, $evidenceFiles) {
            $storedPaths = [];
            foreach ($evidenceFiles as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    // Store privately on 'local' disk (storage/app/private/safety_evidence)
                    $storedPaths[] = $file->store('safety_evidence/' . date('Y/m'), 'local');
                }
            }

            $category = (string) ($data['category'] ?? 'other_violation');
            $priority = $data['priority'] ?? match ($category) {
                'fraud_scam', 'parcel_theft_tampering', 'payment_remittance_dispute' => 'high',
                'counterfeit_product', 'harassment_abuse', 'suspicious_account' => 'medium',
                default => 'medium',
            };

            $report = SafetyReport::create([
                'reference_number' => $this->generateReferenceNumber(),
                'reporter_id' => $reporter->id,
                'reporter_role' => $reporter->role,
                'category' => $category,
                'subject_type' => $data['subject_type'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'reported_user_id' => $data['reported_user_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'parcel_incident_id' => $data['parcel_incident_id'] ?? null,
                'dispute_id' => $data['dispute_id'] ?? null,
                'title' => trim((string) ($data['title'] ?? SafetyReport::CATEGORIES[$category] ?? 'Safety Report')),
                'description' => trim((string) $data['description']),
                'evidence_paths' => $storedPaths ?: null,
                'status' => 'new',
                'priority' => $priority,
            ]);

            AuditLog::log(
                action: 'safety_report_submitted',
                auditable: $report,
                oldValues: null,
                newValues: [
                    'reference_number' => $report->reference_number,
                    'category' => $report->category,
                    'reporter_role' => $report->reporter_role,
                    'priority' => $report->priority,
                ],
                reason: "Safety report {$report->reference_number} submitted by {$reporter->roleLabel()}",
                operatorId: $reporter->id
            );

            $admins = User::where('role', 'admin')->where('account_status', 'active')->get();
            foreach ($admins as $admin) {
                $admin->notify(new SystemNotification(
                    title: "New Safety Report ({$report->reference_number})",
                    message: "{$reporter->full_name} ({$reporter->roleLabel()}) submitted: {$report->category_label}.",
                    actionUrl: route('admin.safety.show', $report),
                    category: 'safety_report'
                ));
            }

            return $report->fresh();
        });
    }

    public function updateCase(
        SafetyReport $report,
        User $admin,
        array $data
    ): SafetyReport {
        return DB::transaction(function () use ($report, $admin, $data) {
            $old = [
                'status' => $report->status,
                'priority' => $report->priority,
                'assigned_admin_id' => $report->assigned_admin_id,
            ];

            $newStatus = $data['status'] ?? $report->status;
            $updates = [
                'status' => $newStatus,
                'priority' => $data['priority'] ?? $report->priority,
                'assigned_admin_id' => $data['assigned_admin_id'] ?? $report->assigned_admin_id,
            ];

            if (! empty($data['resolution_summary'])) {
                $updates['resolution_summary'] = $data['resolution_summary'];
            }
            if (! empty($data['closed_reason'])) {
                $updates['closed_reason'] = $data['closed_reason'];
            }
            if ($newStatus === 'resolved' && ! $report->resolved_at) {
                $updates['resolved_at'] = now();
            }
            if ($newStatus === 'closed' && ! $report->closed_at) {
                $updates['closed_at'] = now();
            }

            $report->update($updates);

            if (! empty($data['public_message'])) {
                SafetyReportNote::create([
                    'safety_report_id' => $report->id,
                    'author_id' => $admin->id,
                    'is_internal' => false,
                    'note' => trim((string) $data['public_message']),
                ]);
            }

            AuditLog::log(
                action: 'safety_report_updated',
                auditable: $report,
                oldValues: $old,
                newValues: [
                    'status' => $report->status,
                    'priority' => $report->priority,
                    'assigned_admin_id' => $report->assigned_admin_id,
                ],
                reason: $data['closed_reason'] ?? $data['resolution_summary'] ?? "Updated case status to {$report->status}",
                operatorId: $admin->id
            );

            if ($report->reporter && $old['status'] !== $report->status) {
                $statusLabel = ucwords(str_replace('_', ' ', $report->status));
                $report->reporter->notify(new SystemNotification(
                    title: "Safety Report {$report->reference_number} Updated",
                    message: "Your report status is now: {$statusLabel}." . (! empty($data['public_message']) ? " Note: {$data['public_message']}" : ''),
                    actionUrl: null,
                    category: 'safety_report'
                ));
            }

            return $report->fresh(['reporter', 'reportedUser', 'assignedAdmin', 'notes.author']);
        });
    }

    public function addNote(SafetyReport $report, User $author, string $note, bool $isInternal = true): SafetyReportNote
    {
        $record = SafetyReportNote::create([
            'safety_report_id' => $report->id,
            'author_id' => $author->id,
            'is_internal' => $isInternal,
            'note' => trim($note),
        ]);

        AuditLog::log(
            action: $isInternal ? 'safety_report_internal_note_added' : 'safety_report_public_note_added',
            auditable: $report,
            oldValues: null,
            newValues: ['note_id' => $record->id, 'is_internal' => $isInternal],
            reason: $isInternal ? 'Internal investigation note added' : 'Case update sent to reporter',
            operatorId: $author->id
        );

        if (! $isInternal && $report->reporter && (int) $report->reporter_id !== (int) $author->id) {
            $report->reporter->notify(new SystemNotification(
                title: "Update on Report {$report->reference_number}",
                message: trim($note),
                actionUrl: null,
                category: 'safety_report'
            ));
        }

        return $record;
    }

    private function generateReferenceNumber(): string
    {
        do {
            $ref = 'RPT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (SafetyReport::where('reference_number', $ref)->exists());

        return $ref;
    }
}
