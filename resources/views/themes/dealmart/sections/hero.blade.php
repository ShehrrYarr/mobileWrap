@php($slides = $section->setting('slides', []))
@php($show = $data['showcase'])
<section>
    <div class="container">
        <div class="dm-hero">
            <div class="dm-slider" x-data="slider({{ max(count($slides), 1) }}, 5500)">
                @foreach ($slides as $i => $s)
                    @php($img = ! empty($s['image']) ? upload_url($s['image']) : $show->get($i)?->imageUrl())
                    <div class="dm-slide {{ $i % 2 ? 'alt' : '' }}" x-show="i === {{ $i }}" x-transition.opacity.duration.500ms @if ($i) x-cloak @endif>
                        <div>
                            @if (! empty($s['badge']))<span class="dm-slide-tag">{{ $s['badge'] }}</span>@endif
                            <h2>{{ $s['title'] ?? '' }}</h2>
                            <p class="mb-4">{{ $s['subtitle'] ?? '' }}</p>
                            @if (! empty($s['button_text']))
                                <a href="{{ $s['button_url'] ?? route('shop') }}" wire:navigate class="btn btn-light btn-lg fw-bold" style="color: #E62E2D">{{ $s['button_text'] }} <i class="ri-arrow-right-line"></i></a>
                            @endif
                        </div>
                        @if ($img)<img src="{{ $img }}" alt="">@endif
                    </div>
                @endforeach
                @if (count($slides) > 1)
                    <div class="dm-dots">
                        @foreach ($slides as $i => $s)
                            <button type="button" :class="{ 'is-active': i === {{ $i }} }" @click="go({{ $i }})" aria-label="Slide {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="dm-tiles">
                <a href="{{ route('deals') }}" wire:navigate class="dm-tile t1 text-decoration-none" style="color: #1E1E28">
                    <span class="fw-bold text-uppercase" style="font-size: 12px; color: #E62E2D"><i class="ri-flashlight-fill"></i> Today only</span>
                    <h4>Flash deals</h4>
                    <span style="font-size: 14px">Up to 25% off bestsellers</span>
                    @if ($p = $show->get(2))<img src="{{ $p->imageUrl() }}" alt="">@endif
                </a>
                <a href="{{ route('shop') }}" wire:navigate class="dm-tile t2 text-decoration-none" style="color: #1E1E28">
                    <span class="fw-bold text-uppercase" style="font-size: 12px; color: #1F5EFF"><i class="ri-truck-line"></i> Free delivery</span>
                    <h4>Over {{ money(setting('shipping.free_threshold', 5000)) }}</h4>
                    <span style="font-size: 14px">Cash on Delivery nationwide</span>
                    @if ($p = $show->get(3))<img src="{{ $p->imageUrl() }}" alt="">@endif
                </a>
            </div>
        </div>
    </div>
</section>
