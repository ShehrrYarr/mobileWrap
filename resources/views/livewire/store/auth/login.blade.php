<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Sign in')] class extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $remember = true;

    public function signIn()
    {
        $this->validate(['login' => 'required|string', 'password' => 'required|string'], [], ['login' => 'email or phone']);

        $key = 'customer-login|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            $this->addError('login', 'Too many attempts. Please wait a minute.');

            return;
        }

        $field = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $value = $field === 'phone' ? Customer::normalizePhone($this->login) : strtolower(trim($this->login));

        if (! Auth::guard('customer')->attempt([$field => $value, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($key);
            $this->addError('login', 'Those details don’t match an account.');

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();
        session()->flash('store_toast', ['type' => 'success', 'message' => 'Welcome back, '.Str::before(auth('customer')->user()->name, ' ').'!']);

        return $this->redirectIntended(route('account'), navigate: true);
    }
}; ?>

<section class="sf-section">
    <div class="container" style="max-width: 480px">
        <div class="sf-card-panel">
            <h1 class="sf-title text-center mb-1" style="font-size: 32px">Welcome back</h1>
            <p class="sf-muted text-center mb-4">Sign in to track orders and checkout faster.</p>
            <form wire:submit="signIn" class="d-grid gap-3">
                <div>
                    <label class="form-label" for="l-login">Email or mobile number</label>
                    <input id="l-login" type="text" class="form-control @error('login') is-invalid @enderror" wire:model="login" autocomplete="username" autofocus>
                    @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div x-data="{ show: false }">
                    <label class="form-label" for="l-pass">Password</label>
                    <div class="position-relative">
                        <input id="l-pass" :type="show ? 'text' : 'password'" class="form-control @error('password') is-invalid @enderror" wire:model="password" autocomplete="current-password">
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y sf-muted" @click="show = !show" aria-label="Show password"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'"></i></button>
                    </div>
                </div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="l-rem" wire:model="remember"><label class="form-check-label" for="l-rem">Keep me signed in</label></div>
                <button class="btn btn-primary btn-lg" type="submit" wire:loading.attr="disabled">Sign in</button>
            </form>
            <p class="text-center sf-muted mt-4 mb-0">New here? <a href="{{ route('register') }}" wire:navigate class="fw-semibold text-decoration-underline">Create an account</a></p>
            <p class="text-center sf-muted mt-2 mb-0" style="font-size: 13px">Ordered as a guest before? Register with the same phone number to see those orders.</p>
        </div>
    </div>
</section>
