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

    /**
     * Correct a ledger row in place (amount, account, date, note), e.g. when a saved expense is edited.
     * Both accounts' balances move by the difference, and so does `balance_after` on every later row,
     * so the ledger reads as if the entry had been right from the start.
     */
    public function amend(FinancialTransaction $txn, BankAccount|CashDrawer $account, float $debit, float $credit, $date = null, ?string $note = null): FinancialTransaction
    {
        return DB::transaction(function () use ($txn, $account, $debit, $credit, $date, $note) {
            $txn = FinancialTransaction::query()->lockForUpdate()->findOrFail($txn->getKey());
            $oldNet = (float) $txn->debit - (float) $txn->credit;
            $newNet = round($debit - $credit, 2);
            $moving = $txn->account_type !== $account->getMorphClass() || (int) $txn->account_id !== (int) $account->getKey();

            if ($moving) {
                // Take the row off its old account…
                if ($old = $txn->account) {
                    $this->shiftBalances($old, $txn->id, -$oldNet);
                }
                // …and slot it into the new one at the same place in posting order.
                $this->shiftBalances($account, $txn->id, $newNet);
                $before = FinancialTransaction::where('account_type', $account->getMorphClass())->where('account_id', $account->getKey())
                    ->where('id', '<', $txn->id)->orderByDesc('id')->value('balance_after');
                $txn->forceFill([
                    'account_type' => $account->getMorphClass(),
                    'account_id' => $account->getKey(),
                    'balance_after' => round((float) $before + $newNet, 2),
                ]);
            } else {
                $this->shiftBalances($account, $txn->id, $newNet - $oldNet);
                $txn->balance_after = round((float) $txn->balance_after + $newNet - $oldNet, 2);
            }

            $txn->fill(['debit' => $debit, 'credit' => $credit, 'date' => $date ?? $txn->date, 'note' => $note ?? $txn->note])->save();

            return $txn;
        });
    }

    /** Add $delta to an account's balance and to `balance_after` on its rows posted after $afterId. */
    protected function shiftBalances(BankAccount|CashDrawer $account, int $afterId, float $delta): void
    {
        $delta = round($delta, 2);
        if (abs($delta) < 0.005) {
            return;
        }

        $locked = $account->newQuery()->lockForUpdate()->find($account->getKey());
        $locked->current_balance = round((float) $locked->current_balance + $delta, 2);
        $locked->save();
        $account->current_balance = $locked->current_balance;

        DB::table('financial_transactions')
            ->where('account_type', $locked->getMorphClass())->where('account_id', $locked->getKey())
            ->where('id', '>', $afterId)
            ->increment('balance_after', $delta);
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
            $options['bank:'.$b->id] = $b->bank_name.' …'.self::lastDigits($b->account_number).' ('.money($b->current_balance).')';
        }

        return $options;
    }

    /** Short name for lists: "Cash In Hand" (or "Cash — Back Office" when there are several drawers) / "Meezan Bank …7890". */
    public static function accountLabel(BankAccount|CashDrawer|null $account): string
    {
        if (! $account) {
            return '—';
        }
        if ($account instanceof CashDrawer) {
            return once(fn () => CashDrawer::count() > 1) ? 'Cash — '.$account->name : 'Cash In Hand';
        }

        return $account->bank_name.' …'.self::lastDigits($account->account_number);
    }

    /** Every account (inactive ones too, for filtering history): ['cash:1' => 'Cash In Hand', 'bank:2' => 'Meezan Bank …7890'] */
    public static function accountList(): array
    {
        $drawers = CashDrawer::orderBy('id')->get();
        $out = [];
        foreach ($drawers as $d) {
            $out['cash:'.$d->id] = $drawers->count() > 1 ? 'Cash — '.$d->name : 'Cash In Hand';
        }
        foreach (BankAccount::orderByDesc('is_active')->orderBy('bank_name')->get() as $b) {
            $out['bank:'.$b->id] = $b->bank_name.' …'.self::lastDigits($b->account_number).($b->is_active ? '' : ' (closed)');
        }

        return $out;
    }

    /** Last four digits of an account number ("0123-4567891-03" → "9103"). */
    public static function lastDigits(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return substr($digits !== '' ? $digits : (string) $number, -4);
    }

    /** "cash:1" → ['App\Models\CashDrawer', 1] for filtering `account_type` / `account_id` columns. */
    public static function accountColumns(?string $key): ?array
    {
        if (! $key || ! preg_match('/^(cash|bank):(\d+)$/', $key, $m)) {
            return null;
        }

        return [($m[1] === 'cash' ? new CashDrawer : new BankAccount)->getMorphClass(), (int) $m[2]];
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
