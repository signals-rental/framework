<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>Setup — Signals</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Martian+Mono:wght@400;500&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="s-auth-bg min-h-screen antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-xl flex-col gap-2">
                <div class="flex flex-col items-center gap-2">
                    <span class="s-logo-mark">Signals</span>
                    <span class="s-auth-label mt-2">Setup Wizard</span>
                </div>
                <div class="s-auth-card mt-4 px-8 py-8">
                    {{ $slot }}
                </div>
            </div>

            <footer class="s-auth-footer">
                <div class="s-auth-footer-text">
                    Signals Rental Framework &mdash; Free &amp; Open Source &mdash; MIT License
                </div>
            </footer>
        </div>
        @fluxScripts
    </body>
</html>
