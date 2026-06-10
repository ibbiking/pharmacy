<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\SmtpSetting;
use App\Modules\BulkEmail\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class SmtpController extends Controller
{
    public function index()
    {
        $smtp = SmtpSetting::first();
        return view('bulk-email::smtp.index', compact('smtp'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
            'encryption' => 'required|string',
            'from_email' => 'required|email',
            'from_name' => 'required|string',
        ]);

        $smtp = SmtpSetting::first();
        if ($smtp) {
            $smtp->update($data);
        } else {
            SmtpSetting::create($data);
        }
        
        ActivityLog::log("Updated SMTP settings");

        return redirect()->route('bec.smtp.index')->with('success', 'SMTP settings updated.');
    }

    public function test(Request $request)
    {
        // Dynamic SMTP test
        try {
            $smtp = SmtpSetting::first();
            if (!$smtp) throw new \Exception("SMTP settings not configured.");

            // Temporarily update config
            $this->applySmtpConfig($smtp);

            Mail::raw('This is a test email for Bulk Email Campaigns.', function ($message) use ($request, $smtp) {
                $message->to($request->test_email ?? auth()->user()->email)
                        ->from($smtp->from_email, $smtp->from_name)
                        ->subject('SMTP Test - Bulk Email Campaigns');
            });
            
            ActivityLog::log("Performed SMTP test to {$request->test_email}");

            return response()->json(['success' => true, 'message' => 'Test email sent successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
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
