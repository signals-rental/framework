<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ isset($title) ? $title . ' | Signals' : 'Signals' }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;600;700&family=Martian+Mono:wght@300;400;500&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@php
    use App\Support\BrandColour;

    $brandPrimary = settings('branding.primary_colour', '#1e3a5f');
    $brandAccent = settings('branding.accent_colour', '#3b82f6');
    $brandPrimary = preg_match('/^#[0-9a-fA-F]{3,8}$/', $brandPrimary) ? $brandPrimary : '#1e3a5f';
    $brandAccent = preg_match('/^#[0-9a-fA-F]{3,8}$/', $brandAccent) ? $brandAccent : '#3b82f6';

    // Contrast-safe derivatives. `*-ink` are readable text/heading/active-state
    // colours on light surfaces (darkened only when the raw colour is too pale);
    // `on-*` are the foreground colour to use ON TOP of the raw brand fills.
    // For the stock navy/green theme the inks equal the raw colours and the
    // on-* values resolve to readable defaults, so there is zero visual change.
    $brandPrimaryInk = BrandColour::ink($brandPrimary);
    $brandAccentInk = BrandColour::ink($brandAccent);
    $brandOnPrimary = BrandColour::on($brandPrimary);
    $brandOnAccent = BrandColour::on($brandAccent);
@endphp
<style>
    :root {
        --brand-primary: {{ $brandPrimary }};
        --brand-accent: {{ $brandAccent }};
        --brand-primary-ink: {{ $brandPrimaryInk }};
        --brand-accent-ink: {{ $brandAccentInk }};
        --brand-on-primary: {{ $brandOnPrimary }};
        --brand-on-accent: {{ $brandOnAccent }};
    }
</style>
