<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Resolves selling prices: tier (retail / wholesale) + live deals
 * (flash sales and category discounts apply to retail prices only).
 */
class PricingService
{
    protected ?Collection $deals = null;

    public function liveDeals(): Collection
    {
        return $this->deals ??= Deal::live()
            ->whereIn('type', ['flash_sale', 'category_discount'])
            ->with('items:id,deal_id,product_id,product_variant_id')
            ->orderByDesc('discount_value')
            ->get();
    }

    public function forgetDeals(): void
    {
        $this->deals = null;
    }

    public function dealFor(Product $product): ?Deal
    {
        $deals = $this->liveDeals();

        $flash = $deals->first(fn (Deal $d) => $d->type === 'flash_sale' && $d->items->contains('product_id', $product->id));
        if ($flash) {
            return $flash;
        }

        $categoryIds = array_filter([$product->category_id, $product->category?->parent_id]);

        return $deals->first(fn (Deal $d) => $d->type === 'category_discount' && in_array($d->category_id, $categoryIds));
    }

    /** ['price' => float, 'compare' => ?float, 'deal' => ?Deal] */
    public function variantPrice(ProductVariant $variant, string $tier = 'retail', ?Product $product = null): array
    {
        $product ??= $variant->product;
        $base = $variant->basePrice($tier);

        if ($tier === 'wholesale') {
            return ['price' => $base, 'compare' => null, 'deal' => null];
        }

        if ($product && ($deal = $this->dealFor($product))) {
            $price = $deal->apply($base);
            if ($price < $base) {
                return ['price' => $price, 'compare' => $base, 'deal' => $deal];
            }
        }

        $compare = $variant->compare_at_price && (float) $variant->compare_at_price > $base ? (float) $variant->compare_at_price : null;

        return ['price' => $base, 'compare' => $compare, 'deal' => null];
    }

    /** Price range for product cards. */
    public function productSummary(Product $product): array
    {
        $variants = $product->relationLoaded('activeVariants') ? $product->activeVariants : $product->activeVariants()->get();

        if ($variants->isEmpty()) {
            return ['min' => 0.0, 'max' => 0.0, 'compare' => null, 'deal' => null, 'percent' => 0];
        }

        $prices = $variants->map(fn ($v) => $this->variantPrice($v, 'retail', $product));
        $cheapest = $prices->sortBy('price')->first();
        $percent = $cheapest['compare'] ? (int) round((1 - $cheapest['price'] / $cheapest['compare']) * 100) : 0;

        return [
            'min' => (float) $prices->min('price'),
            'max' => (float) $prices->max('price'),
            'compare' => $cheapest['compare'],
            'deal' => $cheapest['deal'],
            'percent' => $percent,
        ];
    }
}
