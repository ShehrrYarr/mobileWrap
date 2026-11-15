<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

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

            $txn = $this->finance->record($account, 'expense', 0, (float) $data['amount'], $expense, null, $expense->category->name.($expense->note ? ' — '.$expense->note : ''), $data['date']);
            $expense->update(['financial_transaction_id' => $txn->id]);

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
}
