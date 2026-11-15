<?php

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(Settings::class)->set([
            // Store identity
            'store.name' => 'Mobile Wrap',
            'store.tagline' => 'Precision-cut protection & premium mobile accessories',
            'store.logo' => null,
            'store.email' => 'hello@mobilewrap.test',
            'store.phone' => '0300-1234567',
            'store.whatsapp' => '923001234567',
            'store.address' => 'Shop 12, Main Boulevard, Gulberg III',
            'store.city' => 'Lahore',
            'store.currency_symbol' => 'Rs.',
            'store.announcement' => 'Free delivery on orders over Rs. 5,000 · Cash on Delivery nationwide',
            'store.hours' => 'Mon–Sat 11am – 10pm · Sun 2pm – 9pm',
            'social.facebook' => 'https://facebook.com/',
            'social.instagram' => 'https://instagram.com/',
            'social.tiktok' => 'https://tiktok.com/',
            'social.youtube' => null,
            'seo.meta_title' => 'Mobile Wrap — Phone cases, screen protection, wraps & chargers in Pakistan',
            'seo.meta_description' => 'Shop laser-cut hydrogel & privacy glass, carbon wraps, cases, chargers, cables and power banks. Cash on Delivery across Pakistan.',

            // Storefront
            'store.theme' => 'noir',

            // Shipping
            'shipping.flat_fee' => 250,
            'shipping.free_enabled' => true,
            'shipping.free_threshold' => 5000,
            'shipping.estimated_days' => '2–4 working days',

            // Checkout
            'checkout.cod_enabled' => true,
            'checkout.bank_transfer_enabled' => true,
            'checkout.order_prefix' => 'MW-',
            'checkout.cod_note' => 'Pay in cash when your parcel arrives.',
            'checkout.bank_note' => 'Transfer the total to one of the accounts below and upload the receipt. We confirm within a few hours.',

            // Labels / POS (later phases)
            'labels.size' => '38x25',

            // CMS pages
            'pages' => [
                'about' => ['title' => 'About us', 'content' => "Mobile Wrap started as a single counter in Lahore with one laser cutter and a simple idea: protection should fit your exact phone, not “most phones”.\n\nToday we cut hydrogel, privacy glass and full-body wraps for hundreds of models, and stock the chargers, cables and cases we actually use ourselves."],
                'shipping' => ['title' => 'Shipping policy', 'content' => "We deliver across Pakistan in 2–4 working days. Orders above the free-delivery limit ship free; otherwise a flat delivery fee applies at checkout.\n\nCash on Delivery is available everywhere we deliver."],
                'returns' => ['title' => 'Returns & exchanges', 'content' => "Unused items in original packaging can be exchanged within 7 days. Custom laser-cut items are made for your device and can only be replaced if there is a cutting defect."],
                'privacy' => ['title' => 'Privacy policy', 'content' => "We only use your details to process and deliver your orders and, if you opt in, to send you offers. We never sell your data."],
                'terms' => ['title' => 'Terms & conditions', 'content' => "Prices are in Pakistani Rupees and may change without notice. Orders are confirmed once we verify stock and, for bank transfers, payment."],
            ],
        ]);
    }
}
