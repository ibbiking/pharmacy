<?php

namespace App\Modules\BulkEmail\Jobs;

use App\Modules\BulkEmail\Models\Campaign;
use App\Modules\BulkEmail\Models\Contact;
use App\Modules\BulkEmail\Models\CampaignLog;
use App\Modules\BulkEmail\Models\SmtpSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign;
    protected $contact;

    public function __construct(Campaign $campaign, Contact $contact)
    {
        $this->campaign = $campaign;
        $this->contact = $contact;
    }

    public function handle()
    {
        $smtp = SmtpSetting::where('is_active', true)->first() ?? SmtpSetting::first();
        if (!$smtp) return;

        $this->applySmtpConfig($smtp);

        $trackingId = Str::uuid();
        
        try {
            Mail::send([], [], function ($message) use ($smtp, $trackingId, &$htmlBody) {
                $htmlBody = $this->parseTemplate($this->campaign->template->body, $this->contact);
                
                // Add signature if exists
                if ($this->campaign->signature) {
                    $htmlBody .= $this->parseSignature($this->campaign->signature, $message);
                }
                
                // Final Subject (Campaign override -> Template fallback -> Generic fallback)
                $subject = $this->campaign->subject ?: $this->campaign->template->subject ?: 'Bulk Email';
                $finalSubject = $this->parseTemplate($subject, $this->contact);

                // Add tracking pixel
                $htmlBody .= '<img src="' . route('bec.track.open', $trackingId) . '" width="1" height="1" />';

                $fromName = $this->campaign->from_name ?: $smtp->from_name;

                $message->to($this->contact->email)
                        ->from($smtp->from_email, $fromName)
                        ->subject($finalSubject)
                        ->setBody($htmlBody, 'text/html');
                
                // Add Attachments from Campaign
                if ($this->campaign->attachments && is_array($this->campaign->attachments)) {
                    foreach ($this->campaign->attachments as $attach) {
                        $path = storage_path('app/' . $attach['path']);
                        if (file_exists($path)) {
                            $message->attach($path, ['as' => $attach['name']]);
                        }
                    }
                }

                // Add Attachments from Template
                if ($this->campaign->template->attachments && is_array($this->campaign->template->attachments)) {
                    foreach ($this->campaign->template->attachments as $attach) {
                        $path = storage_path('app/' . $attach['path']);
                        if (file_exists($path)) {
                            $message->attach($path, ['as' => $attach['name']]);
                        }
                    }
                }

                $message->getHeaders()->addTextHeader('X-BEC-Tracking-ID', $trackingId);
            });

            CampaignLog::create([
                'campaign_id' => $this->campaign->id,
                'contact_id' => $this->contact->id,
                'email' => $this->contact->email,
                'status' => 'sent',
                'tracking_id' => $trackingId,
            ]);
        } catch (\Exception $e) {
            CampaignLog::create([
                'campaign_id' => $this->campaign->id,
                'contact_id' => $this->contact->id,
                'email' => $this->contact->email,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'tracking_id' => $trackingId,
            ]);
        }
    }

    private function parseTemplate($content, $contact)
    {
        $data = $contact->data;
        foreach ($data as $key => $value) {
            $content = str_replace('{{ ' . $key . ' }}', $value, $content);
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        $content = str_replace('{{ email }}', $contact->email, $content);
        return $content;
    }

    private function parseSignature($signature, $message)
    {
        $html = '<div class="bec-signature" style="margin-top:20px; border-top:1px solid #eee; padding-top:10px;">';
        if ($signature->logo) {
            $path = storage_path('app/public/' . $signature->logo);
            if (file_exists($path)) {
                $cid = $message->embed($path);
                $html .= '<img src="' . $cid . '" style="max-width:150px;" /><br>';
            }
        }
        $html .= '<strong>' . $signature->name . '</strong><br>';
        if ($signature->designation) $html .= $signature->designation . ' | ';
        if ($signature->company) $html .= $signature->company . '<br>';
        if ($signature->phone) $html .= 'Phone: ' . $signature->phone . '<br>';
        if ($signature->website) $html .= 'Website: <a href="' . $signature->website . '">' . $signature->website . '</a><br>';
        $html .= '</div>';
        return $html;
    }

    private function applySmtpConfig(SmtpSetting $smtp)
    {
        Config::set('mail.mailers.smtp.host', $smtp->host);
        Config::set('mail.mailers.smtp.port', $smtp->port);
        Config::set('mail.mailers.smtp.username', $smtp->username);
        Config::set('mail.mailers.smtp.password', $smtp->password);
        Config::set('mail.mailers.smtp.encryption', $smtp->encryption);
        Config::set('mail.from.address', $smtp->from_email);
        Config::set('mail.from.name', $smtp->from_name);
    }
}
