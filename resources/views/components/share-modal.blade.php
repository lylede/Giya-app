{{--
    The share window: makes (or reuses) a private link to one itinerary,
    with Send (the phone's share sheet), Copy link and Stop sharing.

    Open it with GiyaShare.open(id, name). Used on Saved Itineraries and on
    the trip screen, whether the trip came from Create Custom Itinerary or
    the Visita Iglesia planner. Only the owner can make the link
    (SharedItineraryController checks).
--}}
<div class="modal" id="shareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content share-modal">
            <div class="modal-title"><i class="bi bi-people-fill" style="color:var(--primary)"></i> {{ __('giya.share.title') }}</div>
            <p class="share-help">{{ __('giya.share.help') }} <strong id="shareName"></strong></p>
            {{-- The link with Copy beside it; Stop sharing and Send share the
                 last row, so the window is two rows of controls, not three. --}}
            <div class="share-link-row">
                <input type="text" id="shareUrl" class="giya-input" readonly aria-label="{{ __('giya.share.link') }}">
                <button type="button" class="btn btn-outline btn-sm" id="shareCopy">{{ __('giya.share.copy_link') }}</button>
            </div>
            <p class="share-status" id="shareStatus" role="status" aria-live="polite"></p>
            <div class="share-actions">
                <button type="button" class="btn btn-ghost btn-sm share-stop" id="shareStop">{{ __('giya.share.stop') }}</button>
                <button type="button" class="btn btn-primary" id="shareSend">{{ __('giya.share.send') }}</button>
            </div>
        </div>
    </div>
</div>
<script>
window.GiyaShare = (function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const base = @json(url('/plan'));
    const url = document.getElementById('shareUrl'), status = document.getElementById('shareStatus');
    let current = null;
    const say = (t) => { status.textContent = t; };

    async function open(id, name, shared) {
        current = id;
        document.getElementById('shareName').textContent = name;
        url.value = ''; say(@json(__('giya.share.making')));
        GiyaUI.Modal.open('shareModal');
        try {
            const r = await fetch(base + '/' + id + '/share', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const d = await r.json();
            if (!d.ok) throw new Error();
            url.value = d.url; say('');
        } catch (e) { say(@json(__('giya.share.failed'))); }
    }

    document.getElementById('shareCopy').addEventListener('click', async function () {
        if (!url.value) return;
        try { await navigator.clipboard.writeText(url.value); } catch (e) { url.select(); document.execCommand('copy'); }
        say(@json(__('giya.share.copied_link')));
    });
    document.getElementById('shareSend').addEventListener('click', async function () {
        if (!url.value) return;
        if (navigator.share) {
            try { await navigator.share({ title: document.getElementById('shareName').textContent, url: url.value }); } catch (e) {}
        } else {
            document.getElementById('shareCopy').click();
        }
    });
    document.getElementById('shareStop').addEventListener('click', async function () {
        if (!current) return;
        await fetch(base + '/' + current + '/share', { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
        url.value = ''; say(@json(__('giya.share.stopped')));
    });

    return { open };
})();
</script>
