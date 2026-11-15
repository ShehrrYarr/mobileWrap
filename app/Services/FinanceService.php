<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Posts money in/out of bank accounts and the cash drawer with running balances. */
class FinanceService
{
    public function record(
        BankAccount|CashDrawer $account,
        string $type,
        float $debit,
        float $credit = 0,
        ?Model $reference = null,
        ?Model $party = null,
        ?string $note = null,
        $date = null,
    ): FinancialTransaction {
        return DB::transaction(function () use ($account, $type, $debit, $credit, $reference, $party, $note, $date) {
            $locked = $account->newQuery()->lockForUpdate()->find($account->getKey());
            $locked->current_balance = round((float) $locked->current_balance + $debit - $credit, 2);
            $locked->save();
            $account->current_balance = $locked->current_balance;

            return FinancialTransaction::create([
                'account_type' => $locked->getMorphClass(),
                'account_id' => $locked->getKey(),
                'type' => $type,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $locked->current_balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'party_type' => $party?->getMorphClass(),
                'party_id' => $party?->getKey(),
                'date' => $date ?? now()->toDateString(),
                'note' => $note,
                'user_id' => auth('web')->id(),
            ]);
        });
    }

    /** Opening balance entry when an account is created. */
    public function opening(BankAccount|CashDrawer $account, float $amount): ?FinancialTransaction
    {
        if ($amount == 0.0) {
            return null;
        }

        $account->forceFill(['current_balance' => 0])->save();

        return $this->record($account, 'opening', max($amount, 0), max(-$amount, 0), null, null, 'Opening balance');
    }
}
