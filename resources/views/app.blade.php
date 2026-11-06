<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'SpiggleMail') }}</title>
        <script>
            (function() {
                try {
                    var saved = localStorage.getItem('spiggle_theme');
                    if (saved === 'dark') {
                        document.documentElement.classList.add('dark');
                        document.documentElement.classList.remove('light');
                    } else {
                        document.documentElement.classList.remove('dark');
                        document.documentElement.classList.add('light');
                    }
                } catch (e) {}
            })();
        </script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-[#242730] text-zinc-100 min-h-screen">
        @inertia
    </body>
</html>
