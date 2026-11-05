<?php

namespace Tests\Feature\Catalogue;

use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Company;
use App\Models\Farmula;
use App\Models\ProductType;
use App\Models\Strength;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5: manufacturers, dosage forms, strengths and formulas share one Inertia screen. */
class MasterDataTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public static function entities(): array
    {
        return [
            'packaging' => ['categories', \App\Models\Category::class, 'min_qty_category_id', 'Packaging'],
            'companies' => ['companies', Company::class, 'company_id', 'Manufacturers'],
            'product types' => ['product-types', ProductType::class, 'product_type_id', 'Dosage Forms'],
            'strengths' => ['strengths', Strength::class, 'strength_id', 'Strengths'],
            'formulas' => ['farmulas', Farmula::class, 'farmula_id', 'Formulas'],
        ];
    }

    #[DataProvider('entities')]
    public function test_list_create_edit_archive_restore(string $route, string $model, string $fk, string $plural): void
    {
        $this->post(route("{$route}.store"), ['name' => '  Alpha  ', 'description' => 'First'])->assertSessionHas('success');
        $this->post(route("{$route}.store"), ['name' => 'Alpha'])->assertSessionHasErrors('name');
        $this->post(route("{$route}.store"), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route("{$route}.store"), ['name' => 'Beta', 'description' => str_repeat('x', 256)])->assertSessionHasErrors('description');
        $alpha = $model::where('name', 'Alpha')->firstOrFail();
        $beta = $model::create(['name' => 'Beta']);

        // Used by a product → shows usage, can't be archived. (The product fixture may add its own entries.)
        [$product] = $this->makeProduct('Panadol');
        $product->update([$fk => $alpha->id]);
        $total = $model::count();

        $this->get(route("{$route}.index", ['sort' => 'usage', 'dir' => 'desc']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('MasterData/Index')->where('entity.plural', $plural)->where('entity.canManage', true)
            ->where('rows.data.0.name', 'Alpha')->where('rows.data.0.usage', 1)
            ->where('stats.total', $total)->where('stats.used', fn ($u) => $u >= 1)->where('stats.unused', fn ($u) => $u >= 1)
            ->where('stats', fn ($st) => $st['used'] + $st['unused'] === $total));
        $this->get(route("{$route}.index", ['status' => 'unused', 'q' => 'Beta']))->assertInertia(fn (Assert $page) => $page->where('rows.total', 1)->where('rows.data.0.name', 'Beta'));
        $this->get(route("{$route}.index", ['status' => 'used', 'q' => 'Alpha']))->assertInertia(fn (Assert $page) => $page->where('rows.total', 1)->where('rows.data.0.name', 'Alpha'));

        $this->put(route("{$route}.update", $beta->id), ['name' => 'Alpha'])->assertSessionHasErrors('name');
        $this->put(route("{$route}.update", $beta->id), ['name' => 'Beta 2', 'description' => 'Changed'])->assertSessionHas('success');
        $this->assertSame('Changed', $beta->fresh()->description);

        $this->delete(route("{$route}.destroy", $alpha->id))->assertSessionHas('error', fn ($m) => str_contains($m, 'is used by 1'));
        $this->assertNotSoftDeleted($alpha);

        $this->delete(route("{$route}.destroy", $beta->id))->assertSessionHas('success');
        $this->assertSoftDeleted($beta);
        $this->get(route("{$route}.index", ['status' => 'archived']))->assertInertia(fn (Assert $page) => $page->where('rows.data.0.archived', true)->where('stats.archived', 1));

        // An archived name is free again; restoring it then conflicts.
        $this->post(route("{$route}.store"), ['name' => 'Beta 2'])->assertSessionHas('success');
        $this->post(route("{$route}.restore", $beta->id))->assertSessionHas('error', fn ($m) => str_contains($m, 'already exists'));
        $model::where('name', 'Beta 2')->first()->delete();
        $this->post(route("{$route}.restore", $beta->id))->assertSessionHas('success');
        $this->assertNotSoftDeleted($beta);
    }

    #[DataProvider('entities')]
    public function test_names_are_unique_per_business_and_lists_never_mix_businesses(string $route, string $model, string $fk, string $plural): void
    {
        $this->post(route("{$route}.store"), ['name' => 'Shared'])->assertSessionHas('success');

        $other = Business::create(['name' => 'Other shop']);
        $this->owner->businesses()->attach($other->id, ['role' => 'owner']);
        app()->forgetScopedInstances();
        $this->enterBusiness($other, $this->owner->fresh()); // a real request always loads a fresh user

        $this->post(route("{$route}.store"), ['name' => 'Shared'])->assertSessionHas('success');
        $this->get(route("{$route}.index", ['q' => 'Shared']))->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
        $this->assertSame(2, $model::withoutGlobalScopes()->where('name', 'Shared')->count());
    }

    #[DataProvider('entities')]
    public function test_old_links_and_autocomplete_still_work(string $route, string $model, string $fk, string $plural): void
    {
        $row = $model::create(['name' => 'Zeta']);
        $this->get(route("{$route}.create"))->assertRedirect(route("{$route}.index", ['open' => 'create']));
        $this->get(route("{$route}.edit", $row->id))->assertRedirect(route("{$route}.index", ['open' => $row->id]));
        $this->get(route("{$route}.index", ['open' => $row->id]))->assertInertia(fn (Assert $page) => $page->where('open', $row->id));

        $this->getJson(route("{$route}.autocomplete", ['term' => 'Ze']))->assertOk()
            ->assertJsonPath('results.0', ['id' => 'Zeta', 'text' => 'Zeta'])->assertJsonPath('pagination.more', false);
    }

    public function test_view_only_roles_see_the_list_without_edit_buttons(): void
    {
        $viewer = BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Viewer']);
        $viewer->syncPermissions(['core.catalogue.view']);
        $user = User::create(['name' => 'Viewer', 'email' => 'viewer-md@example.test', 'password' => bcrypt('x')]);
        $user->businesses()->attach($this->business->id, ['role' => 'staff', 'business_role_id' => $viewer->id]);
        app()->forgetScopedInstances();
        $this->enterBusiness($this->business, $user);

        $this->get(route('companies.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('entity.canManage', false));
        $this->post(route('companies.store'), ['name' => 'Nope'])->assertForbidden();
        $this->get(route('strengths.index'))->assertForbidden(); // pharmacy master data needs its own permission
    }

    public function test_packaging_units_used_in_a_product_chain_are_protected(): void
    {
        [, $unit] = $this->makeProduct('Amoxil', ['Carton' => 1, 'Capsule' => 20]);
        $carton = \App\Models\Category::findOrFail($unit['Carton']);

        $this->get(route('categories.index', ['q' => 'Carton']))->assertInertia(fn (Assert $page) => $page->where('rows.data.0.usage', 1));
        $this->delete(route('categories.destroy', $carton->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($carton);
    }

    public function test_strengths_and_formulas_listed_second_in_a_product_still_count_as_used(): void
    {
        foreach ([[Strength::class, 'strengths', 'strength_id'], [Farmula::class, 'farmulas', 'farmula_id']] as [$model, $route, $col]) {
            $first = $model::create(['name' => "First {$route}"]);
            $second = $model::create(['name' => "Second {$route}"]);
            [$product] = $this->makeProduct("Combo {$route}");
            $product->update([$col => "{$first->id}, {$second->id}"]);

            $this->get(route("{$route}.index", ['q' => "Second {$route}"]))->assertInertia(fn (Assert $page) => $page->where('rows.data.0.usage', 1));
            $this->delete(route("{$route}.destroy", $second->id))->assertSessionHas('error');
            $this->assertNotSoftDeleted($second);
        }
    }

    public function test_wording_follows_the_business_type(): void
    {
        $this->business->update(['business_type' => 'inventory']);
        $this->get(route('companies.index'))->assertInertia(fn (Assert $page) => $page->where('entity.plural', 'Brands')->where('entity.products', 'Items'));
    }
}
