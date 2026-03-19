@php
    $format = request()->query('format', 'linkedin');
    $isSquare = $format === 'instagram';
@endphp

@extends('social.layout', ['title' => 'Email & Templates'])

@section('styles')
<style>
    .promo-layout { position: relative; z-index: 1; height: 100%; }

    .promo-layout.linkedin { display: grid; grid-template-columns: 380px 1fr; padding: 52px 60px; gap: 52px; }
    .promo-layout.linkedin .copy { display: flex; flex-direction: column; justify-content: center; gap: 24px; }
    .promo-layout.linkedin .email-area { display: flex; align-items: center; justify-content: center; }
    .promo-layout.linkedin .headline { font-size: 54px; }
    .promo-layout.linkedin .subline { font-size: 14px; max-width: 380px; }
    .promo-layout.linkedin .feature { font-size: 14px; }
    .promo-layout.linkedin .cta-url { font-size: 12px; }
    .promo-layout.linkedin .email-card { max-width: 520px; }
    .promo-layout.linkedin .email-subject { font-size: 16px; }
    .promo-layout.linkedin .email-meta { font-size: 11px; }
    .promo-layout.linkedin .email-body { font-size: 14px; padding: 24px 28px; }

    .promo-layout.instagram { display: flex; flex-direction: column; padding: 56px 56px 48px; gap: 28px; }
    .promo-layout.instagram .copy { display: flex; flex-direction: column; gap: 18px; flex-shrink: 0; }
    .promo-layout.instagram .email-area { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; }
    .promo-layout.instagram .headline { font-size: 64px; }
    .promo-layout.instagram .subline { display: none; }
    .promo-layout.instagram .features { flex-direction: row; flex-wrap: wrap; gap: 6px 24px; }
    .promo-layout.instagram .feature { font-size: 16px; }
    .promo-layout.instagram .cta-url { font-size: 14px; }
    .promo-layout.instagram .email-card { max-width: 100%; }
    .promo-layout.instagram .email-subject { font-size: 18px; }
    .promo-layout.instagram .email-meta { font-size: 12px; }
    .promo-layout.instagram .email-body { font-size: 16px; padding: 28px 32px; }

    .headline { font-family: var(--font-display); font-weight: 700; line-height: 1.08; letter-spacing: -0.01em; text-transform: uppercase; color: var(--white); }
    .headline .hl-accent { color: var(--syn-green); }
    .subline { font-family: var(--font-mono); line-height: 1.8; color: var(--grey-light); }
    .features { display: flex; flex-direction: column; gap: 8px; }
    .feature { display: flex; align-items: center; gap: 10px; color: var(--grey-light); font-family: var(--font-mono); }
    .feature-dot { width: 6px; height: 6px; background: var(--green); flex-shrink: 0; }
    .cta-url { font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.06em; color: var(--grey); }

    /* ── Email Template Card ── */
    .email-card {
        width: 100%;
        background: var(--navy-mid);
        border: 1px solid rgba(148, 163, 184, 0.12);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        opacity: 0;
        transform: translateY(8px);
        transition: opacity 0.5s, transform 0.5s;
    }

    .email-card.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .email-header {
        padding: 18px 24px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.08);
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .email-subject-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .email-subject-label {
        font-family: var(--font-mono);
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--grey);
        flex-shrink: 0;
    }

    .email-subject {
        font-family: var(--font-display);
        font-weight: 600;
        color: var(--white);
    }

    .email-meta {
        font-family: var(--font-mono);
        color: var(--grey);
        display: flex;
        gap: 20px;
    }

    .email-meta-val { color: var(--grey-light); }

    .email-body {
        font-family: var(--font-mono);
        line-height: 2;
        color: var(--grey-light);
    }

    .merge-field {
        display: inline;
        position: relative;
    }

    .merge-placeholder {
        color: var(--syn-purple);
        background: rgba(167, 139, 250, 0.08);
        padding: 1px 6px;
        transition: opacity 0.3s, color 0.3s, background 0.3s;
    }

    .merge-placeholder.resolving {
        color: var(--syn-amber);
        background: rgba(251, 191, 36, 0.1);
    }

    .merge-placeholder.resolved {
        color: var(--syn-green);
        background: rgba(52, 211, 153, 0.08);
    }

    .email-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(148, 163, 184, 0.08);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .send-btn {
        font-family: var(--font-display);
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 8px 24px;
        background: rgba(5, 150, 101, 0.15);
        border: 1px solid rgba(5, 150, 101, 0.3);
        color: var(--grey);
        transition: all 0.4s;
    }

    .send-btn.ready {
        background: var(--green);
        border-color: var(--green);
        color: var(--white);
    }

    .send-btn.sending {
        background: rgba(251, 191, 36, 0.2);
        border-color: rgba(251, 191, 36, 0.4);
        color: var(--syn-amber);
    }

    .send-btn.sent {
        background: rgba(52, 211, 153, 0.15);
        border-color: rgba(52, 211, 153, 0.3);
        color: var(--syn-green);
    }

    .send-status {
        font-family: var(--font-mono);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--grey);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .send-status.visible {
        opacity: 1;
    }

    .send-status.success { color: var(--syn-green); }

    .channel-badges {
        display: flex;
        gap: 8px;
        margin-left: auto;
    }

    .channel-badge {
        font-family: var(--font-mono);
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 3px 10px;
        border: 1px solid rgba(148, 163, 184, 0.12);
        color: var(--grey);
        opacity: 0;
        transform: translateY(4px);
        transition: all 0.3s;
    }

    .channel-badge.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .channel-badge.active {
        border-color: rgba(52, 211, 153, 0.3);
        color: var(--syn-green);
        background: rgba(52, 211, 153, 0.06);
    }
</style>
@endsection

@section('content')
<div class="promo-layout {{ $format }}">
    <div class="copy">
        <span class="logo-mark {{ $isSquare ? 'size-xl' : 'size-lg' }} color-green" style="color: var(--white);">Signals</span>

        <h1 class="headline">
            Templates<br>that send<br><span class="hl-accent">themselves.</span>
        </h1>

        <p class="subline">
            Database-driven email templates with merge fields, multi-channel delivery, and per-user notification preferences.
        </p>

        <div class="features">
            <div class="feature"><span class="feature-dot"></span> SMTP config in admin, not .env</div>
            <div class="feature"><span class="feature-dot"></span> Merge fields with safe syntax</div>
            <div class="feature"><span class="feature-dot"></span> Email, in-app, and broadcast channels</div>
            <div class="feature"><span class="feature-dot"></span> User-controlled notification prefs</div>
        </div>

        <span class="cta-url">docs.signals.rent</span>
    </div>

    <div class="email-area">
        <div class="email-card" id="emailCard">
            <div class="email-header">
                <div class="email-subject-row">
                    <span class="email-subject-label">Subject</span>
                    <span class="email-subject" id="emailSubject"></span>
                </div>
                <div class="email-meta">
                    <span>From: <span class="email-meta-val">Apex Hire</span></span>
                    <span>To: <span class="email-meta-val" id="emailTo"></span></span>
                    <span>Template: <span class="email-meta-val">Quote Ready</span></span>
                </div>
            </div>
            <div class="email-body" id="emailBody"></div>
            <div class="email-footer">
                <div class="send-btn" id="sendBtn">Send</div>
                <div class="send-status" id="sendStatus"></div>
                <div class="channel-badges">
                    <div class="channel-badge" id="chEmail">Email</div>
                    <div class="channel-badge" id="chInApp">In-app</div>
                    <div class="channel-badge" id="chBroadcast">Broadcast</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    const card = document.getElementById('emailCard');
    const subjectEl = document.getElementById('emailSubject');
    const toEl = document.getElementById('emailTo');
    const bodyEl = document.getElementById('emailBody');
    const sendBtn = document.getElementById('sendBtn');
    const sendStatus = document.getElementById('sendStatus');

    const mergeFields = [
        { placeholder: '@{{ member.first_name }}', value: 'James' },
        { placeholder: '@{{ opportunity.number }}', value: 'QU-2024-0847' },
        { placeholder: '@{{ opportunity.total | money }}', value: '\u00a31,245.00' },
        { placeholder: '@{{ opportunity.starts_at | date }}', value: '15 Mar 2026' },
        { placeholder: '@{{ company.name }}', value: 'Apex Hire' },
    ];

    const templateLines = [
        'Hi @{{ member.first_name }},',
        '',
        'Your quote #@{{ opportunity.number }} for @{{ opportunity.total | money }} is ready to review.',
        '',
        'Event date: @{{ opportunity.starts_at | date }}',
        '',
        'View your quote online or reply to this email with any questions.',
        '',
        'Best regards,',
        '@{{ company.name }}',
    ];

    // Build the template body with merge field spans
    const fieldSpans = [];

    for (const line of templateLines) {
        const lineDiv = document.createElement('div');
        if (line === '') {
            lineDiv.style.height = '0.8em';
            bodyEl.appendChild(lineDiv);
            continue;
        }

        let remaining = line;
        while (remaining.length > 0) {
            let earliest = -1;
            let earliestField = null;

            for (const field of mergeFields) {
                const idx = remaining.indexOf(field.placeholder);
                if (idx !== -1 && (earliest === -1 || idx < earliest)) {
                    earliest = idx;
                    earliestField = field;
                }
            }

            if (earliestField && earliest !== -1) {
                if (earliest > 0) {
                    lineDiv.appendChild(document.createTextNode(remaining.substring(0, earliest)));
                }
                const span = document.createElement('span');
                span.className = 'merge-placeholder';
                span.textContent = earliestField.placeholder;
                span.dataset.value = earliestField.value;
                lineDiv.appendChild(span);
                fieldSpans.push(span);
                remaining = remaining.substring(earliest + earliestField.placeholder.length);
            } else {
                lineDiv.appendChild(document.createTextNode(remaining));
                remaining = '';
            }
        }

        bodyEl.appendChild(lineDiv);
    }

    async function animate() {
        // Show card
        await sleep(600);
        card.classList.add('visible');
        await sleep(200);

        // Type subject
        const subjectText = 'Your quote is ready';
        for (let i = 0; i < subjectText.length; i++) {
            subjectEl.textContent += subjectText[i];
            await sleep(30 + Math.random() * 20);
        }

        // Type recipient
        await sleep(200);
        const toText = 'james@festivalsound.co.uk';
        for (let i = 0; i < toText.length; i++) {
            toEl.textContent += toText[i];
            await sleep(25 + Math.random() * 15);
        }

        await sleep(1200);

        // Resolve merge fields one by one
        for (const span of fieldSpans) {
            span.classList.add('resolving');
            await sleep(400);
            span.textContent = span.dataset.value;
            span.classList.remove('resolving');
            span.classList.add('resolved');
            await sleep(600);
        }

        await sleep(1000);

        // Activate send button
        sendBtn.classList.add('ready');
        await sleep(800);

        // Sending
        sendBtn.classList.remove('ready');
        sendBtn.classList.add('sending');
        sendBtn.textContent = 'Sending...';
        await sleep(1200);

        // Sent
        sendBtn.classList.remove('sending');
        sendBtn.classList.add('sent');
        sendBtn.textContent = '\u2713 Sent';

        sendStatus.textContent = 'Delivered in 340ms';
        sendStatus.classList.add('visible', 'success');

        await sleep(600);

        // Show channel badges
        const badges = [
            document.getElementById('chEmail'),
            document.getElementById('chInApp'),
            document.getElementById('chBroadcast'),
        ];

        for (let i = 0; i < badges.length; i++) {
            badges[i].classList.add('visible');
            if (i < 2) badges[i].classList.add('active');
            await sleep(300);
        }
    }

    setTimeout(animate, 400);
</script>
@endsection
