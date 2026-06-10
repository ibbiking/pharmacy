<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\BulkEmail\Models\Campaign;
use App\Modules\BulkEmail\Jobs\SendCampaignJob;
use Carbon\Carbon;

class ProcessScheduledCampaigns extends Command
{
    protected $signature = 'bec:process-scheduled';
    protected $description = 'Process bulk email campaigns that are scheduled to be sent';

    public function handle()
    {
        $campaigns = Campaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', Carbon::now())
            ->get();

        foreach ($campaigns as $campaign) {
            $this->info("Processing scheduled campaign: {$campaign->name}");
            SendCampaignJob::dispatch($campaign);
        }

        return 0;
    }
}
