/* Mobile Wrap storefront — Alpine helpers shared by every theme */
(function () {
    'use strict';

    document.addEventListener('alpine:init', function () {
        Alpine.store('cart', { count: (window.__sf && window.__sf.cartCount) || 0 });

        // Flash-sale countdown: x-data="countdown('2026-11-17T20:00:00+05:00')"
        Alpine.data('countdown', function (end) {
            return {
                d: '00', h: '00', m: '00', s: '00', done: false, timer: null,
                init: function () { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                destroy: function () { clearInterval(this.timer); },
                tick: function () {
                    const diff = Math.max(0, new Date(end).getTime() - Date.now());
                    this.done = diff === 0;
                    const t = Math.floor(diff / 1000);
                    const pad = (n) => String(n).padStart(2, '0');
                    this.d = pad(Math.floor(t / 86400));
                    this.h = pad(Math.floor((t % 86400) / 3600));
                    this.m = pad(Math.floor((t % 3600) / 60));
                    this.s = pad(t % 60);
                },
            };
        });

        // Simple auto-advancing slider: x-data="slider(3, 6000)"
        Alpine.data('slider', function (count, interval) {
            return {
                i: 0, count: count, timer: null,
                init: function () { this.play(); },
                destroy: function () { clearInterval(this.timer); },
                play: function () { if (this.count > 1) { clearInterval(this.timer); this.timer = setInterval(() => this.next(), interval || 6000); } },
                next: function () { this.i = (this.i + 1) % this.count; },
                prev: function () { this.i = (this.i - 1 + this.count) % this.count; },
                go: function (n) { this.i = n; this.play(); },
            };
        });

        // Horizontal product rail with arrow buttons
        Alpine.data('rail', function () {
            return {
                scroll: function (dir) {
                    const el = this.$refs.track;
                    el.scrollBy({ left: dir * el.clientWidth * 0.85, behavior: 'smooth' });
                },
            };
        });

        // Toasts: listen for Livewire "store-toast" events
        Alpine.data('sfToasts', function (initial) {
            return {
                items: [],
                init: function () { if (initial) this.push(initial); },
                push: function (t) {
                    const id = Date.now() + Math.random();
                    this.items.push({ id: id, type: t.type || 'success', message: t.message || '' });
                    setTimeout(() => { this.items = this.items.filter((x) => x.id !== id); }, t.timeout || 3200);
                },
            };
        });
    });

    window.addEventListener('cart-updated', function (e) {
        const detail = e.detail || {};
        const count = detail.count !== undefined ? detail.count : (detail[0] && detail[0].count);
        if (window.Alpine && count !== undefined) Alpine.store('cart').count = count;
    });

    function onScroll() {
        const header = document.querySelector('.sf-header');
        if (header) header.classList.toggle('is-scrolled', window.scrollY > 8);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('livewire:navigated', function () {
        onScroll();
        if (window.__sf && window.Alpine) Alpine.store('cart').count = window.__sf.cartCount;
        const menu = document.getElementById('sfMobileMenu');
        if (menu && window.bootstrap) { const oc = bootstrap.Offcanvas.getInstance(menu); if (oc) oc.hide(); }
        document.querySelectorAll('.offcanvas-backdrop').forEach((b) => b.remove());
        document.body.style.removeProperty('overflow');
    });

    // Search forms navigate without a full reload
    window.sfSearch = function (form) {
        const q = form.querySelector('[name=q]').value.trim();
        const cat = form.querySelector('[name=category]');
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (cat && cat.value) params.set('category', cat.value);
        const url = form.getAttribute('action') + (params.toString() ? '?' + params.toString() : '');
        if (window.Livewire) Livewire.navigate(url); else window.location = url;
        return false;
    };
})();
