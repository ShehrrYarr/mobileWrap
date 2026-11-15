<?php

use App\Support\Settings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('admin.layouts.app')] #[Title('Settings')] class extends Component
{
    use WithFileUploads;

    #[Url]
    public string $tab = 'store';

    public array $s = [];

    public array $pages = [];

    public $logo = null;

    public const KEYS = [
        'store.name', 'store.tagline', 'store.logo', 'store.email', 'store.phone', 'store.whatsapp', 'store.address', 'store.city',
        'store.hours', 'store.announcement', 'social.facebook', 'social.instagram', 'social.tiktok', 'social.youtube',
        'seo.meta_title', 'seo.meta_description',
        'shipping.flat_fee', 'shipping.free_enabled', 'shipping.free_threshold', 'shipping.estimated_days',
        'checkout.cod_enabled', 'checkout.bank_transfer_enabled', 'checkout.order_prefix', 'checkout.cod_note', 'checkout.bank_note',
        'labels.size', 'receipt.header', 'receipt.footer', 'pos.receipt_format', 'pos.auto_print',
    ];

    public function mount(): void
    {
        foreach (self::KEYS as $key) {
            data_set($this->s, $key, setting($key));
        }
        $this->s['shipping']['free_enabled'] = (bool) ($this->s['shipping']['free_enabled'] ?? false);
        $this->s['checkout']['cod_enabled'] = (bool) ($this->s['checkout']['cod_enabled'] ?? true);
        $this->s['checkout']['bank_transfer_enabled'] = (bool) ($this->s['checkout']['bank_transfer_enabled'] ?? true);
        $this->s['pos']['auto_print'] = (bool) ($this->s['pos']['auto_print'] ?? false);
        $this->s['pos']['receipt_format'] = $this->s['pos']['receipt_format'] ?? '80mm';
        $this->s['receipt']['footer'] ??= 'Thank you for shopping with us! Exchange within 7 days with this receipt.';
        $this->pages = setting('pages', []);
    }

    public function save(Settings $settings): void
    {
        $this->validate([
            's.store.name' => 'required|string|max:80',
            's.store.email' => 'nullable|email',
            's.store.whatsapp' => ['nullable', 'regex:/^92\d{10}$/'],
            's.shipping.flat_fee' => 'required|numeric|min:0',
            's.shipping.free_threshold' => 'required|numeric|min:0',
            's.checkout.order_prefix' => 'required|string|max:10',
            'logo' => 'nullable|image|max:2048',
            'pages.*.title' => 'required|string|max:80',
        ], ['s.store.whatsapp.regex' => 'Use international format without + e.g. 923001234567.'], [
            's.store.name' => 'store name', 's.shipping.flat_fee' => 'delivery fee', 's.shipping.free_threshold' => 'free delivery amount',
        ]);

        if (! $this->s['checkout']['cod_enabled'] && ! $this->s['checkout']['bank_transfer_enabled']) {
            $this->addError('s.checkout.cod_enabled', 'Keep at least one payment method enabled.');

            return;
        }

        if ($this->logo) {
            data_set($this->s, 'store.logo', $this->logo->store('branding', 'uploads'));
            $this->logo = null;
        }

        $pairs = [];
        foreach (self::KEYS as $key) {
            $pairs[$key] = data_get($this->s, $key);
        }
        $pairs['shipping.flat_fee'] = (float) $pairs['shipping.flat_fee'];
        $pairs['shipping.free_threshold'] = (float) $pairs['shipping.free_threshold'];
        $pairs['pages'] = $this->pages;

        $settings->set($pairs);
        $this->dispatch('toast', type: 'success', message: 'Settings saved.');
    }
}; ?>

<div>
    <x-admin.page-header title="Settings" subtitle="Store details, delivery charges, checkout options and info pages.">
        <x-slot:actions>
            <button class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled"><i class="ri-save-3-line me-1"></i>Save settings</button>
        </x-slot:actions>
    </x-admin.page-header>

    <ul class="nav nav-tabs mb-4">
        @foreach (['store' => ['Store', 'ri-store-2-line'], 'shipping' => ['Delivery & checkout', 'ri-truck-line'], 'pos' => ['POS & receipts', 'ri-printer-line'], 'pages' => ['Pages', 'ri-pages-line'], 'seo' => ['SEO & social', 'ri-global-line']] as $k => [$label, $icon])
            <li class="nav-item"><button class="nav-link {{ $tab === $k ? 'active' : '' }}" wire:click="$set('tab', '{{ $k }}')"><i class="{{ $icon }} me-1"></i>{{ $label }}</button></li>
        @endforeach
    </ul>

    @if ($tab === 'store')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-0">
                    <div class="card-header"><h5 class="card-title mb-0">Store identity</h5></div>
                    <div class="card-body row g-3">
                        <div class="col-md-6"><label class="form-label">Store name *</label><input class="form-control @error('s.store.name') is-invalid @enderror" wire:model="s.store.name">@error('s.store.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label">Tagline</label><input class="form-control" wire:model="s.store.tagline"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" wire:model="s.store.phone"></div>
                        <div class="col-md-6"><label class="form-label">WhatsApp number</label><input class="form-control @error('s.store.whatsapp') is-invalid @enderror" wire:model="s.store.whatsapp" placeholder="923001234567">@error('s.store.whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control @error('s.store.email') is-invalid @enderror" wire:model="s.store.email">@error('s.store.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label">Opening hours</label><input class="form-control" wire:model="s.store.hours"></div>
                        <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" wire:model="s.store.address"></div>
                        <div class="col-md-4"><label class="form-label">City</label><input class="form-control" wire:model="s.store.city"></div>
                        <div class="col-12"><label class="form-label">Announcement bar</label><input class="form-control" wire:model="s.store.announcement" placeholder="Shown at the top of every storefront page (leave blank to hide)"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-0">
                    <div class="card-header"><h5 class="card-title mb-0">Logo</h5></div>
                    <div class="card-body text-center">
                        <div class="border rounded-3 p-4 mb-3 bg-light">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" style="max-height: 70px" alt="">
                            @elseif ($s['store']['logo'] ?? null)
                                <img src="{{ upload_url($s['store']['logo']) }}" style="max-height: 70px" alt="">
                            @else
                                <span class="fs-4 fw-bold">{{ $s['store']['name'] ?? '' }}</span>
                                <div class="fs-12 text-muted">Text logo (no image uploaded)</div>
                            @endif
                        </div>
                        <input type="file" class="form-control" wire:model="logo" accept="image/*">
                        @if ($s['store']['logo'] ?? null)
                            <button class="btn btn-link btn-sm text-danger mt-2" wire:click="$set('s.store.logo', null)">Use text logo instead</button>
                        @endif
                        <p class="fs-12 text-muted mt-2 mb-0">Transparent PNG/SVG works best. Each theme styles the text logo when no image is set.</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($tab === 'shipping')
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-0">
                    <div class="card-header"><h5 class="card-title mb-0">Delivery charges</h5></div>
                    <div class="card-body row g-3">
                        <div class="col-md-6"><label class="form-label">Delivery fee (Rs.) *</label><input type="number" min="0" class="form-control @error('s.shipping.flat_fee') is-invalid @enderror" wire:model.live.debounce.400ms="s.shipping.flat_fee">@error('s.shipping.flat_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label">Delivery time</label><input class="form-control" wire:model="s.shipping.estimated_days" placeholder="2–4 working days"></div>
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="fs" wire:model.live="s.shipping.free_enabled"><label class="form-check-label" for="fs">Offer free delivery above an order amount</label></div></div>
                        @if ($s['shipping']['free_enabled'])
                            <div class="col-md-6"><label class="form-label">Free delivery from (Rs.) *</label><input type="number" min="0" class="form-control @error('s.shipping.free_threshold') is-invalid @enderror" wire:model.live.debounce.400ms="s.shipping.free_threshold"></div>
                        @endif
                        <div class="col-12">
                            <div class="alert alert-light mb-0 fs-13">
                                <i class="ri-information-line me-1"></i>
                                Customers pay <strong>{{ money((float) ($s['shipping']['flat_fee'] ?: 0)) }}</strong> delivery
                                @if ($s['shipping']['free_enabled']) — free on orders of <strong>{{ money((float) ($s['shipping']['free_threshold'] ?: 0)) }}</strong> or more. The cart shows a progress bar towards free delivery.@else on every order.@endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-0">
                    <div class="card-header"><h5 class="card-title mb-0">Checkout</h5></div>
                    <div class="card-body row g-3">
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="cod" wire:model="s.checkout.cod_enabled"><label class="form-check-label" for="cod">Cash on Delivery</label></div>@error('s.checkout.cod_enabled')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label class="form-label fs-13">COD note</label><input class="form-control form-control-sm" wire:model="s.checkout.cod_note"></div>
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="bt" wire:model="s.checkout.bank_transfer_enabled"><label class="form-check-label" for="bt">Bank transfer (receipt upload)</label></div></div>
                        <div class="col-12"><label class="form-label fs-13">Bank transfer instructions</label><textarea class="form-control form-control-sm" rows="2" wire:model="s.checkout.bank_note"></textarea>
                            <div class="form-text fs-12">Accounts marked “show on checkout” in <a href="{{ route('admin.bank-accounts') }}" wire:navigate>Bank Accounts</a> are listed to customers.</div></div>
                        <div class="col-md-6"><label class="form-label fs-13">Order number prefix</label><input class="form-control form-control-sm" wire:model="s.checkout.order_prefix"></div>
                        <div class="col-md-6"><label class="form-label fs-13">Barcode label size</label><select class="form-select form-select-sm" wire:model="s.labels.size"><option value="38x25">38 × 25 mm</option><option value="50x30">50 × 30 mm</option><option value="40x30">40 × 30 mm</option></select></div>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($tab === 'pos')
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-0">
                    <div class="card-header"><h5 class="card-title mb-0">Receipts</h5></div>
                    <div class="card-body row g-3">
                        <div class="col-md-6"><label class="form-label">Default receipt</label><select class="form-select" wire:model="s.pos.receipt_format"><option value="80mm">Thermal 80 mm</option><option value="a4">A4 invoice</option></select></div>
                        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="ap" wire:model="s.pos.auto_print"><label class="form-check-label" for="ap">Print automatically after each sale</label></div></div>
                        <div class="col-12"><label class="form-label">Header line</label><input class="form-control" wire:model="s.receipt.header" placeholder="e.g. NTN 1234567-8 · Open 11am–10pm"></div>
                        <div class="col-12"><label class="form-label">Footer message</label><textarea class="form-control" rows="2" wire:model="s.receipt.footer"></textarea></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-0">
                    <div class="card-header"><h5 class="card-title mb-0">POS keyboard shortcuts</h5></div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 fs-14 d-grid gap-2">
                            <li><kbd>F2</kbd> Focus the scan / search box (a USB barcode scanner works there automatically)</li>
                            <li><kbd>F4</kbd> Add a laser cut job</li>
                            <li><kbd>F8</kbd> Hold the current sale</li>
                            <li><kbd>F9</kbd> Take payment</li>
                            <li><kbd>Enter</kbd> in the cash box completes the sale when fully paid</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($tab === 'pages')
        <div class="row g-4">
            @foreach ($pages as $slug => $page)
                <div class="col-lg-6" wire:key="pg-{{ $slug }}">
                    <div class="card mb-0 h-100">
                        <div class="card-header d-flex align-items-center"><h5 class="card-title mb-0 flex-grow-1">{{ $page['title'] }}</h5><a href="{{ route('page', $slug) }}" target="_blank" class="fs-12">/page/{{ $slug }} <i class="ri-external-link-line"></i></a></div>
                        <div class="card-body">
                            <input class="form-control mb-2" wire:model="pages.{{ $slug }}.title">
                            <textarea class="form-control" rows="6" wire:model="pages.{{ $slug }}.content"></textarea>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label">Homepage title (SEO)</label><input class="form-control" wire:model="s.seo.meta_title"></div>
                <div class="col-12"><label class="form-label">Homepage description (SEO)</label><textarea class="form-control" rows="2" wire:model="s.seo.meta_description"></textarea></div>
                @foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $net)
                    <div class="col-md-6"><label class="form-label text-capitalize"><i class="ri-{{ $net }}-line me-1"></i>{{ $net }}</label><input class="form-control" wire:model="s.social.{{ $net }}" placeholder="https://"></div>
                @endforeach
            </div>
        </div>
    @endif
</div>
