<?php

namespace App\Services\Pos;

use App\Models\Coupon;
use App\Models\Customer;

/**
 * Pure totals calculator for the POS cart (used by the screen and by checkout).
 *
 * Line shapes:
 *   product: ['type' => 'product', 'variant_id', 'qty', 'price' (unit), 'discount' (line amount), …]
 *   laser:   ['type' => 'laser', 'qty', 'fee' (per piece), 'material_variant_id', 'device_model_id', 'side', 'width', 'height', …]
 */
class PosCart
{
    public static function lineTotal(array $line): float
    {
        if (($line['type'] ?? '') === 'laser') {
            return round(max(0, (float) $line['fee']) * max(1, (int) $line['qty']), 2);
        }

        return round(max(0, (float) $line['price'] * (float) $line['qty'] - (float) ($line['discount'] ?? 0)), 2);
    }

    /**
     * @param  array{type: string, value: float|string}  $discount  cart-level discount
     */
    public static function totals(array $lines, array $discount = [], ?string $couponCode = null, ?Customer $customer = null): array
    {
        $productGross = 0.0;
        $lineDiscounts = 0.0;
        $laser = 0.0;

        foreach ($lines as $line) {
            if (($line['type'] ?? '') === 'laser') {
                $laser += self::lineTotal($line);
            } else {
                $productGross += (float) $line['price'] * (float) $line['qty'];
                $lineDiscounts += min((float) ($line['discount'] ?? 0), (float) $line['price'] * (float) $line['qty']);
            }
        }

        $subtotal = round($productGross - $lineDiscounts + $laser, 2);

        $value = (float) ($discount['value'] ?? 0);
        $cartDiscount = ($discount['type'] ?? 'amount') === 'percent'
            ? round($subtotal * min(100, max(0, $value)) / 100, 2)
            : round(min(max(0, $value), $subtotal), 2);

        $coupon = null;
        $couponDiscount = 0.0;
        $couponError = null;
        if ($couponCode) {
            $coupon = Coupon::findByCode($couponCode);
            if (! $coupon) {
                $couponError = 'Coupon not found.';
            } elseif ($couponError = $coupon->validationError($subtotal - $cartDiscount, $customer, 'pos')) {
                // keep message
            } else {
                $couponDiscount = $coupon->discountFor($subtotal - $cartDiscount);
            }
        }

        $total = round(max(0, $subtotal - $cartDiscount - $couponDiscount), 2);

        return [
            'items' => array_sum(array_map(fn ($l) => (int) $l['qty'], $lines)),
            'product_gross' => round($productGross, 2),
            'line_discounts' => round($lineDiscounts, 2),
            'laser' => round($laser, 2),
            'subtotal' => $subtotal,
            'cart_discount' => $cartDiscount,
            'coupon' => $coupon,
            'coupon_discount' => round($couponDiscount, 2),
            'coupon_error' => $couponError,
            'total' => $total,
        ];
    }
}
