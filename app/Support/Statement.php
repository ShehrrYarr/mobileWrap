<?php

namespace App\Support;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * A ledger over a date range with opening / closing balances.
 *
 * Opening balance is worked back from the current balance, so accounts whose
 * opening amount was never posted as a transaction still reconcile.
 * $sign: +1 when "balance = debit − credit" (customer, bank/cash), −1 for vendors.
 */
class Statement
{
    public function __construct(
        public string $type,
        public string $title,
        public string $partyName,
        public array $partyLines,
        public string $balanceLabel,
        public string $debitLabel,
        public string $creditLabel,
        public ?string $from,
        public ?string $to,
        public float $opening,
        public float $closing,
        public float $totalDebit,
        public float $totalCredit,
        public Collection $rows,   // each: date, type, note, ref, debit, credit, balance
    ) {}

    public static function forCustomer(Customer $c, ?string $from, ?string $to): self
    {
        return self::build('customer', 'Customer statement', $c->name, array_filter([$c->phone, $c->email, $c->isWholesale() ? 'Wholesale customer' : null]),
            'Balance (receivable)', 'Sale / charge', 'Payment / return', $c->transactions(), (float) $c->current_balance, 1, $from, $to);
    }

    public static function forVendor(Vendor $v, ?string $from, ?string $to): self
    {
        return self::build('vendor', 'Vendor statement', $v->name, array_filter([$v->company, $v->phone, trim($v->address.' '.$v->city)]),
            'Balance (payable)', 'Paid / returned', 'Purchased', $v->transactions(), (float) $v->current_balance, -1, $from, $to);
    }

    public static function forAccount(BankAccount|CashDrawer $a, ?string $from, ?string $to): self
    {
        $lines = $a instanceof BankAccount ? array_filter([$a->account_title, 'A/C '.$a->account_number, $a->iban, $a->branch]) : ['Cash in hand'];

        return self::build('account', 'Account statement', $a instanceof BankAccount ? $a->bank_name : $a->name, $lines,
            'Balance', 'Money in', 'Money out', $a->transactions(), (float) $a->current_balance, 1, $from, $to);
    }

    protected static function build(string $type, string $title, string $name, array $lines, string $balanceLabel, string $dLabel, string $cLabel,
        Relation $relation, float $current, int $sign, ?string $from, ?string $to): self
    {
        $base = fn () => $relation->getQuery()->clone()->reorder();

        // Step back from today's balance over everything posted on/after the start date.
        // With no start date this leaves whatever isn't explained by transactions (normally 0).
        $after = $base()->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
        $opening = round($current - $sign * ((float) $after->d - (float) $after->c), 2);

        $entries = $base()->with('reference')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderBy('date')->orderBy('id')->get();

        $running = $opening;
        $rows = $entries->map(function ($e) use (&$running, $sign) {
            $running = round($running + $sign * ((float) $e->debit - (float) $e->credit), 2);

            return (object) [
                'date' => $e->date,
                'type' => str_replace('_', ' ', $e->type),
                'note' => $e->note,
                'ref' => $e->reference->number ?? null,
                'debit' => (float) $e->debit,
                'credit' => (float) $e->credit,
                'balance' => $running,
            ];
        });

        return new self($type, $title, $name, array_values($lines), $balanceLabel, $dLabel, $cLabel, $from, $to,
            $opening, $running, (float) $entries->sum('debit'), (float) $entries->sum('credit'), $rows);
    }
}
