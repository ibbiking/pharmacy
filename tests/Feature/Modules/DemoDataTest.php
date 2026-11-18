<?php

namespace Tests\Feature\Modules;

use App\Models\Accounting\JournalLine;
use App\Models\Business;
use App\Models\User;
use App\Services\Modules\ModuleManager;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The demo seeder goes through every flow with the real services; each business ends with every module and balanced books. */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_owner_gets_three_businesses_with_every_module_and_balanced_books(): void
    {
        $this->seed(\Database\Seeders\GlobalAllCurrencySeeder::class);
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class); // idempotent

        $owner = User::where('email', DemoDataSeeder::OWNER)->firstOrFail();
        $this->assertSame(3, $owner->businesses()->count());
        foreach ($owner->businesses as $business) {
            $manager = app(ModuleManager::class);
            $active = $manager->activeFor($business->id);
            foreach ($manager->allowedFor($business->business_type) as $m) {
                if ($manager->definition($m)['available'] ?? true) {
                    $this->assertContains($m, $active, "{$business->name} lacks {$m}");
                }
            }
            // Never a module of another business type.
            foreach ($active as $m) {
                $this->assertTrue($manager->isAllowed($business->business_type, $m), "{$business->name} has {$m}, which its type does not allow");
            }
            $lines = JournalLine::withoutGlobalScopes()->where('business_id', $business->id)->whereNull('deleted_at');
            $this->assertEqualsWithDelta((float) (clone $lines)->sum('debit'), (float) (clone $lines)->sum('credit'), 0.01, "{$business->name}: books must balance");
            $this->assertGreaterThan(20, (clone $lines)->count());
            $isB2b = $business->business_type === 'inventory_b2b';
            foreach (['sales_invoice', 'credit_note', 'purchase_order', 'goods_receipt', 'bill', 'vendor_credit', 'quotation', 'sales_order'] as $type) {
                $exists = \App\Models\Documents\Document::withoutGlobalScopes()->where('business_id', $business->id)->where('type', $type)->exists();
                $this->assertSame($isB2b, $exists, "{$business->name}: {$type} ".($isB2b ? 'missing' : 'must not exist'));
            }
        }

        // Dated over the last ~6 weeks, ending today.
        $dates = \App\Models\Invoice::withoutGlobalScopes()->selectRaw('min(created_at) as a, max(created_at) as b')->first();
        $this->assertTrue(\Carbon\Carbon::parse($dates->a)->gte(today()->subDays(50)) && \Carbon\Carbon::parse($dates->b)->isToday());

        // The owner can open the main screens of the B2B business.
        $b2b = Business::where('name', 'Demo B2B Distributors')->firstOrFail();
        $this->withoutVite();
        $this->actingAs($owner)->withSession(['business_id' => $b2b->id]);
        foreach (['dashboard', 'analytics.index', 'selling.invoices.index', 'buying.bills.index', 'accounting.journals.index', 'expenses.index', 'warehouses.index', 'inventory.valuation', 'reports.ar_aging'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('accounting.reports', ['report' => 'bs']))->assertOk();
    }
}
