<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ $title ?? config('app.name', 'Signals') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Martian+Mono:wght@400;500&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="s-auth-bg min-h-screen antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="s-logo-mark">Signals</span>
                    <span class="sr-only">{{ config('app.name', 'Signals') }}</span>
                </a>
                <div class="s-auth-card mt-4 px-8 py-8">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <footer class="s-auth-footer">
                <div class="s-auth-footer-text mb-2.5">
                    Signals Rental Framework &mdash; Free &amp; Open Source &mdash; MIT License
                </div>
                <div class="flex justify-center gap-6">
                    <a href="{{ route('docs.index') }}" target="_blank">Documentation</a>
                    <a href="https://github.com/signals-rental/framework" target="_blank">GitHub</a>
                </div>
            </footer>
        </div>
        @fluxScripts
    </body>
</html>
