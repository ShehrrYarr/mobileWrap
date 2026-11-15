<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Deal;
use App\Models\Popup;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        $p = fn (string $name) => Product::where('name', $name)->firstOrFail();

        // Flash sale (ends in ~2 days so the countdown is visible)
        $flash = Deal::create([
            'title' => 'Weekend Flash Sale',
            'type' => 'flash_sale',
            'discount_type' => 'percent',
            'discount_value' => 25,
            'description' => '25% off selected bestsellers — while the timer runs.',
            'starts_at' => now()->subHours(6),
            'ends_at' => now()->addDays(2)->addHours(5),
            'show_on_home' => true,
        ]);
        foreach (['Liquid Silicone Case', '20W USB-C Fast Charger', 'Braided USB-C to USB-C Cable', 'Slim Power Bank', 'Wireless Earbuds Pro', 'Privacy Tempered Glass'] as $name) {
            $flash->items()->create(['product_id' => $p($name)->id]);
        }

        // Category-wide discount
        Deal::create([
            'title' => 'Wraps Week',
            'type' => 'category_discount',
            'discount_type' => 'percent',
            'discount_value' => 15,
            'category_id' => Category::where('slug', 'wraps-skins')->value('id'),
            'description' => '15% off every wrap and skin.',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
        ]);

        // Bundles / combo deals
        $bundles = [
            ['Starter Protection Kit', 2999, 'Case + full-glue glass + lens protectors for a brand-new phone.', ['Liquid Silicone Case', 'UV Tempered Glass (Full Glue)', 'Camera Lens Protector Set']],
            ['Fast Charge Combo', 2599, 'Our 20W charger paired with a braided USB-C cable.', ['20W USB-C Fast Charger', 'Braided USB-C to USB-C Cable']],
            ['Road Trip Pack', 4999, 'Car charger, magnetic mount and a 3-in-1 cable.', ['Car Charger 45W Dual', 'Magnetic Car Mount', '3-in-1 Charging Cable']],
        ];
        foreach ($bundles as [$title, $price, $desc, $items]) {
            $deal = Deal::create([
                'title' => $title,
                'type' => 'bundle',
                'bundle_price' => $price,
                'description' => $desc,
                'starts_at' => now()->subDays(3),
                'ends_at' => now()->addMonth(),
            ]);
            foreach ($items as $name) {
                $product = $p($name);
                $deal->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant()?->id, 'quantity' => 1]);
            }
        }

        // Coupons
        Coupon::create(['code' => 'WELCOME10', 'description' => '10% off your first order', 'type' => 'percent', 'value' => 10, 'min_order_amount' => 2000, 'max_discount_amount' => 1000, 'usage_limit_per_customer' => 1, 'applies_to' => 'online', 'expires_at' => now()->addMonths(3)]);
        Coupon::create(['code' => 'SAVE500', 'description' => 'Rs. 500 off orders above Rs. 5,000', 'type' => 'fixed', 'value' => 500, 'min_order_amount' => 5000, 'usage_limit' => 200, 'applies_to' => 'all', 'expires_at' => now()->addMonth()]);
        Coupon::create(['code' => 'WRAP15', 'description' => 'Extra 15% on wraps (in store)', 'type' => 'percent', 'value' => 15, 'applies_to' => 'pos', 'expires_at' => now()->addWeeks(2)]);
        Coupon::create(['code' => 'EID2026', 'description' => 'Expired Eid campaign', 'type' => 'percent', 'value' => 20, 'is_active' => true, 'starts_at' => now()->subMonths(6), 'expires_at' => now()->subMonths(5)]);

        // Popup
        Popup::create([
            'title' => 'Get 10% off your first order',
            'type' => 'discount',
            'content' => 'Use the code below at checkout. Valid on orders above Rs. 2,000.',
            'button_text' => 'Start shopping',
            'button_url' => '/shop',
            'coupon_code' => 'WELCOME10',
            'display_on' => 'home',
            'delay_seconds' => 4,
            'frequency' => 'once_per_session',
            'is_active' => true,
        ]);
        Popup::create([
            'title' => 'Eid timings',
            'type' => 'announcement',
            'content' => 'Our store is open till 2am during the last ten days of Ramadan.',
            'display_on' => 'all',
            'is_active' => false,
        ]);
    }
}
