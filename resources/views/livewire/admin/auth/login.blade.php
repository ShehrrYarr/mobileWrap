<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.auth')] #[Title('Sign in')] class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = true;

    public function login()
    {
        $this->validate();

        $key = Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return;
        }

        if (! Auth::guard('web')->attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($key);
            $this->addError('email', 'These credentials do not match an active staff account.');

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();
        Auth::guard('web')->user()->forceFill(['last_login_at' => now()])->save();

        return $this->redirectIntended(route('admin.dashboard'));
    }
}; ?>

<div class="account-pages">
    <img src="{{ asset('assets/images/auth/auth_bg.jpeg') }}" alt="" class="auth-bg light">
    <img src="{{ asset('assets/images/auth/auth_bg_dark.jpg') }}" alt="" class="auth-bg dark">
    <div class="container">
        <div class="justify-content-center row gy-0">
            <div class="col-lg-6 auth-banners">
                <div class="bg-login card card-body m-0 h-100 border-0">
                    <img src="{{ asset('assets/images/auth/bg-img-2.png') }}" class="img-fluid auth-banner" alt="">
                    <div class="auth-contain">
                        <div class="text-center text-white my-4 p-4">
                            <h3 class="text-white">{{ store_name() }} back office</h3>
                            <p class="mt-3 mb-0">Inventory, online orders, storefront themes and — soon — the POS and laser cutter queue, all in one place.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="auth-box card card-body m-0 h-100 border-0 justify-content-center">
                    <div class="mb-5 text-center">
                        <span class="mw-logo-mark mb-3" style="width:48px;height:48px;font-size:16px">MW</span>
                        <h4 class="fw-normal">Welcome to <span class="fw-bold text-primary">{{ store_name() }}</span></h4>
                        <p class="text-muted mb-0">Sign in with your staff account.</p>
                    </div>
                    <form class="form-custom mt-6" wire:submit="login">
                        <div class="mb-5">
                            <label class="form-label" for="login-email">Email<span class="text-danger ms-1">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="login-email" wire:model="email" placeholder="you@example.com" autocomplete="username" autofocus>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-5" x-data="{ show: false }">
                            <label class="form-label" for="login-password">Password<span class="text-danger ms-1">*</span></label>
                            <div class="input-group">
                                <input :type="show ? 'text' : 'password'" id="login-password" class="form-control @error('password') is-invalid @enderror" wire:model="password" placeholder="Enter your password" autocomplete="current-password">
                                <button type="button" class="input-group-text bg-transparent" @click="show = !show" aria-label="Show password">
                                    <i class="text-muted" :class="show ? 'ri-eye-line' : 'ri-eye-off-line'"></i>
                                </button>
                            </div>
                            @error('password') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check form-check-sm d-flex align-items-center gap-2 mb-5">
                            <input class="form-check-input" type="checkbox" id="remember-me" wire:model="remember">
                            <label class="form-check-label" for="remember-me">Keep me signed in</label>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-2 w-100" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="login">Sign in</span>
                            <span wire:loading wire:target="login"><i class="ri-loader-2-fill"></i> Please wait…</span>
                        </button>
                        <p class="mb-0 mt-5 text-muted text-center fs-13">
                            <a href="{{ route('home') }}" class="link-primary"><i class="ri-arrow-left-line"></i> Back to the store</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
