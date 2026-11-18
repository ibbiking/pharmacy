<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** The packaging step adds a missing unit in a pop-up: JSON answer with the new row, same rules as master data. */
class UnitQuickAddTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public function test_a_unit_added_from_the_pop_up_comes_back_as_json_and_duplicates_are_refused(): void
    {
        $res = $this->postJson(route('categories.store'), ['name' => 'Crate'])->assertCreated();
        $unit = Category::findOrFail($res->json('id'));
        $this->assertSame('Crate', $res->json('label'));
        $this->assertSame($this->business->id, (int) $unit->business_id);

        $this->postJson(route('categories.store'), ['name' => 'Crate'])->assertStatus(422)->assertJsonValidationErrors('name');
        // The master data screen itself (Inertia) still gets its redirect.
        $this->post(route('categories.store'), ['name' => 'Dozen'])->assertRedirect();
    }
}
