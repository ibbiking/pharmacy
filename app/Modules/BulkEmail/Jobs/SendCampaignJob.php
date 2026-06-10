<?php

namespace App\Modules\BulkEmail\Jobs;

use App\Modules\BulkEmail\Models\Campaign;
use App\Modules\BulkEmail\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign;

    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function handle()
    {
        $this->campaign->update(['status' => 'processing']);

        $contacts = Contact::where('contact_list_id', $this->campaign->contact_list_id)
            ->where('status', 'enabled')
            ->get();

        foreach ($contacts as $contact) {
            SendEmailJob::dispatch($this->campaign, $contact);
        }

        $this->campaign->update(['status' => 'completed']);
    }
}
