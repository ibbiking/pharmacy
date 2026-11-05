<?php

namespace Tests\Feature\Platform;

use App\Http\Middleware\EnsureRoutePermission;
use App\Models\BusinessRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 4i-4: permissions enforced on routes, the sidebar and POS limits. */
class RoutePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private User $staff;
    private BusinessRole $manager;
    private $product;
    private array $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
        [$this->product, $this->unit] = $this->makeProduct('Paracetamol', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6]]);
        $this->purchase($this->product, $this->unit['Box'], 5, 60, 100, now()->addYear()->toDateString());

        $this->manager = BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Manager']);
        $this->manager->syncPermissions(['pos.sell', 'pos.returns.manage', 'pos.invoices.view', 'stock.purchases.view']);
        $this->staff = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => bcrypt('x')]);
        $this->staff->businesses()->attach($this->business->id, ['role' => 'staff', 'business_role_id' => $this->manager->id]);
    }

    private function asStaff(): static
    {
        app()->forgetScopedInstances();
        $this->enterBusiness($this->business, $this->staff);

        return $this;
    }

    public function test_every_authenticated_admin_route_is_mapped_or_open(): void
    {
        $open = config('route_permissions.open');
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($route->uri(), 'admin/') || ! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }
            if (EnsureRoutePermission::requiredFor($name) === null && ! in_array($name, $open, true)) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Map these routes in config/route_permissions.php (or list them as open).');
    }

    public function test_a_custom_role_opens_exactly_its_screens(): void
    {
        $this->asStaff();

        $this->get(route('pos.index'))->assertOk();
        $this->get(route('purchases.index'))->assertOk();
        $this->get(route('purchases.create'))->assertForbidden()->assertInertia(fn (Assert $page) => $page
            ->component('Errors/Forbidden')->where('permission', 'Record purchases')->where('canSell', true));
        $this->get(route('taxes.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();
        $this->postJson(route('purchases.store'), [])->assertForbidden()->assertJsonPath('error', 'forbidden')->assertJsonPath('permission', 'stock.purchases.manage');
        $this->get(route('settings.roles'))->assertForbidden();
    }

    public function test_legacy_global_roles_screens_are_super_admin_only(): void
    {
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('permissions.index'))->assertForbidden();
        $this->get(route('taxes.index'))->assertOk(); // owners still reach everything else
    }

    public function test_sidebar_shows_only_what_the_role_allows(): void
    {
        $this->asStaff();
        $nav = collect($this->get(route('help.index'))->viewData('page')['props']['navigation'])
            ->flatMap(fn ($g) => $g['items'])->flatMap(fn ($i) => array_merge([$i], $i['children'] ?? []))->pluck('label');

        $this->assertContains('POS', $nav);
        $this->assertContains('All purchases', $nav);
        $this->assertNotContains('Add purchase', $nav);
        $this->assertNotContains('Dashboard', $nav);
        $this->assertNotContains('Taxes', $nav);
        $this->assertNotContains('Roles', $nav);
    }

    public function test_pos_role_limits(): void
    {
        $this->asStaff();
        $line = fn (array $o = []) => $this->line($this->product, $this->unit['Tablet'], 2, 10, $o);

        $this->saveInvoice([$line(['discount_percent' => 5])])->assertStatus(422)->assertJsonPath('context.reason', 'role');
        $this->saveInvoice([$line()], ['invoice_discount_type' => 'amount', 'invoice_discount_value' => 1])->assertStatus(422)->assertJsonPath('error', 'invoice_discount_exceeded');
        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 2, 9)])->assertStatus(422)->assertJsonPath('error', 'price_change_not_allowed');
        $this->invoiceFrom($this->saveInvoice([$line()]));

        // Discounts allowed, but capped by the role (business has no cap).
        $this->manager->syncPermissions(['pos.sell', 'pos.discount']);
        $this->manager->update(['limits' => ['max_discount_percent' => 5]]);
        $this->asStaff();
        $this->saveInvoice([$line(['discount_percent' => 10])])->assertStatus(422)->assertJsonPath('error', 'discount_exceeded');
        $this->invoiceFrom($this->saveInvoice([$line(['discount_percent' => 5])]));

        // The owner is not limited by roles.
        $this->enterBusiness($this->business, $this->owner);
        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 2, 9, ['discount_percent' => 20])]));
    }
}
