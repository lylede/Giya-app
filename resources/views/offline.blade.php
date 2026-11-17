<!DOCTYPE html>
{{-- Shown by the offline worker when a page could not be loaded. Standalone
     on purpose: it holds no one's name or data, so it is safe to keep.

     Two different situations end up here. No signal: say so, and come back
     by itself when the signal does. Signal but no answer from GIYA (a
     restart, a dropped connection): say that instead, keep asking the
     server quietly, and reload the moment it answers. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8E3B2F">
    <title>GIYA - You are offline</title>
    <link rel="icon" href="/images/brand/icon-192.png">
    <style>
        body { margin: 0; font-family: Lato, system-ui, sans-serif; background: #EFE8DF; color: #241C18; }
        .wrap { max-width: 460px; margin: 0 auto; padding: 48px 20px; text-align: center; }
        img { width: 72px; height: 72px; }
        h1 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.6rem; margin: 16px 0 8px; }
        p { color: #7A6355; line-height: 1.6; margin: 0 0 18px; }
        ul { list-style: none; padding: 0; margin: 0 0 20px; text-align: left; }
        li a { display: block; padding: 12px 14px; margin-bottom: 8px; border-radius: 12px; background: #fff;
               border: 1px solid rgba(142,59,47,.15); color: #8E3B2F; font-weight: 700; text-decoration: none; }
        .status { margin: 14px 0 0; font-size: .875rem; }
        .spinner { width: 34px; height: 34px; margin: 18px auto 0; border-radius: 50%;
                   border: 3px solid rgba(142,59,47,.18); border-top-color: #8E3B2F; animation: spin .9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 2.5s; } }
        button { padding: 12px 22px; border: 0; border-radius: 999px; background: #8E3B2F; color: #fff; font-weight: 700; font-size: 1rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #17110E; color: #F2E8DC; } p { color: #CDBBA8; }
            li a { background: #241C18; border-color: rgba(215,169,74,.25); color: #E9C77E; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <img src="/images/logo/giya-logo.svg" alt="GIYA">
    <div id="spinner" class="spinner" hidden></div>
    <h1 id="title">You are offline</h1>
    <p id="lead">No signal right now. Pages you opened before are still here:</p>
    <ul id="saved"></ul>
    <p id="none" hidden>Open your itineraries and church pages while you have signal, and they will be here next time.</p>
    <button type="button" onclick="location.reload()">Try again</button>
    <p id="status" class="status" role="status" hidden>Checking again by itself&hellip;</p>
</div>
<script>
/*
 * Come back without being asked. /up is GIYA's own health check: it answers
 * only when the app itself is running, and the worker leaves it alone, so
 * an answer means the page will load. Reloads by itself at most four times
 * a visit, so a real fault cannot turn into an endless loop.
 */
(function () {
    var KEY = 'giya-offline-retries', timer = null;
    var tries = 0;
    try { tries = parseInt(sessionStorage.getItem(KEY) || '0', 10) || 0; } catch (e) {}

    function describe() {
        var online = navigator.onLine !== false;
        var waiting = online && tries < 4;
        document.getElementById('title').textContent = waiting ? 'Opening GIYA\u2026' : (online ? 'GIYA is not answering' : 'You are offline');
        document.title = 'GIYA - ' + document.getElementById('title').textContent;
        document.getElementById('lead').textContent = waiting
            ? 'The connection is slow or GIYA is just starting up. This page opens by itself the moment it answers. Pages you opened before are here too:'
            : (online ? 'GIYA did not answer. Pages you opened before are still here:' : 'No signal right now. Pages you opened before are still here:');
        document.getElementById('spinner').hidden = !waiting;
        document.getElementById('status').hidden = !waiting;
    }

    function check() {
        if (navigator.onLine === false || tries >= 4) return;
        fetch('/up?t=' + Date.now(), { cache: 'no-store', credentials: 'omit' }).then(function (res) {
            if (!res.ok) return;
            clearInterval(timer);
            try { sessionStorage.setItem(KEY, String(tries + 1)); } catch (e) {}
            location.reload();
        }).catch(function () { /* still unreachable; ask again shortly */ });
    }

    describe();
    window.addEventListener('online', function () { describe(); check(); });
    window.addEventListener('offline', describe);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
    // Every 3 seconds for up to 3 minutes: Render's free plan can take a
    // minute to wake up.
    var started = Date.now();
    timer = setInterval(function () {
        if (Date.now() - started > 180000) { clearInterval(timer); tries = 4; describe(); return; }
        check();
    }, 3000);
    setTimeout(check, 800);
})();
</script>
<script>
(async function () {
    const list = document.getElementById('saved');
    try {
        const cache = await caches.open('giya-pages');
        const keys = await cache.keys();
        for (const req of keys.reverse()) {
            const res = await cache.match(req);
            const html = res ? await res.text() : '';
            const m = html.match(/<title>([^<]*)<\/title>/i);
            const a = document.createElement('a');
            a.href = new URL(req.url).pathname;
            a.textContent = (m ? m[1] : a.href).replace(/^GIYA - /, '');
            const li = document.createElement('li');
            li.appendChild(a);
            list.appendChild(li);
        }
        if (!keys.length) document.getElementById('none').hidden = false;
    } catch (e) {
        document.getElementById('none').hidden = false;
    }
})();
</script>
</body>
</html>
