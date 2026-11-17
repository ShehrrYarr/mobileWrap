/* Mobile Wrap — admin glue for Herozi + Livewire SPA navigation */
(function () {
    'use strict';

    // Highlight the sidebar link that matches the current URL (longest prefix wins).
    function markActiveMenu() {
        const path = window.location.pathname.replace(/\/$/, '') || '/';
        document.querySelectorAll('.main-menu').forEach(function (menu) {
            let best = null, bestLen = -1;
            menu.querySelectorAll('a[data-mw-link]').forEach(function (a) {
                a.classList.remove('active');
                const li = a.closest('.slide-menu')?.parentElement;
                if (li) { li.classList.remove('open-menu'); li.querySelector(':scope > .side-menu__item')?.classList.remove('active'); }
                const p = new URL(a.href, window.location.origin).pathname.replace(/\/$/, '') || '/';
                const match = path === p || (p !== '/admin' && path.startsWith(p + '/'));
                if (match && p.length > bestLen) { best = a; bestLen = p.length; }
            });
            if (best) {
                best.classList.add('active');
                const parentLi = best.closest('.slide-menu')?.parentElement;
                if (parentLi) {
                    parentLi.classList.add('open-menu');
                    parentLi.querySelector(':scope > .side-menu__item')?.classList.add('active');
                }
            }
        });
    }

    // SweetAlert2 confirmation used by delete buttons: confirmAction('Delete?', () => $wire.delete(1))
    window.confirmAction = function (message, onConfirm, options) {
        options = options || {};
        // The button that opened the dialog shows "Please wait…" once the user confirms.
        var pressed = window.mwUx ? window.mwUx.lastPressed() : null;
        var go = function () { if (pressed && window.mwUx) window.mwUx.arm(pressed); onConfirm(); };
        if (!window.Swal) { if (window.confirm(message)) go(); return; }
        Swal.fire({
            title: options.title || 'Are you sure?',
            text: message,
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Yes, delete it',
            cancelButtonText: 'Cancel',
            customClass: { confirmButton: 'btn me-2 ' + ((options.icon || 'warning') === 'warning' ? 'btn-danger' : 'btn-primary'), cancelButton: 'btn btn-light' },
            buttonsStyling: false,
        }).then(function (r) { if (r.isConfirmed) go(); });
    };

    // PDF downloads (<a data-pdf href="…">): the button shows "Please wait…" until the file is
    // ready, then the browser saves it under the server's file name.
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[data-pdf]');
        if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        if (a.classList.contains('mw-loading')) return;
        var ux = window.mwUx;
        if (ux) ux.start(a);
        var done = ux ? ux.track() : function () {};
        fetch(a.href, { credentials: 'same-origin', headers: { Accept: 'application/pdf' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                var cd = r.headers.get('Content-Disposition') || '';
                var m = cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
                var name = m ? decodeURIComponent(m[1]) : 'report.pdf';
                return r.blob().then(function (blob) {
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = name;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 5000);
                });
            })
            .catch(function () { window.location.href = a.href; })
            .finally(function () { done(); if (ux) ux.stop(a); });
    });

    function initTooltips() {
        if (!window.bootstrap) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    // Keep Herozi's layout settings across wire:navigate.
    // The template's layout.js puts its settings on <html> from JavaScript at first load
    // (data-main-layout, data-bs-theme, data-sidebar, fonts…). On every wire:navigate, Livewire
    // copies the new page's <html> attributes over the current ones and deletes any the server
    // didn't send, which is all of these. Without data-main-layout="close-sidebar" a phone shows
    // the desktop sidebar as a fixed 240px column; dark mode and fonts reset too.
    // onSwap runs right after the new page is swapped in and before it's painted, so nothing flickers.
    document.addEventListener('livewire:navigating', function (e) {
        const keep = Array.from(document.documentElement.attributes)
            .filter(function (a) { return a.name !== 'class' && a.name !== 'style'; })
            .map(function (a) { return [a.name, a.value]; });
        e.detail?.onSwap?.(function () {
            const html = document.documentElement;
            keep.forEach(function ([name, value]) {
                if (!html.hasAttribute(name)) html.setAttribute(name, value);
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function () { markActiveMenu(); initTooltips(); });
    document.addEventListener('livewire:navigated', function () {
        markActiveMenu();
        initTooltips();
        // close the mobile sidebar after navigating
        const off = document.getElementById('smallScreenSidebar');
        if (off && window.bootstrap) bootstrap.Offcanvas.getInstance(off)?.hide();
        document.querySelectorAll('.tooltip').forEach(function (t) { t.remove(); });
    });
})();
