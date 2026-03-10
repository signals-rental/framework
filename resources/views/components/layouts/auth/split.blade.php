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
    <body class="min-h-screen antialiased" style="font-family: 'Martian Mono', monospace;">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="s-auth-bg-dark relative hidden h-full flex-col p-10 text-white lg:flex dark:border-r dark:border-neutral-800">
                <a href="{{ route('home') }}" class="relative z-20 flex items-center" wire:navigate>
                    <span class="s-logo-mark" style="color: white;">Signals</span>
                </a>

                <div class="relative z-20 mt-auto">
                    <p class="s-auth-heading" style="font-size: 1.5rem; margin-bottom: 12px; color: white;">
                        Professional <em style="font-style: italic; color: #059669; font-weight: 600;">Rental</em> Software
                    </p>
                    <p class="s-auth-description" style="color: #94a3b8;">
                        Free. Open Source. Forever.
                    </p>

                    <footer class="s-auth-footer mt-6" style="text-align: left; padding: 0;">
                        <div class="s-auth-footer-text mb-2.5">
                            Signals Rental Framework &mdash; Free &amp; Open Source &mdash; MIT License
                        </div>
                        <div class="flex gap-6">
                            <a href="{{ route('docs.index') }}" target="_blank">Documentation</a>
                            <a href="https://github.com/signals-rental/framework" target="_blank">GitHub</a>
                        </div>
                    </footer>
                </div>
            </div>
            <div class="s-auth-bg w-full lg:p-8">
                <div class="s-auth-card mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="s-logo-mark">Signals</span>
                        <span class="sr-only">{{ config('app.name', 'Signals') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
