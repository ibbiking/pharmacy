<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Gossip Slip 🎭' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/gossip-slip/game.css') }}">
    <style>
        /* ── Kill Laravel Debugbar & PHP dev-server footer ── */
        #debugbar,
        #debugbar-js-var,
        phpdebugbar,
        .phpdebugbar,
        .phpdebugbar-header,
        .phpdebugbar-mini-design,
        iframe[src*="debugbar"],
        div[id^="debugbar"] { display: none !important; visibility: hidden !important; }

        /* ── x-cloak: hide Alpine elements before JS boots ── */
        [x-cloak] { display: none !important; }
    </style>
    @stack('head')
</head>
<body>

@yield('content')

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="{{ asset('assets/gossip-slip/game.js') }}"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@stack('scripts')
</body>
</html>
