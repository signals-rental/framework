<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $subjectLine  Rendered subject line.
     * @param  string  $bodyHtml  Rendered (markdown -> HTML) body content.
     * @param  string|null  $eyebrow  Optional mono eyebrow label above the body.
     * @param  string|null  $footerContext  Optional footer context line.
     * @param  string|null  $preheader  Optional hidden preheader text.
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        public ?string $eyebrow = null,
        public ?string $footerContext = null,
        public ?string $preheader = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.layouts.signals',
            with: [
                'bodyHtml' => $this->bodyHtml,
                'eyebrow' => $this->eyebrow,
                'footerContext' => $this->footerContext,
                'preheader' => $this->preheader,
            ],
        );
    }
}
