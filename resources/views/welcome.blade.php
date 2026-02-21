<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signals</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Martian+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --base: #f8f9fb;
            --white: #ffffff;
            --navy: #0f172a;
            --navy-mid: #1e293b;
            --blue: #2563eb;
            --green: #059669;
            --grey: #64748b;
            --grey-light: #94a3b8;
            --grey-border: #e2e8f0;
            --font-display: 'Chakra Petch', sans-serif;
            --font-mono: 'Martian Mono', monospace;

            /* Light mode defaults */
            --bg: var(--base);
            --text: var(--navy);
            --text-muted: var(--grey);
            --card-bg: var(--white);
            --card-border: var(--grey-border);
            --badge-border: rgba(100, 116, 139, 0.2);
            --outline-color: var(--navy);
            --outline-hover-bg: rgba(15, 23, 42, 0.06);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: var(--navy);
                --text: var(--white);
                --text-muted: var(--grey-light);
                --card-bg: var(--navy-mid);
                --card-border: rgba(148, 163, 184, 0.15);
                --badge-border: rgba(148, 163, 184, 0.2);
                --outline-color: var(--white);
                --outline-hover-bg: rgba(255, 255, 255, 0.08);
            }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-mono);
            background: var(--bg);
            color: var(--text);
            line-height: 1.7;
            font-size: 13px;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        /* Light grid */
        .grid-bg {
            background-color: var(--base);
            background-image:
                repeating-linear-gradient(0deg, rgba(59,130,246,0.06) 0px, rgba(59,130,246,0.06) 1px, transparent 1px, transparent 32px),
                repeating-linear-gradient(90deg, rgba(59,130,246,0.06) 0px, rgba(59,130,246,0.06) 1px, transparent 1px, transparent 32px),
                repeating-linear-gradient(0deg, rgba(59,130,246,0.12) 0px, rgba(59,130,246,0.12) 1px, transparent 1px, transparent 160px),
                repeating-linear-gradient(90deg, rgba(59,130,246,0.12) 0px, rgba(59,130,246,0.12) 1px, transparent 1px, transparent 160px);
        }

        @media (prefers-color-scheme: dark) {
            .grid-bg {
                background-color: var(--navy);
                background-image:
                    repeating-linear-gradient(0deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(90deg, rgba(148,163,184,0.08) 0px, rgba(148,163,184,0.08) 1px, transparent 1px, transparent 32px),
                    repeating-linear-gradient(0deg, rgba(148,163,184,0.15) 0px, rgba(148,163,184,0.15) 1px, transparent 1px, transparent 160px),
                    repeating-linear-gradient(90deg, rgba(148,163,184,0.15) 0px, rgba(148,163,184,0.15) 1px, transparent 1px, transparent 160px);
            }
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 32px; }

        h1, h2, h3 {
            font-family: var(--font-display);
            text-transform: uppercase;
            letter-spacing: -0.01em;
            font-weight: 700;
        }
        h1 { font-size: clamp(2rem, 4vw, 3rem); line-height: 1.1; }
        h1 em { font-style: italic; color: var(--green); font-weight: 600; }
        h2 { font-size: clamp(1.5rem, 3vw, 2.25rem); line-height: 1.15; }

        .logo-mark {
            display: inline-block;
            font-family: var(--font-display);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 2px solid currentColor;
            position: relative;
            text-decoration: none;
            color: var(--text);
        }
        .logo-mark::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--green);
        }
        .logo-mark.size-xl { font-size: 2.5rem; padding: 12px 28px; letter-spacing: 0.12em; }
        .logo-mark.size-xl::after { width: 14px; height: 14px; }
        .logo-mark.size-sm { font-size: 0.75rem; padding: 4px 10px; }
        .logo-mark.size-sm::after { width: 5px; height: 5px; }

        .annotation {
            font-family: var(--font-mono);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }

        .section-stamp {
            display: inline-flex;
            align-items: stretch;
            margin-bottom: 32px;
            font-family: var(--font-display);
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: 2px solid var(--navy);
        }
        .stamp-num { background: var(--navy); color: var(--white); padding: 4px 10px; }
        .stamp-title { padding: 4px 12px; color: var(--navy); border-right: 2px solid var(--navy); }
        .stamp-rev { padding: 4px 10px; color: var(--green); font-weight: 700; }

        @media (prefers-color-scheme: dark) {
            .section-stamp { border-color: var(--grey-light); }
            .stamp-num { background: var(--grey-light); color: var(--navy); }
            .stamp-title { color: var(--grey-light); border-right-color: var(--grey-light); }
        }

        .dimension-line { display: flex; align-items: center; padding: 24px 32px; max-width: 1200px; margin: 0 auto; }
        .dim-arrow-left { width: 0; height: 0; border-top: 4px solid transparent; border-bottom: 4px solid transparent; border-right: 8px solid var(--green); }
        .dim-arrow-right { width: 0; height: 0; border-top: 4px solid transparent; border-bottom: 4px solid transparent; border-left: 8px solid var(--green); }
        .dim-line { flex: 1; height: 1px; background: var(--green); opacity: 0.3; }
        .dim-label { padding: 0 16px; font-family: var(--font-mono); font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); white-space: nowrap; }

        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .hero-content { text-align: center; max-width: 720px; }

        .hero-subtitle {
            font-size: 0.8125rem;
            color: var(--text-muted);
            line-height: 1.8;
            max-width: 560px;
            margin: 0 auto;
        }

        .btn {
            display: inline-block;
            font-family: var(--font-display);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-decoration: none;
            padding: 10px 24px;
            font-size: 0.8125rem;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--green);
            color: var(--white);
            border: 1px solid var(--green);
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-outline {
            background: transparent;
            color: var(--outline-color);
            border: 1px solid var(--outline-color);
        }
        .btn-outline:hover { background: var(--outline-hover-bg); }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-mono);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            padding: 6px 12px;
            border: 1px solid var(--badge-border);
            margin-bottom: 24px;
        }
        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
        }

        .cli-block {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 24px 32px;
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-muted);
            position: relative;
        }
        .cli-block .prompt { color: var(--green); }
        .cli-block .command { color: var(--text); }

        .trim-marks {
            position: absolute;
            width: 18px;
            height: 18px;
        }
        .trim-marks::before,
        .trim-marks::after { content: ''; position: absolute; background: var(--green); opacity: 0.3; }
        .trim-tl::before { top: 0; left: 0; width: 18px; height: 1.5px; }
        .trim-tl::after { top: 0; left: 0; width: 1.5px; height: 18px; }
        .trim-tr::before { top: 0; right: 0; width: 18px; height: 1.5px; }
        .trim-tr::after { top: 0; right: 0; width: 1.5px; height: 18px; }
        .trim-bl::before { bottom: 0; left: 0; width: 18px; height: 1.5px; }
        .trim-bl::after { bottom: 0; left: 0; width: 1.5px; height: 18px; }
        .trim-br::before { bottom: 0; right: 0; width: 18px; height: 1.5px; }
        .trim-br::after { bottom: 0; right: 0; width: 1.5px; height: 18px; }

        footer {
            padding: 48px 0;
            text-align: center;
        }
        footer a { color: var(--text-muted); }
        footer a:hover { color: var(--text); }

        .reveal { opacity: 0; transform: translateY(20px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        @media (max-width: 768px) {
            .hero { min-height: auto; padding: 120px 0 80px; }
        }
        @media (max-width: 480px) {
            .container { padding: 0 16px; }
        }
    </style>
</head>
<body class="grid-bg">

<!-- ============================================ -->
<!-- HERO                                         -->
<!-- ============================================ -->
<section class="hero">
    <div class="trim-marks trim-tl" style="top: 32px; left: 32px;"></div>
    <div class="trim-marks trim-tr" style="top: 32px; right: 32px;"></div>
    <div class="trim-marks trim-bl" style="bottom: 32px; left: 32px;"></div>
    <div class="trim-marks trim-br" style="bottom: 32px; right: 32px;"></div>

    <div class="hero-content">
        <div class="status-badge">
            <span class="status-dot"></span>
            @if(config('signals.installed'))
                Infrastructure configured
            @else
                Awaiting configuration
            @endif
        </div>

        <div style="margin-bottom: 32px;">
            <span class="logo-mark size-xl">Signals</span>
        </div>

        <div style="margin-bottom: 12px;">
            <span class="annotation">Open Source Rental Management Framework</span>
        </div>

        <h1 style="margin-bottom: 20px;">Professional <em>Rental</em> Software</h1>

        <p class="hero-subtitle" style="margin-bottom: 40px;">
            Free. Open Source. Forever. Everything you need to run a modern equipment rental company.
        </p>

        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            @if(config('signals.installed') && config('signals.setup_complete'))
                <a href="{{ route('login') }}" class="btn btn-primary">Log In</a>
                <a href="https://framework.signals.rent/docs" class="btn btn-outline" target="_blank">Documentation</a>
            @elseif(config('signals.installed'))
                <a href="{{ url('/setup') }}" class="btn btn-primary">Complete Setup</a>
                <a href="{{ route('login') }}" class="btn btn-outline">Log In</a>
            @else
                <a href="https://framework.signals.rent/docs" class="btn btn-primary" target="_blank">Get Started</a>
                <a href="https://github.com/signals-rental/framework" class="btn btn-outline" target="_blank">GitHub</a>
            @endif
        </div>
    </div>
</section>

<!-- Dimension Divider -->
<div class="dimension-line">
    <span class="dim-arrow-left"></span><span class="dim-line"></span>
    <span class="dim-label">Next Steps</span>
    <span class="dim-line"></span><span class="dim-arrow-right"></span>
</div>

<!-- ============================================ -->
<!-- CLI QUICKSTART                               -->
<!-- ============================================ -->
<section style="padding: 60px 0;">
    <div class="container">
        <div class="section-stamp">
            <span class="stamp-num">SIG-002</span>
            <span class="stamp-title">Getting Started</span>
            <span class="stamp-rev">V1</span>
        </div>

        <h2 style="margin-bottom: 12px;">Quick <em>Start</em></h2>
        <p class="hero-subtitle" style="margin-bottom: 48px;">
            Configure your infrastructure in minutes with the interactive installer.
        </p>

        <div class="cli-block" style="margin-bottom: 16px;">
            <span class="annotation" style="position: absolute; top: 8px; right: 12px;">[Terminal]</span>
            <div style="margin-bottom: 8px;"><span class="prompt">$</span> <span class="command">php artisan signals:install</span></div>
            <div style="color: var(--grey);">Configure PostgreSQL, Redis, S3, and Reverb interactively.</div>
        </div>
        <div class="cli-block" style="margin-bottom: 16px;">
            <div style="margin-bottom: 8px;"><span class="prompt">$</span> <span class="command">php artisan signals:status</span></div>
            <div style="color: var(--grey);">Check connection health and installation state.</div>
        </div>
        <div class="cli-block">
            <div style="margin-bottom: 8px;"><span class="prompt">$</span> <span class="command">composer dev</span></div>
            <div style="color: var(--grey);">Start server, queue worker, Vite, and Reverb concurrently.</div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- FOOTER                                       -->
<!-- ============================================ -->
<footer>
    <div class="container">
        <span class="logo-mark size-sm" style="border-color: var(--text-muted); margin-bottom: 20px;">Signals</span>
        <div style="margin-top: 20px; font-size: 0.5625rem; color: var(--grey); text-transform: uppercase; letter-spacing: 0.06em;">
            Signals Rental Framework &mdash; Free &amp; Open Source &mdash; MIT License
        </div>
        <div style="margin-top: 12px; display: flex; gap: 24px; justify-content: center;">
            <a href="https://framework.signals.rent/docs" style="font-size: 0.5625rem; text-decoration: none; text-transform: uppercase; letter-spacing: 0.06em;">Documentation</a>
            <a href="https://github.com/signals-rental/framework" style="font-size: 0.5625rem; text-decoration: none; text-transform: uppercase; letter-spacing: 0.06em;">GitHub</a>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var els = document.querySelectorAll('.section-stamp, .cli-block, h1, h2');
    var obs = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });
    els.forEach(function(el) { el.classList.add('reveal'); obs.observe(el); });
});
</script>

</body>
</html>
