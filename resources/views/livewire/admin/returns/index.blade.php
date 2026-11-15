<?php

use App\Models\OrderReturn;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Returns')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'returns' => OrderReturn::with(['order:id,number,customer_name', 'exchangeOrder:id,number', 'user:id,name'])->withCount('items')
                ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%")->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$this->search}%")->orWhere('customer_name', 'like', "%{$this->search}%")))
                ->latest('id')->paginate(25),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Returns & exchanges" subtitle="Every return is tied to its invoice; restocked items appear in the stock ledger.">
        <x-slot:actions>
            <a href="{{ route('admin.returns.create') }}" wire:navigate class="btn btn-sm btn-primary"><i class="ri-arrow-go-back-line me-1"></i>New return / exchange</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-header"><div class="form-icon" style="max-width: 360px"><input type="search" class="form-control form-control-icon" placeholder="Return #, invoice # or customer" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Return</th><th>Invoice</th><th>Type</th><th>Refund</th><th class="text-center">Items</th><th>Reason</th><th>By</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    @forelse ($returns as $r)
                        <tr wire:key="ret-{{ $r->id }}">
                            <td><span class="fw-semibold">{{ $r->number }}</span><div class="fs-12 text-muted">{{ $r->created_at->format('d M, h:i a') }}</div></td>
                            <td>{{ $r->order?->number }}<div class="fs-12 text-muted">{{ $r->order?->customer_name }}</div></td>
                            <td>
                                @if ($r->type === 'exchange')
                                    <span class="badge bg-info-subtle text-info">Exchange</span>
                                    @if ($r->exchangeOrder)<div class="fs-12 text-muted">→ {{ $r->exchangeOrder->number }}</div>@else<div class="fs-12"><a href="{{ route('admin.pos', ['exchange' => $r->id]) }}">Credit unused — open POS</a></div>@endif
                                @else
                                    <span class="badge bg-warning-subtle text-warning">Return</span>
                                @endif
                            </td>
                            <td class="fs-13">{{ ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'customer_credit' => 'Khata credit', 'exchange' => 'Exchange credit', 'none' => 'No refund'][$r->refund_method] ?? $r->refund_method }}</td>
                            <td class="text-center">{{ $r->items_count }}</td>
                            <td class="fs-13 text-muted">{{ $r->reason }}</td>
                            <td class="fs-13">{{ $r->user?->name }}</td>
                            <td class="text-end fw-semibold">{{ money($r->refund_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-admin.empty icon="ri-arrow-go-back-line" title="No returns yet" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($returns->hasPages())<div class="card-footer">{{ $returns->links() }}</div>@endif
    </div>
</div>
