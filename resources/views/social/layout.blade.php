@php
    $format = request()->query('format', 'linkedin');
    $isSquare = $format === 'instagram';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signals — {{ $title ?? 'Social' }} ({{ $isSquare ? 'Instagram' : 'LinkedIn' }})</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Martian+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --navy: #0f172a;
            --navy-mid: #1e293b;
            --white: #ffffff;
            --grey: #64748b;
            --grey-light: #94a3b8;
            --grey-border: #e2e8f0;
            --green: #059669;
            --blue: #2563eb;
            --grid-dark-fine: rgba(148, 163, 184, 0.08);
            --grid-dark-strong: rgba(148, 163, 184, 0.15);
            --font-display: 'Chakra Petch', sans-serif;
            --font-mono: 'Martian Mono', monospace;

            --syn-green: #34d399;
            --syn-blue: #38bdf8;
            --syn-amber: #fbbf24;
            --syn-purple: #a78bfa;
        }

        body {
            background: #000;
            color: var(--grey-light);
            font-family: var(--font-mono);
            overflow: hidden;
            height: 100vh;
            width: 100vw;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Canvas ── */
        .canvas {
            position: relative;
            overflow: hidden;
            background: var(--navy);
            outline: 2px dashed rgba(148, 163, 184, 0.3);
            outline-offset: 6px;
        }

        .canvas.linkedin { width: 1200px; height: 627px; }
        .canvas.instagram { width: 1080px; height: 1080px; }

        /* ── Blueprint Grid ── */
        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                repeating-linear-gradient(0deg, var(--grid-dark-fine) 0px, var(--grid-dark-fine) 1px, transparent 1px, transparent 32px),
                repeating-linear-gradient(90deg, var(--grid-dark-fine) 0px, var(--grid-dark-fine) 1px, transparent 1px, transparent 32px),
                repeating-linear-gradient(0deg, var(--grid-dark-strong) 0px, var(--grid-dark-strong) 1px, transparent 1px, transparent 160px),
                repeating-linear-gradient(90deg, var(--grid-dark-strong) 0px, var(--grid-dark-strong) 1px, transparent 1px, transparent 160px);
        }

        /* ── Registration Marks ── */
        .reg-mark {
            position: absolute;
            width: 32px; height: 32px;
            z-index: 2;
        }

        .reg-mark::before, .reg-mark::after {
            content: '';
            position: absolute;
            background: rgba(5, 150, 105, 0.2);
        }

        .reg-mark::before { width: 1px; height: 32px; left: 50%; top: 0; }
        .reg-mark::after { width: 32px; height: 1px; top: 50%; left: 0; }

        .reg-mark.tl { top: 16px; left: 16px; }
        .reg-mark.tr { top: 16px; right: 16px; }
        .reg-mark.bl { bottom: 16px; left: 16px; }
        .reg-mark.br { bottom: 16px; right: 16px; }

        /* ── Brand Logo Mark (from brand-blueprint.html — exact reproduction) ── */
        .logo-mark {
            display: inline-block;
            align-self: flex-start;
            font-family: var(--font-display);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 2px solid currentColor;
            padding: 6px 16px;
            position: relative;
            text-decoration: none;
            color: inherit;
        }

        .logo-mark::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
        }

        .logo-mark.color-green::after { background: var(--green); }
        .logo-mark.color-blue::after { background: var(--blue); }

        .logo-mark.size-xl { font-size: 2.5rem; padding: 12px 28px; letter-spacing: 0.12em; }
        .logo-mark.size-xl::after { width: 14px; height: 14px; }
        .logo-mark.size-lg { font-size: 1.5rem; padding: 8px 20px; }
        .logo-mark.size-lg::after { width: 10px; height: 10px; }
        .logo-mark.size-md { font-size: 1rem; padding: 6px 16px; }
        .logo-mark.size-sm { font-size: 0.75rem; padding: 4px 10px; }
        .logo-mark.size-sm::after { width: 5px; height: 5px; }

        /* ── Format Switcher ── */
        .format-switcher {
            position: fixed;
            top: 12px; right: 12px;
            z-index: 100;
            display: flex;
            gap: 6px;
            font-family: var(--font-mono);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .format-switcher a {
            padding: 5px 12px;
            text-decoration: none;
            transition: all 0.15s;
        }

        .format-switcher a.active {
            background: var(--green);
            color: var(--white);
        }

        .format-switcher a:not(.active) {
            background: rgba(255, 255, 255, 0.04);
            color: var(--grey);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }

        .format-switcher a:not(.active):hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--white);
        }

        @yield('styles')
    </style>
</head>
<body>
    <div class="format-switcher">
        <a href="?format=linkedin" class="{{ !$isSquare ? 'active' : '' }}">LinkedIn 1200&times;627</a>
        <a href="?format=instagram" class="{{ $isSquare ? 'active' : '' }}">Instagram 1080&times;1080</a>
    </div>

    <div class="canvas {{ $format }}">
        <div class="bg-grid"></div>
        <div class="reg-mark tl"></div>
        <div class="reg-mark tr"></div>
        <div class="reg-mark bl"></div>
        <div class="reg-mark br"></div>

        @yield('content')
    </div>

    @yield('scripts')
</body>
</html>
