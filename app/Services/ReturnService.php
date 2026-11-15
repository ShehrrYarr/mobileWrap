<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Returns and exchanges against an invoice (POS or online). */
class ReturnService
{
    public function __construct(
        protected InventoryService $inventory,
        protected FinanceService $finance,
        protected CustomerLedgerService $ledger,
    ) {}

    public static function nextNumber(): string
    {
        $last = OrderReturn::pluck('number')->map(fn ($n) => (int) substr($n, 4))->max();

        return 'RET-'.max(1001, (int) $last + 1);
    }

    /** Net amount actually paid per unit of an item, after order-level discounts. */
    public static function netUnitPrice(Order $order, OrderItem $item): float
    {
        $qty = max(0.001, (float) $item->quantity);
        $lineNet = (float) $item->total / $qty;
        $orderDiscount = (float) $order->discount_total + (float) $order->coupon_discount;
        $ratio = (float) $order->subtotal > 0 ? min(1, $orderDiscount / (float) $order->subtotal) : 0;

        return round($lineNet * (1 - $ratio), 2);
    }

    /**
     * @param  array  $lines  [order_item_id => ['qty' => int, 'condition' => 'restock'|'damaged']]
     * @param  string  $refundMethod  cash | bank_transfer | customer_credit | exchange | none
     */
    public function process(Order $order, array $lines, string $refundMethod, ?float $refundAmount = null, ?int $bankAccountId = null, ?string $reason = null): OrderReturn
    {
        $lines = array_filter($lines, fn ($l) => (float) ($l['qty'] ?? 0) > 0);
        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'Choose at least one item to return.']);
        }
        if (in_array($refundMethod, ['customer_credit', 'exchange'], true) && ! $order->customer_id && $refundMethod === 'customer_credit') {
            throw ValidationException::withMessages(['refund_method' => 'This invoice has no customer — refund in cash or by exchange instead.']);
        }

        return DB::transaction(function () use ($order, $lines, $refundMethod, $refundAmount, $bankAccountId, $reason) {
            $order->load('items', 'customer');
            $computed = 0.0;
            $rows = [];

            foreach ($lines as $itemId => $line) {
                $item = $order->items->firstWhere('id', (int) $itemId);
                if (! $item) {
                    throw ValidationException::withMessages(['lines' => 'Item not on this invoice.']);
                }
                $qty = (float) $line['qty'];
                $returnable = (float) $item->quantity - (float) $item->returned_quantity;
                if ($qty > $returnable + 0.0001) {
                    throw ValidationException::withMessages(['lines' => "Only {$returnable} of “{$item->name}” can still be returned."]);
                }
                $amount = round(self::netUnitPrice($order, $item) * $qty, 2);
                $computed += $amount;
                $rows[] = [$item, $qty, $line['condition'] ?? 'restock', $amount];
            }

            $refund = $refundMethod === 'none' ? 0.0 : round($refundAmount ?? $computed, 2);

            $return = OrderReturn::create([
                'number' => self::nextNumber(),
                'order_id' => $order->id,
                'type' => $refundMethod === 'exchange' ? 'exchange' : 'return',
                'refund_method' => $refundMethod,
                'refund_amount' => $refund,
                'bank_account_id' => $refundMethod === 'bank_transfer' ? $bankAccountId : null,
                'reason' => $reason,
                'user_id' => auth('web')->id(),
            ]);

            foreach ($rows as [$item, $qty, $condition, $amount]) {
                $return->items()->create(['order_item_id' => $item->id, 'quantity' => $qty, 'condition' => $condition, 'amount' => $amount]);
                $item->increment('returned_quantity', $qty);

                if ($item->item_type === 'product' && $condition === 'restock' && $item->variant && $item->variant->product?->track_stock) {
                    $this->inventory->adjust($item->variant, $qty, 'sale_return', $return, "Return {$return->number} ({$order->number})");
                }
            }

            // Money back
            if ($refund > 0) {
                switch ($refundMethod) {
                    case 'cash':
                        $txn = $this->finance->record(CashDrawer::main(), 'refund', 0, $refund, $return, $order->customer, "Refund {$return->number} for {$order->number}");
                        $return->update(['financial_transaction_id' => $txn->id]);
                        break;
                    case 'bank_transfer':
                        $txn = $this->finance->record(BankAccount::findOrFail($bankAccountId), 'refund', 0, $refund, $return, $order->customer, "Refund {$return->number} for {$order->number}");
                        $return->update(['financial_transaction_id' => $txn->id]);
                        break;
                    case 'customer_credit':
                        $this->ledger->record($order->customer, 'return', 0, $refund, $return, "Return {$return->number} for {$order->number}");
                        break;
                    // exchange: credit is spent on the next POS sale (payment method "exchange_credit")
                }
            }

            $order->load('items');
            $fully = $order->items->every(fn ($i) => (float) $i->returned_quantity >= (float) $i->quantity);
            $order->update(['status' => $fully ? 'returned' : 'partially_returned']);
            $order->statusHistories()->create([
                'status' => $order->status,
                'note' => ($return->type === 'exchange' ? 'Exchange ' : 'Return ').$return->number.' · '.money($refund).($refundMethod !== 'none' ? ' via '.str_replace('_', ' ', $refundMethod) : ''),
                'user_id' => auth('web')->id(),
            ]);

            return $return;
        });
    }
}
