/*
 * Mobile Wrap — shared UX for the admin, POS and storefront.
 *
 * 1. Button loaders: whatever button / POS card the user presses shows the Herozi
 *    "Please wait… ⟳" state (a spinner on small buttons, an overlay on cards) until
 *    the Livewire request it started has finished and the page has updated.
 * 2. Network toast: "Your internet is slow, please wait…" when a page load, page
 *    change or action takes longer than 3 seconds, plus an offline / back-online notice.
 *
 * Livewire 3.8 doesn't mark the element that triggered a request, so we remember the
 * element the user just pressed and attach it to the next request Livewire sends.
 * Opt an element out with data-no-loader.
 */
(function () {
    'use strict';
    if (window.mwUx) return;

    var SLOW_AFTER_MS = 3000;   // show the slow-internet toast after this long
    var ARM_WINDOW_MS = 800;    // a request must start this soon after a press to belong to it
    var GIVE_UP_MS = 60000;     // never leave a loader or toast stuck
    var TEXT = {
        wait: 'Please wait…',
        slow: 'Your internet is slow, please wait…',
        offline: 'You’re offline. Waiting for the connection…',
        online: 'Back online',
    };
    var PRESSABLE = 'button, a.btn, [role="button"], .pos-tile, .list-group-item-action, .dropdown-item, .nav-link';

    // ── Styles (self-contained so every theme gets them) ─────────────────
    var CSS = [
        '@keyframes mw-spin { to { transform: rotate(360deg); } }',
        '.mw-spin { display: inline-block; line-height: 1; animation: mw-spin .8s linear infinite; }',
        '.mw-loading { pointer-events: none !important; cursor: progress; }',
        '.mw-loading-text, .mw-loading-icon { color: transparent !important; }',
        '.mw-loading-text > :not(.mw-loader), .mw-loading-icon > :not(.mw-loader) { visibility: hidden !important; }',
        '.mw-loader { position: absolute; inset: 0; z-index: 3; display: flex; align-items: center; justify-content: center; gap: .45em;',
        '  white-space: nowrap; overflow: hidden; pointer-events: none; border-radius: inherit; font-weight: inherit; }',
        '.mw-loader i { font-size: 1.15em; }',
        '.mw-loading-card > :not(.mw-loader) { opacity: .35; transition: opacity .15s ease; }',
        '.mw-loading-card > .mw-loader { background: rgba(255, 255, 255, .35); color: var(--bs-primary, #2563eb); font-size: 1.75rem; }',
        '[data-bs-theme="dark"] .mw-loading-card > .mw-loader { background: rgba(0, 0, 0, .3); }',
        '.mw-net { position: fixed; left: 50%; top: 14px; z-index: 2147483000; display: flex; align-items: center; gap: 10px;',
        '  max-width: calc(100vw - 32px); padding: 10px 18px; border-radius: 999px; background: #1f2937; color: #fff;',
        '  font-size: 14px; font-weight: 500; line-height: 1.35; box-shadow: 0 14px 36px -10px rgba(0, 0, 0, .5);',
        '  transform: translate(-50%, -160%); opacity: 0; transition: transform .3s ease, opacity .3s ease; pointer-events: none; }',
        '.mw-net.show { transform: translate(-50%, 0); opacity: 1; }',
        '.mw-net.is-offline { background: #b42318; } .mw-net.is-online { background: #067647; }',
        '.mw-net i { font-size: 18px; }',
        '@media (prefers-reduced-motion: reduce) { .mw-net { transition: none; } .mw-spin { animation-duration: 2s; } }',
    ].join('\n');

    function injectStyles() {
        if (document.getElementById('mw-ux-style')) return;
        var s = document.createElement('style');
        s.id = 'mw-ux-style';
        s.textContent = CSS;
        (document.head || document.documentElement).appendChild(s);
    }
    injectStyles();

    // ── Network toast ───────────────────────────────────────────────────
    var pending = 0, slowTimer = null, hideTimer = null, toastEl = null, offline = navigator.onLine === false;

    function toast() {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'mw-net';
            toastEl.setAttribute('role', 'status');
            toastEl.setAttribute('aria-live', 'polite');
        }
        if (!toastEl.isConnected && document.body) document.body.appendChild(toastEl);
        return toastEl;
    }

    function showToast(kind) {
        var el = toast();
        clearTimeout(hideTimer);
        var icon = kind === 'offline' ? 'ri-wifi-off-line' : kind === 'online' ? 'ri-wifi-line' : 'ri-loader-2-fill mw-spin';
        el.className = 'mw-net is-' + kind;
        el.innerHTML = '<i class="' + icon + '" aria-hidden="true"></i><span>' + TEXT[kind] + '</span>';
        void el.offsetWidth; // restart the slide-in
        el.classList.add('show');
    }

    function hideToast(kind, delay) {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(function () {
            if (toastEl && (!kind || toastEl.classList.contains('is-' + kind))) toastEl.classList.remove('show');
        }, delay || 0);
    }

    function armSlowTimer() {
        if (slowTimer || pending === 0) return;
        slowTimer = setTimeout(function () {
            slowTimer = null;
            if (pending > 0 && !offline) showToast('slow');
        }, SLOW_AFTER_MS);
    }

    /** Count something in flight; returns an idempotent "done" callback. */
    function track() {
        var finished = false;
        pending++;
        armSlowTimer();
        var guard = setTimeout(done, GIVE_UP_MS);
        function done() {
            if (finished) return;
            finished = true;
            clearTimeout(guard);
            pending = Math.max(0, pending - 1);
            if (pending === 0) {
                clearTimeout(slowTimer);
                slowTimer = null;
                hideToast('slow');
            }
        }
        return done;
    }

    window.addEventListener('offline', function () { offline = true; showToast('offline'); });
    window.addEventListener('online', function () {
        offline = false;
        showToast('online');
        hideToast('online', 2500);
        armSlowTimer();
    });
    if (offline) document.addEventListener('DOMContentLoaded', function () { showToast('offline'); });

    // A first page load that's still fetching CSS / images / scripts.
    if (document.readyState !== 'complete') {
        var pageDone = track();
        window.addEventListener('load', pageDone, { once: true });
    }

    // ── Button / card loaders ───────────────────────────────────────────
    function modeFor(el) {
        if (el.matches('.pos-tile, .list-group-item, .card')) return 'card';
        var isBtn = el.matches('.btn, .dropdown-item');
        if (!isBtn && el.children.length > 1 && el.offsetHeight > 46) return 'card';
        var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
        if (isBtn && text && el.offsetWidth >= 104) return 'text';
        return 'icon';
    }

    function startLoading(el) {
        if (!el || !el.isConnected || el.classList.contains('mw-loading') || el.closest('[data-no-loader]')) return;
        var cs = getComputedStyle(el);
        if (cs.display === 'none' || cs.display.indexOf('table') === 0) return;
        var mode = modeFor(el);
        var color = cs.color;
        if (cs.position === 'static') { el.style.position = 'relative'; el.dataset.mwPos = '1'; }
        el.classList.add('mw-loading', 'mw-loading-' + mode);
        el.setAttribute('aria-busy', 'true');
        var box = document.createElement('span');
        box.className = 'mw-loader';
        box.setAttribute('aria-hidden', 'true');
        if (mode !== 'card') box.style.color = color;
        box.innerHTML = mode === 'text'
            ? '<span>' + TEXT.wait + '</span><i class="ri-loader-2-fill mw-spin"></i>'
            : '<i class="ri-loader-2-fill mw-spin"></i>';
        el.appendChild(box);
        el._mwGiveUp = setTimeout(function () { stopLoading(el); }, GIVE_UP_MS);
    }

    function stopLoading(el) {
        if (!el) return;
        clearTimeout(el._mwGiveUp);
        el.classList.remove('mw-loading', 'mw-loading-text', 'mw-loading-icon', 'mw-loading-card');
        el.removeAttribute('aria-busy');
        if (el.dataset && el.dataset.mwPos) { el.style.position = ''; delete el.dataset.mwPos; }
        Array.prototype.forEach.call(el.querySelectorAll(':scope > .mw-loader'), function (n) { n.remove(); });
    }

    function stopAll() {
        Array.prototype.forEach.call(document.querySelectorAll('.mw-loading'), stopLoading);
    }

    // Remember what was pressed. Capture phase records the element; the bubble-phase
    // listener refreshes the time after the element's own handlers ran (so a
    // wire:confirm dialog that blocked for a while still counts).
    var armed = null, lastPressed = null;

    function pressable(target) {
        var el = target && target.closest ? target.closest(PRESSABLE) : null;
        if (!el || el.disabled || el.closest('[data-no-loader]')) return null;
        if (el.tagName === 'A' && el.target && el.target !== '_self') return null;
        return el;
    }

    document.addEventListener('click', function (e) {
        var el = pressable(e.target);
        if (el) { lastPressed = el; armed = { el: el, at: performance.now() }; }
    }, true);
    document.addEventListener('click', function (e) {
        if (armed && armed.el.contains(e.target)) armed.at = performance.now();
    });
    document.addEventListener('submit', function (e) {
        var btn = e.submitter || (e.target.querySelector && e.target.querySelector('[type="submit"]'))
            || (e.target.id && document.querySelector('[type="submit"][form="' + CSS.escape(e.target.id) + '"]'));
        if (btn) { lastPressed = btn; armed = { el: btn, at: performance.now() }; }
    }, true);

    /** Hand a button to the next request, e.g. after a SweetAlert confirmation. */
    function arm(el) { if (el) armed = { el: el, at: performance.now() }; }

    function takeArmed() {
        var a = armed;
        armed = null;
        return a && performance.now() - a.at <= ARM_WINDOW_MS && a.el.isConnected ? a.el : null;
    }

    // ── Livewire requests ───────────────────────────────────────────────
    function hookLivewire() {
        if (hookLivewire.done || !window.Livewire || !window.Livewire.hook) return;
        hookLivewire.done = true;
        window.Livewire.hook('request', function (ctx) {
            var done = track();
            var el = takeArmed();
            if (el) startLoading(el);
            var finish = function () { done(); if (el) stopLoading(el); };
            ctx.succeed(finish);
            ctx.fail(finish);
        });
    }
    document.addEventListener('livewire:init', hookLivewire);
    hookLivewire();

    // wire:navigate page changes (SPA)
    var navDone = null;
    document.addEventListener('livewire:navigate', function () {
        if (navDone) navDone();
        navDone = track();
        var el = takeArmed();
        if (el && el.matches('a.btn')) startLoading(el);
    });
    document.addEventListener('livewire:navigated', function () {
        if (navDone) { navDone(); navDone = null; }
        injectStyles();
        stopAll();
    });

    // Classic page loads: plain links and non-Livewire forms (the page stays up
    // until the next one arrives, so the toast can still show).
    function leavingPage(el) {
        var done = track();
        setTimeout(done, 20000);
        if (el && el.matches('a.btn, button, [type="submit"]')) startLoading(el);
    }
    document.addEventListener('click', function (e) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a) return;
        setTimeout(function () {
            if (e.defaultPrevented || (a.target && a.target !== '_self') || a.hasAttribute('download')) return;
            var url;
            try { url = new URL(a.href, location.href); } catch (_) { return; }
            if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
            if (url.pathname === location.pathname && url.search === location.search) return; // #hash only
            leavingPage(a);
        }, 0);
    });
    document.addEventListener('submit', function (e) {
        setTimeout(function () {
            if (e.defaultPrevented || (e.target.target && e.target.target !== '_self')) return;
            leavingPage(e.submitter);
        }, 0);
    });

    // Back/forward cache restores a page exactly as it was left: clear leftovers.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        pending = 0;
        clearTimeout(slowTimer);
        slowTimer = null;
        hideToast();
        stopAll();
    });

    window.mwUx = { start: startLoading, stop: stopLoading, arm: arm, lastPressed: function () { return lastPressed; }, track: track };
})();
