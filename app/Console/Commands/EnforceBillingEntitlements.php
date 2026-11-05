<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\PlatformSetting;
use App\Services\Billing\BillingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily: once launch pricing (and its grace date) is over, switch off modules that no active
 * subscription covers. Does nothing while "Free for everyone" is on. Data is never deleted.
 */
class EnforceBillingEntitlements extends Command
{
    protected $signature = 'billing:enforce-entitlements';

    protected $description = 'Switch off modules not covered by a subscription once launch pricing has ended';

    public function handle(BillingService $billing): int
    {
        if (PlatformSetting::freeForEveryone()) {
            $this->info('Launch pricing is on: nothing to do.');

            return self::SUCCESS;
        }

        $failed = 0;
        Business::query()->select('id', 'name')->chunkById(100, function ($businesses) use ($billing, &$failed) {
            foreach ($businesses as $business) {
                try {
                    $billing->applyEntitlements($business);
                } catch (Throwable $e) {
                    $failed++;
                    report($e);
                }
            }
        });

        $this->info($failed ? "Done with {$failed} error(s) (see the log)." : 'Done.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
