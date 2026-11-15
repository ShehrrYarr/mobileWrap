<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Session cart for the storefront.
 * Structure: ['items' => [variantId => qty], 'bundles' => [dealId => qty], 'coupon' => ?string]
 */
class CartService
{
    public const KEY = 'store_cart';

    protected ?Collection $lines = null;

    public function __construct(protected PricingService $pricing) {}

    // ── Raw state ────────────────────────────────────────────────────

    public function raw(): array
    {
        return session(self::KEY, []) + ['items' => [], 'bundles' => [], 'coupon' => null];
    }

    protected function save(array $cart): void
    {
        session([self::KEY => $cart]);
        $this->lines = null;
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
        $this->lines = null;
    }

    public function isEmpty(): bool
    {
        $cart = $this->raw();

        return empty($cart['items']) && empty($cart['bundles']);
    }

    public function count(): int
    {
        $cart = $this->raw();

        return (int) (array_sum($cart['items']) + array_sum($cart['bundles']));
    }

    // ── Mutations (return an error message or null) ──────────────────

    public function add(int $variantId, int $qty = 1): ?string
    {
        $variant = ProductVariant::with(['product', 'stock'])->find($variantId);

        if (! $variant || ! $variant->is_active || ! $variant->product?->is_active || ! $variant->product->show_online) {
            return 'This item is not available.';
        }

        $cart = $this->raw();
        $newQty = ($cart['items'][$variantId] ?? 0) + max(1, $qty);

        if ($variant->product->track_stock && $newQty > $variant->availableQuantity()) {
            $left = (int) $variant->availableQuantity();

            return $left > 0 ? "Only {$left} left in stock." : 'This item is out of stock.';
        }

        $cart['items'][$variantId] = $newQty;
        $this->save($cart);

        return null;
    }

    public function update(int $variantId, int $qty): ?string
    {
        $cart = $this->raw();

        if ($qty <= 0) {
            unset($cart['items'][$variantId]);
            $this->save($cart);

            return null;
        }

        $variant = ProductVariant::with(['product', 'stock'])->find($variantId);
        if ($variant && $variant->product?->track_stock && $qty > $variant->availableQuantity()) {
            $qty = (int) $variant->availableQuantity();
            $cart['items'][$variantId] = max($qty, 0);
            if ($qty <= 0) {
                unset($cart['items'][$variantId]);
            }
            $this->save($cart);

            return "Only {$qty} available.";
        }

        $cart['items'][$variantId] = $qty;
        $this->save($cart);

        return null;
    }

    public function remove(int $variantId): void
    {
        $cart = $this->raw();
        unset($cart['items'][$variantId]);
        $this->save($cart);
    }

    public function addBundle(int $dealId, int $qty = 1): ?string
    {
        $deal = Deal::live()->ofType('bundle')->with('items.variant.stock', 'items.product.activeVariants.stock')->find($dealId);

        if (! $deal) {
            return 'This bundle is no longer available.';
        }

        $cart = $this->raw();
        $newQty = ($cart['bundles'][$dealId] ?? 0) + max(1, $qty);

        foreach ($deal->items as $item) {
            $variant = $item->variant ?? $item->product?->defaultVariant();
            if (! $variant || $variant->availableQuantity() < $item->quantity * $newQty) {
                return 'Part of this bundle is out of stock.';
            }
        }

        $cart['bundles'][$dealId] = $newQty;
        $this->save($cart);

        return null;
    }

    public function updateBundle(int $dealId, int $qty): void
    {
        $cart = $this->raw();
        if ($qty <= 0) {
            unset($cart['bundles'][$dealId]);
        } else {
            $cart['bundles'][$dealId] = $qty;
        }
        $this->save($cart);
    }

    public function applyCoupon(string $code, ?Customer $customer = null): ?string
    {
        $coupon = Coupon::findByCode($code);
        if (! $coupon) {
            return 'Coupon code not found.';
        }

        if ($error = $coupon->validationError($this->subtotal(), $customer, 'online')) {
            return $error;
        }

        $cart = $this->raw();
        $cart['coupon'] = $coupon->code;
        $this->save($cart);

        return null;
    }

    public function removeCoupon(): void
    {
        $cart = $this->raw();
        $cart['coupon'] = null;
        $this->save($cart);
    }

    // ── Read model ───────────────────────────────────────────────────

    /** Normalised cart lines for display and checkout. */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $cart = $this->raw();
        $lines = collect();

        if ($cart['items']) {
            $variants = ProductVariant::with(['product.category', 'product.colors.images', 'color.images', 'stock', 'attributeValues'])
                ->whereIn('id', array_keys($cart['items']))->get()->keyBy('id');

            foreach ($cart['items'] as $variantId => $qty) {
                $variant = $variants->get($variantId);
                if (! $variant || ! $variant->product || ! $variant->is_active) {
                    continue;
                }

                $price = $this->pricing->variantPrice($variant, 'retail', $variant->product);
                $image = $variant->color?->images->first()?->path;

                $lines->push([
                    'key' => 'v'.$variant->id,
                    'type' => 'item',
                    'id' => $variant->id,
                    'variant' => $variant,
                    'product' => $variant->product,
                    'name' => $variant->product->name,
                    'label' => $variant->label && $variant->label !== 'Standard' ? $variant->label : null,
                    'image' => $image ? upload_url($image) : $variant->product->imageUrl(),
                    'url' => route('product', $variant->product->slug),
                    'qty' => (int) $qty,
                    'max' => $variant->product->track_stock ? (int) $variant->availableQuantity() : 99,
                    'unit' => $price['price'],
                    'compare' => $price['compare'],
                    'total' => round($price['price'] * $qty, 2),
                    // per-unit composition; order placement multiplies by the line quantity
                    'components' => [['variant' => $variant, 'qty' => 1, 'unit' => $price['price']]],
                ]);
            }
        }

        if ($cart['bundles']) {
            $deals = Deal::live()->ofType('bundle')
                ->with(['items.variant.color.images', 'items.variant.stock', 'items.product.colors.images', 'items.product.activeVariants.stock'])
                ->whereIn('id', array_keys($cart['bundles']))->get()->keyBy('id');

            foreach ($cart['bundles'] as $dealId => $qty) {
                $deal = $deals->get($dealId);
                if (! $deal || ! $deal->bundle_price) {
                    continue;
                }

                $regular = $deal->bundleRegularTotal();
                $components = $deal->items->map(function ($item) use ($deal, $regular) {
                    $variant = $item->variant ?? $item->product?->defaultVariant();
                    $share = $regular > 0 && $variant ? ((float) $variant->retail_price * $item->quantity) / $regular : 0;

                    return [
                        'variant' => $variant,
                        'qty' => (int) $item->quantity,
                        'unit' => $item->quantity > 0 ? round((float) $deal->bundle_price * $share / $item->quantity, 2) : 0,
                    ];
                })->filter(fn ($c) => $c['variant'])->values()->all();

                $first = $deal->items->first()?->product;

                $lines->push([
                    'key' => 'b'.$deal->id,
                    'type' => 'bundle',
                    'id' => $deal->id,
                    'deal' => $deal,
                    'name' => $deal->title,
                    'label' => $deal->items->map(fn ($i) => ($i->quantity > 1 ? $i->quantity.'× ' : '').$i->product?->name)->implode(' + '),
                    'image' => $deal->bannerUrl() ?? $first?->imageUrl() ?? asset('assets/store/img/placeholder.svg'),
                    'url' => route('deals'),
                    'qty' => (int) $qty,
                    'max' => 20,
                    'unit' => (float) $deal->bundle_price,
                    'compare' => $regular > (float) $deal->bundle_price ? $regular : null,
                    'total' => round((float) $deal->bundle_price * $qty, 2),
                    'components' => $components,
                ]);
            }
        }

        return $this->lines = $lines;
    }

    public function subtotal(): float
    {
        return round((float) $this->lines()->sum('total'), 2);
    }

    /** Full price breakdown incl. coupon and shipping rules from settings. */
    public function totals(?Customer $customer = null): array
    {
        $subtotal = $this->subtotal();
        $savings = (float) $this->lines()->sum(fn ($l) => $l['compare'] ? ($l['compare'] - $l['unit']) * $l['qty'] : 0);

        $couponCode = $this->raw()['coupon'];
        $coupon = $couponCode ? Coupon::findByCode($couponCode) : null;
        $couponError = null;
        $couponDiscount = 0.0;

        if ($coupon) {
            $couponError = $coupon->validationError($subtotal, $customer, 'online');
            $couponDiscount = $couponError ? 0.0 : $coupon->discountFor($subtotal);
        }

        $fee = (float) setting('shipping.flat_fee', 250);
        $freeEnabled = (bool) setting('shipping.free_enabled', true);
        $threshold = (float) setting('shipping.free_threshold', 5000);
        $isFree = $subtotal <= 0 || ($freeEnabled && $subtotal >= $threshold);
        $shipping = $isFree ? 0.0 : $fee;

        return [
            'count' => $this->count(),
            'subtotal' => $subtotal,
            'savings' => round($savings, 2),
            'coupon' => $coupon,
            'coupon_code' => $couponCode,
            'coupon_error' => $couponError,
            'coupon_discount' => round($couponDiscount, 2),
            'shipping' => $shipping,
            'free_shipping_enabled' => $freeEnabled,
            'free_shipping_threshold' => $threshold,
            'free_shipping_remaining' => $freeEnabled ? max(0, $threshold - $subtotal) : null,
            'free_shipping_progress' => $freeEnabled && $threshold > 0 ? min(100, (int) round($subtotal / $threshold * 100)) : 0,
            'total' => round(max(0, $subtotal - $couponDiscount) + $shipping, 2),
        ];
    }
}
