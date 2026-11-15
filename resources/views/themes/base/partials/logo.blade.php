{{-- Text or image logo. Pass $mark = true to show the square monogram. --}}
@php([$first, $rest] = store_nav()->nameParts())
<a href="{{ route('home') }}" wire:navigate class="sf-logo {{ $class ?? '' }}" aria-label="{{ store_name() }} home">
    @if ($logo = store_nav()->logoUrl())
        <img src="{{ $logo }}" alt="{{ store_name() }}">
    @else
        @if ($mark ?? false)
            <span class="sf-logo-mark">{{ strtoupper(substr($first, 0, 1).substr($rest ?: $first, 0, 1)) }}</span>
        @endif
        <span>{{ $first }}@if ($rest)<span class="sf-logo-accent">{{ $space ?? '' }}{{ $rest }}</span>@endif</span>
    @endif
</a>
