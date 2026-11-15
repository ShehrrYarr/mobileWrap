@if ($data['categories']->isNotEmpty())
    <section class="sf-section">
        <div class="container">
            @include('themes.base.partials.section-head', ['link' => route('shop'), 'linkText' => 'Shop all'])
            <div class="sf-cats" style="--sf-cat-cols: {{ min(max($data['categories']->count(), 3), 8) }}">
                @foreach ($data['categories'] as $cat)
                    <a href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate class="sf-cat" wire:key="hc-{{ $section->id }}-{{ $cat->id }}">
                        <span class="sf-cat-img">
                            @if ($cat->image)
                                <img src="{{ upload_url($cat->image) }}" alt="" loading="lazy">
                            @else
                                <i class="{{ $cat->icon }}" style="font-size: 40px"></i>
                            @endif
                        </span>
                        <span>
                            <span class="sf-cat-name d-block">{{ $cat->name }}</span>
                            <span class="sf-cat-count">{{ $cat->products_count }} {{ Str::plural('item', $cat->products_count) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
