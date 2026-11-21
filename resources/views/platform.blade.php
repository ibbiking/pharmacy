<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="app-brand" content="{{ config('app.brand', 'Suite') }} Platform">
    {{-- Super admin portal (docs/SUPER_ADMIN_PORTAL.md): its own root view; theme from this browser's last choice. --}}
    <script>
        (function () {
            var pref = null; try { pref = localStorage.getItem('theme-pref'); } catch (e) {}
            if (pref !== 'light' && pref !== 'soft' && pref !== 'dark') pref = 'dark';
            document.documentElement.setAttribute('data-bs-theme', pref === 'dark' ? 'dark' : 'light');
            document.documentElement.setAttribute('data-app-theme', pref);
        })();
    </script>
    @routes
    @viteReactRefresh
    @vite('resources/js/app.tsx')
    @inertiaHead
</head>
<body class="platform-portal">
    @inertia
</body>
</html>
