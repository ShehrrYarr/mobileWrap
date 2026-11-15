<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\FinancialTransaction;
use App\Models\Vendor;
use App\Support\Statement;
use Illuminate\Http\Request;

/** Printable ledger statements (customer khata, vendor, bank/cash) and khata payment receipts. */
class StatementController extends Controller
{
    public function show(Request $request, string $type, string $id)
    {
        $user = $request->user();
        abort_unless($type === 'customer' ? $user->can('customers.manage') : $user->can('finance.manage'), 403);

        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        $statement = match ($type) {
            'customer' => Statement::forCustomer(Customer::withTrashed()->findOrFail($id), $from, $to),
            'vendor' => Statement::forVendor(Vendor::findOrFail($id), $from, $to),
            'account' => Statement::forAccount($this->account($id), $from, $to),
        };

        return view('admin.print.statement', ['s' => $statement, 'autoprint' => $request->boolean('autoprint', true)]);
    }

    public function paymentReceipt(Request $request, CustomerTransaction $entry)
    {
        abort_unless($request->user()->can('customers.manage') || $request->user()->can('pos.use'), 403);
        abort_unless($entry->type === 'payment', 404);

        $entry->load('customer', 'user');
        $txn = $entry->financial_transaction_id ? FinancialTransaction::with('account')->find($entry->financial_transaction_id) : null;

        return view('admin.print.payment-receipt', [
            'entry' => $entry,
            'account' => $txn?->account,
            'autoprint' => $request->boolean('autoprint', true),
        ]);
    }

    /** "cash-1" / "bank-2" (dash keeps the URL clean). */
    protected function account(string $id): BankAccount|CashDrawer
    {
        [$kind, $key] = array_pad(explode('-', $id, 2), 2, null);

        return $kind === 'cash' ? CashDrawer::findOrFail($key) : BankAccount::findOrFail($key);
    }
}
