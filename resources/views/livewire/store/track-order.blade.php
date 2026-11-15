<?php

use App\Models\Customer;
use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Track your order')] class extends Component
{
    #[Url(except: '')]
    public string $order = '';

    #[Url(except: '')]
    public string $phone = '';

    public ?int $foundId = null;

    public bool $searched = false;

    public function mount(): void
    {
        if ($this->order && $this->phone) {
            $this->track();
        }
    }

    public function track(): void
    {
        $this->validate(['order' => 'required|string|max:30', 'phone' => 'required|string|max:20'], [], ['order' => 'order number', 'phone' => 'mobile number']);
        $this->searched = true;
        $this->foundId = Order::online()
            ->where('number', strtoupper(trim($this->order)))
            ->where('customer_phone', Customer::normalizePhone($this->phone))
            ->value('id');
    }

    public function with(): array
    {
        return ['found' => $this->foundId ? Order::with('items', 'statusHistories')->find($this->foundId) : null];
    }
}; ?>

<section class="sf-section">
    <div class="container" style="max-width: 820px">
        <div class="text-center mb-4">
            <h1 class="sf-title">Track your order</h1>
            <p class="sf-sub mx-auto">Enter your order number and the mobile number you ordered with.</p>
        </div>
        <form wire:submit="track" class="sf-card-panel mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-5"><label class="form-label" for="t-order">Order number</label><input id="t-order" class="form-control text-uppercase @error('order') is-invalid @enderror" wire:model="order" placeholder="MW-1001"></div>
                <div class="col-md-4"><label class="form-label" for="t-phone">Mobile number</label><input id="t-phone" class="form-control @error('phone') is-invalid @enderror" wire:model="phone" placeholder="03XX XXXXXXX"></div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit" wire:loading.attr="disabled">Track</button></div>
            </div>
            @if ($errors->any())<div class="text-danger mt-2" style="font-size: 13px">{{ $errors->first() }}</div>@endif
        </form>

        @if ($found)
            <div class="sf-card-panel">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div><h4 class="mb-0">{{ $found->number }}</h4><span class="sf-muted">Placed {{ $found->placed_at?->format('d M Y, h:i a') }}</span></div>
                    <div class="text-end"><div class="fw-bold fs-5">{{ money($found->grand_total) }}</div><span class="sf-muted">{{ $found->paymentMethodLabel() }} · {{ $found->paymentStatusLabel() }}</span></div>
                </div>
                @if ($found->status === 'cancelled')
                    <div class="alert alert-danger mb-0">This order was cancelled.</div>
                @else
                    <div class="sf-timeline my-4">
                        @foreach (\App\Models\Order::TRACKING_STEPS as $i => $step)
                            <div class="sf-tl-step {{ $i <= $found->trackingIndex() ? 'is-done' : '' }}"><div class="sf-tl-dot">@if ($i <= $found->trackingIndex())<i class="ri-check-line"></i>@endif</div>{{ \App\Models\Order::STATUSES[$step][0] }}</div>
                        @endforeach
                    </div>
                @endif
                <h6 class="mt-4">Updates</h6>
                <ul class="list-unstyled mb-0">
                    @foreach ($found->statusHistories as $h)
                        <li class="d-flex gap-3 py-2" style="border-bottom: 1px solid var(--sf-border)">
                            <span class="sf-muted text-nowrap" style="font-size: 13px; min-width: 130px">{{ $h->created_at->format('d M, h:i a') }}</span>
                            <span><b>{{ \App\Models\Order::STATUSES[$h->status][0] ?? ucfirst($h->status) }}</b>@if ($h->note) — {{ $h->note }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($searched)
            <div class="sf-empty sf-card-panel"><i class="ri-search-line"></i><h4>We couldn’t find that order</h4><p>Check the order number (e.g. MW-1001) and the phone number used at checkout.</p></div>
        @endif
    </div>
</section>
