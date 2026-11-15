<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

/** A handful of past online orders so the dashboard and order list are not empty. */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $inventory = app(InventoryService::class);
        mt_srand(7);

        $variants = ProductVariant::whereHas('product', fn ($q) => $q->where('show_online', true))
            ->whereHas('stock', fn ($q) => $q->where('quantity', '>=', 8))
            ->with('product', 'color.images')->get();

        $plan = [
            ['Ali Raza', 'delivered', 'cod', 9], ['Ayesha Khan', 'delivered', 'bank_transfer', 8],
            ['Hamza Riaz', 'shipped', 'cod', 4], ['Sana Malik', 'processing', 'bank_transfer', 2],
            ['Usman Ahmed', 'confirmed', 'cod', 1], ['Fatima Noor', 'pending', 'bank_transfer', 0],
            ['Bilal Sheikh', 'pending', 'cod', 0], ['Ayesha Khan', 'cancelled', 'cod', 6],
        ];

        foreach ($plan as $n => [$name, $status, $method, $daysAgo]) {
            $customer = Customer::where('name', $name)->first();
            $when = now()->subDays($daysAgo)->subHours(mt_rand(1, 9));
            $picked = $variants->random(mt_rand(1, 3));

            $order = Order::create([
                'number' => 'MW-'.(1001 + $n),
                'channel' => 'online',
                'customer_id' => $customer->id,
                'status' => $status,
                'payment_status' => in_array($status, ['delivered'], true) ? 'paid' : 'unpaid',
                'payment_method' => $method,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'shipping_address' => $customer->address,
                'shipping_city' => $customer->city,
                'placed_at' => $when,
                'created_at' => $when,
                'updated_at' => $when,
            ]);

            $subtotal = 0;
            foreach ($picked as $variant) {
                $qty = mt_rand(1, 2);
                $price = (float) $variant->retail_price;
                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'name' => $variant->product->name,
                    'sku' => $variant->sku,
                    'variant_label' => $variant->label !== 'Standard' ? $variant->label : null,
                    'image' => $variant->color?->images->first()?->path,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit_cost' => (float) $variant->cost_price,
                    'total' => $price * $qty,
                ]);
                $subtotal += $price * $qty;
                if ($status !== 'cancelled') {
                    $inventory->adjust($variant, -$qty, 'online_order', $order, 'Online order '.$order->number);
                }
            }

            $shipping = $subtotal >= 5000 ? 0 : 250;
            $total = $subtotal + $shipping;
            $order->update([
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'grand_total' => $total,
                'paid_total' => $order->payment_status === 'paid' ? $total : 0,
                'completed_at' => $status === 'delivered' ? $when->copy()->addDays(3) : null,
                'cancelled_at' => $status === 'cancelled' ? $when->copy()->addHours(5) : null,
            ]);

            $order->payments()->create([
                'method' => $method,
                'amount' => $total,
                'status' => $status === 'delivered' ? 'verified' : 'pending',
                'bank_account_id' => $method === 'bank_transfer' ? 1 : null,
                'reference' => $method === 'bank_transfer' ? 'TRX'.mt_rand(100000, 999999) : null,
                'verified_at' => $status === 'delivered' ? $when->copy()->addDays(3) : null,
            ]);

            $steps = array_slice(Order::TRACKING_STEPS, 0, max(1, array_search($status, Order::TRACKING_STEPS, true) + 1));
            if ($status === 'cancelled') {
                $steps = ['pending', 'cancelled'];
            }
            foreach ($steps as $k => $s) {
                $order->statusHistories()->create([
                    'status' => $s,
                    'note' => $k === 0 ? 'Order placed online' : null,
                    'created_at' => $when->copy()->addHours($k * 14),
                    'updated_at' => $when->copy()->addHours($k * 14),
                ]);
            }

            $customer->update(['last_order_at' => $when]);
        }
    }
}
