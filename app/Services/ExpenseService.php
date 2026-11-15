<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Expense;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Shop expenses paid from a bank account or the cash drawer. */
class ExpenseService
{
    public function __construct(protected FinanceService $finance) {}

    public function create(array $data, BankAccount|CashDrawer $account, ?string $attachment = null): Expense
    {
        FinanceService::ensureFunds($account, (float) $data['amount']);

        return DB::transaction(function () use ($data, $account, $attachment) {
            $expense = Expense::create([
                'expense_category_id' => $data['expense_category_id'],
                'account_type' => $account->getMorphClass(),
                'account_id' => $account->getKey(),
                'amount' => (float) $data['amount'],
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
                'attachment' => $attachment,
                'user_id' => auth('web')->id(),
            ]);
            $expense->load('category');

            $txn = $this->finance->record($account, 'expense', 0, (float) $data['amount'], $expense, null, $this->ledgerNote($expense), $data['date']);
            $expense->update(['financial_transaction_id' => $txn->id]);

            return $expense;
        });
    }

    /**
     * Correct a saved expense. Its ledger entry is changed in place: a new amount moves the
     * difference on the account, a new account moves the whole amount from the old one to the
     * new one, and a new date moves the entry to that day in the ledger.
     *
     * @param  string|null  $attachment  newly uploaded receipt path (replaces the old one)
     */
    public function update(Expense $expense, array $data, BankAccount|CashDrawer $account, ?string $attachment = null, bool $removeAttachment = false): Expense
    {
        $amount = round((float) $data['amount'], 2);
        $sameAccount = $expense->account_type === $account->getMorphClass() && (int) $expense->account_id === (int) $account->getKey();

        // Cash can't go below zero: only the extra money taken from the drawer has to be there.
        $extra = $sameAccount ? $amount - (float) $expense->amount : $amount;
        if ($extra > 0.009) {
            FinanceService::ensureFunds($account, $extra);
        }

        return DB::transaction(function () use ($expense, $data, $account, $amount, $attachment, $removeAttachment) {
            $oldFile = $expense->attachment;

            $expense->fill([
                'expense_category_id' => $data['expense_category_id'],
                'account_type' => $account->getMorphClass(),
                'account_id' => $account->getKey(),
                'amount' => $amount,
                'date' => $data['date'],
                'note' => ($data['note'] ?? '') !== '' ? $data['note'] : null,
            ]);
            if ($attachment) {
                $expense->attachment = $attachment;
            } elseif ($removeAttachment) {
                $expense->attachment = null;
            }

            if (! $expense->isDirty()) {
                return $expense;
            }

            $expense->forceFill(['edited_by' => auth('web')->id(), 'edited_at' => now()])->save();
            $expense->load('category');

            $txn = $expense->financial_transaction_id ? FinancialTransaction::find($expense->financial_transaction_id) : null;
            if ($txn) {
                $this->finance->amend($txn, $account, 0, $amount, $data['date'], $this->ledgerNote($expense));
            } else {
                // Older expense whose ledger row is missing: post it now.
                $txn = $this->finance->record($account, 'expense', 0, $amount, $expense, null, $this->ledgerNote($expense), $data['date']);
                $expense->update(['financial_transaction_id' => $txn->id]);
            }

            if ($oldFile && $oldFile !== $expense->attachment) {
                DB::afterCommit(fn () => Storage::disk('uploads')->delete($oldFile));
            }

            return $expense;
        });
    }

    /** Remove an expense and put the money back on its account (logged as an adjustment). */
    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $expense->load('account', 'category');
            if ($expense->account) {
                $this->finance->record($expense->account, 'adjustment', (float) $expense->amount, 0, null, null, 'Expense deleted: '.$expense->category?->name.' '.money($expense->amount));
            }
            $expense->delete();
        });
    }

    protected function ledgerNote(Expense $expense): string
    {
        return $expense->category->name.($expense->note ? ' — '.$expense->note : '');
    }
}
