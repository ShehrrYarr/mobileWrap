<?php

use App\Models\NewsletterSubscriber;
use App\Models\Popup;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $home = false;

    public ?int $popupId = null;

    public string $email = '';

    public bool $done = false;

    public function mount(bool $home = false): void
    {
        $this->home = $home;
        $this->popupId = Popup::live()
            ->when(! $home, fn ($q) => $q->where('display_on', 'all'))
            ->latest('id')->value('id');
    }

    public function subscribe(): void
    {
        $this->validate(['email' => 'required|email|max:190']);
        NewsletterSubscriber::firstOrCreate(['email' => strtolower($this->email)]);
        $this->done = true;
    }

    public function with(): array
    {
        return ['popup' => $this->popupId ? Popup::find($this->popupId) : null];
    }
}; ?>

<div>
    @if ($popup)
        <div x-data="{
                show: false,
                key: 'sf-popup-{{ $popup->id }}-{{ $popup->updated_at?->timestamp }}',
                init() {
                    const freq = @js($popup->frequency);
                    let seen = null;
                    try { seen = freq === 'once_per_session' ? sessionStorage.getItem(this.key) : localStorage.getItem(this.key); } catch (e) {}
                    if (freq === 'once_per_day' && seen && Date.now() - Number(seen) > 86400000) seen = null;
                    if (freq === 'always' || !seen) setTimeout(() => this.show = true, {{ (int) $popup->delay_seconds * 1000 }});
                },
                close() {
                    this.show = false;
                    try { (@js($popup->frequency) === 'once_per_session' ? sessionStorage : localStorage).setItem(this.key, String(Date.now())); } catch (e) {}
                }
             }" x-on:keydown.escape.window="close()">
            <template x-teleport="body">
                <div class="sf-popup-wrap" x-show="show" x-transition.opacity x-cloak @click.self="close()">
                    <div class="sf-popup" x-show="show" x-transition.scale.90 role="dialog" aria-modal="true" aria-label="{{ $popup->title }}">
                        <button type="button" class="sf-icon-btn sf-popup-close" @click="close()" aria-label="Close"><i class="ri-close-line"></i></button>
                        @if ($popup->imageUrl())
                            <img src="{{ $popup->imageUrl() }}" alt="" class="mb-3 rounded-sf" style="max-height: 160px">
                        @else
                            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border-radius: 50%; background: var(--sf-primary); color: var(--sf-primary-contrast); font-size: 30px">
                                <i class="{{ ['discount' => 'ri-coupon-3-line', 'newsletter' => 'ri-mail-star-line', 'announcement' => 'ri-megaphone-line'][$popup->type] }}"></i>
                            </div>
                        @endif
                        <h3 class="mb-2" style="font-size: 26px">{{ $popup->title }}</h3>
                        @if ($popup->content)<p class="sf-muted mb-4">{{ $popup->content }}</p>@endif

                        @if ($popup->type === 'discount' && $popup->coupon_code)
                            <div x-data="{ copied: false }" class="mb-4">
                                <button type="button" class="sf-code bg-transparent" style="color: var(--sf-text)" @click="navigator.clipboard?.writeText(@js($popup->coupon_code)); copied = true; setTimeout(() => copied = false, 1800)">
                                    {{ $popup->coupon_code }} <i :class="copied ? 'ri-check-line' : 'ri-file-copy-line'" style="font-size: 18px"></i>
                                </button>
                                <div class="sf-muted mt-2" style="font-size: 12px" x-text="copied ? 'Copied! Paste it at checkout.' : 'Tap to copy'"></div>
                            </div>
                        @endif

                        @if ($popup->type === 'newsletter')
                            @if ($done)
                                <p class="fw-semibold"><i class="ri-checkbox-circle-fill text-success"></i> You’re on the list!</p>
                            @else
                                <form wire:submit="subscribe" class="d-flex gap-2">
                                    <input type="email" class="form-control" wire:model="email" placeholder="you@example.com" required aria-label="Email">
                                    <button class="btn btn-primary" type="submit">Join</button>
                                </form>
                                @error('email')<div class="text-danger mt-1" style="font-size: 13px">{{ $message }}</div>@enderror
                            @endif
                        @elseif ($popup->button_text)
                            <a href="{{ $popup->button_url ?: route('shop') }}" wire:navigate class="btn btn-primary btn-lg w-100" @click="close()">{{ $popup->button_text }}</a>
                        @endif
                        <button type="button" class="btn btn-link sf-muted mt-2" style="font-size: 13px" @click="close()">No thanks</button>
                    </div>
                </div>
            </template>
        </div>
    @endif
</div>
