<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A branded email from the platform itself (sign-up, password reset), using the same email layout as
 * business emails (resources/views/mail/business/message.blade.php) with the platform brand.
 */
class PlatformMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string[]  $lines
     * @param  array<int, array{0: string, 1: string}>  $summary
     * @param  array{0: string, 1: string}|null  $action
     */
    public function __construct(
        public string $mailSubject,
        public string $title,
        public array $lines = [],
        public array $summary = [],
        public ?array $action = null,
        public ?string $outro = null,
        public ?string $preheader = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.business.message', with: [
            'brand' => [
                'name' => config('app.brand', 'PharmaSuite'),
                'logoUrl' => null,
                'color' => '#6d3ef0',
                'initials' => 'PS',
                'address' => null,
                'phone' => null,
            ],
            'title' => $this->title,
            'lines' => $this->lines,
            'summary' => $this->summary,
            'action' => $this->action,
            'outro' => $this->outro,
            'preheader' => $this->preheader,
        ]);
    }
}
