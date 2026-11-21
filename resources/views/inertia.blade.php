<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-brand" content="{{ config('app.brand', 'Suite') }}">
    {{-- Apply the theme before first paint (no flash of the wrong theme). Dark is the default. Signed in: the user's
         own saved choice (users.theme). Signed out: this browser's last choice. --}}
    <script>
        (function () {
            var pref = @json(auth()->check() ? (auth()->user()->theme ?: 'dark') : null);
            if (!pref) { try { pref = localStorage.getItem('theme-pref'); } catch (e) {} }
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
<body>
    @inertia
</body>
</html>
