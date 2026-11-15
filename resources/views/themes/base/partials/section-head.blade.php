@if ($section->title || $section->subtitle || isset($link))
    <div class="sf-section-head">
        <div>
            @isset($eyebrow)<div class="sf-eyebrow">{{ $eyebrow }}</div>@endisset
            @if ($section->title)<h2 class="sf-title">{{ $section->title }}</h2>@endif
            @if ($section->subtitle)<p class="sf-sub">{{ $section->subtitle }}</p>@endif
        </div>
        <div class="d-flex align-items-center gap-3">
            {{ $slot ?? '' }}
            @isset($link)<a href="{{ $link }}" wire:navigate class="sf-link-arrow">{{ $linkText ?? 'View all' }} <i class="ri-arrow-right-line"></i></a>@endisset
        </div>
    </div>
@endif
