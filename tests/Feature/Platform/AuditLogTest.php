<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Tax;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4a: audit log. */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public function test_tax_changes_are_audited_with_a_field_diff(): void
    {
        $this->post(route('taxes.store'), ['name' => 'GST', 'rate' => 17]);
        $tax = Tax::firstWhere('name', 'GST');
        $this->put(route('taxes.update', $tax), ['name' => 'GST', 'rate' => 18]);
        $this->delete(route('taxes.destroy', $tax));

        $logs = AuditLog::where('subject_type', $tax->getMorphClass())->where('subject_id', $tax->id)->orderBy('id')->get();

        $this->assertSame(['created', 'updated', 'deleted'], $logs->pluck('action')->all());
        $this->assertEquals(['from' => 17, 'to' => 18], array_map('floatval', $logs[1]->changes['rate']));
        $this->assertArrayNotHasKey('updated_at', $logs[1]->changes);
        $this->assertSame($this->owner->id, $logs[1]->user_id);
        $this->assertSame($this->business->id, $logs[1]->business_id);
    }

    public function test_secret_fields_are_never_logged(): void
    {
        $this->business->update(['fbr_ntn' => '1234567']);
        $this->business->fbr_api_token = 'super-secret-token';
        $this->business->save();

        $all = AuditLog::where('subject_type', $this->business->getMorphClass())->get()->pluck('changes')->toJson();
        $this->assertStringContainsString('1234567', $all);
        $this->assertStringNotContainsString('super-secret-token', $all);
        $this->assertStringNotContainsString('fbr_api_token', $all);
    }

    public function test_price_override_at_checkout_is_audited(): void
    {
        [$product, $u] = $this->makeProduct('Paracetamol', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6]]);
        $this->purchase($product, $u['Box'], 1, 60, 100, now()->addYear()->toDateString());

        $this->saveInvoice([$this->line($product, $u['Tablet'], 2, 9)])->assertOk();

        $log = AuditLog::where('action', 'price_override')->firstOrFail();
        $this->assertEquals(['from' => 10, 'to' => 9], array_map('floatval', $log->changes['price']));
        $this->assertStringContainsString('system price 10.00, charged 9.00', $log->summary);
    }

    public function test_audit_entries_are_scoped_to_the_business(): void
    {
        $this->post(route('taxes.store'), ['name' => 'Mine', 'rate' => 1]);
        $other = Business::create(['name' => 'Other']);
        session(['business_id' => $other->id]);

        $this->assertSame(0, AuditLog::where('summary', 'like', '%Mine%')->count());
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('summary', 'like', '%Mine%')->count());
    }

    public function test_a_failing_audit_write_never_breaks_the_action(): void
    {
        Schema::drop('audit_logs');

        $this->assertNull(app(AuditLogger::class)->record('anything'));
        $this->post(route('taxes.store'), ['name' => 'Still saved', 'rate' => 3])->assertSessionHasNoErrors();
        $this->assertTrue(Tax::where('name', 'Still saved')->exists());
    }
}
