{{-- Livewire-driven Bootstrap modal. Usage:
     <x-admin.modal :show="$showForm" close="closeForm" title="Edit" size="lg"> … <x-slot:footer>…</x-slot:footer></x-admin.modal> --}}
@props(['show' => false, 'close' => '$set(\'showForm\', false)', 'title' => '', 'size' => '', 'subtitle' => null])

@if ($show)
    <div class="modal fade show d-block mw-modal" tabindex="-1" role="dialog" aria-modal="true" wire:keydown.escape.window="{{ $close }}">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $size ? 'modal-'.$size : '' }}">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">{{ $title }}</h5>
                        @if ($subtitle)
                            <p class="text-muted fs-12 mb-0">{{ $subtitle }}</p>
                        @endif
                    </div>
                    <button type="button" class="btn-close" wire:click="{{ $close }}" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{ $slot }}
                </div>
                @isset($footer)
                    <div class="modal-footer">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif
