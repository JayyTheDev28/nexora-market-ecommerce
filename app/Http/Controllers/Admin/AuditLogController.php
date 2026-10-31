<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->query('action');
        $operatorId = $request->query('operator_id');
        $search = $request->query('q');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = AuditLog::query()->with('operator');

        if ($action) {
            $query->where('action', $action);
        }

        if ($operatorId) {
            $query->where('operator_id', $operatorId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('operator', fn ($oq) => $oq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $logs = $query->latest('created_at')->paginate(20)->withQueryString();

        $actions = AuditLog::select('action')->distinct()->pluck('action')->all();
        $operators = User::whereIn('id', AuditLog::select('operator_id')->distinct()->pluck('operator_id'))->get();

        return view('admin.audit_logs.index', compact('logs', 'actions', 'operators', 'action', 'operatorId', 'search', 'startDate', 'endDate'));
    }
}
