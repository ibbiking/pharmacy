<?php

namespace Tests\Feature\Platform;

use App\Models\BusinessModule;
use App\Models\Currency\GlobalCurrency;
use App\Models\Pharmacy;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4j: timestamps + soft deletes everywhere. */
class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    /** Tables allowed to skip timestamps and/or soft deletes, with the reason. */
    private const EXEMPT = [
        'migrations' => 'framework',
        'jobs' => 'framework queue',
        'job_batches' => 'framework queue',
        'failed_jobs' => 'framework queue',
        'model_has_permissions' => 'permission pivot',
        'model_has_roles' => 'permission pivot',
        'role_has_permissions' => 'permission pivot',
        'permissions' => 'global permission catalogue (per-business roles arrive in 4i)',
        'roles' => 'global roles (per-business roles arrive in 4i)',
        'business_user' => 'membership pivot (status column: active/suspended)',
        'business_role_permissions' => 'join rows, replaced when a role is saved (the role itself is soft-deleted)',
        'settings' => 'qcod app settings key/value',
        'platform_settings' => 'platform key/value',
        'signup_requests' => 'short-lived verification tokens',
        'pos_cart_reservations' => 'short-lived cart holds',
        'audit_logs' => 'append-only: never deleted or edited',
        'stripe_events' => 'append-only webhook inbox (idempotency + troubleshooting)',
        'password_resets' => 'short-lived reset codes',
        'personal_access_tokens' => 'API tokens (revoked, not archived)',
        'notifications' => 'framework notifications',
    ];

    public function test_every_business_table_has_timestamps_and_soft_deletes(): void
    {
        $columns = collect(DB::select('select table_name as t, column_name as c from information_schema.columns where table_schema = database()'))
            ->groupBy('t')->map(fn ($rows) => collect($rows)->pluck('c')->all());

        $missing = [];
        foreach ($columns as $table => $cols) {
            if (isset(self::EXEMPT[$table])) {
                continue;
            }
            foreach (['created_at', 'updated_at', 'deleted_at'] as $required) {
                if (! in_array($required, $cols, true)) {
                    $missing[] = "{$table}.{$required}";
                }
            }
        }

        $this->assertSame([], $missing, 'Add timestamps/softDeletes (or document an exemption in this test).');
    }

    public function test_switching_a_module_off_and_on_restores_the_same_row(): void
    {
        $this->setUpBusiness();
        $id = BusinessModule::where('business_id', $this->business->id)->where('module', 'pharmacy')->value('id');

        $this->delete(route('modules.deactivate', 'pharmacy'));
        $this->assertSoftDeleted('business_modules', ['id' => $id]);

        $this->post(route('modules.activate', 'pharmacy'));
        $this->assertSame(1, BusinessModule::withTrashed()->where('business_id', $this->business->id)->where('module', 'pharmacy')->count());
        $this->assertNotSoftDeleted('business_modules', ['id' => $id]);
        app()->forgetScopedInstances();
        $this->assertTrue(app(ModuleManager::class)->isActive($this->business->id, 'pharmacy'));
    }

    public function test_an_archived_currency_code_can_be_added_again(): void
    {
        $this->setUpBusiness();
        $payload = ['currency_code' => 'AED', 'name' => 'Dirham', 'symbol' => 'د.إ', 'exchange_rate' => 75];

        $this->post(route('currencies.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('currencies.store'), $payload)->assertSessionHasErrors('currency_code');

        GlobalCurrency::where('business_id', $this->business->id)->where('currency_code', 'AED')->first()->delete();
        $this->post(route('currencies.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(2, GlobalCurrency::withTrashed()->where('business_id', $this->business->id)->where('currency_code', 'AED')->count());
        $this->assertSame(1, GlobalCurrency::where('business_id', $this->business->id)->where('currency_code', 'AED')->count());
    }

    public function test_deleting_archives_instead_of_removing(): void
    {
        $this->setUpBusiness();
        $pharmacy = Pharmacy::create(['name' => 'Branch 2']);

        $pharmacy->delete();

        $this->assertSoftDeleted($pharmacy);
        $this->assertSame(0, Pharmacy::count());
        $this->assertSame(1, Pharmacy::withTrashed()->count());
    }
}
