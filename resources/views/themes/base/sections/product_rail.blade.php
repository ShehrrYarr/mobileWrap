@if ($data['products']->isNotEmpty())
    @php($source = $section->setting('source', 'featured'))
    @php($link = route('shop', array_filter(['sort' => $source === 'new' ? 'new' : ($source === 'bestsellers' ? 'popular' : null), 'sale' => $source === 'sale' ? 1 : null])))
    <section class="sf-section" x-data="rail">
        <div class="container">
            <div class="sf-section-head">
                <div>
                    @if ($section->title)<h2 class="sf-title">{{ $section->title }}</h2>@endif
                    @if ($section->subtitle)<p class="sf-sub">{{ $section->subtitle }}</p>@endif
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ $link }}" wire:navigate class="sf-link-arrow">View all <i class="ri-arrow-right-line"></i></a>
                    <div class="sf-rail-nav d-none d-md-flex">
                        <button type="button" class="sf-rail-btn" @click="scroll(-1)" aria-label="Previous"><i class="ri-arrow-left-s-line"></i></button>
                        <button type="button" class="sf-rail-btn" @click="scroll(1)" aria-label="Next"><i class="ri-arrow-right-s-line"></i></button>
                    </div>
                </div>
            </div>
            <div class="sf-rail" x-ref="track">
                @foreach ($data['products'] as $product)
                    @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                @endforeach
            </div>
        </div>
    </section>
@endif
