<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Running balances in date order.
 *
 * `balance_after` on ledger rows follows posting order, so a back-dated entry
 * (e.g. a bill received last week but entered today) would show balances out of
 * sequence when the ledger is sorted by date. This wraps the ledger in a window
 * query that adds `running_balance` computed by (date, id) instead.
 */
class Ledger
{
    /**
     * @param  string  $expr  'debit - credit' (customer, bank/cash) or 'credit - debit' (vendor)
     * @param  float  $current  The party/account's current balance, used to absorb any amount
     *                          not explained by ledger rows (normally 0).
     * @param  string|null  $partition  e.g. 'account_type, account_id' when listing several accounts at once
     */
    public static function running(Relation|Builder $base, string $expr, ?float $current = null, ?string $partition = null): Builder
    {
        $query = $base instanceof Relation ? $base->getQuery() : $base;
        $model = $query->getModel();
        $table = $model->getTable();

        $offset = 0.0;
        if ($current !== null) {
            $sum = (float) $query->clone()->reorder()->selectRaw("COALESCE(SUM({$expr}), 0) s")->value('s');
            $offset = round($current - $sum, 2);
        }

        $over = ($partition ? "PARTITION BY {$partition} " : '').'ORDER BY `date`, `id`';
        $inner = $query->clone()->reorder()->toBase()
            ->select("{$table}.*")
            ->selectRaw("ROUND(SUM({$expr}) OVER ({$over}) + ?, 2) AS running_balance", [$offset]);

        return $model->newQuery()->fromSub($inner, $table);
    }
}
