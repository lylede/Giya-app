<!DOCTYPE html>
{{-- "You may not open this page". Standalone, with a way out: the plain
     error page left a pilgrim on a phone stuck with nothing to tap. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8E3B2F">
    <meta name="robots" content="noindex">
    <title>GIYA - This page is not for this account</title>
    <link rel="icon" href="/images/brand/icon-192.png">
    <style>
        body { margin: 0; font-family: Lato, system-ui, sans-serif; background: #FFF8ED; color: #241C18; }
        .wrap { max-width: 460px; margin: 0 auto; padding: 64px 20px 40px; text-align: center; }
        img { width: 72px; height: 72px; }
        h1 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.6rem; margin: 18px 0 10px; }
        p { color: #7A6355; line-height: 1.6; margin: 0 0 20px; }
        .actions { display: grid; gap: 10px; max-width: 300px; margin: 0 auto; }
        .btn { display: block; width: 100%; box-sizing: border-box; padding: 13px 22px; border-radius: 999px; font: inherit; font-weight: 700; font-size: 1rem; text-decoration: none; cursor: pointer; }
        .primary { border: 0; background: #8E3B2F; color: #fff; }
        .outline { border: 1px solid rgba(142,59,47,.4); background: transparent; color: #8E3B2F; }
        form { margin: 0; }
        @media (prefers-color-scheme: dark) {
            body { background: #17110E; color: #F2E8DC; } p { color: #CDBBA8; }
            .outline { color: #E9C77E; border-color: rgba(233,199,126,.45); }
        }
    </style>
</head>
<body>
<div class="wrap">
    <img src="/images/logo/giya-logo.svg" alt="GIYA">
    @if (request()->is('admin', 'admin/*'))
        <h1>This page is for administrators</h1>
        <p>
            @auth You are signed in as {{ auth()->user()->name }}, which is not an admin account. @endauth
            Go back to GIYA, or sign in with an admin account.
        </p>
    @else
        <h1>You cannot open this page</h1>
        <p>{{ $exception->getMessage() ?: 'This page belongs to another account.' }}</p>
    @endif

    <div class="actions">
        <a class="btn primary" href="{{ auth()->check() ? route('home') : url('/') }}">Go to GIYA home</a>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn outline">Sign out and switch account</button>
            </form>
        @endauth
    </div>
</div>
</body>
</html>
