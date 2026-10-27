<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ApplicationDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $role = $request->query('role');
        $search = $request->query('q');

        $query = User::query()
            ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
            ->with(['sellerProfile', 'sortingCenterProfile']);

        if ($status !== 'all') {
            $query->where('approval_status', $status);
        }

        if ($role) {
            $query->where('role', $role);
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

        $applications = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'pending' => User::where('approval_status', 'pending')->whereIn('role', ['buyer', 'seller', 'sorting_center'])->count(),
            'approved' => User::where('approval_status', 'approved')->whereIn('role', ['buyer', 'seller', 'sorting_center'])->count(),
            'disapproved' => User::where('approval_status', 'disapproved')->whereIn('role', ['buyer', 'seller', 'sorting_center'])->count(),
        ];

        return view('admin.applications.index', compact('applications', 'status', 'role', 'search', 'counts'));
    }

    public function approve(User $user): RedirectResponse
    {
        $oldValues = [
            'approval_status' => $user->approval_status,
            'disapproval_reason' => $user->disapproval_reason,
        ];

        $user->update([
            'approval_status' => 'approved',
            'disapproval_reason' => null,
        ]);

        AuditLog::log(
            action: 'application_approved',
            auditable: $user,
            oldValues: $oldValues,
            newValues: ['approval_status' => 'approved', 'disapproval_reason' => null],
            reason: 'Account application approved by administrator'
        );

        $user->notify(new ApplicationDecision(approved: true));

        return back()->with('status', "Approved {$user->full_name}. They've been notified by email.");
    }

    public function disapprove(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $validated['reason'] ?? 'Application did not meet our requirements.';

        $oldValues = [
            'approval_status' => $user->approval_status,
            'disapproval_reason' => $user->disapproval_reason,
        ];

        $user->update([
            'approval_status' => 'disapproved',
            'disapproval_reason' => $reason,
        ]);

        AuditLog::log(
            action: 'application_declined',
            auditable: $user,
            oldValues: $oldValues,
            newValues: ['approval_status' => 'disapproved', 'disapproval_reason' => $reason],
            reason: $reason
        );

        $user->notify(new ApplicationDecision(approved: false, reason: $reason));

        return back()->with('status', "Declined {$user->full_name}. They've been notified by email.");
    }
}
