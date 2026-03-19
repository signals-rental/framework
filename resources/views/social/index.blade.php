@php
    $promos = [
        ['route' => 'social.api-promo', 'title' => 'API & Developer Experience', 'accent' => '#059669', 'description' => 'REST API with Sanctum auth, Ransack filtering, and webhooks'],
        ['route' => 'social.admin-promo', 'title' => 'Admin & Setup', 'accent' => '#fbbf24', 'description' => 'CLI installer, web wizard, module system, and admin panel'],
        ['route' => 'social.members-promo', 'title' => 'People & Places', 'accent' => '#38bdf8', 'description' => 'Universal entity for contacts, organisations, venues, and users'],
        ['route' => 'social.permissions-promo', 'title' => 'Permissions & Roles', 'accent' => '#a78bfa', 'description' => 'Four-layer authorisation with store scoping and cost visibility'],
        ['route' => 'social.email-promo', 'title' => 'Email & Templates', 'accent' => '#34d399', 'description' => 'Database-driven templates with merge fields and multi-channel delivery'],
        ['route' => 'social.health-promo', 'title' => 'System Health', 'accent' => '#34d399', 'description' => 'Built-in health checks, metrics, and Nightwatch APM integration'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signals — Social Promo Index</title>
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
            --green: #059669;
            --font-display: 'Chakra Petch', sans-serif;
            --font-mono: 'Martian Mono', monospace;
        }

        body {
            background: var(--navy);
            color: var(--grey-light);
            font-family: var(--font-mono);
            min-height: 100vh;
            padding: 60px 40px;
        }

        .page-header {
            max-width: 960px;
            margin: 0 auto 48px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .page-title {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--white);
        }

        .page-desc {
            font-size: 12px;
            line-height: 1.8;
            color: var(--grey);
            max-width: 600px;
        }

        .promo-grid {
            max-width: 960px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
            gap: 16px;
        }

        .promo-card {
            background: var(--navy-mid);
            border: 1px solid rgba(148, 163, 184, 0.1);
            padding: 28px 32px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: border-color 0.2s;
        }

        .promo-card:hover {
            border-color: rgba(148, 163, 184, 0.25);
        }

        .card-accent {
            width: 24px;
            height: 3px;
        }

        .card-title {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--white);
        }

        .card-desc {
            font-size: 11px;
            line-height: 1.7;
            color: var(--grey);
        }

        .card-links {
            display: flex;
            gap: 12px;
            margin-top: 4px;
        }

        .card-link {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            text-decoration: none;
            padding: 5px 14px;
            border: 1px solid rgba(148, 163, 184, 0.15);
            color: var(--grey-light);
            transition: all 0.15s;
        }

        .card-link:hover {
            background: rgba(255, 255, 255, 0.04);
            color: var(--white);
            border-color: rgba(148, 163, 184, 0.3);
        }
    </style>
</head>
<body>
    <div class="page-header">
        <span class="logo-mark size-md color-green" style="display: inline-block; align-self: flex-start; font-family: var(--font-display); font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; border: 2px solid var(--white); padding: 6px 16px; position: relative; color: var(--white); text-decoration: none;"><span style="position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background: var(--green);"></span>Signals</span>
        <h1 class="page-title">Social Media Promos</h1>
        <p class="page-desc">Promotional graphics for LinkedIn (1200x627) and Instagram (1080x1080). Each page supports ?format=linkedin or ?format=instagram.</p>
    </div>

    <div class="promo-grid">
        @foreach ($promos as $promo)
        <div class="promo-card">
            <div class="card-accent" style="background: {{ $promo['accent'] }};"></div>
            <div class="card-title">{{ $promo['title'] }}</div>
            <div class="card-desc">{{ $promo['description'] }}</div>
            <div class="card-links">
                <a class="card-link" href="{{ route($promo['route'], ['format' => 'linkedin']) }}">LinkedIn</a>
                <a class="card-link" href="{{ route($promo['route'], ['format' => 'instagram']) }}">Instagram</a>
            </div>
        </div>
        @endforeach
    </div>
</body>
</html>
