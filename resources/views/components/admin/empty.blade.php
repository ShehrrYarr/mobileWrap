@props(['icon' => 'ri-inbox-line', 'title' => 'Nothing here yet', 'text' => null])

<div class="text-center py-6 px-3">
    <div class="avatar avatar-xl avatar-item bg-light text-muted rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
        <i class="{{ $icon }} fs-2"></i>
    </div>
    <h6 class="mb-1">{{ $title }}</h6>
    @if ($text)
        <p class="text-muted fs-13 mb-3">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
