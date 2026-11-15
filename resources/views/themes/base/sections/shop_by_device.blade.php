@php($brands = $data['deviceBrands']->filter(fn ($b) => $b->models->isNotEmpty())->values())
@if ($brands->isNotEmpty())
    <section class="sf-section" x-data="{ tab: {{ $brands->first()->id }} }">
        <div class="container">
            <div class="sf-section-head">
                <div>
                    @if ($section->title)<h2 class="sf-title">{{ $section->title }}</h2>@endif
                    @if ($section->subtitle)<p class="sf-sub">{{ $section->subtitle }}</p>@endif
                </div>
            </div>
            <div class="sf-device-tabs mb-4">
                @foreach ($brands as $b)
                    <button type="button" class="sf-chip" :class="{ 'is-active': tab === {{ $b->id }} }" @click="tab = {{ $b->id }}">{{ $b->name }}</button>
                @endforeach
            </div>
            @foreach ($brands as $b)
                <div x-show="tab === {{ $b->id }}" @if (! $loop->first) x-cloak @endif>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($b->models->take($data['limit'] ?: 12) as $m)
                            <a href="{{ route('shop', ['device' => $m->slug]) }}" wire:navigate class="sf-chip">
                                <i class="ri-smartphone-line"></i>{{ $m->name }} <small>{{ $m->products_count }}</small>
                            </a>
                        @endforeach
                        @if ($b->models->count() > ($data['limit'] ?: 12))
                            <a href="{{ route('shop') }}" wire:navigate class="sf-chip">+{{ $b->models->count() - ($data['limit'] ?: 12) }} more</a>
                        @endif
                    </div>
                </div>
            @endforeach
            <p class="sf-muted mt-4 mb-0" style="font-size: 14px"><i class="ri-scissors-cut-line"></i> Don’t see your model? Our laser cutter makes custom hydrogel, privacy glass & wraps in store for almost any phone.</p>
        </div>
    </section>
@endif
