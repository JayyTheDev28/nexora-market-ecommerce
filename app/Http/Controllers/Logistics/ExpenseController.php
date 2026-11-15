<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Hub;
use App\Models\LogisticsExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->logisticsCompanyId();

        $query = LogisticsExpense::where('logistics_company_id', $companyId)
            ->with(['hub', 'recorder'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->string('from')->toString());
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->string('to')->toString());
        }

        $expenses = $query->paginate(20)->withQueryString();
        $hubs = Hub::where('logistics_company_id', $companyId)->orderBy('name')->get();
        $totalRecorded = (float) LogisticsExpense::where('logistics_company_id', $companyId)
            ->where('status', 'recorded')
            ->sum('amount');
        $monthRecorded = (float) LogisticsExpense::where('logistics_company_id', $companyId)
            ->where('status', 'recorded')
            ->where('expense_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('amount');

        return view('logistics.expenses.index', [
            'expenses' => $expenses,
            'hubs' => $hubs,
            'categories' => LogisticsExpense::CATEGORIES,
            'totalRecorded' => $totalRecorded,
            'monthRecorded' => $monthRecorded,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->logisticsCompanyId();

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(LogisticsExpense::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'hub_id' => ['nullable', 'integer', 'exists:hubs,id'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'receipt_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['hub_id'])) {
            $hub = Hub::find($validated['hub_id']);
            abort_unless($hub && (int) $hub->logistics_company_id === $companyId, 403, 'Selected hub does not belong to your logistics company.');
        }

        $receiptPath = $request->file('receipt_file')
            ? $request->file('receipt_file')->store('expenses/logistics', 'public')
            : null;

        $expense = LogisticsExpense::create([
            'expense_number' => 'EXP-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'logistics_company_id' => $companyId,
            'hub_id' => $validated['hub_id'] ?? null,
            'category' => $validated['category'],
            'description' => $validated['description'],
            'amount' => round((float) $validated['amount'], 2),
            'expense_date' => $validated['expense_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'receipt_path' => $receiptPath,
            'recorded_by' => $request->user()->id,
            'status' => 'recorded',
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::log('logistics_expense_recorded', $expense, null, $expense->toArray(), "Recorded expense {$expense->expense_number}", $request->user()->id);

        return back()->with('status', "Expense #{$expense->expense_number} (₱" . number_format((float) $expense->amount, 2) . ") recorded.");
    }

    public function void(Request $request, LogisticsExpense $expense): RedirectResponse
    {
        $companyId = $request->user()->logisticsCompanyId();
        abort_unless((int) $expense->logistics_company_id === $companyId, 403, 'Unauthorized expense access.');

        if ($expense->status === 'voided') {
            return back()->with('status', "Expense #{$expense->expense_number} is already voided.");
        }

        $expense->update(['status' => 'voided']);
        AuditLog::log('logistics_expense_voided', $expense, ['status' => 'recorded'], ['status' => 'voided'], 'Operator voided expense entry', $request->user()->id);

        return back()->with('status', "Expense #{$expense->expense_number} marked as voided.");
    }
}
