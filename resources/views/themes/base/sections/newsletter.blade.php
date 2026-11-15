<section class="sf-section">
    <div class="container">
        <div class="sf-card-panel text-center py-5 px-3" style="background: var(--sf-surface)">
            <div class="mx-auto" style="max-width: 560px">
                <i class="ri-mail-send-line" style="font-size: 40px; color: var(--sf-primary-text, var(--sf-primary))"></i>
                <h2 class="sf-title mt-2" style="font-size: clamp(24px, 3vw, 36px)">{{ $section->title ?: 'Stay in the loop' }}</h2>
                @if ($section->subtitle)<p class="sf-sub mx-auto">{{ $section->subtitle }}</p>@endif
                @if ($subscribed)
                    <div class="mt-4 fw-semibold"><i class="ri-checkbox-circle-fill text-success"></i> You’re subscribed — watch your inbox.</div>
                @else
                    <form wire:submit="subscribe" class="d-flex gap-2 mt-4 flex-column flex-sm-row">
                        <input type="email" class="form-control @error('newsletterEmail') is-invalid @enderror" placeholder="you@example.com" wire:model="newsletterEmail" aria-label="Email address" required>
                        <button type="submit" class="btn btn-primary flex-shrink-0" wire:loading.attr="disabled">{{ $section->setting('button_text', 'Subscribe') }}</button>
                    </form>
                    @error('newsletterEmail')<div class="text-danger mt-2" style="font-size: 13px">{{ $message }}</div>@enderror
                @endif
            </div>
        </div>
    </div>
</section>
