<!DOCTYPE html>
{{-- Shown by the offline worker when there is no signal. Standalone on
     purpose: it holds no one's name or data, so it is safe to keep. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8E3B2F">
    <title>GIYA - You are offline</title>
    <link rel="icon" href="/images/brand/icon-192.png">
    <style>
        body { margin: 0; font-family: Lato, system-ui, sans-serif; background: #FFF8ED; color: #241C18; }
        .wrap { max-width: 460px; margin: 0 auto; padding: 48px 20px; text-align: center; }
        img { width: 72px; height: 72px; }
        h1 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.6rem; margin: 16px 0 8px; }
        p { color: #7A6355; line-height: 1.6; margin: 0 0 18px; }
        ul { list-style: none; padding: 0; margin: 0 0 20px; text-align: left; }
        li a { display: block; padding: 12px 14px; margin-bottom: 8px; border-radius: 12px; background: #fff;
               border: 1px solid rgba(142,59,47,.15); color: #8E3B2F; font-weight: 700; text-decoration: none; }
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
    <h1>You are offline</h1>
    <p>No signal right now. Pages you opened before are still here:</p>
    <ul id="saved"></ul>
    <p id="none" hidden>Open your itineraries and church pages while you have signal, and they will be here next time.</p>
    <button type="button" onclick="location.reload()">Try again</button>
</div>
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
