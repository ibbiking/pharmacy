<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The public Guide page (landing navbar "Guide"): no login, facts from the same config the app uses. */
class GuidePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_is_public_and_built_from_the_module_and_wording_config(): void
    {
        $this->withoutVite();

        $this->get(route('guide'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Guide')
            ->has('types', 3)
            ->has('modules', count(config('modules.modules')))
            ->where('wording.pharmacy.product', 'Medicine')
            ->where('wording.inventory_b2b.supplier', 'Vendor')
            ->where('signedIn', false));
    }
}
