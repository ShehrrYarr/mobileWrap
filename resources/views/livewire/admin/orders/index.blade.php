<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Online orders')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $payment = '';

    #[Url(except: '')]
    public string $search = '';

    public function updated($prop): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $counts = Order::online()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'orders' => Order::online()
                ->withCount('items')
                ->with(['payments' => fn ($q) => $q->latest('id')])
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->payment === 'verify', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', 'pending')->where('method', 'bank_transfer')))
                ->when($this->payment === 'unpaid', fn ($q) => $q->where('payment_status', 'unpaid'))
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$this->search}%")->orWhere('customer_name', 'like', "%{$this->search}%")->orWhere('customer_phone', 'like', "%{$this->search}%")))
                ->latest('placed_at')
                ->paginate(20),
            'counts' => $counts,
            'total' => $counts->sum(),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Online orders" subtitle="Orders placed on the storefront. Confirm, ship, and verify COD / bank-transfer payments." />

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button class="btn btn-sm {{ $status === '' ? 'btn-primary' : 'btn-light' }}" wire:click="$set('status', '')">All <span class="badge bg-white text-body ms-1">{{ $total }}</span></button>
        @foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'] as $s)
            <button class="btn btn-sm {{ $status === $s ? 'btn-primary' : 'btn-light' }}" wire:click="$set('status', '{{ $s }}')">
                {{ \App\Models\Order::STATUSES[$s][0] }} <span class="badge bg-{{ \App\Models\Order::STATUSES[$s][1] }}-subtle text-{{ \App\Models\Order::STATUSES[$s][1] }} ms-1">{{ $counts[$s] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-6 col-lg-4">
                    <div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Order #, customer name or phone" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <select class="form-select" wire:model.live="payment">
                        <option value="">Any payment</option>
                        <option value="verify">Bank transfers to verify</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Order</th><th>Customer</th><th>Items</th><th>Payment</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($pay = $order->payments->first())
                        <tr wire:key="o-{{ $order->id }}">
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="fw-semibold">{{ $order->number }}</a>
                                <div class="fs-12 text-muted">{{ $order->placed_at?->format('d M, h:i a') }}</div>
                            </td>
                            <td>{{ $order->customer_name }}<div class="fs-12 text-muted">{{ $order->customer_phone }} · {{ $order->shipping_city }}</div></td>
                            <td>{{ $order->items_count }}</td>
                            <td>
                                <div class="fs-13">{{ $order->paymentMethodLabel() }}</div>
                                @if ($pay && $pay->method === 'bank_transfer' && $pay->status === 'pending')
                                    <span class="badge bg-info-subtle text-info"><i class="ri-time-line"></i> Verify transfer</span>
                                @else
                                    <span class="badge bg-{{ $order->paymentStatusColor() }}-subtle text-{{ $order->paymentStatusColor() }}">{{ $order->paymentStatusLabel() }}</span>
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $order->statusColor() }}-subtle text-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></td>
                            <td class="text-end fw-semibold">{{ money($order->grand_total) }}</td>
                            <td class="text-end"><a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="btn btn-sm btn-light-primary icon-btn-sm"><i class="ri-arrow-right-line"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-admin.empty icon="ri-shopping-bag-3-line" title="No orders found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
    </div>
</div>
