<?php

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Deal;
use App\Models\Product;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Deals & bundles')] class extends Component
{
    use InteractsWithCart;

    public function with(): array
    {
        $flash = Deal::live()->ofType('flash_sale')->with('items')->orderBy('ends_at')->get();
        $categoryDeals = Deal::live()->ofType('category_discount')->with('category')->get();

        return [
            'flash' => $flash->map(fn ($d) => ['deal' => $d, 'products' => Product::online()->withCardData()->whereIn('id', $d->items->pluck('product_id'))->get()]),
            'categoryDeals' => $categoryDeals,
            'bundles' => Deal::live()->ofType('bundle')->with(['items.product.colors.images', 'items.product.activeVariants', 'items.variant'])->get(),
            'compareDeals' => Product::online()->withCardData()->whereHas('activeVariants', fn ($v) => $v->whereColumn('compare_at_price', '>', 'retail_price'))->limit(8)->get(),
            'wishlist' => $this->wishlistIds(),
        ];
    }
}; ?>

<div>
    <section class="sf-section-sm">
        <div class="container">
            <div class="sf-eyebrow"><i class="ri-fire-fill"></i> Limited time</div>
            <h1 class="sf-title">Deals & bundles</h1>
            <p class="sf-sub">Flash sales, category-wide discounts and combo packs. Prices update automatically while each offer is live.</p>
        </div>
    </section>

    @foreach ($categoryDeals as $cd)
        <div class="container mb-4">
            <a href="{{ route('shop', ['category' => $cd->category?->slug]) }}" wire:navigate class="sf-card-panel d-flex flex-wrap align-items-center gap-3" style="border-color: var(--sf-primary)">
                <span class="sf-badge" style="font-size: 18px; padding: 10px 14px">{{ $cd->discountLabel() }}</span>
                <div class="flex-grow-1"><h4 class="mb-1">{{ $cd->title }}</h4><span class="sf-muted">{{ $cd->description }} @if ($cd->ends_at)· Ends {{ $cd->ends_at->diffForHumans() }}@endif</span></div>
                <span class="btn btn-primary">Shop {{ $cd->category?->name }} <i class="ri-arrow-right-line"></i></span>
            </a>
        </div>
    @endforeach

    @foreach ($flash as $f)
        <section class="sf-section pt-3">
            <div class="container">
                <div class="sf-section-head">
                    <div><div class="sf-eyebrow"><i class="ri-flashlight-fill"></i> {{ $f['deal']->discountLabel() }} off</div><h2 class="sf-title">{{ $f['deal']->title }}</h2>@if ($f['deal']->description)<p class="sf-sub">{{ $f['deal']->description }}</p>@endif</div>
                    @if ($f['deal']->ends_at)
                        <div class="sf-countdown" x-data="countdown('{{ $f['deal']->ends_at->toIso8601String() }}')">
                            <div class="sf-cd"><b x-text="d">00</b><small>days</small></div><span class="sf-cd-sep">:</span>
                            <div class="sf-cd"><b x-text="h">00</b><small>hrs</small></div><span class="sf-cd-sep">:</span>
                            <div class="sf-cd"><b x-text="m">00</b><small>min</small></div><span class="sf-cd-sep">:</span>
                            <div class="sf-cd"><b x-text="s">00</b><small>sec</small></div>
                        </div>
                    @endif
                </div>
                <div class="sf-grid">
                    @foreach ($f['products'] as $product)
                        @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    @if ($bundles->isNotEmpty())
        <section class="sf-section pt-3">
            <div class="container">
                <div class="sf-section-head"><div><h2 class="sf-title">Combo bundles</h2><p class="sf-sub">Everything you need, for less than buying separately.</p></div></div>
                <div class="row g-4">
                    @foreach ($bundles as $deal)
                        @include('themes.base.partials.bundle-card', ['deal' => $deal])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($compareDeals->isNotEmpty())
        <section class="sf-section pt-3">
            <div class="container">
                <div class="sf-section-head"><h2 class="sf-title">Price drops</h2></div>
                <div class="sf-grid">
                    @foreach ($compareDeals as $product)
                        @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($flash->isEmpty() && $bundles->isEmpty() && $categoryDeals->isEmpty())
        <div class="container"><div class="sf-empty sf-card-panel"><i class="ri-price-tag-3-line"></i><h4>No live deals right now</h4><p>Check back soon — or subscribe to hear about the next drop.</p><a href="{{ route('shop') }}" wire:navigate class="btn btn-primary">Shop all</a></div></div>
    @endif
</div>
