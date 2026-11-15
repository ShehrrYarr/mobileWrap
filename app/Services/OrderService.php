<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Notifications\NewOnlineOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected InventoryService $inventory,
        protected FinanceService $finance,
    ) {}

    /**
     * Turn the storefront cart into an online order.
     *
     * @param  array{name:string, phone:string, email:?string, address:string, city:string, notes:?string, payment_method:string, bank_account_id:?int, payment_reference:?string}  $data
     */
    public function placeOnlineOrder(array $data, CartService $cart, ?Customer $customer = null, ?UploadedFile $receipt = null): Order
    {
        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $order = DB::transaction(function () use ($data, $cart, $customer, $receipt) {
            $lines = $cart->lines();
            $totals = $cart->totals($customer);

            // Re-check stock with row locks so two shoppers cannot buy the last unit.
            $needed = [];
            foreach ($lines as $line) {
                foreach ($line['components'] as $c) {
                    if ($c['variant']->product?->track_stock) {
                        $needed[$c['variant']->id] = ($needed[$c['variant']->id] ?? 0) + $c['qty'] * $line['qty'];
                    }
                }
            }
            $stocks = InventoryStock::whereIn('product_variant_id', array_keys($needed))->lockForUpdate()->get()->keyBy('product_variant_id');
            foreach ($needed as $variantId => $qty) {
                $available = (float) ($stocks[$variantId]->quantity ?? 0) - (float) ($stocks[$variantId]->reserved_quantity ?? 0);
                if ($available < $qty) {
                    $name = $lines->flatMap(fn ($l) => $l['components'])->firstWhere('variant.id', $variantId)['variant']->displayName();
                    throw ValidationException::withMessages(['cart' => "Sorry, “{$name}” just went out of stock. Please update your cart."]);
                }
            }

            $customer = $this->resolveCustomer($data, $customer);

            $order = Order::create([
                'number' => Order::generateNumber('online'),
                'channel' => 'online',
                'customer_id' => $customer->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $data['payment_method'],
                'price_tier' => 'retail',
                'subtotal' => $totals['subtotal'],
                'discount_total' => 0,
                'coupon_id' => $totals['coupon_discount'] > 0 ? $totals['coupon']?->id : null,
                'coupon_code' => $totals['coupon_discount'] > 0 ? $totals['coupon_code'] : null,
                'coupon_discount' => $totals['coupon_discount'],
                'shipping_fee' => $totals['shipping'],
                'grand_total' => $totals['total'],
                'customer_name' => $data['name'],
                'customer_phone' => Customer::normalizePhone($data['phone']),
                'customer_email' => $data['email'] ?? null,
                'shipping_address' => $data['address'],
                'shipping_city' => $data['city'],
                'notes' => $data['notes'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                foreach ($line['components'] as $c) {
                    $variant = $c['variant'];
                    $quantity = $c['qty'] * $line['qty'];
                    $image = $variant->color?->images->first()?->path ?? $variant->product?->colors->first()?->images->first()?->path;

                    $item = $order->items()->create([
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'item_type' => 'product',
                        'name' => $variant->product?->name ?? 'Item',
                        'sku' => $variant->sku,
                        'variant_label' => $variant->label !== 'Standard' ? $variant->label : null,
                        'image' => $image,
                        'quantity' => $quantity,
                        'unit_price' => $c['unit'],
                        'unit_cost' => (float) $variant->cost_price,
                        'discount' => 0,
                        'total' => round($c['unit'] * $quantity, 2),
                        'meta' => $line['type'] === 'bundle' ? ['bundle_id' => $line['id'], 'bundle' => $line['name']] : null,
                    ]);

                    if ($variant->product?->track_stock) {
                        $this->inventory->adjust($variant, -$quantity, 'online_order', $order, 'Online order '.$order->number);
                    }
                }
            }

            $receiptPath = $receipt?->store('receipts/'.now()->format('Y/m'), 'uploads');

            $order->payments()->create([
                'method' => $data['payment_method'],
                'amount' => $totals['total'],
                'bank_account_id' => $data['payment_method'] === 'bank_transfer' ? ($data['bank_account_id'] ?? null) : null,
                'reference' => $data['payment_reference'] ?? null,
                'receipt_path' => $receiptPath,
                'status' => 'pending',
            ]);

            if ($order->coupon_id) {
                CouponUsage::create([
                    'coupon_id' => $order->coupon_id,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'discount_amount' => $order->coupon_discount,
                ]);
                Coupon::whereKey($order->coupon_id)->increment('used_count');
            }

            $order->statusHistories()->create(['status' => 'pending', 'note' => 'Order placed online']);
            $customer->forceFill(['last_order_at' => now()])->save();

            return $order;
        });

        $cart->clear();

        $staff = User::permission('orders.manage')->get()->merge(User::role('Admin')->get())->unique('id');
        if ($staff->isNotEmpty()) {
            Notification::send($staff, new NewOnlineOrder($order));
        }

        return $order;
    }

    protected function resolveCustomer(array $data, ?Customer $customer): Customer
    {
        if ($customer) {
            $customer->fill(array_filter([
                'address' => $customer->address ?: $data['address'],
                'city' => $customer->city ?: $data['city'],
            ]))->save();

            return $customer;
        }

        $phone = Customer::normalizePhone($data['phone']);
        $existing = Customer::withTrashed()->where('phone', $phone)->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->fill(array_filter([
                'email' => $existing->email ?: (Customer::where('email', $data['email'] ?? null)->exists() ? null : ($data['email'] ?? null)),
                'address' => $data['address'],
                'city' => $data['city'],
            ]))->save();

            return $existing;
        }

        $email = $data['email'] ?? null;
        if ($email && Customer::withTrashed()->where('email', $email)->exists()) {
            $email = null;
        }

        return Customer::create([
            'type' => 'retail',
            'name' => $data['name'],
            'phone' => $phone,
            'email' => $email,
            'address' => $data['address'],
            'city' => $data['city'],
        ]);
    }

    /** Change status (admin), restocking on cancellation. */
    public function changeStatus(Order $order, string $status, ?string $note = null): void
    {
        if ($order->status === $status) {
            return;
        }

        DB::transaction(function () use ($order, $status, $note) {
            if ($status === 'cancelled' && $order->status !== 'cancelled') {
                foreach ($order->items()->with('variant.product')->get() as $item) {
                    if ($item->variant && $item->variant->product?->track_stock && $item->item_type === 'product') {
                        $this->inventory->adjust($item->variant, (float) $item->quantity, 'online_cancel', $order, 'Order '.$order->number.' cancelled');
                    }
                }
                $order->cancelled_at = now();
                if ($order->coupon_id) {
                    Coupon::whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
                }
            }

            if (in_array($status, ['delivered', 'completed'], true)) {
                $order->completed_at ??= now();
            }

            $order->status = $status;
            $order->save();

            $order->statusHistories()->create([
                'status' => $status,
                'note' => $note,
                'user_id' => auth('web')->id(),
            ]);
        });
    }

    /**
     * Verify an online payment and post it to the bank/cash ledger.
     * COD → cash drawer; bank transfer → the chosen bank account.
     */
    public function verifyPayment(OrderPayment $payment, ?int $bankAccountId = null): void
    {
        if ($payment->status === 'verified') {
            return;
        }

        DB::transaction(function () use ($payment, $bankAccountId) {
            $order = $payment->order;

            $account = $payment->method === 'bank_transfer'
                ? BankAccount::findOrFail($bankAccountId ?? $payment->bank_account_id)
                : CashDrawer::main();

            $txn = $this->finance->record(
                $account, 'sale_receipt', (float) $payment->amount, 0, $order, $order->customer,
                ($payment->method === 'cod' ? 'COD collected' : 'Bank transfer').' — '.$order->number,
            );

            $payment->forceFill([
                'status' => 'verified',
                'bank_account_id' => $account instanceof BankAccount ? $account->id : null,
                'cash_drawer_id' => $account instanceof CashDrawer ? $account->id : null,
                'verified_by' => auth('web')->id(),
                'verified_at' => now(),
                'paid_at' => now(),
                'financial_transaction_id' => $txn->id,
            ])->save();

            $paid = (float) $order->payments()->where('status', 'verified')->sum('amount');
            $order->forceFill([
                'paid_total' => $paid,
                'payment_status' => $paid >= (float) $order->grand_total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            ])->save();

            $order->statusHistories()->create([
                'status' => $order->status,
                'note' => 'Payment of '.money($payment->amount).' verified ('.$payment->methodLabel().')',
                'user_id' => auth('web')->id(),
            ]);
        });
    }

    public function rejectPayment(OrderPayment $payment, ?string $reason = null): void
    {
        $payment->forceFill(['status' => 'rejected', 'verified_by' => auth('web')->id(), 'verified_at' => now()])->save();
        $payment->order->statusHistories()->create([
            'status' => $payment->order->status,
            'note' => 'Payment rejected'.($reason ? ': '.$reason : ''),
            'user_id' => auth('web')->id(),
        ]);
    }
}
