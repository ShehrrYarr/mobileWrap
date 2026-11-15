<?php

namespace App\Services\Pos;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\DeviceModel;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ProductVariant;
use App\Services\CustomerLedgerService;
use App\Services\FinanceService;
use App\Services\InventoryService;
use App\Services\LaserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Completes POS sales: order + items, stock, laser jobs, payments and ledgers. */
class PosService
{
    public function __construct(
        protected InventoryService $inventory,
        protected FinanceService $finance,
        protected CustomerLedgerService $ledger,
        protected LaserService $laser,
    ) {}

    /**
     * @param  array  $lines  POS cart lines (see PosCart)
     * @param  array  $payments  [['method', 'amount', 'tendered'?, 'bank_account_id'?, 'reference'?, 'return_id'?], …]
     */
    public function checkout(array $lines, array $payments, ?Customer $customer, array $discount = [], ?string $couponCode = null, ?string $note = null): Order
    {
        if (! $lines) {
            throw ValidationException::withMessages(['cart' => 'The cart is empty.']);
        }

        $totals = PosCart::totals($lines, $discount, $couponCode, $customer);
        if ($couponCode && $totals['coupon_error']) {
            throw ValidationException::withMessages(['coupon' => $totals['coupon_error']]);
        }

        $payments = array_values(array_filter($payments, fn ($p) => (float) ($p['amount'] ?? 0) > 0));
        $paid = round(array_sum(array_map(fn ($p) => (float) $p['amount'], $payments)), 2);
        if (abs($paid - $totals['total']) > 0.01) {
            throw ValidationException::withMessages(['payments' => 'Payments ('.money($paid).') must equal the total ('.money($totals['total']).').']);
        }
        foreach ($payments as $p) {
            if ($p['method'] === 'customer_credit' && ! $customer) {
                throw ValidationException::withMessages(['payments' => 'Select a customer to sell on khata (credit).']);
            }
            if ($p['method'] === 'bank_transfer' && empty($p['bank_account_id'])) {
                throw ValidationException::withMessages(['payments' => 'Choose the bank account for the transfer.']);
            }
        }

        return DB::transaction(function () use ($lines, $payments, $customer, $discount, $totals, $note) {
            $tier = $customer?->isWholesale() ? 'wholesale' : 'retail';
            $credit = (float) collect($payments)->where('method', 'customer_credit')->sum('amount');
            $change = (float) collect($payments)->where('method', 'cash')->sum(fn ($p) => max(0, (float) ($p['tendered'] ?? $p['amount']) - (float) $p['amount']));
            $methods = collect($payments)->pluck('method')->unique()->values();

            $order = Order::create([
                'number' => Order::generateNumber('pos'),
                'channel' => 'pos',
                'customer_id' => $customer?->id,
                'user_id' => auth('web')->id(),
                'status' => 'completed',
                'payment_status' => $credit <= 0 ? 'paid' : ($credit >= $totals['total'] ? 'unpaid' : 'partial'),
                'payment_method' => $methods->count() > 1 ? 'split' : ($methods->first() ?? 'cash'),
                'price_tier' => $tier,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['cart_discount'],
                'coupon_id' => $totals['coupon_discount'] > 0 ? $totals['coupon']?->id : null,
                'coupon_code' => $totals['coupon_discount'] > 0 ? $totals['coupon']?->code : null,
                'coupon_discount' => $totals['coupon_discount'],
                'service_fee_total' => $totals['laser'],
                'grand_total' => $totals['total'],
                'paid_total' => round($totals['total'] - $credit, 2),
                'change_due' => round($change, 2),
                'due_total' => round($credit, 2),
                'customer_name' => $customer?->name ?? 'Walk-in customer',
                'customer_phone' => $customer?->phone,
                'customer_email' => $customer?->email,
                'staff_notes' => $note,
                'placed_at' => now(),
                'completed_at' => now(),
            ]);

            foreach ($lines as $line) {
                if ($line['type'] === 'laser') {
                    $this->addLaserLine($order, $line);
                } else {
                    $this->addProductLine($order, $line);
                }
            }

            foreach ($payments as $p) {
                $this->recordPayment($order, $p, $customer);
            }

            if ($order->coupon_id) {
                CouponUsage::create(['coupon_id' => $order->coupon_id, 'order_id' => $order->id, 'customer_id' => $customer?->id, 'discount_amount' => $order->coupon_discount]);
                Coupon::whereKey($order->coupon_id)->increment('used_count');
            }

            $order->statusHistories()->create(['status' => 'completed', 'note' => 'POS sale', 'user_id' => auth('web')->id()]);
            $customer?->forceFill(['last_order_at' => now()])->save();

            return $order;
        });
    }

    protected function addProductLine(Order $order, array $line): void
    {
        $variant = ProductVariant::with('product', 'color.images')->findOrFail($line['variant_id']);
        $qty = (float) $line['qty'];

        $order->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'item_type' => 'product',
            'name' => $variant->product?->name ?? 'Item',
            'sku' => $variant->sku,
            'variant_label' => $variant->label !== 'Standard' ? $variant->label : null,
            'image' => $variant->color?->images->first()?->path,
            'quantity' => $qty,
            'unit_price' => (float) $line['price'],
            'unit_cost' => (float) ($variant->stock?->average_cost ?: $variant->cost_price),
            'discount' => min((float) ($line['discount'] ?? 0), (float) $line['price'] * $qty),
            'total' => PosCart::lineTotal($line),
        ]);

        if ($variant->product?->track_stock) {
            $this->inventory->adjust($variant, -$qty, 'sale', $order, 'POS sale '.$order->number);
        }
    }

    protected function addLaserLine(Order $order, array $line): void
    {
        $device = DeviceModel::with('brand')->findOrFail($line['device_model_id']);
        $material = ProductVariant::with('product')->findOrFail($line['material_variant_id']);
        $side = ['front' => 'Front', 'back' => 'Back', 'full' => 'Full body'][$line['side']] ?? $line['side'];

        $item = $order->items()->create([
            'product_id' => $material->product_id,
            'product_variant_id' => null,
            'item_type' => 'laser_service',
            'name' => 'Laser cut — '.$material->product?->name.' ('.$side.')',
            'sku' => null,
            'variant_label' => $device->full_name.' · '.$material->label.' · '.(float) $line['width'].'×'.(float) $line['height'].' mm',
            'quantity' => (int) $line['qty'],
            'unit_price' => (float) $line['fee'],
            'unit_cost' => 0,
            'discount' => 0,
            'total' => PosCart::lineTotal($line),
            'meta' => collect($line)->only(['device_model_id', 'material_variant_id', 'side', 'width', 'height', 'notes'])->all(),
        ]);

        $job = $this->laser->createFromSale($order, $item, $line);
        $item->update(['meta' => array_merge($item->meta ?? [], ['job_id' => $job->id, 'job_number' => $job->number])]);
    }

    protected function recordPayment(Order $order, array $p, ?Customer $customer): void
    {
        $amount = round((float) $p['amount'], 2);
        $payment = $order->payments()->create([
            'method' => $p['method'],
            'amount' => $amount,
            'tendered' => $p['method'] === 'cash' ? (float) ($p['tendered'] ?? $amount) : null,
            'reference' => $p['reference'] ?? null,
            'status' => 'verified',
            'verified_by' => auth('web')->id(),
            'verified_at' => now(),
            'paid_at' => now(),
        ]);

        switch ($p['method']) {
            case 'cash':
                $drawer = CashDrawer::main();
                $txn = $this->finance->record($drawer, 'sale_receipt', $amount, 0, $order, $customer, 'POS sale '.$order->number);
                $payment->update(['cash_drawer_id' => $drawer->id, 'financial_transaction_id' => $txn->id]);
                break;

            case 'bank_transfer':
                $bank = BankAccount::findOrFail($p['bank_account_id']);
                $txn = $this->finance->record($bank, 'sale_receipt', $amount, 0, $order, $customer, 'POS sale '.$order->number);
                $payment->update(['bank_account_id' => $bank->id, 'financial_transaction_id' => $txn->id]);
                break;

            case 'customer_credit':
                $this->ledger->record($customer, 'sale', $amount, 0, $order, 'Credit sale '.$order->number);
                break;

            case 'exchange_credit':
                $return = OrderReturn::where('type', 'exchange')->whereNull('exchange_order_id')->findOrFail($p['return_id']);
                $return->update(['exchange_order_id' => $order->id]);
                $payment->update(['reference' => $return->number]);

                // Credit bigger than the new sale: hand the difference back in cash
                $leftover = round((float) $return->refund_amount - $amount, 2);
                if ($leftover > 0) {
                    $txn = $this->finance->record(CashDrawer::main(), 'refund', 0, $leftover, $return, $customer, "Exchange balance returned in cash ({$return->number})");
                    $return->update(['financial_transaction_id' => $txn->id]);
                    $order->update(['change_due' => round((float) $order->change_due + $leftover, 2)]);
                }
                break;
        }
    }

    // ── Held sales ───────────────────────────────────────────────────

    public function hold(array $lines, ?Customer $customer, array $discount = [], ?string $note = null): Order
    {
        if (! $lines) {
            throw ValidationException::withMessages(['cart' => 'Nothing to hold.']);
        }

        return DB::transaction(function () use ($lines, $customer, $discount, $note) {
            $totals = PosCart::totals($lines, $discount, null, $customer);
            $order = Order::create([
                'number' => Order::generateNumber('pos', 'HOLD-'),
                'channel' => 'pos',
                'customer_id' => $customer?->id,
                'user_id' => auth('web')->id(),
                'status' => 'held',
                'payment_status' => 'unpaid',
                'price_tier' => $customer?->isWholesale() ? 'wholesale' : 'retail',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['cart_discount'],
                'grand_total' => $totals['total'],
                'customer_name' => $customer?->name ?? 'Walk-in customer',
                'customer_phone' => $customer?->phone,
                'staff_notes' => $note,
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_variant_id' => $line['type'] === 'product' ? $line['variant_id'] : null,
                    'item_type' => $line['type'] === 'laser' ? 'laser_service' : 'product',
                    'name' => $line['name'] ?? 'Item',
                    'variant_label' => $line['label'] ?? null,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['type'] === 'laser' ? (float) $line['fee'] : (float) $line['price'],
                    'discount' => (float) ($line['discount'] ?? 0),
                    'total' => PosCart::lineTotal($line),
                    'meta' => ['line' => $line, 'cart_discount' => $discount],
                ]);
            }

            return $order;
        });
    }

    /** Turn a held sale back into cart lines and remove it. */
    public function resume(Order $held): array
    {
        abort_unless($held->status === 'held', 404);
        $held->load('items');
        $lines = $held->items->map(fn ($i) => $i->meta['line'] ?? null)->filter()->values()->all();
        $discount = $held->items->first()?->meta['cart_discount'] ?? [];
        $customerId = $held->customer_id;
        $note = $held->staff_notes;
        $held->items()->delete();
        $held->forceDelete();

        return compact('lines', 'discount', 'customerId', 'note');
    }
}
