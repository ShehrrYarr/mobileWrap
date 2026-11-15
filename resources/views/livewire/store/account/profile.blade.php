<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Profile')] class extends Component
{
    public array $profile = [];

    public array $address = ['name' => '', 'phone' => '', 'address' => '', 'city' => ''];

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $me = auth('customer')->user();
        $this->profile = ['name' => $me->name, 'phone' => (string) $me->phone, 'email' => (string) $me->email];
        $a = $me->addresses()->first();
        $this->address = ['name' => $a?->name ?? $me->name, 'phone' => $a?->phone ?? (string) $me->phone, 'address' => $a?->address ?? (string) $me->address, 'city' => $a?->city ?? (string) $me->city];
    }

    public function saveProfile(): void
    {
        $me = auth('customer')->user();
        $this->profile['phone'] = Customer::normalizePhone($this->profile['phone']) ?? '';
        $this->validate([
            'profile.name' => 'required|string|max:120',
            'profile.phone' => ['required', 'regex:/^03\d{9}$/', Rule::unique('customers', 'phone')->ignore($me->id)],
            'profile.email' => ['nullable', 'email', Rule::unique('customers', 'email')->ignore($me->id)],
            'address.name' => 'required|string|max:120',
            'address.phone' => 'required|string|max:20',
            'address.address' => 'required|string|max:255',
            'address.city' => 'required|string|max:80',
        ], ['profile.phone.regex' => 'Enter a valid mobile number.'], ['profile.name' => 'name', 'profile.phone' => 'phone', 'address.address' => 'address', 'address.city' => 'city']);

        $me->update(['name' => $this->profile['name'], 'phone' => $this->profile['phone'], 'email' => $this->profile['email'] ?: null, 'address' => $this->address['address'], 'city' => $this->address['city']]);
        $me->addresses()->updateOrCreate(['is_default' => true], $this->address + ['label' => 'Home']);
        $this->dispatch('store-toast', type: 'success', message: 'Profile saved.');
    }

    public function changePassword(): void
    {
        $me = auth('customer')->user();
        $this->validate(['current_password' => 'required', 'password' => 'required|string|min:8|confirmed']);
        if (! Hash::check($this->current_password, $me->password)) {
            $this->addError('current_password', 'Your current password is incorrect.');

            return;
        }
        $me->update(['password' => $this->password]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('store-toast', type: 'success', message: 'Password updated.');
    }
}; ?>

<section class="sf-section">
    <div class="container">
        <h1 class="sf-title mb-4">Profile & address</h1>
        <div class="row g-4">
            <div class="col-lg-3">@include('themes.base.partials.account-nav')</div>
            <div class="col-lg-9">
                <form wire:submit="saveProfile" class="sf-card-panel mb-4">
                    <h5 class="mb-3">Your details</h5>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="p-name">Name</label><input id="p-name" class="form-control @error('profile.name') is-invalid @enderror" wire:model="profile.name"></div>
                        <div class="col-md-4"><label class="form-label" for="p-phone">Mobile</label><input id="p-phone" class="form-control @error('profile.phone') is-invalid @enderror" wire:model="profile.phone">@error('profile.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="p-email">Email</label><input id="p-email" type="email" class="form-control @error('profile.email') is-invalid @enderror" wire:model="profile.email">@error('profile.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div>
                    <h5 class="mt-4 mb-3">Delivery address</h5>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="a-name">Recipient</label><input id="a-name" class="form-control" wire:model="address.name"></div>
                        <div class="col-md-6"><label class="form-label" for="a-phone">Recipient phone</label><input id="a-phone" class="form-control" wire:model="address.phone"></div>
                        <div class="col-md-8"><label class="form-label" for="a-addr">Address</label><input id="a-addr" class="form-control @error('address.address') is-invalid @enderror" wire:model="address.address"></div>
                        <div class="col-md-4"><label class="form-label" for="a-city">City</label><input id="a-city" class="form-control @error('address.city') is-invalid @enderror" wire:model="address.city"></div>
                    </div>
                    <button class="btn btn-primary mt-4" type="submit">Save changes</button>
                </form>

                <form wire:submit="changePassword" class="sf-card-panel">
                    <h5 class="mb-3">Change password</h5>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="cp-cur">Current</label><input id="cp-cur" type="password" class="form-control @error('current_password') is-invalid @enderror" wire:model="current_password" autocomplete="current-password">@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="cp-new">New</label><input id="cp-new" type="password" class="form-control @error('password') is-invalid @enderror" wire:model="password" autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="cp-new2">Confirm</label><input id="cp-new2" type="password" class="form-control" wire:model="password_confirmation" autocomplete="new-password"></div>
                    </div>
                    <button class="btn btn-outline-ink mt-4" type="submit">Update password</button>
                </form>
            </div>
        </div>
    </div>
</section>
