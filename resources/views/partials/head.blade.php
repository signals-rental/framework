<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? settings('company.name', 'Signals') }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;600;700&family=Martian+Mono:wght@300;400;500&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@php
    $brandPrimary = settings('branding.primary_colour', '#1e3a5f');
    $brandAccent = settings('branding.accent_colour', '#3b82f6');
    $brandPrimary = preg_match('/^#[0-9a-fA-F]{3,8}$/', $brandPrimary) ? $brandPrimary : '#1e3a5f';
    $brandAccent = preg_match('/^#[0-9a-fA-F]{3,8}$/', $brandAccent) ? $brandAccent : '#3b82f6';
@endphp
<style>
    :root {
        --brand-primary: {{ $brandPrimary }};
        --brand-accent: {{ $brandAccent }};
    }
</style>
