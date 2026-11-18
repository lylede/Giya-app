/*
 * GiyaCombo: GIYA's own suggestion list for <input list="…">.
 *
 * The browser's built-in list (a <datalist>) is drawn by the browser itself,
 * so it cannot take GIYA's colours: in dark mode on Windows it came up as a
 * cold blue-grey box. This takes over every input that has a datalist,
 * keeps the datalist as the source of options, and draws the list in the
 * palette. Choosing an option fills the input and fires input and change,
 * so the page's own code reacts exactly as before.
 *
 *   <input list="towns"> <datalist id="towns"><option value="Argao">…</option></datalist>
 */
(function (document) {
    const fold = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const MAX = 60;

    function enhance(input) {
        const source = document.getElementById(input.getAttribute('list'));
        if (!source || input.dataset.combo) return;
        input.dataset.combo = '1';
        input.removeAttribute('list');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        const wrap = input.parentElement;
        if (getComputedStyle(wrap).position === 'static') wrap.style.position = 'relative';

        const list = document.createElement('ul');
        list.className = 'giya-combo';
        list.id = (source.id || 'combo') + '-list';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        wrap.appendChild(list);
        input.setAttribute('aria-controls', list.id);

        let shown = [], on = -1;

        function options() {
            return Array.prototype.map.call(source.querySelectorAll('option'), (o) => ({
                value: o.value, note: (o.textContent || '').trim() !== o.value ? (o.textContent || '').trim() : '',
            }));
        }

        function render() {
            const q = fold(input.value.trim());
            const all = options();
            // Names that start with what was typed come first.
            const starts = [], within = [];
            all.forEach((o) => {
                const v = fold(o.value);
                if (!q || v.startsWith(q)) starts.push(o);
                else if (v.includes(q)) within.push(o);
            });
            shown = starts.concat(within).slice(0, MAX);
            on = -1;
            list.innerHTML = '';
            if (!shown.length) {
                const li = document.createElement('li');
                li.className = 'giya-combo-none';
                li.textContent = 'No match. Check the spelling.';
                list.appendChild(li);
            }
            shown.forEach((o, i) => {
                const li = document.createElement('li');
                li.className = 'giya-combo-item';
                li.id = list.id + '-' + i;
                li.setAttribute('role', 'option');
                const b = document.createElement('b');
                b.textContent = o.value;
                li.appendChild(b);
                if (o.note) {
                    const s = document.createElement('small');
                    s.textContent = o.note;
                    li.appendChild(s);
                }
                // mousedown, not click: it lands before the input loses focus.
                li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(i); });
                list.appendChild(li);
            });
            open();
        }

        function open() { list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); }

        function light(i) {
            const items = list.querySelectorAll('.giya-combo-item');
            if (!items.length) return;
            on = (i + items.length) % items.length;
            items.forEach((el, k) => el.classList.toggle('is-on', k === on));
            items[on].scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', items[on].id);
        }

        function choose(i) {
            const o = shown[i];
            if (!o) return;
            input.value = o.value;
            close();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        input.addEventListener('focus', render);
        input.addEventListener('click', () => { if (list.hidden) render(); });
        input.addEventListener('input', (e) => { if (e.isTrusted) render(); });
        input.addEventListener('blur', () => setTimeout(close, 120));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) render(); light(on + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); light(on - 1); }
            else if (e.key === 'Enter' && !list.hidden && on >= 0) { e.preventDefault(); choose(on); }
            else if (e.key === 'Escape' && !list.hidden) { e.preventDefault(); close(); }
        });
    }

    function init() { document.querySelectorAll('input[list]').forEach(enhance); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    window.GiyaCombo = { enhance: enhance };
})(document);
