<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\Campaign;
use App\Modules\BulkEmail\Models\ActivityLog;
use App\Modules\BulkEmail\Models\ContactList;
use App\Modules\BulkEmail\Models\EmailTemplate;
use App\Modules\BulkEmail\Models\Signature;
use App\Modules\BulkEmail\Jobs\SendCampaignJob;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::latest()->paginate(10);
        return view('bulk-email::campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $lists = ContactList::where('status', 'completed')->get();
        $templates = EmailTemplate::all();
        $signatures = Signature::all();

        return view('bulk-email::campaigns.create', compact('lists', 'templates', 'signatures'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_list_id' => 'required|exists:bec_contact_lists,id',
            'template_id' => 'required|exists:bec_email_templates,id',
            'signature_id' => 'nullable|exists:bec_signatures,id',
            'from_name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'scheduled_at' => 'nullable|date',
            'files.*' => 'nullable|file|max:25600', // 25MB for Outlook/Gmail parity
        ]);

        if ($request->hasFile('files')) {
            $attachments = [];
            foreach ($request->file('files') as $file) {
                $path = $file->store('bulk-email/attachments');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
            $data['attachments'] = $attachments;
        }

        $data['status'] = $request->scheduled_at ? 'scheduled' : 'draft';
        
        $campaign = Campaign::create($data);
        ActivityLog::log("Created campaign: {$campaign->name}", $campaign);

        return redirect()->route('bec.campaigns.index')->with('success', 'Campaign created as ' . $data['status'] . '.');
    }

    public function edit(Campaign $campaign)
    {
        // Allow editing draft and scheduled campaigns
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return redirect()->route('bec.campaigns.index')->with('error', 'Only draft or scheduled campaigns can be edited.');
        }

        $lists = ContactList::where('status', 'completed')->get();
        $templates = EmailTemplate::all();
        $signatures = Signature::all();

        return view('bulk-email::campaigns.edit', compact('campaign', 'lists', 'templates', 'signatures'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return redirect()->route('bec.campaigns.index')->with('error', 'Only draft or scheduled campaigns can be updated.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_list_id' => 'required|exists:bec_contact_lists,id',
            'template_id' => 'required|exists:bec_email_templates,id',
            'signature_id' => 'nullable|exists:bec_signatures,id',
            'from_name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'scheduled_at' => 'nullable|date',
            'files.*' => 'nullable|file|max:25600',
        ]);

        if ($request->hasFile('files')) {
            $attachments = $campaign->attachments ?: [];
            foreach ($request->file('files') as $file) {
                $path = $file->store('bulk-email/attachments');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
            $data['attachments'] = $attachments;
        }

        $data['status'] = $request->scheduled_at ? 'scheduled' : 'draft';

        $campaign->update($data);
        ActivityLog::log("Updated campaign: {$campaign->name}", $campaign);

        return redirect()->route('bec.campaigns.index')->with('success', 'Campaign updated.');
    }

    public function show(Campaign $campaign)
    {
        $logs = $campaign->logs()->with('contact')->paginate(50);
        return view('bulk-email::campaigns.show', compact('campaign', 'logs'));
    }

    public function send(Campaign $campaign)
    {
        // "Send Now" ignores the scheduled date and sends immediately in the background
        ActivityLog::log("Started immediate sending for campaign: {$campaign->name}", $campaign);
        
        SendCampaignJob::dispatch($campaign);
        $campaign->update(['status' => 'scheduled']);

        return back()->with('success', 'Campaign queued for immediate background sending.');
    }

    public function destroy(Campaign $campaign)
    {
        ActivityLog::log("Deleted campaign: {$campaign->name}", $campaign);
        $campaign->delete();
        return redirect()->route('bec.campaigns.index')->with('success', 'Campaign deleted.');
    }
}
