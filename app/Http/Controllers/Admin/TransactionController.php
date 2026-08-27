<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = Transaction::with('user')
            // when() passes the CONDITION to the callback. Testing a boolean
            // here meant $s was `true` and the filter matched nothing.
            ->when($request->status === 'All' ? null : $request->status,
                fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.transactions', [
            'transactions' => $transactions,
            'status'       => $request->status ?? 'All',
            'summary'      => [
                'revenue' => (float) Transaction::where('status', 'Paid')->sum('amount'),
                'paid'    => Transaction::where('status', 'Paid')->count(),
                'pending' => Transaction::where('status', 'Pending')->count(),
            ],
        ]);
    }
}
