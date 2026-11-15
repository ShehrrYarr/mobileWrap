@php($c = $product->cardData())
@php($p = $c['price'])
<article class="sf-card st-card {{ $c['in_stock'] ? '' : 'is-oos' }}" x-data="{ img: @js($c['image']), alt: @js($c['image_alt']), sw: null }">
    <div class="position-relative">
        <a href="{{ $c['url'] }}" :href="sw ? '{{ $c['url'] }}?colour=' + sw : '{{ $c['url'] }}'" wire:navigate class="sf-card-media {{ $c['image_alt'] ? 'has-alt' : '' }}" style="aspect-ratio: 4 / 5">
            <img src="{{ $c['image'] }}" :src="img" alt="{{ $product->name }}" class="sf-card-img" loading="lazy">
            @if ($c['image_alt'])<img src="{{ $c['image_alt'] }}" :src="alt || img" alt="" class="sf-card-img-alt" loading="lazy">@endif
        </a>
        <div class="sf-badges">
            @if (! $c['in_stock'])<span class="sf-badge sf-badge--oos">Sold out</span>
            @elseif ($p['percent'] > 0)<span class="sf-badge">−{{ $p['percent'] }}%</span>
            @elseif ($c['is_new'])<span class="sf-badge" style="background: var(--sf-bg); color: var(--sf-text)">New</span>@endif
        </div>
        <button type="button" class="sf-wish" wire:click="toggleWishlist({{ $product->id }})" aria-label="Save"><i class="{{ in_array($product->id, $wishlist ?? []) ? 'ri-heart-fill' : 'ri-heart-line' }}"></i></button>
        @if ($c['single_variant_id'] && $c['in_stock'])
            <button type="button" class="st-quick" wire:click="addToCart({{ $c['single_variant_id'] }})">Quick add — {{ money($p['min']) }}</button>
        @else
            <a href="{{ $c['url'] }}" wire:navigate class="st-quick text-center">Choose options</a>
        @endif
    </div>
    <div class="sf-card-body">
        <div class="d-flex justify-content-between align-items-baseline gap-2">
            <a href="{{ $c['url'] }}" wire:navigate class="sf-card-title">{{ $product->name }}</a>
            @include('themes.base.partials.price', ['p' => $p])
        </div>
        @if (count($c['swatches']) > 1)
            <div class="sf-dots">
                @foreach ($c['swatches'] as $s)
                    <button type="button" class="sf-dot" style="width: 12px; height: 12px; background: {{ $s['hex'] }}" :class="{ 'is-active': sw === {{ $s['id'] }} }" title="{{ $s['name'] }}" @mouseenter="img = @js($s['image']); alt = @js($s['image_alt']); sw = {{ $s['id'] }}"></button>
                @endforeach
                <span class="sf-dots-more ms-1">{{ count($c['swatches']) }} colours</span>
            </div>
        @else
            <span class="sf-card-cat">{{ $c['category'] }}</span>
        @endif
    </div>
</article>
