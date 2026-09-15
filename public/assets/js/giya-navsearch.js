/**
 * GIYA - the navbar search.
 *
 * The pill opens on hover and on focus, which CSS does on its own. This is
 * here for the case CSS cannot answer: what a press on the magnifier should
 * do while the field is still shut.
 *
 * It submitted. The magnifier is the form's submit button, so pressing it
 * with nothing typed navigated to the map with an empty query - the search
 * appeared to throw you somewhere at random instead of opening. On a touch
 * screen that was the only thing a tap could do, because there is no hover to
 * open it with first.
 *
 * So: an empty search never submits. It opens the field and puts the cursor
 * in it, which is what pressing a magnifier means when there is nothing to
 * search for yet. That one rule covers the press while shut, the tap on a
 * phone, and a stray Enter on an empty field.
 */
(function (window, document) {
    'use strict';

    var form = document.querySelector('.nav-search');

    if (!form) return;                       // guest bar, or the admin layout

    var input = form.querySelector('input[type="search"]');

    if (!input) return;

    function open() {
        /* A class as well as the CSS states, because neither :hover nor
           :focus-within survives the moment between the press and the focus
           landing - and on a touch screen :hover never happens at all. */
        form.classList.add('is-open');
        input.focus();
    }

    form.addEventListener('submit', function (e) {
        if (input.value.trim() !== '') return;     // a real search; let it go

        e.preventDefault();
        open();
    });

    /* Closing again. The field is only held open by the class when it was
       opened by a press, so this is the way back out of that - otherwise a
       pill opened by a tap would stay open for the rest of the visit. */
    function close() {
        if (input.value.trim() !== '') return;     // never close over a term

        form.classList.remove('is-open');
        input.blur();
    }

    document.addEventListener('click', function (e) {
        if (!form.classList.contains('is-open')) return;
        if (form.contains(e.target)) return;

        close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && form.classList.contains('is-open')) close();
    });
})(window, document);
