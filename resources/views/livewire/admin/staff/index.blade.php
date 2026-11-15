<?php

use App\Models\User;
use Database\Seeders\RolesAndStaffSeeder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Layout('admin.layouts.app')] #[Title('Staff & roles')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'Cashier', 'password' => '', 'is_active' => true];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $u = User::findOrFail($id);
        $this->editingId = $id;
        $this->form = ['name' => $u->name, 'email' => $u->email, 'phone' => (string) $u->phone, 'role' => $u->getRoleNames()->first() ?? 'Cashier', 'password' => '', 'is_active' => $u->is_active];
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:120',
            'form.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.phone' => 'nullable|string|max:30',
            'form.role' => 'required|exists:roles,name',
            'form.password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
        ], [], ['form.name' => 'name', 'form.email' => 'email', 'form.password' => 'password']);

        if ($this->editingId === auth('web')->id() && (! $this->form['is_active'] || $this->form['role'] !== 'Admin') && auth('web')->user()->hasRole('Admin')) {
            $this->addError('form.role', 'You can’t remove your own admin access.');

            return;
        }

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $user->fill(collect($this->form)->only(['name', 'email', 'phone', 'is_active'])->all());
        if (filled($this->form['password'])) {
            $user->password = $this->form['password'];
        }
        $user->save();
        $user->syncRoles([$this->form['role']]);

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $user->name.' saved.');
    }

    public function with(): array
    {
        return [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::with('permissions')->orderBy('id')->get(),
            'labels' => RolesAndStaffSeeder::PERMISSIONS,
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Staff & roles" subtitle="Preset roles control what each person can see and do.">
        <x-slot:actions><button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-user-add-line me-1"></i>Add staff</button></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover table-mw mb-0">
                        <thead class="table-light"><tr><th>Name</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($users as $u)
                                <tr wire:key="u-{{ $u->id }}">
                                    <td><div class="d-flex align-items-center gap-2"><span class="avatar avatar-sm avatar-item bg-primary text-white fs-12">{{ $u->initials() }}</span><div><div class="fw-medium">{{ $u->name }}</div><div class="fs-12 text-muted">{{ $u->email }}</div></div></div></td>
                                    <td><span class="badge bg-primary-subtle text-primary">{{ $u->roleLabel() }}</span></td>
                                    <td class="fs-13 text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td>@if ($u->is_active)<span class="badge bg-success-subtle text-success">Active</span>@else<span class="badge bg-light text-muted">Disabled</span>@endif</td>
                                    <td class="text-end"><button class="btn btn-sm btn-light-primary icon-btn-sm" wire:click="edit({{ $u->id }})"><i class="ri-pencil-line"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">What each role can do</h5></div>
                <div class="card-body d-flex flex-column gap-3">
                    @foreach ($roles as $role)
                        <div>
                            <h6 class="mb-1">{{ $role->name }}</h6>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($role->permissions as $perm)
                                    <span class="badge bg-light text-body fw-normal">{{ $labels[$perm->name] ?? $perm->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit staff member' : 'Add staff member'">
        <form wire:submit="save" id="st-form" class="row g-3">
            <div class="col-md-6"><label class="form-label">Name *</label><input class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name"></div>
            <div class="col-md-6"><label class="form-label">Role *</label><select class="form-select @error('form.role') is-invalid @enderror" wire:model="form.role">@foreach ($roles as $r)<option value="{{ $r->name }}">{{ $r->name }}</option>@endforeach</select>@error('form.role')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Email *</label><input type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model="form.email">@error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" wire:model="form.phone"></div>
            <div class="col-12"><label class="form-label">{{ $editingId ? 'New password (leave blank to keep)' : 'Password *' }}</label><input type="password" class="form-control @error('form.password') is-invalid @enderror" wire:model="form.password" autocomplete="new-password">@error('form.password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="st-act" wire:model="form.is_active"><label class="form-check-label" for="st-act">Account active</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="st-form" class="btn btn-primary">Save</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
