<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function buyer(): View
    {
        return view('buyer.dashboard.index');
    }

    public function seller(): View
    {
        return view('seller.dashboard');
    }

    public function logistics(): View
    {
        return view('logistics.dashboard');
    }

    public function admin(): View
    {
        // Per the ERP spec, the admin approves buyer / seller / logistics
        // applications. Couriers wait on the Logistics/Sorting Center's
        // approval instead, so they're deliberately excluded here and
        // surface on the logistics dashboard's rider management instead.
        $pending = User::where('approval_status', 'pending')
            ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
            ->with(['sellerProfile', 'sortingCenterProfile'])
            ->latest()
            ->get();

        return view('admin.dashboard', compact('pending'));
    }
}
