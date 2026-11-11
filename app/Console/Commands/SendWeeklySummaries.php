<?php

namespace App\Console\Commands;

use App\Jobs\SendBusinessMail;
use App\Mail\BusinessMessage;
use App\Models\Business;
use App\Services\Analytics\AnalyticsService;
use App\Services\Modules\ModuleManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Weekly summary email to the owners of every business that switched it on (Advanced Reporting, step 12). Queued. */
class SendWeeklySummaries extends Command
{
    protected $signature = 'analytics:weekly-summaries';

    protected $description = 'Queue last week\'s summary email to business owners who asked for it.';

    public function handle(AnalyticsService $analytics, ModuleManager $modules): int
    {
        $ids = DB::table('preferences')->where('type', 'analytics')->where('slug', 'weekly-summary')->where('status', true)->whereNull('deleted_at')->pluck('business_id');
        $from = now()->subWeek()->startOfWeek()->toDateString();
        $to = now()->subWeek()->endOfWeek()->toDateString();
        foreach (Business::whereIn('id', $ids)->get() as $business) {
            if (! $modules->isActive($business->id, 'analytics')) {
                continue;
            }
            $f = $analytics->figures($business->id, $from, $to);
            $cur = $business->currencySymbol();
            $summary = [['Revenue', $cur.' '.number_format($f['revenue'], 2)], ['Gross profit', $cur.' '.number_format($f['gross_profit'], 2)],
                ['Expenses', $cur.' '.number_format($f['expenses'], 2)], ['Net profit', $cur.' '.number_format($f['net_profit'], 2)],
                ['Payments received', $cur.' '.number_format($f['payments_received'], 2)]];
            $owners = DB::table('business_user')->join('users', 'users.id', '=', 'business_user.user_id')->where('business_user.business_id', $business->id)
                ->where('business_user.role', 'owner')->where('business_user.status', 'active')->pluck('users.email');
            foreach ($owners as $email) {
                SendBusinessMail::dispatch($business, $email, new BusinessMessage($business, "Your week at {$business->name}", 'Last week in numbers',
                    ["Here is how {$business->name} did from ".\Carbon\Carbon::parse($from)->format('d M').' to '.\Carbon\Carbon::parse($to)->format('d M Y').'.'],
                    $summary, ['Open analytics', route('analytics.index', ['from' => $from, 'to' => $to])], 'You get this because the weekly summary is on in Analytics.'));
            }
            $this->line("{$business->name}: {$owners->count()} owner(s)");
        }

        return self::SUCCESS;
    }
}
