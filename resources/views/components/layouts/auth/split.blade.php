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

        <style>
            .signals-grid-dark {
                background-color: #0f172a;
                background-image:
                    repeating-linear-gradient(0deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(90deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(0deg, rgba(148,163,184,0.15) 0px, rgba(148,163,184,0.15) 1px, transparent 1px, transparent 160px),
                    repeating-linear-gradient(90deg, rgba(148,163,184,0.15) 0px, rgba(148,163,184,0.15) 1px, transparent 1px, transparent 160px);
            }
            .signals-grid-light {
                background-color: #f8f9fb;
                background-image:
                    repeating-linear-gradient(0deg, rgba(59,130,246,0.06) 0px, rgba(59,130,246,0.06) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(90deg, rgba(59,130,246,0.06) 0px, rgba(59,130,246,0.06) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(0deg, rgba(59,130,246,0.12) 0px, rgba(59,130,246,0.12) 1px, transparent 1px, transparent 160px),
                    repeating-linear-gradient(90deg, rgba(59,130,246,0.12) 0px, rgba(59,130,246,0.12) 1px, transparent 1px, transparent 160px);
            }
            body { font-family: 'Martian Mono', monospace; }
            .signals-logo-mark {
                display: inline-block;
                font-family: 'Chakra Petch', sans-serif;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                border: 2px solid currentColor;
                position: relative;
                text-decoration: none;
                font-size: 1rem;
                padding: 6px 16px;
            }
            .signals-logo-mark::after {
                content: '';
                position: absolute;
                top: -2px;
                right: -2px;
                width: 8px;
                height: 8px;
                background: #059669;
            }
            .signals-logo-xl {
                font-size: 2rem;
                padding: 10px 24px;
                letter-spacing: 0.12em;
            }
            .signals-logo-xl::after {
                width: 12px;
                height: 12px;
            }
            .signals-auth-heading {
                font-family: 'Chakra Petch', sans-serif;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: -0.01em;
                font-size: 1rem;
                line-height: 1.3;
                color: #0f172a;
            }
            .dark .signals-auth-heading { color: #ffffff; }
            .signals-auth-description {
                font-family: 'Martian Mono', monospace;
                font-size: 11px;
                color: #64748b;
                line-height: 1.7;
            }
            .dark .signals-auth-description { color: #94a3b8; }
            .signals-auth-footer {
                text-align: center;
                padding: 0 16px;
            }
            .signals-auth-footer-text {
                font-family: 'Martian Mono', monospace;
                font-size: 9px;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }
            .signals-auth-footer a {
                font-family: 'Martian Mono', monospace;
                font-size: 9px;
                color: #94a3b8;
                text-decoration: none;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                transition: color 0.2s;
            }
            .signals-auth-footer a:hover { color: #ffffff; }

            /* Compact form fields */
            .signals-form-card label,
            .space-y-6 label {
                font-family: 'Martian Mono', monospace;
                font-size: 11px !important;
            }
            .signals-form-card input,
            .space-y-6 input {
                font-family: 'Martian Mono', monospace;
                font-size: 12px !important;
            }
            .signals-form-card input::placeholder,
            .space-y-6 input::placeholder {
                font-size: 12px !important;
            }
            .signals-form-card button[type="submit"],
            .space-y-6 button[type="submit"] {
                font-family: 'Chakra Petch', sans-serif;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                font-size: 12px !important;
            }
            .signals-form-card a,
            .space-y-6 a {
                font-size: 11px !important;
            }
            .signals-form-card .text-sm,
            .space-y-6 .text-sm {
                font-size: 11px !important;
            }
        </style>
    </head>
    <body class="min-h-screen antialiased">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="signals-grid-dark relative hidden h-full flex-col p-10 text-white lg:flex dark:border-r dark:border-neutral-800">
                <a href="{{ route('home') }}" class="relative z-20 flex items-center" wire:navigate>
                    <span class="signals-logo-mark" style="color: white;">Signals</span>
                </a>

                <div class="relative z-20 mt-auto">
                    <p style="font-family: 'Chakra Petch', sans-serif; font-weight: 700; text-transform: uppercase; font-size: 1.5rem; line-height: 1.15; letter-spacing: -0.01em; margin-bottom: 12px;">
                        Professional <em style="font-style: italic; color: #059669; font-weight: 600;">Rental</em> Software
                    </p>
                    <p style="font-family: 'Martian Mono', monospace; font-size: 11px; color: #94a3b8; line-height: 1.7;">
                        Free. Open Source. Forever.
                    </p>

                    <footer class="signals-auth-footer" style="text-align: left; padding: 0; margin-top: 24px;">
                        <div class="signals-auth-footer-text" style="margin-bottom: 10px;">
                            Signals Rental Framework &mdash; Free &amp; Open Source &mdash; MIT License
                        </div>
                        <div style="display: flex; gap: 24px;">
                            <a href="https://framework.signals.rent/docs" target="_blank">Documentation</a>
                            <a href="https://github.com/signals-rental/framework" target="_blank">GitHub</a>
                        </div>
                    </footer>
                </div>
            </div>
            <div class="signals-grid-light w-full lg:p-8 dark:signals-grid-dark">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="signals-logo-mark" style="color: #0f172a;">Signals</span>
                        <span class="sr-only">{{ config('app.name', 'Signals') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
