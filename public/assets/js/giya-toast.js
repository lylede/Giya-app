/*
 * GiyaToast: a short note at the bottom of the screen, with an optional
 * button (such as Undo). One at a time; a new one replaces the old.
 *
 *   GiyaToast.show('Moved Sto. Nino.')
 *   GiyaToast.show('Deleted.', { action: 'Undo', onAction: fn, ms: 6000 })
 */
window.GiyaToast = (function (document) {
    let box = null, timer = null;

    function el() {
        if (box) return box;
        box = document.createElement('div');
        box.className = 'giya-toast';
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        document.body.appendChild(box);
        return box;
    }

    function hide() {
        clearTimeout(timer);
        if (box) box.classList.remove('is-shown');
    }

    function show(text, opts) {
        opts = opts || {};
        const b = el();
        clearTimeout(timer);
        b.innerHTML = '';
        const t = document.createElement('span');
        t.className = 'giya-toast-text';
        t.textContent = text;
        b.appendChild(t);
        if (opts.action) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'giya-toast-btn';
            btn.textContent = opts.action;
            btn.addEventListener('click', function () { hide(); if (opts.onAction) opts.onAction(); });
            b.appendChild(btn);
        }
        b.classList.toggle('is-error', !!opts.error);
        requestAnimationFrame(function () { b.classList.add('is-shown'); });
        timer = setTimeout(function () { hide(); if (opts.onExpire) opts.onExpire(); }, opts.ms || 3500);
    }

    return { show: show, hide: hide };
})(document);
