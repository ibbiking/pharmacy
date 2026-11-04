<?php

namespace App\Services\Mail;

use App\Models\Business;
use App\Models\BusinessMailSetting;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a business's email through that business's own SMTP / Mailgun account (Roadmap 4f).
 * Without its own account, the platform mailer is used with the business name as the sender name.
 */
class BusinessMailer
{
    /** The mailer for this business: its own SMTP / Mailgun account, or the platform mailer. */
    public function mailerFor(Business $business): Mailer
    {
        $settings = $this->settings($business);

        return $settings && $settings->usesOwnAccount()
            ? Mail::build($this->transportConfig($settings))
            : Mail::mailer();
    }

    /** Send now (use from queued jobs; never during a request the user waits on, except the settings test). */
    public function send(Business $business, string|array $to, Mailable $mailable): void
    {
        $settings = $this->settings($business);
        $name = $settings?->from_name ?: $business->name;
        $address = $settings?->usesOwnAccount() && $settings->from_address ? $settings->from_address : config('mail.from.address');

        if ($mailable instanceof \Illuminate\Mail\Mailable) {
            $mailable->from($address, $name);
            if ($settings?->reply_to) {
                $mailable->replyTo($settings->reply_to, $name);
            }
        }

        $this->mailerFor($business)->to($to)->send($mailable);
    }

    private function settings(Business $business): ?BusinessMailSetting
    {
        return BusinessMailSetting::query()->where('business_id', $business->id)->first();
    }

    public function transportConfig(BusinessMailSetting $settings): array
    {
        if ($settings->driver === 'mailgun') {
            return [
                'transport' => 'mailgun',
                'domain' => $settings->mailgun_domain,
                'secret' => $settings->mailgun_secret,
                'endpoint' => $settings->mailgun_endpoint ?: 'api.mailgun.net',
                'scheme' => 'https',
            ];
        }

        return [
            'transport' => 'smtp',
            'host' => $settings->smtp_host,
            'port' => $settings->smtp_port ?: 587,
            // Port 465 = implicit TLS (smtps); otherwise STARTTLS is negotiated automatically.
            'scheme' => $settings->smtp_encryption === 'ssl' || (int) $settings->smtp_port === 465 ? 'smtps' : 'smtp',
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'timeout' => 20,
        ];
    }
}
