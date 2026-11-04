<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * A branded email from a business (logo or initials badge, brand colour, address footer).
 * Documents (invoices, receipts, POs, statements) build on this; the layout lives in
 * resources/views/mail/business/message.blade.php.
 */
class BusinessMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string[]  $lines
     * @param  array<int, array{0: string, 1: string}>  $summary
     * @param  array{0: string, 1: string}|null  $action
     */
    public function __construct(
        public Business $business,
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
            'brand' => self::brandFor($this->business),
            'title' => $this->title,
            'lines' => $this->lines,
            'summary' => $this->summary,
            'action' => $this->action,
            'outro' => $this->outro,
            'preheader' => $this->preheader,
        ]);
    }

    public static function brandFor(Business $business): array
    {
        $initials = collect(preg_split('/\s+/', trim($business->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

        return [
            'name' => $business->name,
            'logoUrl' => $business->logo ? url(Storage::disk('uploads')->url($business->logo)) : null,
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $business->brand_color) ? $business->brand_color : '#6d3ef0',
            'initials' => $initials ?: 'B',
            'address' => $business->address,
            'phone' => $business->phone,
        ];
    }
}
