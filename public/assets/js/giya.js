/**
 * GIYA — front-end behaviour.
 *
 * Written for this project so the app carries no third-party JavaScript and
 * runs with no network access. Exposes four small helpers used by the Blade
 * templates: Modal, password visibility toggling, the mobile nav, and the
 * profile page (tabs, favorites, avatar preview).
 */
(function (window, document) {
    'use strict';

    /* ── Modal ─────────────────────────────────────────────── */
    const Modal = {
        open(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            const focusable = el.querySelector('input:not([type=hidden]), select, textarea, button');
            if (focusable) focusable.focus();
        },
        close(id) {
            const el = id ? document.getElementById(id) : document.querySelector('.modal.is-open');
            if (!el) return;
            el.classList.remove('is-open');
            document.body.style.overflow = '';
        },
    };

    // Declarative triggers: data-modal-open="id" / data-modal-close
    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();
            Modal.open(opener.dataset.modalOpen);
            return;
        }

        if (event.target.closest('[data-modal-close]')) {
            event.preventDefault();
            Modal.close();
            return;
        }

        // Click on the backdrop itself closes the dialog.
        if (event.target.classList.contains('modal')) {
            Modal.close(event.target.id);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') Modal.close();
    });

    /* ── Password visibility ───────────────────────────────── */
    function togglePassword(inputId, trigger) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon   = trigger.querySelector('.bi');
        const hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        if (icon) icon.className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        trigger.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
    }

    /* ── Mobile navigation ─────────────────────────────────── */
    /*
       Delegated from document, not bound to the button.

       A direct listener is lost the moment its element is replaced — which
       Livewire does whenever it re-renders a component that contains the
       navbar. The button then looks fine and does nothing. Delegation survives
       any amount of DOM replacement, because the listener lives on document.
    */
    /*
       The mobile menu is entirely CSS now — a checkbox and a label, styled in
       components/navbar.blade.php. The script used to mirror that state, and
       its outside-click handler was unchecking the box on the very tap that
       opened it, so the menu opened once and then appeared dead.

       Nothing here touches it any more. Closing on an outside tap is handled by
       a full-screen label in the markup, which needs no JavaScript.
    */
    function initMobileNav() { /* intentionally empty — see navbar.blade.php */ }

    /* ── Navbar height ─────────────────────────────────────
       Overlays position themselves under the bar. Measuring it beats
       hard-coding a number that is wrong on one device or another. */
    function publishNavHeight() {
        var nav = document.querySelector('.giya-nav');
        if (!nav) return;
        document.documentElement.style.setProperty(
            '--nav-height', Math.round(nav.getBoundingClientRect().height) + 'px');
    }

    document.addEventListener('DOMContentLoaded', publishNavHeight);
    window.addEventListener('resize', publishNavHeight);
    window.addEventListener('orientationchange', publishNavHeight);

    initMobileNav();

    /* Flash messages dismiss themselves — see components/flash.blade.php.
       Keeping a second implementation here would mean two timers racing. */


    /* ── Profile: tabs, favorites, avatar ──────────────────── */
    const Profile = {
        show(id, btn) {
            document.querySelectorAll('.profile-panel').forEach(p => p.classList.add('d-none'));
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('is-active'));
            const panel = document.getElementById('panel-' + id);
            if (panel) panel.classList.remove('d-none');
            if (btn) btn.classList.add('is-active');
        },

        /** Remove a saved destination without reloading the page. */
        unfavorite(churchId) {
            const token = document.querySelector('meta[name="csrf-token"]');
            if (!token) return;

            fetch('/favorites/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.content },
                body: JSON.stringify({ church_id: churchId }),
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok || data.saved) return;
                    const row = document.querySelector('[data-favorite-row="' + churchId + '"]');
                    if (row) row.remove();
                })
                .catch(() => window.location.reload());
        },

        /** Swap the modal thumbnail as soon as a file is chosen. */
        previewAvatar(input) {
            const file = input.files && input.files[0];
            if (!file) return;

            const wrap = document.getElementById('avatarPreviewWrap');
            if (!wrap) return;

            const reader = new FileReader();
            reader.onload = e => {
                wrap.innerHTML = '<img id="avatarPreview" alt="" src="' + e.target.result +
                    '" style="width:64px;height:64px;border-radius:14px;object-fit:cover;border:2px solid var(--border)">';
            };
            reader.readAsDataURL(file);
        },
    };

    // Preference pills follow their radio state; avatar preview on file pick.
    document.addEventListener('change', function (event) {
        if (event.target.matches('.pref-choice input[type=radio]')) {
            document.querySelectorAll('.pref-choice input[name="' + event.target.name + '"]')
                .forEach(input => input.closest('.pref-choice').classList.toggle('is-active', input.checked));
            return;
        }

        if (event.target.id === 'pf-avatar') {
            Profile.previewAvatar(event.target);
        }
    });

    window.GiyaUI = { Modal, togglePassword };
    window.GiyaProfile = Profile;
    window.giyaTogglePassword = togglePassword;   // used inline by the auth forms
})(window, document);