/*
 * GiyaSoftList: change page in an admin list without reloading the page.
 *
 * On Destinations, every Next page reloaded everything, map included, so
 * the map started again from scratch to show ten different rows. Now the
 * next page is fetched in the background and only the list is swapped.
 * The address bar still changes (Back and Forward work, and a reload or a
 * copied link opens the same page), and if anything goes wrong it falls
 * back to an ordinary page load.
 *
 *   <div id="dmList" data-soft-list> … table … pagination … </div>
 *   <form data-soft-list-form="dmList"> (filters outside the list)
 *   <a data-soft-list-link="dmList" href="…"> (e.g. Reset)
 */
(function (window, document) {
    function load(id, url, push) {
        const box = document.getElementById(id);
        if (!box) { window.location.href = url; return; }
        box.classList.add('is-soft-loading');
        box.setAttribute('aria-busy', 'true');

        fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok || r.redirected) throw new Error('page');
                return r.text();
            })
            .then(function (html) {
                const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById(id);
                if (!fresh) throw new Error('list');
                box.innerHTML = fresh.innerHTML;
                box.classList.remove('is-soft-loading');
                box.removeAttribute('aria-busy');
                if (push) window.history.pushState({ softList: id }, '', url);

                // Bring the top of the list into view if it scrolled away.
                const top = box.getBoundingClientRect().top;
                if (top < 0 || top > window.innerHeight * 0.6) {
                    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    box.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
                }
                box.focus({ preventScroll: true });
                box.dispatchEvent(new CustomEvent('giya:list-changed', { bubbles: true }));
            })
            .catch(function () { window.location.href = url; });
    }

    function formUrl(form) {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        // A new filter or page size starts again at page 1.
        url.searchParams.delete('page');
        return url.toString();
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest('a[href]');
        if (!a) return;
        let id = a.dataset.softListLink;
        if (!id) {
            const box = a.closest('[data-soft-list]');
            if (!box || !a.closest('.giya-pagination')) return;
            id = box.id;
        }
        e.preventDefault();
        load(id, a.href, true);
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (e.defaultPrevented || (form.method || 'get').toLowerCase() !== 'get') return;
        const id = form.dataset.softListForm || (form.closest('[data-soft-list]') || {}).id;
        if (!id) return;
        e.preventDefault();
        load(id, formUrl(form), true);
    });

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.softList) load(e.state.softList, window.location.href, false);
        else if (document.querySelector('[data-soft-list]')) load(document.querySelector('[data-soft-list]').id, window.location.href, false);
    });

    // So Back can come all the way back to the first page shown.
    const first = document.querySelector('[data-soft-list]');
    if (first && !(window.history.state && window.history.state.softList)) {
        window.history.replaceState({ softList: first.id }, '', window.location.href);
    }
})(window, document);
