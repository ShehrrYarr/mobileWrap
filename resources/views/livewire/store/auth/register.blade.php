<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Create account')] class extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register()
    {
        $this->phone = Customer::normalizePhone($this->phone) ?? '';
        $this->email = strtolower(trim($this->email));

        $this->validate([
            'name' => 'required|string|max:120',
            'phone' => ['required', 'regex:/^03\d{9}$/'],
            'email' => 'nullable|email|max:190',
            'password' => 'required|string|min:8|confirmed',
        ], ['phone.regex' => 'Enter a valid mobile number, e.g. 0300 1234567.']);

        // Claim an existing guest/POS customer record with the same phone (keeps their order history)
        $customer = Customer::where('phone', $this->phone)->first();
        if ($customer?->hasAccount()) {
            $this->addError('phone', 'An account with this number already exists — please sign in.');

            return;
        }
        if ($this->email && Customer::where('email', $this->email)->when($customer, fn ($q) => $q->whereKeyNot($customer->id))->exists()) {
            $this->addError('email', 'This email is already registered.');

            return;
        }

        $customer ??= new Customer(['type' => 'retail']);
        $customer->fill(['name' => $this->name, 'phone' => $this->phone, 'email' => $this->email ?: $customer->email, 'password' => $this->password, 'is_active' => true])->save();

        Auth::guard('customer')->login($customer, true);
        session()->regenerate();
        session()->flash('store_toast', ['type' => 'success', 'message' => 'Your account is ready!']);

        return $this->redirectIntended(route('account'), navigate: true);
    }
}; ?>

<section class="sf-section">
    <div class="container" style="max-width: 520px">
        <div class="sf-card-panel">
            <h1 class="sf-title text-center mb-1" style="font-size: 32px">Create your account</h1>
            <p class="sf-muted text-center mb-4">Track orders, save a wishlist and check out in seconds.</p>
            <form wire:submit="register" class="d-grid gap-3">
                <div><label class="form-label" for="r-name">Full name</label><input id="r-name" class="form-control @error('name') is-invalid @enderror" wire:model="name" autocomplete="name">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div><label class="form-label" for="r-phone">Mobile number</label><input id="r-phone" type="tel" class="form-control @error('phone') is-invalid @enderror" wire:model="phone" placeholder="03XX XXXXXXX" autocomplete="tel">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div><label class="form-label" for="r-email">Email <span class="sf-muted fw-normal">(optional)</span></label><input id="r-email" type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email" autocomplete="email">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label" for="r-pass">Password</label><input id="r-pass" type="password" class="form-control @error('password') is-invalid @enderror" wire:model="password" autocomplete="new-password"></div>
                    <div class="col-6"><label class="form-label" for="r-pass2">Confirm</label><input id="r-pass2" type="password" class="form-control" wire:model="password_confirmation" autocomplete="new-password"></div>
                    @error('password')<div class="text-danger" style="font-size: 13px">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary btn-lg" type="submit" wire:loading.attr="disabled">Create account</button>
            </form>
            <p class="text-center sf-muted mt-4 mb-0">Already have an account? <a href="{{ route('login') }}" wire:navigate class="fw-semibold text-decoration-underline">Sign in</a></p>
        </div>
    </div>
</section>
