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

        <style>
            /* Light mode */
            .signals-auth-bg {
                background-color: #f8f9fb;
                background-image:
                    repeating-linear-gradient(0deg, rgba(59,130,246,0.06) 0px, rgba(59,130,246,0.06) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(90deg, rgba(59,130,246,0.06) 0px, rgba(59,130,246,0.06) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(0deg, rgba(59,130,246,0.12) 0px, rgba(59,130,246,0.12) 1px, transparent 1px, transparent 160px),
                    repeating-linear-gradient(90deg, rgba(59,130,246,0.12) 0px, rgba(59,130,246,0.12) 1px, transparent 1px, transparent 160px);
            }

            /* Dark mode */
            .dark .signals-auth-bg {
                background-color: #0f172a;
                background-image:
                    repeating-linear-gradient(0deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(90deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(0deg, rgba(148,163,184,0.15) 0px, rgba(148,163,184,0.15) 1px, transparent 1px, transparent 160px),
                    repeating-linear-gradient(90deg, rgba(148,163,184,0.15) 0px, rgba(148,163,184,0.15) 1px, transparent 1px, transparent 160px);
            }

            .signals-logo-mark {
                display: inline-block;
                font-family: 'Chakra Petch', sans-serif;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                border: 2px solid currentColor;
                position: relative;
                text-decoration: none;
                color: #0f172a;
                font-size: 1rem;
                padding: 6px 16px;
            }
            .dark .signals-logo-mark {
                color: white;
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

            .signals-setup-heading {
                font-family: 'Chakra Petch', sans-serif;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: -0.01em;
                font-size: 1rem;
                line-height: 1.3;
                color: #0f172a;
            }
            .dark .signals-setup-heading {
                color: #ffffff;
            }
            .signals-setup-description {
                font-family: 'Martian Mono', monospace;
                font-size: 11px;
                color: #64748b;
                line-height: 1.7;
            }
            .dark .signals-setup-description {
                color: #94a3b8;
            }

            /* Light mode card */
            .signals-form-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
            }
            /* Dark mode card */
            .dark .signals-form-card {
                background: #1e293b;
                border: 1px solid rgba(148, 163, 184, 0.15);
            }

            /* Brand typography */
            .signals-auth-bg {
                font-family: 'Martian Mono', monospace;
            }

            /* Footer */
            .signals-auth-footer {
                text-align: center;
                padding: 0 16px;
            }
            .signals-auth-footer-text {
                font-family: 'Martian Mono', monospace;
                font-size: 9px;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }
            .dark .signals-auth-footer-text {
                color: #94a3b8;
            }

            /* Compact form fields */
            .signals-form-card label {
                font-family: 'Martian Mono', monospace;
                font-size: 11px !important;
            }
            .signals-form-card input,
            .signals-form-card select {
                font-family: 'Martian Mono', monospace;
                font-size: 12px !important;
            }
            .signals-form-card input::placeholder {
                font-size: 12px !important;
            }
            .signals-form-card button[type="submit"],
            .signals-form-card [data-flux-button] {
                font-family: 'Chakra Petch', sans-serif;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                font-size: 12px !important;
            }

            /* Step indicator */
            .signals-step-indicator {
                font-family: 'Martian Mono', monospace;
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #64748b;
            }
            .dark .signals-step-indicator {
                color: #94a3b8;
            }
        </style>
    </head>
    <body class="signals-auth-bg min-h-screen antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-xl flex-col gap-2">
                <div class="flex flex-col items-center gap-2">
                    <span class="signals-logo-mark">Signals</span>
                    <span class="signals-step-indicator mt-2">Setup Wizard</span>
                </div>
                <div class="signals-form-card mt-4 px-8 py-8">
                    {{ $slot }}
                </div>
            </div>

            <footer class="signals-auth-footer">
                <div class="signals-auth-footer-text">
                    Signals Rental Framework &mdash; Free &amp; Open Source &mdash; MIT License
                </div>
            </footer>
        </div>
        @fluxScripts
    </body>
</html>
