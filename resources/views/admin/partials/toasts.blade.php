{{-- Global toast stack. Fire from Livewire with: $this->dispatch('toast', type: 'success', message: '…') --}}
<div x-data="{
        toasts: [],
        push(t) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: t.type || 'success', message: t.message || '' });
            setTimeout(() => this.remove(id), t.timeout || 3500);
        },
        remove(id) { this.toasts = this.toasts.filter(x => x.id !== id) }
    }"
    x-init="@if (session('toast')) push(@js(session('toast'))) @endif"
    x-on:toast.window="push($event.detail[0] ?? $event.detail)"
    class="toast-container position-fixed top-0 end-0 p-3 mw-toasts" style="z-index: 1090">
    <template x-for="t in toasts" :key="t.id">
        <div class="toast show align-items-center border-0 mb-2 shadow" role="alert" aria-live="assertive"
             :class="{ 'text-bg-success': t.type === 'success', 'text-bg-danger': t.type === 'error', 'text-bg-warning': t.type === 'warning', 'text-bg-info': t.type === 'info' }"
             x-transition.opacity>
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i :class="t.type === 'error' ? 'ri-error-warning-line' : (t.type === 'warning' ? 'ri-alert-line' : 'ri-checkbox-circle-line')" class="fs-16"></i>
                    <span x-text="t.message"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="remove(t.id)" aria-label="Close"></button>
            </div>
        </div>
    </template>
</div>
