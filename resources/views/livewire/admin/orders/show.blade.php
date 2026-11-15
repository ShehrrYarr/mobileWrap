<?php

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\OrderService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Order')] class extends Component
{
    public Order $order;

    public string $newStatus = '';

    public string $note = '';

    public string $staffNotes = '';

    public array $verifyAccount = [];

    public function mount(Order $order): void
    {
        $this->order = $order;
        $this->newStatus = $order->status;
        $this->staffNotes = (string) $order->staff_notes;
        foreach ($order->payments as $p) {
            $this->verifyAccount[$p->id] = (string) ($p->bank_account_id ?? BankAccount::active()->value('id'));
        }
    }

    public function updateStatus(OrderService $orders): void
    {
        $this->validate(['newStatus' => 'required|in:'.implode(',', array_keys(Order::STATUSES)), 'note' => 'nullable|string|max:255']);
        $orders->changeStatus($this->order, $this->newStatus, $this->note ?: null);
        $this->note = '';
        $this->order->refresh();
        $this->dispatch('toast', type: 'success', message: 'Order marked '.$this->order->statusLabel().'.');
    }

    public function verify(int $paymentId, OrderService $orders): void
    {
        $payment = OrderPayment::where('order_id', $this->order->id)->findOrFail($paymentId);
        $orders->verifyPayment($payment, $payment->method === 'bank_transfer' ? (int) ($this->verifyAccount[$paymentId] ?? 0) : null);
        $this->order->refresh();
        $this->dispatch('toast', type: 'success', message: 'Payment verified and posted to the ledger.');
    }

    public function reject(int $paymentId, OrderService $orders): void
    {
        $orders->rejectPayment(OrderPayment::where('order_id', $this->order->id)->findOrFail($paymentId), 'Receipt could not be matched');
        $this->order->refresh();
        $this->dispatch('toast', type: 'warning', message: 'Payment rejected.');
    }

    public function saveNotes(): void
    {
        $this->order->update(['staff_notes' => $this->staffNotes ?: null]);
        $this->dispatch('toast', type: 'success', message: 'Notes saved.');
    }

    public function with(): array
    {
        $this->order->load(['items', 'payments.bankAccount', 'payments.cashDrawer', 'payments.verifier', 'statusHistories.user', 'customer']);

        return [
            'banks' => BankAccount::active()->get(),
            'whatsapp' => 'https://wa.me/'.preg_replace('/^0/', '92', (string) $this->order->customer_phone).'?text='.rawurlencode('Assalam o Alaikum '.$this->order->customer_name.', your '.store_name().' order '.$this->order->number.' is now '.strtolower($this->order->statusLabel()).'.'),
        ];
    }
}; ?>

<div>
    <x-admin.page-header :title="'Order '.$order->number" :crumbs="['Online orders' => route('admin.orders.index')]">
        <x-slot:actions>
            <span class="badge bg-{{ $order->statusColor() }}-subtle text-{{ $order->statusColor() }} fs-13 py-2 px-3">{{ $order->statusLabel() }}</span>
            <span class="badge bg-{{ $order->paymentStatusColor() }}-subtle text-{{ $order->paymentStatusColor() }} fs-13 py-2 px-3">{{ $order->paymentStatusLabel() }}</span>
            <a href="{{ $whatsapp }}" target="_blank" class="btn btn-sm btn-success"><i class="ri-whatsapp-line me-1"></i>WhatsApp customer</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Items</h5></div>
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="{{ $item->imageUrl() }}" class="mw-thumb" alt="">
                                            <div>
                                                <div class="fw-medium">{{ $item->name }}</div>
                                                <div class="fs-12 text-muted">{{ $item->variant_label }} · {{ $item->sku }}
                                                    @if (! empty($item->meta['bundle']))<span class="badge bg-warning-subtle text-warning ms-1">{{ $item->meta['bundle'] }}</span>@endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">{{ $item->qty() }}</td>
                                    <td class="text-end">{{ money($item->unit_price) }}</td>
                                    <td class="text-end fw-semibold">{{ money($item->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-end text-muted">Subtotal</td><td class="text-end">{{ money($order->subtotal) }}</td></tr>
                            @if ($order->coupon_discount > 0)
                                <tr><td colspan="3" class="text-end text-muted">Coupon {{ $order->coupon_code }}</td><td class="text-end text-success">−{{ money($order->coupon_discount) }}</td></tr>
                            @endif
                            <tr><td colspan="3" class="text-end text-muted">Delivery</td><td class="text-end">{{ $order->shipping_fee > 0 ? money($order->shipping_fee) : 'Free' }}</td></tr>
                            <tr><td colspan="3" class="text-end fw-semibold">Grand total</td><td class="text-end fw-bold fs-16">{{ money($order->grand_total) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Payments</h5></div>
                <div class="card-body">
                    @foreach ($order->payments as $p)
                        <div class="border rounded-3 p-3 mb-3" wire:key="pay-{{ $p->id }}">
                            <div class="d-flex flex-wrap gap-3 align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">{{ $p->methodLabel() }} · {{ money($p->amount) }}</h6>
                                    <div class="fs-13 text-muted">
                                        @if ($p->bankAccount) To {{ $p->bankAccount->displayName() }} @endif
                                        @if ($p->reference) · Ref: <strong>{{ $p->reference }}</strong> @endif
                                    </div>
                                    @if ($p->status === 'verified')
                                        <div class="fs-12 text-success mt-1"><i class="ri-checkbox-circle-line"></i> Verified {{ $p->verified_at?->diffForHumans() }} by {{ $p->verifier?->name ?? 'system' }} → {{ $p->bankAccount?->bank_name ?? $p->cashDrawer?->displayName() }}</div>
                                    @elseif ($p->status === 'rejected')
                                        <div class="fs-12 text-danger mt-1"><i class="ri-close-circle-line"></i> Rejected</div>
                                    @endif
                                </div>
                                @if ($p->receiptUrl())
                                    <a href="{{ $p->receiptUrl() }}" target="_blank"><img src="{{ $p->receiptUrl() }}" class="mw-thumb mw-thumb-lg" alt="Payment receipt"></a>
                                @endif
                            </div>
                            @if ($p->status === 'pending' && $order->status !== 'cancelled')
                                <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                                    @if ($p->method === 'bank_transfer')
                                        <select class="form-select form-select-sm" style="max-width: 280px" wire:model="verifyAccount.{{ $p->id }}">
                                            @foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->displayName() }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-sm btn-success" x-data @click="confirmAction('Post {{ money($p->amount) }} to the selected bank account?', () => $wire.verify({{ $p->id }}), { icon: 'question', confirmText: 'Verify payment', title: 'Verify transfer' })"><i class="ri-check-line me-1"></i>Verify transfer</button>
                                        <button class="btn btn-sm btn-light-danger" wire:click="reject({{ $p->id }})">Reject</button>
                                    @else
                                        <button class="btn btn-sm btn-success" x-data @click="confirmAction('Record {{ money($p->amount) }} cash collected into the cash drawer?', () => $wire.verify({{ $p->id }}), { icon: 'question', confirmText: 'Mark collected', title: 'COD collected' })"><i class="ri-hand-coin-line me-1"></i>Mark COD collected</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Update status</h5></div>
                <div class="card-body">
                    <form wire:submit="updateStatus" class="d-flex flex-column gap-2">
                        <select class="form-select" wire:model="newStatus">
                            @foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'] as $s)
                                <option value="{{ $s }}">{{ \App\Models\Order::STATUSES[$s][0] }}</option>
                            @endforeach
                        </select>
                        <input type="text" class="form-control" wire:model="note" placeholder="Note (e.g. TCS tracking #)">
                        <button class="btn btn-primary" type="submit" @disabled($order->status === 'cancelled')>Update</button>
                        <p class="fs-12 text-muted mb-0">Cancelling puts the items back into stock.</p>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Customer & delivery</h5></div>
                <div class="card-body fs-13">
                    <h6 class="mb-1">{{ $order->customer_name }}
                        @if ($order->customer?->hasAccount())<span class="badge bg-primary-subtle text-primary ms-1">Account</span>@else<span class="badge bg-light text-muted ms-1">Guest</span>@endif
                    </h6>
                    <div><i class="ri-phone-line me-1 text-muted"></i>{{ $order->customer_phone }}</div>
                    @if ($order->customer_email)<div><i class="ri-mail-line me-1 text-muted"></i>{{ $order->customer_email }}</div>@endif
                    <div class="mt-2"><i class="ri-map-pin-line me-1 text-muted"></i>{{ $order->shipping_address }}, {{ $order->shipping_city }}</div>
                    @if ($order->notes)<div class="alert alert-warning py-2 px-3 mt-3 mb-0 fs-12"><strong>Customer note:</strong> {{ $order->notes }}</div>@endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Timeline</h5></div>
                <div class="card-body">
                    <ul class="mw-timeline">
                        @foreach ($order->statusHistories as $h)
                            <li>
                                <div class="fw-medium fs-13">{{ \App\Models\Order::STATUSES[$h->status][0] ?? ucfirst($h->status) }}</div>
                                @if ($h->note)<div class="fs-12">{{ $h->note }}</div>@endif
                                <div class="fs-11 text-muted">{{ $h->created_at->format('d M Y, h:i a') }} · {{ $h->user?->name ?? 'Customer' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Staff notes</h5></div>
                <div class="card-body">
                    <textarea class="form-control mb-2" rows="3" wire:model="staffNotes" placeholder="Internal only"></textarea>
                    <button class="btn btn-sm btn-light-primary" wire:click="saveNotes">Save notes</button>
                </div>
            </div>
        </div>
    </div>
</div>
