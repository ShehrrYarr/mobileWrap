<?php

use Livewire\Volt\Component;

new class extends Component
{
    public function markAllRead(): void
    {
        auth('web')->user()?->unreadNotifications->markAsRead();
    }

    public function open(string $id)
    {
        $notification = auth('web')->user()?->notifications()->find($id);
        $notification?->markAsRead();

        return $this->redirect($notification->data['url'] ?? route('admin.dashboard'), navigate: true);
    }

    public function with(): array
    {
        $user = auth('web')->user();

        return [
            'items' => $user ? $user->notifications()->latest()->limit(12)->get() : collect(),
            'unread' => $user ? $user->unreadNotifications()->count() : 0,
        ];
    }
}; ?>

<div class="dropdown features-dropdown" wire:poll.60s>
    <button type="button" class="btn icon-btn btn-text-primary rounded-circle position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="ri-notification-2-line fs-20"></i>
        @if ($unread)
            <span class="position-absolute translate-middle badge rounded-pill p-1 min-w-20px text-bg-danger">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" wire:ignore.self>
        <div class="dropdown-header d-flex align-items-center py-3">
            <h6 class="mb-0 me-auto">Notifications</h6>
            @if ($unread)
                <button type="button" class="btn btn-sm btn-light-primary" wire:click="markAllRead">Mark all read</button>
            @endif
        </div>
        <ul class="list-group list-group-flush" style="max-height: 360px; overflow-y: auto">
            @forelse ($items as $n)
                <li class="list-group-item list-group-item-action border-start-0 border-end-0 cursor-pointer {{ $n->read_at ? '' : 'bg-primary-subtle bg-opacity-25' }}" wire:click="open('{{ $n->id }}')">
                    <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-item avatar avatar-title bg-{{ $n->data['color'] ?? 'primary' }}-subtle text-{{ $n->data['color'] ?? 'primary' }}">
                                <i class="{{ $n->data['icon'] ?? 'ri-notification-2-line' }}"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="mb-1 small text-truncate">{{ $n->data['title'] ?? 'Notification' }}</h6>
                            <small class="mb-1 d-block text-body text-truncate">{{ $n->data['message'] ?? '' }}</small>
                            <small class="text-muted">{{ $n->created_at->diffForHumans() }}</small>
                        </div>
                    </div>
                </li>
            @empty
                <li class="list-group-item text-center text-muted py-5 border-0">
                    <i class="ri-notification-off-line fs-3 d-block mb-1"></i> You're all caught up
                </li>
            @endforelse
        </ul>
    </div>
</div>
