@php($c = $product->cardData())
@php($p = $c['price'])
<article class="sf-card dm-card {{ $c['in_stock'] ? '' : 'is-oos' }}" x-data="{ img: @js($c['image']), sw: null }">
    <a href="{{ $c['url'] }}" :href="sw ? '{{ $c['url'] }}?colour=' + sw : '{{ $c['url'] }}'" wire:navigate class="sf-card-media">
        <img src="{{ $c['image'] }}" :src="img" alt="{{ $product->name }}" class="sf-card-img" loading="lazy">
    </a>
    <div class="sf-badges">
        @if (! $c['in_stock'])<span class="sf-badge sf-badge--oos">Sold out</span>
        @elseif ($p['percent'] > 0)<span class="sf-badge">-{{ $p['percent'] }}%</span>
        @elseif ($c['is_new'])<span class="sf-badge sf-badge--new">NEW</span>@endif
    </div>
    <button type="button" class="sf-wish {{ in_array($product->id, $wishlist ?? []) ? 'is-on' : '' }}" wire:click="toggleWishlist({{ $product->id }})" aria-label="Save"><i class="{{ in_array($product->id, $wishlist ?? []) ? 'ri-heart-3-fill' : 'ri-heart-3-line' }}"></i></button>
    <div class="sf-card-body">
        <a href="{{ $c['url'] }}" wire:navigate class="sf-card-title">{{ $product->name }}</a>
        <div class="sf-price">
            <span class="sf-price-now">{{ money($p['min']) }}</span>
            @if ($p['compare'])<span class="sf-price-was">{{ money($p['compare']) }}</span>@endif
        </div>
        <div class="d-flex align-items-center justify-content-between" style="min-height: 18px">
            @if (count($c['swatches']) > 1)
                <div class="sf-dots">
                    @foreach (array_slice($c['swatches'], 0, 4) as $s)
                        <button type="button" class="sf-dot" style="width: 13px; height: 13px; background: {{ $s['hex'] }}" :class="{ 'is-active': sw === {{ $s['id'] }} }" title="{{ $s['name'] }}" @mouseenter="img = @js($s['image']); sw = {{ $s['id'] }}"></button>
                    @endforeach
                </div>
            @else
                <span></span>
            @endif
            @if ($c['low_stock'])<span class="dm-sold" style="color: #E62E2D">Only few left</span>@elseif ($p['min'] >= (float) setting('shipping.free_threshold', 5000))<span class="dm-sold"><i class="ri-truck-line"></i> Free delivery</span>@endif
        </div>
        <div class="mt-2">
            @if ($c['single_variant_id'] && $c['in_stock'])
                <button type="button" class="dm-add" wire:click="addToCart({{ $c['single_variant_id'] }})" wire:loading.attr="disabled" wire:target="addToCart({{ $c['single_variant_id'] }})"><i class="ri-shopping-cart-2-line me-1"></i>Add to cart</button>
            @else
                <a href="{{ $c['url'] }}" wire:navigate class="dm-add d-block text-center">{{ $c['in_stock'] ? 'Choose options' : 'View item' }}</a>
            @endif
        </div>
    </div>
</article>
