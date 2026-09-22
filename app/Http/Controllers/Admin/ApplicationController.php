<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ApplicationDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function approve(User $user): RedirectResponse
    {
        $user->update([
            'approval_status' => 'approved',
            'disapproval_reason' => null,
        ]);

        $user->notify(new ApplicationDecision(approved: true));

        return back()->with('status', "Approved {$user->full_name}. They've been notified by email.");
    }

    public function disapprove(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update([
            'approval_status' => 'disapproved',
            'disapproval_reason' => $validated['reason'] ?? null,
        ]);

        $user->notify(new ApplicationDecision(approved: false, reason: $validated['reason'] ?? null));

        return back()->with('status', "Declined {$user->full_name}. They've been notified by email.");
    }
}
