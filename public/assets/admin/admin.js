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
        if (!window.Swal) { if (window.confirm(message)) onConfirm(); return; }
        Swal.fire({
            title: options.title || 'Are you sure?',
            text: message,
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Yes, delete it',
            cancelButtonText: 'Cancel',
            customClass: { confirmButton: 'btn me-2 ' + ((options.icon || 'warning') === 'warning' ? 'btn-danger' : 'btn-primary'), cancelButton: 'btn btn-light' },
            buttonsStyling: false,
        }).then(function (r) { if (r.isConfirmed) onConfirm(); });
    };

    function initTooltips() {
        if (!window.bootstrap) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

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
