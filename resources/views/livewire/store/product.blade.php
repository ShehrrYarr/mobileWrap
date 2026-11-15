<?php

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    use InteractsWithCart;

    public int $productId;

    #[Url(as: 'colour', except: null)]
    public ?int $colorId = null;

    /** attribute_id => attribute_value_id */
    public array $selected = [];

    public int $qty = 1;

    public int $image = 0;

    public string $tab = 'description';

    protected ?Product $loaded = null;

    public function mount(string $slug): void
    {
        $product = Product::online()->where('slug', $slug)->firstOrFail();
        $this->productId = $product->id;
        DB::table('products')->where('id', $product->id)->increment('views');

        $product = $this->product();
        if (! $this->colorId || ! $product->colors->contains('id', $this->colorId)) {
            $this->colorId = $product->defaultColor()?->id;
        }
        $this->pickDefaults();
    }

    protected function product(): Product
    {
        return $this->loaded ??= Product::with([
            'category.parent', 'brand', 'colors.images',
            'activeVariants.attributeValues.attribute', 'activeVariants.stock', 'deviceModels.brand',
        ])->findOrFail($this->productId);
    }

    /** Variants that belong to the chosen colour. */
    protected function colorVariants(): Collection
    {
        $variants = $this->product()->activeVariants;
        $forColor = $variants->where('product_color_id', $this->colorId);

        return $forColor->isNotEmpty() ? $forColor : $variants;
    }

    protected function pickDefaults(): void
    {
        $variants = $this->colorVariants();
        $current = $this->matchVariant();
        if ($current) {
            return;
        }
        $pick = $variants->first(fn ($v) => $v->availableQuantity() > 0 && $v->is_default) ?? $variants->first(fn ($v) => $v->availableQuantity() > 0) ?? $variants->first();
        $this->selected = $pick ? $pick->attributeValues->mapWithKeys(fn ($av) => [$av->attribute_id => $av->id])->all() : [];
    }

    protected function matchVariant(): ?ProductVariant
    {
        $wanted = collect($this->selected)->map(fn ($v) => (int) $v)->sort()->values()->all();

        return $this->colorVariants()->first(function ($v) use ($wanted) {
            return $v->attributeValues->pluck('id')->sort()->values()->all() === $wanted;
        });
    }

    public function selectColor(int $id): void
    {
        if (! $this->product()->colors->contains('id', $id)) {
            return;
        }
        $this->colorId = $id;
        $this->image = 0;
        $this->pickDefaults();
    }

    public function selectValue(int $attributeId, int $valueId): void
    {
        $this->selected[$attributeId] = $valueId;
        if (! $this->matchVariant()) {
            // keep the clicked value, adjust the others to an existing combination
            $alt = $this->colorVariants()->first(fn ($v) => $v->attributeValues->contains('id', $valueId));
            if ($alt) {
                $this->selected = $alt->attributeValues->mapWithKeys(fn ($av) => [$av->attribute_id => $av->id])->all();
            }
        }
    }

    public function changeQty(int $delta): void
    {
        $max = (int) max(1, $this->matchVariant()?->availableQuantity() ?? 1);
        $this->qty = max(1, min($max, $this->qty + $delta));
    }

    public function add(bool $buyNow = false)
    {
        $variant = $this->matchVariant();
        if (! $variant) {
            $this->dispatch('store-toast', type: 'error', message: 'Please choose an available option.');

            return;
        }

        if ($this->addToCart($variant->id, $this->qty, ! $buyNow) && $buyNow) {
            return $this->redirectRoute('checkout', navigate: true);
        }
    }

    public function rendering($view): void
    {
        $view->title($this->product()->meta_title ?: $this->product()->name);
    }

    public function with(): array
    {
        $product = $this->product();
        $variant = $this->matchVariant();
        $pricing = app(PricingService::class);
        $color = $product->colors->firstWhere('id', $this->colorId) ?? $product->defaultColor();
        $images = $color?->images->isNotEmpty() ? $color->images : ($product->defaultColor()?->images ?? collect());

        // Options available for the chosen colour
        $options = [];
        foreach ($this->colorVariants() as $v) {
            foreach ($v->attributeValues as $av) {
                $options[$av->attribute_id]['name'] = $av->attribute->name;
                $options[$av->attribute_id]['values'][$av->id] = $av->value;
            }
        }
        $colorStock = $product->colors->mapWithKeys(fn ($c) => [$c->id => $product->activeVariants->where('product_color_id', $c->id)->sum(fn ($v) => $v->availableQuantity())]);

        $price = $variant ? $pricing->variantPrice($variant, 'retail', $product) : ['price' => $product->priceSummary()['min'], 'compare' => null, 'deal' => null];
        $available = $variant ? ($product->track_stock ? $variant->availableQuantity() : 99) : 0;

        return [
            'product' => $product,
            'variant' => $variant,
            'color' => $color,
            'images' => $images->map(fn ($i) => upload_url($i->path))->values(),
            'swatches' => $product->swatches(),
            'colorStock' => $colorStock,
            'options' => $options,
            'price' => $price,
            'available' => $available,
            'gallery' => theme()->option('gallery', 'thumbs-bottom'),
            'devices' => $product->deviceModels->groupBy(fn ($m) => $m->brand?->name ?? 'Other'),
            'related' => Product::online()->withCardData()->where('category_id', $product->category_id)->whereKeyNot($product->id)->inRandomOrder()->limit(4)->get(),
            'wishlist' => $this->wishlistIds(),
            'freeFrom' => setting('shipping.free_enabled', true) ? (float) setting('shipping.free_threshold', 5000) : null,
            'shareUrl' => 'https://wa.me/?text='.rawurlencode($product->name.' — '.route('product', $product->slug)),
        ];
    }
}; ?>

<div>
    <div class="container">
        <nav class="sf-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" wire:navigate>Home</a><i class="ri-arrow-right-s-line"></i>
            @if ($product->category?->parent)<a href="{{ route('shop', ['category' => $product->category->parent->slug]) }}" wire:navigate>{{ $product->category->parent->name }}</a><i class="ri-arrow-right-s-line"></i>@endif
            <a href="{{ route('shop', ['category' => $product->category?->slug]) }}" wire:navigate>{{ $product->category?->name }}</a><i class="ri-arrow-right-s-line"></i>
            <span>{{ $product->name }}</span>
        </nav>

        <div class="row g-4 g-lg-5">
            {{-- Gallery: swatches swap these images without a page reload --}}
            <div class="col-lg-7">
                <div class="sf-gallery sf-gallery--{{ $gallery }}" x-data="{ preview: null, init() { $wire.$watch('colorId', () => this.preview = null); $wire.$watch('image', () => this.preview = null); } }" x-on:sf-preview.window="preview = $event.detail">
                    @if ($gallery === 'grid')
                        @foreach ($images as $i => $url)
                            <div class="sf-gallery-main" wire:key="g-{{ $colorId }}-{{ $i }}">
                                <img src="{{ $url }}" @if ($i === 0) :src="preview || '{{ $url }}'" @endif alt="{{ $product->name }} — {{ $color?->name }}">
                            </div>
                        @endforeach
                    @else
                        <div class="sf-gallery-main" wire:key="main-{{ $colorId }}-{{ $image }}">
                            <img src="{{ $images[$image] ?? $images[0] ?? $product->imageUrl() }}" :src="preview || '{{ $images[$image] ?? $images[0] ?? $product->imageUrl() }}'" alt="{{ $product->name }} — {{ $color?->name }}">
                            <div class="sf-gallery-loading" wire:loading.flex wire:target="selectColor"><span class="spinner-border spinner-border-sm"></span></div>
                            @if ($price['compare'])
                                <div class="sf-badges"><span class="sf-badge">-{{ (int) round((1 - $price['price'] / $price['compare']) * 100) }}%</span></div>
                            @endif
                        </div>
                        @if ($images->count() > 1)
                            <div class="sf-thumbs">
                                @foreach ($images as $i => $url)
                                    <button type="button" class="sf-thumb {{ $i === $image ? 'is-active' : '' }}" wire:click="$set('image', {{ $i }})" wire:key="t-{{ $colorId }}-{{ $i }}" aria-label="Image {{ $i + 1 }}"><img src="{{ $url }}" alt=""></button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Buy box --}}
            <div class="col-lg-5">
                <div style="position: sticky; top: 110px">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            @if ($product->brand)<a href="{{ route('shop', ['brand' => $product->brand->slug]) }}" wire:navigate class="sf-eyebrow mb-0">{{ $product->brand->name }}</a>@endif
                            <h1 class="sf-pdp-title">{{ $product->name }}</h1>
                        </div>
                        <button type="button" class="sf-wish position-static flex-shrink-0 {{ in_array($product->id, $wishlist) ? 'is-on' : '' }}" style="width: 46px; height: 46px" wire:click="toggleWishlist({{ $product->id }})" aria-label="Save to wishlist">
                            <i class="{{ in_array($product->id, $wishlist) ? 'ri-heart-3-fill' : 'ri-heart-3-line' }}"></i>
                        </button>
                    </div>
                    @if ($product->short_description)<p class="sf-muted mb-3">{{ $product->short_description }}</p>@endif

                    <div class="sf-pdp-price mb-2">
                        <div class="sf-price">
                            <span class="sf-price-now">{{ money($price['price']) }}</span>
                            @if ($price['compare'])
                                <span class="sf-price-was">{{ money($price['compare']) }}</span>
                                <span class="sf-badge">Save {{ money($price['compare'] - $price['price']) }}</span>
                            @endif
                        </div>
                    </div>
                    @if ($price['deal'] && $price['deal']->ends_at)
                        <div class="d-flex align-items-center gap-2 mb-3" style="font-size: 14px" x-data="countdown('{{ $price['deal']->ends_at->toIso8601String() }}')">
                            <i class="ri-flashlight-fill text-primary-sf"></i><strong>{{ $price['deal']->title }}</strong>
                            <span class="sf-muted">ends in <b class="text-body" x-text="(d !== '00' ? d + 'd ' : '') + h + 'h ' + m + 'm ' + s + 's'"></b></span>
                        </div>
                    @endif

                    <hr style="border-color: var(--sf-border); opacity: 1" class="my-4">

                    @if ($swatches->isNotEmpty())
                        <div class="mb-4">
                            <div class="sf-opt-label">Colour: <span>{{ $color?->name }}</span></div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($swatches as $sw)
                                    @php($first = $sw->images->first())
                                    <button type="button"
                                            class="sf-swatch {{ $sw->id === $colorId ? 'is-active' : '' }} {{ ($colorStock[$sw->id] ?? 0) <= 0 && $product->track_stock ? 'is-oos' : '' }}"
                                            style="background: {{ $sw->hex_code }}"
                                            title="{{ $sw->name }}" aria-label="{{ $sw->name }}" aria-pressed="{{ $sw->id === $colorId ? 'true' : 'false' }}"
                                            @if ($first) x-on:click="$dispatch('sf-preview', '{{ upload_url($first->path) }}')" @endif
                                            wire:click="selectColor({{ $sw->id }})"></button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @foreach ($options as $attrId => $opt)
                        <div class="mb-4" wire:key="opt-{{ $attrId }}">
                            <div class="sf-opt-label">{{ $opt['name'] }}: <span>{{ $opt['values'][$selected[$attrId] ?? 0] ?? 'Choose' }}</span></div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($opt['values'] as $valueId => $value)
                                    @php($stockForValue = $product->activeVariants->where('product_color_id', $colorId)->filter(fn ($v) => $v->attributeValues->contains('id', $valueId))->sum(fn ($v) => $v->availableQuantity()))
                                    <button type="button" class="sf-option {{ ($selected[$attrId] ?? null) == $valueId ? 'is-active' : '' }} {{ $stockForValue <= 0 && $product->track_stock ? 'is-oos' : '' }}" wire:click="selectValue({{ $attrId }}, {{ $valueId }})">{{ $value }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="mb-3">
                        @if (! $variant)
                            <span class="sf-stock is-out">This combination is unavailable</span>
                        @elseif ($available <= 0)
                            <span class="sf-stock is-out">Out of stock — message us on WhatsApp for a restock date</span>
                        @elseif ($available <= 5 && $product->track_stock)
                            <span class="sf-stock is-low">Only {{ (int) $available }} left in stock</span>
                        @else
                            <span class="sf-stock">In stock, ready to ship</span>
                        @endif
                    </div>

                    <div class="d-flex gap-2 mb-3">
                        <div class="sf-qty">
                            <button type="button" wire:click="changeQty(-1)" aria-label="Decrease"><i class="ri-subtract-line"></i></button>
                            <input type="number" wire:model.blur="qty" min="1" aria-label="Quantity">
                            <button type="button" wire:click="changeQty(1)" aria-label="Increase"><i class="ri-add-line"></i></button>
                        </div>
                        <button type="button" class="btn btn-primary btn-lg flex-grow-1" wire:click="add" wire:loading.attr="disabled" wire:target="add" @disabled(! $variant || $available <= 0)>
                            <span wire:loading.remove wire:target="add"><i class="ri-shopping-bag-3-line me-1"></i>Add to cart</span>
                            <span wire:loading wire:target="add"><span class="spinner-border spinner-border-sm"></span></span>
                        </button>
                    </div>
                    <button type="button" class="btn btn-outline-ink btn-lg w-100 mb-4" wire:click="add(true)" @disabled(! $variant || $available <= 0)>Buy it now</button>

                    <div class="sf-perks sf-card-panel">
                        <div class="sf-perk"><i class="ri-truck-line"></i><div><strong>Delivery in {{ setting('shipping.estimated_days', '2–4 working days') }}</strong>@if ($freeFrom) Free on orders over {{ money($freeFrom) }} · otherwise {{ money(setting('shipping.flat_fee', 250)) }} @else {{ money(setting('shipping.flat_fee', 250)) }} flat @endif</div></div>
                        <div class="sf-perk"><i class="ri-hand-coin-line"></i><div><strong>Cash on Delivery</strong>Or pay by bank transfer at checkout</div></div>
                        <div class="sf-perk"><i class="ri-arrow-go-back-line"></i><div><strong>7-day exchange</strong>Unused items in original packaging</div></div>
                    </div>

                    <div class="d-flex flex-wrap gap-3 mt-3" style="font-size: 13px">
                        @if ($variant)<span class="sf-muted">SKU: {{ $variant->sku }}</span>@endif
                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="sf-muted"><i class="ri-whatsapp-line"></i> Share</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="sf-section pb-0" x-data="{ tab: 'description' }">
            <div class="sf-tabs mb-4">
                <button type="button" :class="{ 'is-active': tab === 'description' }" @click="tab = 'description'">Description</button>
                @if ($devices->isNotEmpty())<button type="button" :class="{ 'is-active': tab === 'fit' }" @click="tab = 'fit'">Compatible devices ({{ $devices->flatten()->count() }})</button>@endif
                <button type="button" :class="{ 'is-active': tab === 'delivery' }" @click="tab = 'delivery'">Delivery & returns</button>
            </div>
            <div x-show="tab === 'description'" class="row"><div class="col-lg-8"><div class="sf-prose">{{ $product->description ?: $product->short_description }}</div></div></div>
            @if ($devices->isNotEmpty())
                <div x-show="tab === 'fit'" x-cloak>
                    @foreach ($devices as $brandName => $models)
                        <div class="mb-3">
                            <div class="sf-opt-label mb-2">{{ $brandName }}</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($models as $m)<a href="{{ route('shop', ['device' => $m->slug]) }}" wire:navigate class="sf-chip">{{ $m->name }}</a>@endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            <div x-show="tab === 'delivery'" x-cloak class="row"><div class="col-lg-8"><div class="sf-prose">{{ setting('pages.shipping.content') ?? data_get(setting('pages'), 'shipping.content') }}

{{ data_get(setting('pages'), 'returns.content') }}</div></div></div>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="sf-section">
            <div class="container">
                <div class="sf-section-head"><h2 class="sf-title">You may also like</h2><a href="{{ route('shop', ['category' => $product->category?->slug]) }}" wire:navigate class="sf-link-arrow">More {{ strtolower($product->category?->name) }} <i class="ri-arrow-right-line"></i></a></div>
                <div class="sf-grid">
                    @foreach ($related as $rp)
                        @include(theme_view('partials.product-card'), ['product' => $rp, 'wishlist' => $wishlist])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Mobile sticky buy bar --}}
    <div class="sf-sticky-buy d-lg-none">
        <div class="flex-grow-1 min-w-0"><div class="fw-bold text-truncate" style="font-size: 14px">{{ $product->name }}</div><div class="sf-price-now" style="font-size: 16px">{{ money($price['price']) }}</div></div>
        <button type="button" class="btn btn-primary" wire:click="add" @disabled(! $variant || $available <= 0)>Add to cart</button>
    </div>
</div>
