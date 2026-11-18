<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->query('view') === 'plans') {
            return view('admin.plans', [
                'plans' => SubscriptionPlan::query()
                    ->withCount([
                        'transactions as paid_count'    => fn ($q) => $q->where('status', 'Paid'),
                        'transactions as pending_count' => fn ($q) => $q->where('status', 'Pending'),
                        'transactions as all_count',
                    ])
                    ->orderByDesc('is_active')->orderBy('price')->get(),
                'min' => PlanController::MIN_PRICE,
                'max' => PlanController::MAX_PRICE,
                'maxDays' => PlanController::MAX_DAYS,
            ]);
        }

        $transactions = Transaction::with('user')
            // when() passes the CONDITION to the callback. Testing a boolean
            // here meant $s was `true` and the filter matched nothing.
            ->when($request->status === 'All' ? null : $request->status,
                fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage = $this->perPage($request, 10))
            ->withQueryString();

        return view('admin.transactions', [
            'transactions' => $transactions,
            'perPage'      => $perPage,
            'status'       => $request->status ?? 'All',
            'summary'      => [
                'revenue' => (float) Transaction::where('status', 'Paid')->sum('amount'),
                'paid'    => Transaction::where('status', 'Paid')->count(),
                'pending' => Transaction::where('status', 'Pending')->count(),
            ],
        ]);
    }
}
