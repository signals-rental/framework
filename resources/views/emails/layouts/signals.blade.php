@php
    /** @var string $bodyHtml */
    /** @var string|null $eyebrow */
    /** @var string|null $preheader */
    /** @var string|null $footerContext */
    $eyebrow ??= null;
    $preheader ??= null;
    $footerContext ??= null;
    $companyName = settings('company.name', 'Signals');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{ $companyName }}</title>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@600;700&family=Martian+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@600;700&family=Martian+Mono:wght@400;500&display=swap');
    body { margin: 0; padding: 0; width: 100% !important; }
    table { border-collapse: collapse; }
    a { color: #059669; }

    /* Body content typography (renderer outputs markdown HTML without inline styles) */
    .sig-body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; }
    .sig-body p { margin: 0 0 18px; font-size: 16px; line-height: 1.7; color: #334155; }
    .sig-body h1 { margin: 0 0 22px; font-family: 'Chakra Petch','Segoe UI',Helvetica,Arial,sans-serif; font-weight: 700; font-size: 32px; line-height: 1.15; letter-spacing: -0.5px; text-transform: uppercase; color: #0f172a; }
    .sig-body h2 { margin: 0 0 16px; font-family: 'Chakra Petch','Segoe UI',Helvetica,Arial,sans-serif; font-weight: 700; font-size: 22px; line-height: 1.2; text-transform: uppercase; color: #0f172a; }
    .sig-body h3 { margin: 0 0 14px; font-family: 'Chakra Petch','Segoe UI',Helvetica,Arial,sans-serif; font-weight: 600; font-size: 18px; line-height: 1.3; color: #0f172a; }
    .sig-body a { color: #059669; text-decoration: underline; }
    .sig-body strong { color: #0f172a; font-weight: 700; }
    .sig-body ul, .sig-body ol { margin: 0 0 18px; padding-left: 22px; }
    .sig-body li { font-size: 16px; line-height: 1.7; color: #334155; margin: 0 0 8px; }
    .sig-body hr { border: none; border-top: 1px solid #e2e8f0; margin: 26px 0; }

    /* CTA button: template bodies use <a class="sig-btn" href="...">Label</a> */
    .sig-body a.sig-btn {
        display: inline-block;
        background-color: #059669;
        padding: 15px 30px;
        font-family: 'Chakra Petch','Segoe UI',Helvetica,Arial,sans-serif;
        font-weight: 700;
        font-size: 13px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #ffffff !important;
        text-decoration: none;
        margin: 4px 0 22px;
    }

    @media only screen and (max-width: 620px) {
        .sig-container { width: 100% !important; }
        .sig-pad { padding-left: 24px !important; padding-right: 24px !important; }
        .sig-body h1 { font-size: 28px !important; }
    }
</style>
</head>
<body style="margin:0; padding:0; background-color:#f8f9fb; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
@if ($preheader)
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f8f9fb; opacity:0;">
    {{ $preheader }}
</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fb;">
<tr>
<td align="center" style="padding:40px 16px;">
    <table role="presentation" class="sig-container" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff; border:2px solid #0f172a;">
        <tr>
        <td class="sig-pad" align="left" style="padding:38px 40px 32px; background-color:#0f172a;">
            <!--[if mso]>
            <table role="presentation" cellpadding="0" cellspacing="0" align="left">
                <tr><td align="center" style="padding-bottom:16px;">
                    <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                        <td style="border:2px solid #ffffff; padding:9px 20px; font-family:'Segoe UI',Arial,sans-serif; font-weight:bold; font-size:23px; letter-spacing:3px; color:#ffffff; line-height:1;">SIGNALS</td>
                        <td valign="top" width="11"><div style="width:9px; height:9px; background-color:#059669; font-size:1px; line-height:1px;">&nbsp;</div></td>
                    </tr></table>
                </td></tr>
                <tr><td align="center" style="font-family:'Courier New',Courier,monospace; font-size:9px; letter-spacing:2.5px; text-transform:uppercase; color:#94a3b8;">Rental Framework</td></tr>
            </table>
            <![endif]-->
            <!--[if !mso]><!-- -->
            <table role="presentation" cellpadding="0" cellspacing="0" align="left">
                <tr><td align="center" style="padding-bottom:16px; line-height:0;">
                    <div style="position:relative; display:inline-block; line-height:0;">
                        <div style="border:2px solid #ffffff; padding:9px 20px; font-family:'Chakra Petch','Segoe UI',Helvetica,Arial,sans-serif; font-weight:700; font-size:23px; letter-spacing:0.1em; text-transform:uppercase; color:#ffffff; line-height:1;">SIGNALS</div>
                        <div style="position:absolute; top:0; right:0; width:9px; height:9px; background-color:#059669; font-size:1px; line-height:1px;">&nbsp;</div>
                    </div>
                </td></tr>
                <tr><td align="center" style="font-family:'Martian Mono','Courier New',Courier,monospace; font-size:9px; letter-spacing:2.5px; text-transform:uppercase; color:#94a3b8;">Rental Framework</td></tr>
            </table>
            <!--<![endif]-->
        </td>
        </tr>
        <tr>
        <td class="sig-pad" style="padding:40px;">
            @if ($eyebrow)
                <div style="font-family:'Martian Mono','Courier New',Courier,monospace; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#059669; margin-bottom:16px;">{{ $eyebrow }}</div>
            @endif
            <div class="sig-body">
                {!! $bodyHtml !!}
            </div>
        </td>
        </tr>
        <tr>
        <td class="sig-pad" style="padding:24px 40px 28px; border-top:1px solid #e2e8f0; background-color:#f8f9fb;">
            <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; font-size:12px; line-height:1.7; color:#64748b;">
                @if ($footerContext)
                    {{ $footerContext }}<br>
                @endif
                &copy; {{ date('Y') }} {{ $companyName }}
            </p>
        </td>
        </tr>
    </table>
</td>
</tr>
</table>
</body>
</html>
