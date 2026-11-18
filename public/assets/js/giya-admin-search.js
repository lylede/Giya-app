/*
 * Admin quick search. Type a church, a person or a page; arrows move, Enter
 * opens. Ctrl+K (or /) jumps here from anywhere. Pages are read from the
 * sidebar itself, so a new admin page is found without touching this file.
 */
(function (document) {
    const input = document.getElementById('admQsInput');
    const list = document.getElementById('admQsList');
    if (!input || !list) return;
    const esc = window.giyaEsc || function (v) { return String(v); };

    const pages = Array.prototype.map.call(document.querySelectorAll('.admin-sidebar a[href]'), function (a) {
        return { name: a.textContent.trim(), url: a.href };
    }).filter(function (p) { return p.name && p.name !== 'Sign Out'; });

    let items = [], active = -1, timer = null, seq = 0;

    function draw(groups) {
        items = [];
        let html = '';
        groups.forEach(function (g) {
            if (!g.rows.length) return;
            html += '<div class="adm-qs-group">' + esc(g.title) + '</div>';
            g.rows.forEach(function (r) {
                const i = items.push(r) - 1;
                html += '<a class="adm-qs-item" role="option" id="admQs' + i + '" href="' + esc(r.url) + '">'
                      + '<i class="bi bi-' + g.icon + '" aria-hidden="true"></i>'
                      + '<span><b>' + esc(r.name) + '</b>' + (r.sub ? '<small>' + esc(r.sub) + '</small>' : '') + '</span></a>';
            });
        });
        if (!items.length) html = '<p class="adm-qs-none">Nothing matches "' + esc(input.value.trim()) + '".</p>';
        list.innerHTML = html;
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        active = items.length ? 0 : -1;
        mark();
    }

    function mark() {
        list.querySelectorAll('.adm-qs-item').forEach(function (a, i) { a.classList.toggle('is-on', i === active); });
        input.setAttribute('aria-activedescendant', active >= 0 ? 'admQs' + active : '');
        const on = list.querySelector('.adm-qs-item.is-on');
        if (on) on.scrollIntoView({ block: 'nearest' });
    }

    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); }

    function search() {
        const q = input.value.trim();
        if (!q) { close(); return; }
        const ql = q.toLowerCase();
        const pageRows = pages.filter(function (p) { return p.name.toLowerCase().indexOf(ql) !== -1; })
            .slice(0, 4).map(function (p) { return { name: p.name, sub: 'Admin page', url: p.url }; });
        if (q.length < 2) { draw([{ title: 'Pages', icon: 'arrow-right', rows: pageRows }]); return; }
        const mine = ++seq;
        fetch(input.dataset.url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (mine !== seq) return;
                draw([
                    { title: 'Churches', icon: 'giya-spires', rows: d.churches.map(function (c) { return { name: c.name, sub: (c.place || '') + (c.draft ? ' · Draft' : ''), url: c.url }; }) },
                    { title: 'People', icon: 'giya-pilgrim', rows: d.users.map(function (u) { return { name: u.name, sub: u.email + (u.role === 'admin' ? ' · Admin' : ''), url: u.url }; }) },
                    { title: 'Pages', icon: 'arrow-right', rows: pageRows },
                ]);
            })
            .catch(function () { if (mine === seq) draw([{ title: 'Pages', icon: 'arrow-right', rows: pageRows }]); });
    }

    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 180); });
    input.addEventListener('focus', function () { if (input.value.trim()) search(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); active = (active + 1) % items.length; mark(); }
        else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); active = (active - 1 + items.length) % items.length; mark(); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); window.location.href = items[active].url; }
        else if (e.key === 'Escape') { close(); input.blur(); }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('#admQs')) close(); });
    document.addEventListener('keydown', function (e) {
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) || document.activeElement.isContentEditable;
        if ((e.key === 'k' && (e.ctrlKey || e.metaKey)) || (e.key === '/' && !typing)) {
            e.preventDefault(); input.focus(); input.select();
        }
    });
})(document);
