<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ $title ?? config('app.name', 'Signals') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Martian+Mono:wght@400;500&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen antialiased">
        <div class="s-auth-split">
            <div class="s-auth-split-form">
                <header>
                    <a href="{{ route('home') }}" wire:navigate class="inline-block">
                        <span class="s-logo-mark size-sm">Signals</span>
                    </a>
                </header>

                <div class="s-auth-split-form-inner">
                    {{ $slot }}
                </div>

                <footer class="s-auth-footer s-auth-split-footer">
                    <div class="s-auth-footer-text mb-2.5">
                        Signals Rental Framework &mdash; Free &amp; Open Source &mdash; MIT License
                    </div>
                    <div class="flex gap-6">
                        <a href="{{ route('docs.index') }}" target="_blank">Documentation</a>
                        <a href="https://github.com/signals-rental/framework" target="_blank">GitHub</a>
                    </div>
                </footer>
            </div>

            <x-auth-montage />
        </div>
        @fluxScripts
    </body>
</html>
