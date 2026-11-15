@php($c = $product->cardData())
@php($p = $c['price'])
<article class="sf-card cl-card {{ $c['in_stock'] ? '' : 'is-oos' }}" x-data="{ img: @js($c['image']), sw: null }">
    <a href="{{ $c['url'] }}" :href="sw ? '{{ $c['url'] }}?colour=' + sw : '{{ $c['url'] }}'" wire:navigate class="sf-card-media">
        <img src="{{ $c['image'] }}" :src="img" alt="{{ $product->name }}" class="sf-card-img" loading="lazy">
    </a>
    <div class="sf-badges" style="top: 20px; left: 20px">
        @if (! $c['in_stock'])<span class="sf-badge sf-badge--oos">Out of stock</span>
        @elseif ($p['percent'] > 0)<span class="sf-badge">Save {{ $p['percent'] }}%</span>
        @elseif ($c['is_new'])<span class="sf-badge sf-badge--new">New</span>@endif
    </div>
    <button type="button" class="sf-wish" style="top: 18px; right: 18px" wire:click="toggleWishlist({{ $product->id }})" aria-label="Save"><i class="{{ in_array($product->id, $wishlist ?? []) ? 'ri-heart-3-fill text-danger' : 'ri-heart-3-line' }}"></i></button>
    <div class="sf-card-body">
        <div class="d-flex justify-content-between align-items-center">
            <span class="sf-card-cat">{{ $c['brand'] ?? $c['category'] }}</span>
            @if ($c['in_stock'])<span class="cl-rating"><i class="ri-checkbox-circle-fill"></i> In stock</span>@endif
        </div>
        <a href="{{ $c['url'] }}" wire:navigate class="sf-card-title">{{ $product->name }}</a>
        @if (count($c['swatches']) > 1)
            <div class="sf-dots">
                @foreach (array_slice($c['swatches'], 0, 5) as $s)
                    <button type="button" class="sf-dot" style="background: {{ $s['hex'] }}" :class="{ 'is-active': sw === {{ $s['id'] }} }" title="{{ $s['name'] }}" @mouseenter="img = @js($s['image']); sw = {{ $s['id'] }}" @click="img = @js($s['image']); sw = {{ $s['id'] }}"></button>
                @endforeach
            </div>
        @endif
        @include('themes.base.partials.price', ['p' => $p])
        <div class="mt-2">
            @if ($c['single_variant_id'] && $c['in_stock'])
                <button type="button" class="cl-add" wire:click="addToCart({{ $c['single_variant_id'] }})" wire:loading.attr="disabled" wire:target="addToCart({{ $c['single_variant_id'] }})"><i class="ri-shopping-cart-line me-1"></i>Add to cart</button>
            @else
                <a href="{{ $c['url'] }}" wire:navigate class="cl-add d-block text-center">{{ $c['in_stock'] ? 'Select options' : 'View details' }}</a>
            @endif
        </div>
    </div>
</article>
