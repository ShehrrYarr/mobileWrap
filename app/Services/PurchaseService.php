<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\VendorTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchases from vendors. Stock and the vendor payable are booked when goods
 * are received (partial deliveries allowed). Cost prices follow the
 * weighted average of stock on hand.
 */
class PurchaseService
{
    public function __construct(
        protected InventoryService $inventory,
        protected FinanceService $finance,
        protected VendorLedgerService $ledger,
    ) {}

    public static function nextNumber(): string
    {
        $last = PurchaseOrder::withTrashed()->pluck('number')->map(fn ($n) => (int) substr($n, 3))->max();

        return 'PO-'.max(1001, (int) $last + 1);
    }

    /**
     * Create or update a draft / ordered purchase.
     *
     * @param  array  $data  vendor_id, bill_number, order_date, due_date, discount, shipping, notes, status
     * @param  array  $items  [['product_variant_id', 'quantity', 'unit_cost'], …]
     */
    public function save(?PurchaseOrder $po, array $data, array $items): PurchaseOrder
    {
        if ($po && in_array($po->status, ['partial', 'received', 'cancelled'], true)) {
            throw ValidationException::withMessages(['po' => 'Received purchases can’t be edited — use a return instead.']);
        }

        return DB::transaction(function () use ($po, $data, $items) {
            $subtotal = round(collect($items)->sum(fn ($i) => (float) $i['quantity'] * (float) $i['unit_cost']), 2);
            $total = round(max(0, $subtotal - (float) ($data['discount'] ?? 0) + (float) ($data['shipping'] ?? 0)), 2);

            $po ??= new PurchaseOrder(['number' => self::nextNumber(), 'user_id' => auth('web')->id()]);
            $po->fill([
                'vendor_id' => $data['vendor_id'],
                'bill_number' => $data['bill_number'] ?: null,
                'status' => $data['status'] ?? 'draft',
                'order_date' => $data['order_date'],
                'due_date' => $data['due_date'] ?: null,
                'subtotal' => $subtotal,
                'discount' => (float) ($data['discount'] ?? 0),
                'shipping' => (float) ($data['shipping'] ?? 0),
                'total' => $total,
                'notes' => $data['notes'] ?: null,
            ])->save();

            $po->items()->delete();
            foreach ($items as $i) {
                $po->items()->create([
                    'product_variant_id' => $i['product_variant_id'],
                    'quantity' => (float) $i['quantity'],
                    'unit_cost' => (float) $i['unit_cost'],
                    'total' => round((float) $i['quantity'] * (float) $i['unit_cost'], 2),
                ]);
            }

            return $po->fresh('items');
        });
    }

    /** Receive goods: [purchase_order_item_id => qty]. Books stock + vendor payable. */
    public function receive(PurchaseOrder $po, array $quantities, ?string $date = null): void
    {
        if (in_array($po->status, ['received', 'cancelled'], true)) {
            throw ValidationException::withMessages(['po' => 'This purchase is already '.strtolower($po->statusLabel()).'.']);
        }

        DB::transaction(function () use ($po, $quantities, $date) {
            $po->load('items.variant.product', 'vendor');
            $value = 0.0;
            $any = false;

            foreach ($po->items as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 3);
                $open = (float) $item->quantity - (float) $item->received_quantity;
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $open + 0.0001) {
                    throw ValidationException::withMessages(['receive' => "Only {$open} of {$item->variant?->displayName()} are still to receive."]);
                }

                $variant = $item->variant;
                $this->inventory->adjust($variant, $qty, 'purchase', $po, "Received on {$po->number}", (float) $item->unit_cost);
                // weighted-average cost becomes the product's cost price
                $avg = (float) $variant->fresh('stock')->stock?->average_cost;
                if ($avg > 0) {
                    ProductVariant::whereKey($variant->id)->update(['cost_price' => $avg]);
                }

                $item->increment('received_quantity', $qty);
                $value += $qty * (float) $item->unit_cost;
                $any = true;
            }

            if (! $any) {
                throw ValidationException::withMessages(['receive' => 'Enter a quantity to receive.']);
            }

            $payable = round($value * $po->payableFactor(), 2);
            $this->ledger->record($po->vendor, 'purchase', 0, $payable, $po, "Goods received — {$po->number}".($po->bill_number ? " (bill {$po->bill_number})" : ''), null, $date);

            $po->load('items');
            $complete = $po->items->every(fn ($i) => (float) $i->received_quantity >= (float) $i->quantity);
            $po->update([
                'status' => $complete ? 'received' : 'partial',
                'received_date' => $date ?? now()->toDateString(),
                'received_total' => round((float) $po->received_total + $payable, 2),
            ]);
        });
    }

    /** Pay a vendor from a bank account or the cash drawer, optionally against a purchase. */
    public function pay(Vendor $vendor, BankAccount|CashDrawer $account, float $amount, ?PurchaseOrder $po = null, ?string $note = null, $date = null): VendorTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }
        FinanceService::ensureFunds($account, $amount);

        return DB::transaction(function () use ($vendor, $account, $amount, $po, $note, $date) {
            $label = $note ?: 'Payment to '.$vendor->name.($po ? " for {$po->number}" : '');
            $txn = $this->finance->record($account, 'vendor_payment', 0, $amount, $po, $vendor, $label, $date);
            $entry = $this->ledger->record($vendor, 'payment', $amount, 0, $po, $label, $txn->id, $date);
            if ($po) {
                $po->increment('paid_amount', $amount);
            }

            return $entry;
        });
    }

    /** Send received goods back: [purchase_order_item_id => qty]. Reduces stock and the payable. */
    public function returnToVendor(PurchaseOrder $po, array $quantities, ?string $note = null): float
    {
        return DB::transaction(function () use ($po, $quantities, $note) {
            $po->load('items.variant', 'vendor');
            $value = 0.0;

            foreach ($po->items as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }
                $returnable = (float) $item->received_quantity - (float) $item->returned_quantity;
                if ($qty > $returnable + 0.0001) {
                    throw ValidationException::withMessages(['return' => "Only {$returnable} of {$item->variant?->displayName()} can be returned."]);
                }
                $this->inventory->adjust($item->variant, -$qty, 'purchase_return', $po, "Returned to {$po->vendor->name} ({$po->number})", (float) $item->unit_cost);
                $item->increment('returned_quantity', $qty);
                $value += $qty * (float) $item->unit_cost;
            }

            if ($value <= 0) {
                throw ValidationException::withMessages(['return' => 'Enter a quantity to return.']);
            }

            $credit = round($value * $po->payableFactor(), 2);
            $this->ledger->record($po->vendor, 'return', $credit, 0, $po, trim("Returned goods — {$po->number}. ".($note ?? '')));
            $po->increment('returned_total', $credit);

            return $credit;
        });
    }

    public function cancel(PurchaseOrder $po): void
    {
        if (! in_array($po->status, ['draft', 'ordered'], true)) {
            throw ValidationException::withMessages(['po' => 'Only purchases that haven’t been received can be cancelled.']);
        }
        $po->update(['status' => 'cancelled']);
    }
}
