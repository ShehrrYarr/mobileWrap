<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Support\Period;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the Sales, Purchase and Profit & Loss reports.
 *
 * When something counts:
 *  - a sale counts on its completed_at: POS when paid, online when delivered (or completed);
 *    orders still in progress or cancelled are not sales;
 *  - a return counts on the day it is made, at the net price the customer paid;
 *  - cost of goods is the weighted-average cost saved on each sold line;
 *  - purchases count when goods are received (vendor ledger), not when ordered.
 */
class ReportService
{
    /** Orders that are (or were) sales. Returned ones stay sales; the return is booked separately. */
    public const SOLD_STATUSES = ['delivered', 'completed', 'returned', 'partially_returned'];

    protected const SALE_AT = 'COALESCE(orders.completed_at, orders.placed_at)';

    public const PAYMENT_LABELS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'customer_credit' => 'Khata (credit)',
        'exchange_credit' => 'Exchange credit',
        'cod' => 'Cash on delivery',
    ];

    // ── Sales ───────────────────────────────────────────────────────────

    /** Scope any query that has `orders` joined to sales in the period. */
    protected function soldIn(Builder $q, Period $p, ?string $channel = null): Builder
    {
        return $q->whereIn('orders.status', self::SOLD_STATUSES)
            ->whereRaw(self::SALE_AT.' BETWEEN ? AND ?', [$p->from, $p->to])
            ->when($channel, fn ($q) => $q->where('orders.channel', $channel));
    }

    protected function orders(Period $p, ?string $channel = null): Builder
    {
        return $this->soldIn(DB::table('orders'), $p, $channel);
    }

    protected function items(Period $p, ?string $channel = null): Builder
    {
        return $this->soldIn(DB::table('order_items as oi')->join('orders', 'orders.id', '=', 'oi.order_id'), $p, $channel);
    }

    protected function returnItems(Period $p, ?string $channel = null): Builder
    {
        return DB::table('order_return_items as ri')
            ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
            ->join('order_items as oi', 'oi.id', '=', 'ri.order_item_id')
            ->join('orders', 'orders.id', '=', 'r.order_id')
            ->whereBetween('r.created_at', [$p->from, $p->to])
            ->when($channel, fn ($q) => $q->where('orders.channel', $channel));
    }

    protected function dateKey(string $column, Period $p): string
    {
        return "DATE_FORMAT({$column}, '".($p->groupsByMonth() ? '%Y-%m' : '%Y-%m-%d')."')";
    }

    /** Headline numbers. All amounts in PKR. */
    public function salesSummary(Period $p, ?string $channel = null): array
    {
        $o = $this->orders($p, $channel)->selectRaw('COUNT(*) n, COALESCE(SUM(grand_total), 0) total,
            COALESCE(SUM(discount_total + coupon_discount), 0) order_discounts, COALESCE(SUM(shipping_fee), 0) delivery')->first();

        $i = $this->items($p, $channel)->selectRaw("
            COALESCE(SUM(CASE WHEN oi.item_type = 'product' THEN oi.quantity * oi.unit_price END), 0) gross,
            COALESCE(SUM(CASE WHEN oi.item_type = 'product' THEN oi.discount END), 0) line_discounts,
            COALESCE(SUM(CASE WHEN oi.item_type = 'product' THEN oi.quantity END), 0) units,
            COALESCE(SUM(CASE WHEN oi.item_type = 'product' THEN oi.quantity * oi.unit_cost END), 0) cogs,
            COALESCE(SUM(CASE WHEN oi.item_type = 'laser_service' THEN oi.total END), 0) laser,
            COALESCE(SUM(CASE WHEN oi.item_type = 'laser_service' THEN oi.quantity END), 0) laser_pieces")->first();

        $r = $this->returnItems($p, $channel)->selectRaw("COUNT(DISTINCT r.id) n, COALESCE(SUM(ri.amount), 0) amount,
            COALESCE(SUM(ri.quantity), 0) units,
            COALESCE(SUM(CASE WHEN ri.`condition` = 'restock' THEN ri.quantity * oi.unit_cost END), 0) restocked_cost")->first();

        $discounts = (float) $i->line_discounts + (float) $o->order_discounts;
        $returns = (float) $r->amount;
        $net = (float) $o->total - $returns;
        $goodsRevenue = (float) $i->gross - $discounts - $returns;     // products only, after discounts & returns
        $goodsCost = (float) $i->cogs - (float) $r->restocked_cost;     // restocked returns go back to stock

        return [
            'invoices' => (int) $o->n,
            'units' => (float) $i->units,
            'gross' => (float) $i->gross,
            'discounts' => $discounts,
            'laser' => (float) $i->laser,
            'laser_pieces' => (int) $i->laser_pieces,
            'delivery' => (float) $o->delivery,
            'sales_total' => (float) $o->total,
            'returns' => $returns,
            'returns_count' => (int) $r->n,
            'returned_units' => (float) $r->units,
            'net' => $net,
            'average' => $o->n ? (float) $o->total / (int) $o->n : 0.0,
            'goods_revenue' => $goodsRevenue,
            'goods_cost' => $goodsCost,
            'goods_margin' => $goodsRevenue - $goodsCost,
            'cogs' => (float) $i->cogs,
            'restocked_cost' => (float) $r->restocked_cost,
        ];
    }

    /** One row per day (or month for long ranges), including days with no sales. */
    public function salesByDay(Period $p, ?string $channel = null): array
    {
        $key = $this->dateKey(self::SALE_AT, $p);
        $orders = $this->orders($p, $channel)->selectRaw("{$key} k, COUNT(*) n, SUM(grand_total) total, SUM(discount_total + coupon_discount) disc")
            ->groupBy('k')->get()->keyBy('k');
        $lines = $this->items($p, $channel)->selectRaw("{$key} k, SUM(oi.discount) disc, SUM(CASE WHEN oi.item_type = 'product' THEN oi.quantity * oi.unit_cost END) cost")
            ->groupBy('k')->get()->keyBy('k');
        $returns = $this->returnItems($p, $channel)->selectRaw($this->dateKey('r.created_at', $p)." k, SUM(ri.amount) amount,
            SUM(CASE WHEN ri.`condition` = 'restock' THEN ri.quantity * oi.unit_cost END) cost")
            ->groupBy('k')->get()->keyBy('k');

        $rows = [];
        foreach ($p->buckets() as $k => $label) {
            $o = $orders[$k] ?? null;
            $total = (float) ($o->total ?? 0);
            $ret = (float) ($returns[$k]->amount ?? 0);
            $rows[] = [
                'key' => $k,
                'label' => $label,
                'invoices' => (int) ($o->n ?? 0),
                'sales' => $total,
                'discounts' => (float) ($o->disc ?? 0) + (float) ($lines[$k]->disc ?? 0),
                'returns' => $ret,
                'net' => $total - $ret,
                'cost' => (float) ($lines[$k]->cost ?? 0) - (float) ($returns[$k]->cost ?? 0),
            ];
        }

        return $rows;
    }

    public function salesByProduct(Period $p, ?string $channel = null, ?int $limit = null): array
    {
        return $this->items($p, $channel)->where('oi.item_type', 'product')
            ->groupByRaw('COALESCE(oi.product_variant_id, oi.name)')
            ->selectRaw('MAX(oi.name) name, MAX(oi.variant_label) label, MAX(oi.sku) sku, SUM(oi.quantity) qty, SUM(oi.returned_quantity) returned_qty,
                SUM(oi.total) amount, SUM(oi.quantity * oi.unit_cost) cost, COUNT(DISTINCT orders.id) invoices')
            ->orderByDesc('amount')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()->map(fn ($r) => (array) $r + ['profit' => (float) $r->amount - (float) $r->cost])->all();
    }

    public function salesByCategory(Period $p, ?string $channel = null): array
    {
        $rows = $this->items($p, $channel)->where('oi.item_type', 'product')
            ->leftJoin('products as pr', 'pr.id', '=', 'oi.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'pr.category_id')
            ->groupBy('c.id')
            ->selectRaw("COALESCE(MAX(c.name), 'Uncategorised') name, SUM(oi.quantity) qty, SUM(oi.total) amount, SUM(oi.quantity * oi.unit_cost) cost")
            ->orderByDesc('amount')->get()
            ->map(fn ($r) => (array) $r + ['profit' => (float) $r->amount - (float) $r->cost])->all();

        $laser = $this->items($p, $channel)->where('oi.item_type', 'laser_service')
            ->selectRaw('COALESCE(SUM(oi.quantity), 0) qty, COALESCE(SUM(oi.total), 0) amount')->first();
        if ((float) $laser->amount > 0) {
            // material for laser jobs is costed in the P&L (laser material used)
            $rows[] = ['name' => 'Laser cutting service', 'qty' => $laser->qty, 'amount' => $laser->amount, 'cost' => null, 'profit' => null];
        }

        return $rows;
    }

    public function salesByChannel(Period $p): array
    {
        return $this->orders($p)->selectRaw('channel, COUNT(*) n, SUM(grand_total) total')
            ->groupBy('channel')->orderByDesc('total')->get()
            ->map(fn ($r) => ['name' => $r->channel === 'pos' ? 'In store (POS)' : 'Online store', 'invoices' => (int) $r->n, 'amount' => (float) $r->total])->all();
    }

    public function salesByPayment(Period $p, ?string $channel = null): array
    {
        return $this->soldIn(DB::table('order_payments as op')->join('orders', 'orders.id', '=', 'op.order_id'), $p, $channel)
            ->where('op.status', '!=', 'rejected')
            ->selectRaw('op.method, COUNT(DISTINCT orders.id) n, SUM(op.amount) amount')
            ->groupBy('op.method')->orderByDesc('amount')->get()
            ->map(fn ($r) => ['name' => self::PAYMENT_LABELS[$r->method] ?? ucfirst(str_replace('_', ' ', $r->method)), 'invoices' => (int) $r->n, 'amount' => (float) $r->amount])->all();
    }

    public function salesByStaff(Period $p, ?string $channel = null): array
    {
        return $this->orders($p, $channel)->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->selectRaw("CASE WHEN orders.channel = 'online' THEN 'Online store' ELSE COALESCE(users.name, 'Unknown') END who, COUNT(*) n, SUM(grand_total) total")
            ->groupBy('who')->orderByDesc('total')->get()
            ->map(fn ($r) => ['name' => $r->who, 'invoices' => (int) $r->n, 'amount' => (float) $r->total])->all();
    }

    public function salesByCustomer(Period $p, ?string $channel = null, int $limit = 25): array
    {
        return $this->orders($p, $channel)->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->selectRaw("orders.customer_id, MAX(COALESCE(customers.name, orders.customer_name)) name, MAX(customers.type) type,
                MAX(COALESCE(customers.phone, orders.customer_phone)) phone, COUNT(*) n, SUM(grand_total) total")
            ->groupBy('orders.customer_id')->orderByDesc('total')->limit($limit)->get()
            ->map(fn ($r) => [
                'name' => $r->customer_id ? $r->name : 'Walk-in customers',
                'type' => $r->customer_id ? ucfirst((string) $r->type) : '',
                'phone' => $r->customer_id ? $r->phone : '',
                'invoices' => (int) $r->n,
                'amount' => (float) $r->total,
            ])->all();
    }

    /** Online orders placed but not yet delivered — shown for context, not counted as sales. */
    public function pendingOnline(): array
    {
        $r = DB::table('orders')->where('channel', 'online')->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])
            ->selectRaw('COUNT(*) n, COALESCE(SUM(grand_total), 0) total')->first();

        return ['count' => (int) $r->n, 'amount' => (float) $r->total];
    }

    // ── Purchases ───────────────────────────────────────────────────────

    public function purchaseSummary(Period $p, ?int $vendorId = null): array
    {
        $t = DB::table('vendor_transactions')
            ->whereBetween('date', [$p->from->toDateString(), $p->to->toDateString()])
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'purchase' THEN credit END), 0) received,
                COALESCE(SUM(CASE WHEN type = 'return' THEN debit END), 0) returned,
                COALESCE(SUM(CASE WHEN type = 'payment' THEN debit END), 0) paid")->first();

        $bills = PurchaseOrder::query()->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('order_date', [$p->from->toDateString(), $p->to->toDateString()])
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId));

        return [
            'received' => (float) $t->received,
            'returned' => (float) $t->returned,
            'net' => (float) $t->received - (float) $t->returned,
            'paid' => (float) $t->paid,
            'bills' => (clone $bills)->count(),
            'billed' => (float) (clone $bills)->sum('total'),
            'payable_now' => (float) Vendor::where('current_balance', '>', 0)->when($vendorId, fn ($q) => $q->whereKey($vendorId))->sum('current_balance'),
            'awaiting' => (float) PurchaseOrder::where('status', 'ordered')->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))->sum('total'),
        ];
    }

    public function purchaseBills(Period $p, ?int $vendorId = null)
    {
        return PurchaseOrder::with('vendor:id,name')
            ->whereBetween('order_date', [$p->from->toDateString(), $p->to->toDateString()])
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->orderBy('order_date')->orderBy('id')->get();
    }

    public function purchasesByVendor(Period $p, ?int $vendorId = null): array
    {
        return DB::table('vendor_transactions as t')->join('vendors as v', 'v.id', '=', 't.vendor_id')
            ->whereBetween('t.date', [$p->from->toDateString(), $p->to->toDateString()])
            ->when($vendorId, fn ($q) => $q->where('t.vendor_id', $vendorId))
            ->groupBy('t.vendor_id')
            ->selectRaw("MAX(v.name) name, MAX(v.current_balance) balance,
                COALESCE(SUM(CASE WHEN t.type = 'purchase' THEN t.credit END), 0) received,
                COALESCE(SUM(CASE WHEN t.type = 'return' THEN t.debit END), 0) returned,
                COALESCE(SUM(CASE WHEN t.type = 'payment' THEN t.debit END), 0) paid")
            ->havingRaw('received > 0 OR returned > 0 OR paid > 0')
            ->orderByDesc('received')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function purchasesByProduct(Period $p, ?int $vendorId = null): array
    {
        return DB::table('stock_movements as m')
            ->join('product_variants as pv', 'pv.id', '=', 'm.product_variant_id')
            ->join('products as pr', 'pr.id', '=', 'pv.product_id')
            ->whereIn('m.type', ['purchase', 'purchase_return'])
            ->whereBetween('m.created_at', [$p->from, $p->to])
            ->when($vendorId, fn ($q) => $q->where('m.reference_type', PurchaseOrder::class)
                ->whereIn('m.reference_id', PurchaseOrder::where('vendor_id', $vendorId)->select('id')))
            ->groupBy('m.product_variant_id')
            ->selectRaw("MAX(pr.name) name, MAX(pv.label) label, MAX(pv.sku) sku, MAX(pr.unit) unit,
                COALESCE(SUM(CASE WHEN m.type = 'purchase' THEN m.quantity END), 0) qty,
                COALESCE(SUM(CASE WHEN m.type = 'purchase' THEN m.quantity * m.unit_cost END), 0) value,
                COALESCE(SUM(CASE WHEN m.type = 'purchase_return' THEN -m.quantity END), 0) returned_qty,
                COALESCE(SUM(CASE WHEN m.type = 'purchase_return' THEN -m.quantity * m.unit_cost END), 0) returned_value")
            ->orderByDesc('value')->get()->map(fn ($r) => (array) $r)->all();
    }

    // ── Profit & loss ───────────────────────────────────────────────────

    public function profitLoss(Period $p): array
    {
        $s = $this->salesSummary($p);
        $stock = DB::table('stock_movements')->whereBetween('created_at', [$p->from, $p->to])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'laser_usage' THEN -quantity * unit_cost END), 0) laser_material,
                COALESCE(SUM(CASE WHEN type = 'wastage' THEN -quantity * unit_cost END), 0) wastage,
                COALESCE(SUM(CASE WHEN type IN ('adjustment_in', 'adjustment_out') THEN -quantity * unit_cost END), 0) adjustments")->first();

        $expenses = DB::table('expenses as e')->leftJoin('expense_categories as c', 'c.id', '=', 'e.expense_category_id')
            ->whereBetween('e.date', [$p->from->toDateString(), $p->to->toDateString()])
            ->groupBy('e.expense_category_id')
            ->selectRaw("COALESCE(MAX(c.name), 'Other') name, SUM(e.amount) amount, COUNT(*) n")
            ->orderByDesc('amount')->get()->map(fn ($r) => (array) $r)->all();
        $expenseTotal = array_sum(array_column($expenses, 'amount'));

        $revenue = $s['gross'] - $s['discounts'] + $s['laser'] + $s['delivery'] - $s['returns'];
        $cost = $s['cogs'] - $s['restocked_cost'] + (float) $stock->laser_material + (float) $stock->wastage + (float) $stock->adjustments;
        $gross = $revenue - $cost;

        return [
            'gross_sales' => $s['gross'],
            'discounts' => $s['discounts'],
            'laser' => $s['laser'],
            'delivery' => $s['delivery'],
            'returns' => $s['returns'],
            'revenue' => $revenue,
            'cogs' => $s['cogs'],
            'restocked_cost' => $s['restocked_cost'],
            'laser_material' => (float) $stock->laser_material,
            'wastage' => (float) $stock->wastage,
            'adjustments' => (float) $stock->adjustments,
            'cost' => $cost,
            'gross_profit' => $gross,
            'gross_margin' => $revenue != 0.0 ? $gross / $revenue * 100 : null,
            'expenses' => $expenses,
            'expense_total' => (float) $expenseTotal,
            'net_profit' => $gross - $expenseTotal,
            'net_margin' => $revenue != 0.0 ? ($gross - $expenseTotal) / $revenue * 100 : null,
            'invoices' => $s['invoices'],
        ];
    }

    /** % change helper for "vs previous period" (null when there's nothing to compare with). */
    public static function change(float $now, float $before): ?float
    {
        return abs($before) < 0.005 ? null : ($now - $before) / abs($before) * 100;
    }

    /** "+12.5%" / "−3.0%" / "—" */
    public static function pct(?float $value): string
    {
        return $value === null ? '—' : ($value > 0 ? '+' : ($value < 0 ? '−' : '')).number_format(abs($value), 1).'%';
    }
}
