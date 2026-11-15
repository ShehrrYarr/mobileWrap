<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Customer khata ledger. Debit = customer owes more (credit sale);
 * credit = customer owes less (payment, return). Balance > 0 means receivable.
 */
class CustomerLedgerService
{
    /** Khata collection: money in to cash/bank, customer owes less. */
    public function receivePayment(Customer $customer, \App\Models\BankAccount|\App\Models\CashDrawer $account, float $amount, ?string $note = null): CustomerTransaction
    {
        if ($amount <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        return DB::transaction(function () use ($customer, $account, $amount, $note) {
            $label = $note ?: 'Khata payment from '.$customer->name;
            $txn = app(FinanceService::class)->record($account, 'customer_payment', $amount, 0, null, $customer, $label);
            $entry = $this->record($customer, 'payment', 0, $amount, null, $label, $txn->id);
            $txn->update(['reference_type' => $entry->getMorphClass(), 'reference_id' => $entry->id]);

            return $entry;
        });
    }

    public function record(
        Customer $customer,
        string $type,
        float $debit,
        float $credit = 0,
        ?Model $reference = null,
        ?string $note = null,
        ?int $financialTransactionId = null,
    ): CustomerTransaction {
        return DB::transaction(function () use ($customer, $type, $debit, $credit, $reference, $note, $financialTransactionId) {
            $locked = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);
            $locked->current_balance = round((float) $locked->current_balance + $debit - $credit, 2);
            $locked->save();
            $customer->current_balance = $locked->current_balance;

            return CustomerTransaction::create([
                'customer_id' => $locked->id,
                'type' => $type,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $locked->current_balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'financial_transaction_id' => $financialTransactionId,
                'date' => now()->toDateString(),
                'note' => $note,
                'user_id' => auth('web')->id(),
            ]);
        });
    }
}
