<!DOCTYPE html>
{{-- Shown to everyone except admins while maintenance mode is on.
     Standalone on purpose: it must render even if the rest of the site cannot. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8E3B2F">
    <meta name="robots" content="noindex">
    <title>GIYA - Back soon</title>
    <link rel="icon" href="/images/brand/icon-192.png">
    <style>
        body { margin: 0; font-family: Lato, system-ui, sans-serif; background: #FFF8ED; color: #241C18; }
        .wrap { max-width: 460px; margin: 0 auto; padding: 64px 20px 40px; text-align: center; }
        img { width: 72px; height: 72px; }
        h1 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.7rem; margin: 18px 0 10px; }
        p { color: #7A6355; line-height: 1.6; margin: 0 0 16px; overflow-wrap: anywhere; }
        .back { display: inline-block; padding: 8px 14px; border-radius: 999px; background: #FBEFD6; color: #5A3B12; font-weight: 700; font-size: .9rem; }
        button { margin-top: 8px; padding: 12px 22px; border: 0; border-radius: 999px; background: #8E3B2F; color: #fff; font-weight: 700; font-size: 1rem; cursor: pointer; }
        .admin { margin-top: 36px; font-size: .8125rem; }
        .admin a { color: #8E3B2F; }
        .preview { background: #241C18; color: #fff; padding: 8px 12px; font-size: .8125rem; text-align: center; }
        @media (prefers-color-scheme: dark) {
            body { background: #17110E; color: #F2E8DC; } p { color: #CDBBA8; }
            .back { background: #3A2A1C; color: #F0D9B5; } .admin a { color: #E9C77E; }
        }
    </style>
</head>
<body>
@if ($preview ?? false)
    <div class="preview">Preview only. This is what pilgrims see while maintenance mode is on.</div>
@endif
<div class="wrap">
    <img src="/images/logo/giya-logo.svg" alt="GIYA">
    <h1>GIYA is being updated</h1>
    <p>{{ $message }}</p>
    @if ($backBy)
        <p><span class="back">We expect to be back by {{ $backBy->format('M j, g:i A') }}</span></p>
    @endif
    @unless ($preview ?? false)
        <button type="button" onclick="location.reload()">Try again</button>
        <p class="admin">Administrator? <a href="/login">Sign in</a></p>
    @endunless
</div>
</body>
</html>
