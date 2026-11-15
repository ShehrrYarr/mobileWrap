<div class="sf-toasts" x-data="sfToasts(@js(session('store_toast')))" x-on:store-toast.window="push($event.detail[0] ?? $event.detail)" aria-live="polite">
    <template x-for="t in items" :key="t.id">
        <div class="sf-toast" :class="{ 'is-error': t.type === 'error' }" x-transition.opacity.duration.250ms>
            <i :class="t.type === 'error' ? 'ri-error-warning-fill' : (t.type === 'info' ? 'ri-information-fill' : 'ri-checkbox-circle-fill')"></i>
            <span x-text="t.message"></span>
        </div>
    </template>
</div>
