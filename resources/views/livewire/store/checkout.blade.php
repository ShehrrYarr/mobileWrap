<?php

use App\Models\BankAccount;
use App\Models\Customer;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Title('Checkout')] class extends Component
{
    use WithFileUploads;

    public array $form = ['name' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'notes' => ''];

    public string $payment = '';

    public ?int $bankAccountId = null;

    public string $reference = '';

    public $receipt = null;

    public string $coupon = '';

    public bool $createAccount = false;

    public string $password = '';

    public function mount()
    {
        if (app(CartService::class)->isEmpty()) {
            return $this->redirectRoute('cart', navigate: true);
        }

        if ($customer = auth('customer')->user()) {
            $address = $customer->addresses()->first();
            $this->form = [
                'name' => $address?->name ?? $customer->name,
                'phone' => $address?->phone ?? (string) $customer->phone,
                'email' => (string) $customer->email,
                'address' => $address?->address ?? (string) $customer->address,
                'city' => $address?->city ?? (string) $customer->city,
                'notes' => '',
            ];
        }

        $this->payment = setting('checkout.cod_enabled', true) ? 'cod' : 'bank_transfer';
        $this->bankAccountId = BankAccount::forCheckout()->value('id');
    }

    public function applyCoupon(): void
    {
        $this->validate(['coupon' => 'required|string|max:40']);
        if ($error = app(CartService::class)->applyCoupon($this->coupon, auth('customer')->user())) {
            $this->addError('coupon', $error);

            return;
        }
        $this->coupon = '';
    }

    public function removeCoupon(): void
    {
        app(CartService::class)->removeCoupon();
    }

    public function placeOrder(OrderService $orders)
    {
        $methods = array_keys(array_filter([
            'cod' => setting('checkout.cod_enabled', true),
            'bank_transfer' => setting('checkout.bank_transfer_enabled', true),
        ]));

        $this->form['phone'] = Customer::normalizePhone($this->form['phone']) ?? '';
        $guest = ! auth('customer')->check();

        $this->validate([
            'form.name' => 'required|string|max:120',
            'form.phone' => ['required', 'regex:/^03\d{9}$/'],
            'form.email' => [($guest && $this->createAccount) ? 'required' : 'nullable', 'email', 'max:190'],
            'form.address' => 'required|string|max:255',
            'form.city' => 'required|string|max:80',
            'form.notes' => 'nullable|string|max:500',
            'payment' => 'required|in:'.implode(',', $methods),
            'bankAccountId' => $this->payment === 'bank_transfer' ? 'required|exists:bank_accounts,id' : 'nullable',
            'reference' => 'nullable|string|max:80',
            'receipt' => $this->payment === 'bank_transfer' ? 'required|image|max:5120' : 'nullable',
            'password' => ($guest && $this->createAccount) ? 'required|string|min:8' : 'nullable',
        ], [
            'form.phone.regex' => 'Enter a valid mobile number, e.g. 0300 1234567.',
            'receipt.required' => 'Upload a screenshot or photo of your transfer receipt.',
        ], [
            'form.name' => 'full name', 'form.phone' => 'mobile number', 'form.address' => 'address', 'form.city' => 'city', 'form.email' => 'email', 'bankAccountId' => 'bank account',
        ]);

        if ($guest && $this->createAccount) {
            $existing = Customer::where('phone', $this->form['phone'])->orWhere('email', $this->form['email'])->first();
            if ($existing?->hasAccount()) {
                $this->addError('form.email', 'An account already exists for this phone or email — please sign in.');

                return;
            }
        }

        try {
            $order = $orders->placeOnlineOrder([
                ...$this->form,
                'email' => $this->form['email'] ?: null,
                'payment_method' => $this->payment,
                'bank_account_id' => $this->bankAccountId,
                'payment_reference' => $this->reference ?: null,
            ], app(CartService::class), auth('customer')->user(), $this->receipt);
        } catch (ValidationException $e) {
            $this->addError('cart', collect($e->errors())->flatten()->first());

            return;
        }

        if ($guest && $this->createAccount && $order->customer) {
            $order->customer->forceFill(['password' => $this->password, 'email' => $order->customer->email ?: $this->form['email']])->save();
            auth('customer')->login($order->customer, true);
        }
        if (! $guest) {
            $c = auth('customer')->user();
            if (! $c->addresses()->exists()) {
                $c->addresses()->create(['label' => 'Home', 'name' => $this->form['name'], 'phone' => $this->form['phone'], 'address' => $this->form['address'], 'city' => $this->form['city'], 'is_default' => true]);
            }
        }

        session()->put('last_order', $order->number);
        $this->dispatch('cart-updated', count: 0);

        return $this->redirectRoute('order.thanks', ['number' => $order->number], navigate: true);
    }

    public function with(): array
    {
        $cart = app(CartService::class);

        return [
            'lines' => $cart->lines(),
            'totals' => $cart->totals(auth('customer')->user()),
            'banks' => BankAccount::forCheckout()->get(),
            'codEnabled' => (bool) setting('checkout.cod_enabled', true),
            'bankEnabled' => (bool) setting('checkout.bank_transfer_enabled', true),
            'cities' => ['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar', 'Quetta', 'Sialkot', 'Gujranwala', 'Hyderabad', 'Bahawalpur', 'Sargodha', 'Abbottabad'],
        ];
    }
}; ?>

<div>
    <section class="sf-section-sm">
        <div class="container">
            <nav class="sf-breadcrumb pt-0"><a href="{{ route('cart') }}" wire:navigate>Cart</a><i class="ri-arrow-right-s-line"></i><span>Checkout</span></nav>
            <h1 class="sf-title mb-4">Checkout</h1>

            @error('cart')<div class="alert alert-danger d-flex gap-2"><i class="ri-error-warning-line"></i>{{ $message }} <a href="{{ route('cart') }}" wire:navigate class="ms-auto fw-semibold">Update cart</a></div>@enderror

            <form wire:submit="placeOrder" class="row g-4">
                <div class="col-lg-7">
                    {{-- Contact & delivery --}}
                    <div class="sf-card-panel mb-4">
                        <h5 class="mb-3"><span class="sf-step">1</span>Delivery details</h5>
                        @guest('customer')
                            <p class="sf-muted" style="font-size: 14px">Have an account? <a href="{{ route('login') }}" wire:navigate class="fw-semibold text-decoration-underline">Sign in</a> for faster checkout — or continue as a guest.</p>
                        @endguest
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="co-name">Full name</label>
                                <input id="co-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model.blur="form.name" autocomplete="name">
                                @error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="co-phone">Mobile number</label>
                                <input id="co-phone" type="tel" class="form-control @error('form.phone') is-invalid @enderror" wire:model.blur="form.phone" placeholder="03XX XXXXXXX" autocomplete="tel">
                                @error('form.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="co-email">Email <span class="sf-muted fw-normal">(optional, for order updates)</span></label>
                                <input id="co-email" type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model.blur="form.email" autocomplete="email">
                                @error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="co-address">Address</label>
                                <input id="co-address" type="text" class="form-control @error('form.address') is-invalid @enderror" wire:model.blur="form.address" placeholder="House, street, area" autocomplete="street-address">
                                @error('form.address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="co-city">City</label>
                                <input id="co-city" type="text" list="pk-cities" class="form-control @error('form.city') is-invalid @enderror" wire:model.blur="form.city" autocomplete="address-level2">
                                <datalist id="pk-cities">@foreach ($cities as $c)<option value="{{ $c }}">@endforeach</datalist>
                                @error('form.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="co-notes">Order notes <span class="sf-muted fw-normal">(optional)</span></label>
                                <textarea id="co-notes" class="form-control" rows="2" wire:model.blur="form.notes" placeholder="Landmark, preferred delivery time, phone model to double-check…"></textarea>
                            </div>
                            @guest('customer')
                                <div class="col-12">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" id="co-acc" wire:model.live="createAccount"><label class="form-check-label" for="co-acc">Create an account to track orders and save my details</label></div>
                                    @if ($createAccount)
                                        <input type="password" class="form-control mt-2 @error('password') is-invalid @enderror" wire:model="password" placeholder="Choose a password (min 8 characters)" autocomplete="new-password">
                                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @endif
                                </div>
                            @endguest
                        </div>
                    </div>

                    {{-- Payment --}}
                    <div class="sf-card-panel">
                        <h5 class="mb-3"><span class="sf-step">2</span>Payment</h5>
                        <div class="d-grid gap-3">
                            @if ($codEnabled)
                                <label class="sf-choice {{ $payment === 'cod' ? 'is-active' : '' }}">
                                    <input type="radio" class="form-check-input mt-1" value="cod" wire:model.live="payment">
                                    <span class="sf-choice-icon"><i class="ri-hand-coin-line"></i></span>
                                    <span><b class="d-block">Cash on Delivery</b><span class="sf-muted" style="font-size: 14px">{{ setting('checkout.cod_note', 'Pay in cash when your parcel arrives.') }}</span></span>
                                </label>
                            @endif
                            @if ($bankEnabled && $banks->isNotEmpty())
                                <label class="sf-choice {{ $payment === 'bank_transfer' ? 'is-active' : '' }}">
                                    <input type="radio" class="form-check-input mt-1" value="bank_transfer" wire:model.live="payment">
                                    <span class="sf-choice-icon"><i class="ri-bank-line"></i></span>
                                    <span><b class="d-block">Bank transfer</b><span class="sf-muted" style="font-size: 14px">{{ setting('checkout.bank_note') }}</span></span>
                                </label>
                                @if ($payment === 'bank_transfer')
                                    <div class="d-grid gap-2">
                                        @foreach ($banks as $bank)
                                            <label class="sf-choice py-3 {{ $bankAccountId === $bank->id ? 'is-active' : '' }}" wire:key="bank-{{ $bank->id }}">
                                                <input type="radio" class="form-check-input mt-1" value="{{ $bank->id }}" wire:model.live="bankAccountId">
                                                <span class="flex-grow-1">
                                                    <b class="d-block">{{ $bank->bank_name }}</b>
                                                    <span style="font-size: 14px">{{ $bank->account_title }}</span>
                                                    <span class="d-block font-monospace" style="font-size: 14px" x-data="{ c: false }">{{ $bank->account_number }}
                                                        <button type="button" class="btn btn-link p-0 ms-1 sf-muted" style="font-size: 13px" @click.prevent="navigator.clipboard?.writeText('{{ $bank->account_number }}'); c = true; setTimeout(() => c = false, 1500)"><i :class="c ? 'ri-check-line' : 'ri-file-copy-line'"></i></button>
                                                    </span>
                                                    @if ($bank->iban)<span class="d-block sf-muted font-monospace" style="font-size: 12px">IBAN {{ $bank->iban }}</span>@endif
                                                </span>
                                            </label>
                                        @endforeach
                                        @error('bankAccountId')<div class="text-danger" style="font-size: 13px">{{ $message }}</div>@enderror
                                        <div class="row g-2 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label" for="co-ref">Transaction ID <span class="sf-muted fw-normal">(optional)</span></label>
                                                <input id="co-ref" type="text" class="form-control" wire:model="reference">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="co-receipt">Payment receipt</label>
                                                <input id="co-receipt" type="file" accept="image/*" class="form-control @error('receipt') is-invalid @enderror" wire:model="receipt">
                                                <div wire:loading wire:target="receipt" class="sf-muted" style="font-size: 13px">Uploading…</div>
                                                @error('receipt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            @if ($receipt && method_exists($receipt, 'temporaryUrl'))
                                                <div class="col-12"><img src="{{ $receipt->temporaryUrl() }}" alt="Receipt preview" class="rounded-sf" style="max-height: 140px"></div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endif
                            @error('payment')<div class="text-danger" style="font-size: 13px">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Summary --}}
                <div class="col-lg-5">
                    <div class="sf-card-panel" style="position: sticky; top: 110px">
                        <h5 class="mb-2">Order summary</h5>
                        <div style="max-height: 300px; overflow-y: auto" class="mb-2">
                            @foreach ($lines as $line)
                                <div class="sf-line py-3" wire:key="co-{{ $line['key'] }}">
                                    <div class="sf-line-img position-relative" style="width: 64px; height: 64px">
                                        <img src="{{ $line['image'] }}" alt="">
                                        <span class="sf-count" style="top: -6px; right: -6px">{{ $line['qty'] }}</span>
                                    </div>
                                    <div class="flex-grow-1 min-w-0"><div class="sf-line-title">{{ $line['name'] }}</div>@if ($line['label'])<div class="sf-line-meta">{{ $line['label'] }}</div>@endif</div>
                                    <div class="fw-semibold">{{ money($line['total']) }}</div>
                                </div>
                            @endforeach
                        </div>

                        @if ($totals['coupon_code'] && ! $totals['coupon_error'])
                            <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 14px">
                                <span><i class="ri-coupon-3-line"></i> {{ $totals['coupon_code'] }} applied</span>
                                <button type="button" class="sf-remove" wire:click="removeCoupon">Remove</button>
                            </div>
                        @else
                            <div class="d-flex gap-2 mb-1">
                                <input type="text" class="form-control text-uppercase @error('coupon') is-invalid @enderror" placeholder="Coupon code" wire:model="coupon" wire:keydown.enter.prevent="applyCoupon" aria-label="Coupon code">
                                <button type="button" class="btn btn-outline-ink" wire:click="applyCoupon">Apply</button>
                            </div>
                            @error('coupon')<div class="text-danger mb-2" style="font-size: 13px">{{ $message }}</div>@enderror
                            @if ($totals['coupon_error'])<div class="text-danger mb-2" style="font-size: 13px">{{ $totals['coupon_code'] }}: {{ $totals['coupon_error'] }}</div>@endif
                        @endif

                        <div class="sf-summary-row mt-2"><span>Subtotal</span><span>{{ money($totals['subtotal']) }}</span></div>
                        @if ($totals['coupon_discount'] > 0)<div class="sf-summary-row"><span>Discount</span><span class="sf-off">−{{ money($totals['coupon_discount']) }}</span></div>@endif
                        <div class="sf-summary-row"><span>Delivery</span><span>{{ $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' }}</span></div>
                        @if ($totals['free_shipping_enabled'] && $totals['free_shipping_remaining'] > 0)
                            <div class="sf-muted" style="font-size: 13px">Add {{ money($totals['free_shipping_remaining']) }} more for free delivery.</div>
                        @endif
                        <div class="sf-summary-total"><span>Total</span><span>{{ money($totals['total']) }}</span></div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-4" wire:loading.attr="disabled" wire:target="placeOrder,receipt">
                            <span wire:loading.remove wire:target="placeOrder"><i class="ri-lock-line me-1"></i>Place order · {{ money($totals['total']) }}</span>
                            <span wire:loading wire:target="placeOrder"><span class="spinner-border spinner-border-sm me-1"></span>Placing order…</span>
                        </button>
                        <p class="sf-muted text-center mt-3 mb-0" style="font-size: 12px">By placing your order you agree to our <a href="{{ route('page', 'terms') }}" wire:navigate class="text-decoration-underline">terms</a>.</p>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>
