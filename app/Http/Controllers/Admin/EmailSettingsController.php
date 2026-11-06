<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BusinessMessage;
use App\Models\Business;
use App\Models\BusinessMailSetting;
use App\Services\Mail\BusinessMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Settings → Email & branding (Roadmap 4f). Owner only. */
class EmailSettingsController extends Controller
{
    /** Quick-fill presets for common providers (SeeATS-style quick picks). */
    private const PRESETS = [
        ['key' => 'gmail', 'label' => 'Gmail / Google Workspace', 'icon' => 'google', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'hint' => 'Use an App Password (Google account → Security → App passwords).'],
        ['key' => 'outlook', 'label' => 'Microsoft 365 / Outlook', 'icon' => 'microsoft', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'hint' => 'SMTP AUTH must be enabled for the mailbox.'],
        ['key' => 'zoho', 'label' => 'Zoho Mail', 'icon' => 'envelope', 'host' => 'smtp.zoho.com', 'port' => 465, 'encryption' => 'ssl', 'hint' => 'Use an application-specific password if 2FA is on.'],
        ['key' => 'ses', 'label' => 'Amazon SES', 'icon' => 'amazon', 'host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'hint' => 'Use SES SMTP credentials and the region of your SES account.'],
    ];

    public function index(Request $request)
    {
        $business = $this->ownerBusiness($request);
        $s = BusinessMailSetting::query()->firstOrNew(['business_id' => $business->id]);

        return Inertia::render('Settings/Email', [
            'settings' => [
                'driver' => $s->driver ?? 'platform',
                'from_name' => $s->from_name ?? $business->name,
                'from_address' => $s->from_address,
                'reply_to' => $s->reply_to,
                'smtp_host' => $s->smtp_host,
                'smtp_port' => $s->smtp_port,
                'smtp_encryption' => $s->smtp_encryption ?? 'tls',
                'smtp_username' => $s->smtp_username,
                'has_smtp_password' => filled($s->getRawOriginal('smtp_password')),
                'mailgun_domain' => $s->mailgun_domain,
                'mailgun_endpoint' => $s->mailgun_endpoint ?? 'api.mailgun.net',
                'has_mailgun_secret' => filled($s->getRawOriginal('mailgun_secret')),
                'last_tested_at' => $s->last_tested_at?->format('d M Y, H:i'),
                'last_test_ok' => $s->last_test_ok,
                'last_test_error' => $s->last_test_error,
            ],
            'brand' => BusinessMessage::brandFor($business),
            'presets' => self::PRESETS,
            'platformFrom' => config('mail.from.address'),
            'userEmail' => $request->user()->email,
        ]);
    }

    public function update(Request $request)
    {
        $business = $this->ownerBusiness($request);
        $data = $request->validate([
            'driver' => ['required', Rule::in(['platform', 'smtp', 'mailgun'])],
            'from_name' => ['nullable', 'string', 'max:100'],
            'from_address' => ['nullable', 'required_unless:driver,platform', 'email:rfc', 'max:191'],
            'reply_to' => ['nullable', 'email:rfc', 'max:191'],
            'smtp_host' => ['nullable', 'required_if:driver,smtp', 'string', 'max:191', function ($attr, $value, $fail) {
                if ($value && ! self::publicHost((string) $value)) {
                    $fail('Use your mail provider’s public SMTP host (e.g. smtp.gmail.com). Internal addresses aren’t allowed.');
                }
            }],
            'smtp_port' => ['nullable', 'required_if:driver,smtp', 'integer', Rule::in([25, 465, 587, 2525])],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:191'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'mailgun_domain' => ['nullable', 'required_if:driver,mailgun', 'string', 'max:191'],
            'mailgun_secret' => ['nullable', 'string', 'max:500'],
            'mailgun_endpoint' => ['nullable', Rule::in(['api.mailgun.net', 'api.eu.mailgun.net'])],
        ], [
            'from_address.required_unless' => 'Enter the sender email address for your own account.',
            'smtp_host.required_if' => 'Enter the SMTP host (or pick a provider above).',
            'smtp_port.in' => 'Mail servers use port 587, 465, 25 or 2525.',
            'smtp_port.required_if' => 'Enter the SMTP port.',
            'mailgun_domain.required_if' => 'Enter your Mailgun sending domain.',
        ], [
            'from_address' => 'sender email', 'reply_to' => 'reply-to email', 'smtp_host' => 'SMTP host', 'smtp_port' => 'port',
        ]);

        $settings = BusinessMailSetting::query()->firstOrNew(['business_id' => $business->id]);

        // Blank secret fields mean "keep the saved one" (secrets are never sent back to the browser).
        foreach (['smtp_password', 'mailgun_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }
        if ($data['driver'] === 'mailgun' && blank($data['mailgun_secret'] ?? null) && blank($settings->getRawOriginal('mailgun_secret'))) {
            return back()->withErrors(['mailgun_secret' => 'Enter the Mailgun API key.']);
        }

        $settings->fill($data)->fill(['last_tested_at' => null, 'last_test_ok' => null, 'last_test_error' => null])->save();

        return back()->with('success', 'Email settings saved. Send a test email to check them.');
    }

    /** Sent immediately (not queued) so the owner sees the real result of their settings. */
    public function test(Request $request, BusinessMailer $mailer)
    {
        $business = $this->ownerBusiness($request);
        $data = $request->validate(['to' => ['required', 'email:rfc']]);
        $settings = BusinessMailSetting::query()->firstOrNew(['business_id' => $business->id]);

        // 13e: re-check at send time (the host's DNS may have changed since it was saved).
        if ($settings->driver === 'smtp' && ! self::publicHost((string) $settings->smtp_host)) {
            return back()->with('error', 'Test email failed: the SMTP host points to an internal address.');
        }

        try {
            $mailer->send($business, $data['to'], $this->sample($business, 'Test email', 'Your email settings work. Customers and suppliers will receive invoices, receipts and orders from this address.'));
            $settings->fill(['last_tested_at' => now(), 'last_test_ok' => true, 'last_test_error' => null])->save();

            return back()->with('success', "Test email sent to {$data['to']}.");
        } catch (\Throwable $e) {
            $message = $this->safeError($e->getMessage(), $settings);
            $settings->fill(['last_tested_at' => now(), 'last_test_ok' => false, 'last_test_error' => $message])->save();

            return back()->with('error', 'Test email failed: '.$message);
        }
    }

    public function preview(Request $request)
    {
        $business = $this->ownerBusiness($request);

        return response($this->sample($business, 'Invoice INV-000123', 'Thank you for your purchase. Here is a summary of your invoice.', [
            ['Invoice', 'INV-000123'], ['Date', now()->format('d M Y')], ['Amount', $business->currencySymbol().' 1,250.00'], ['Status', 'Paid'],
        ], ['View invoice', url('/')])->render());
    }

    public function branding(Request $request)
    {
        $business = $this->ownerBusiness($request);
        $data = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['nullable', 'boolean'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['brand_color.regex' => 'Pick a colour (format #RRGGBB).']);

        if ($request->boolean('remove_logo') && $business->logo) {
            Storage::disk('uploads')->delete($business->logo);
            $business->logo = null;
        }
        if ($request->hasFile('logo')) {
            if ($business->logo) {
                Storage::disk('uploads')->delete($business->logo);
            }
            $business->logo = $request->file('logo')->storeAs('business-logos', $business->id.'-'.time().'.'.$request->file('logo')->extension(), 'uploads');
        }
        if (array_key_exists('brand_color', $data)) {
            $business->brand_color = $data['brand_color'];
        }
        $business->save();

        return back()->with('success', 'Branding saved.');
    }

    private function sample(Business $business, string $title, string $line, array $summary = [], ?array $action = null): BusinessMessage
    {
        return new BusinessMessage($business, $title.' · '.$business->name, $title, [$line], $summary, $action, 'Questions? Just reply to this email.', $line);
    }

    /** Never echo credentials back in an error message. */
    /**
     * Security review 13e (SSRF): an owner-entered SMTP host must resolve only to public addresses, otherwise "send test
     * email" could probe the server's internal network (127.0.0.1, 10.x, 169.254.169.254 …).
     */
    /** Tests replace DNS with this (host => list of IPs). */
    public static ?\Closure $resolveUsing = null;

    public static function publicHost(string $host): bool
    {
        $host = trim($host);
        if ($host === '' || preg_match('/[\/\s@:]/', $host)) {
            return false;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (self::$resolveUsing ? (self::$resolveUsing)($host) : array_merge(@gethostbynamel($host) ?: [], collect(@dns_get_record($host, DNS_AAAA) ?: [])->pluck('ipv6')->all()));
        if (! $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private function safeError(string $message, BusinessMailSetting $settings): string
    {
        foreach ([$settings->smtp_password, $settings->mailgun_secret] as $secret) {
            if (filled($secret)) {
                $message = str_replace($secret, '••••', $message);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $message)), 0, 480);
    }

    private function ownerBusiness(Request $request): Business
    {
        $business = Business::findOrFail((int) (session('impersonate_business_id') ?? session('business_id')));
        abort_unless($request->user()->can('core.settings.manage'), 403);

        return $business;
    }
}
