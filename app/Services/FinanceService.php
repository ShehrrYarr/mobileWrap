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

    /** Move money between two accounts (e.g. deposit cash into the bank). */
    public function transfer(BankAccount|CashDrawer $from, BankAccount|CashDrawer $to, float $amount, ?string $note = null, $date = null): array
    {
        self::ensureFunds($from, $amount);

        return DB::transaction(function () use ($from, $to, $amount, $note, $date) {
            $group = (string) \Illuminate\Support\Str::uuid();
            $label = fn ($a) => $a instanceof CashDrawer ? 'Cash In Hand' : $a->bank_name;

            $out = $this->record($from, 'transfer_out', 0, $amount, null, null, $note ?: 'Transfer to '.$label($to), $date);
            $in = $this->record($to, 'transfer_in', $amount, 0, null, null, $note ?: 'Transfer from '.$label($from), $date);
            $out->update(['transfer_group' => $group]);
            $in->update(['transfer_group' => $group]);

            return [$out, $in];
        });
    }

    /** Resolve "bank:3" / "cash:1" style keys used by account pickers. */
    public static function accountFromKey(?string $key): BankAccount|CashDrawer|null
    {
        if (! $key || ! str_contains($key, ':')) {
            return null;
        }
        [$kind, $id] = explode(':', $key, 2);

        return $kind === 'cash' ? CashDrawer::find($id) : BankAccount::find($id);
    }

    /** Options for account dropdowns: ['cash:1' => 'Cash In Hand (Main Counter)', 'bank:2' => 'Meezan Bank …7890'] */
    public static function accountOptions(): array
    {
        $options = [];
        foreach (CashDrawer::where('is_active', true)->get() as $d) {
            $options['cash:'.$d->id] = 'Cash In Hand — '.$d->name.' ('.money($d->current_balance).')';
        }
        foreach (BankAccount::active()->get() as $b) {
            $options['bank:'.$b->id] = $b->bank_name.' …'.substr($b->account_number, -4).' ('.money($b->current_balance).')';
        }

        return $options;
    }

    /** Cash can't go below zero — you can't hand over notes you don't have. Banks may overdraw. */
    public static function ensureFunds(BankAccount|CashDrawer $account, float $amount, string $field = 'amount'): void
    {
        $account->refresh();
        if ($account instanceof CashDrawer && $amount > (float) $account->current_balance + 0.009) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'Not enough cash in hand — the drawer has '.money($account->current_balance).'. Pay from a bank account or add money first.',
            ]);
        }
    }

    /** Pre-select cash when it covers the amount, otherwise the bank account with the most money. */
    public static function defaultAccountKey(float $amount = 0): ?string
    {
        $cash = CashDrawer::where('is_active', true)->orderBy('id')->first();
        if ($cash && (float) $cash->current_balance >= $amount) {
            return 'cash:'.$cash->id;
        }
        $bank = BankAccount::active()->orderByDesc('current_balance')->first();

        return $bank ? 'bank:'.$bank->id : ($cash ? 'cash:'.$cash->id : null);
    }

    /** Opening balance entry when an account is created. */
    public function opening(BankAccount|CashDrawer $account, float $amount, $date = null): ?FinancialTransaction
    {
        if ($amount == 0.0) {
            return null;
        }

        $account->forceFill(['current_balance' => 0])->save();

        return $this->record($account, 'opening', max($amount, 0), max(-$amount, 0), null, null, 'Opening balance', $date);
    }
}
