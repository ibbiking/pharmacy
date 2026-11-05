<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4h: help pages. */
class HelpTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public function test_help_center_lists_topics_for_active_modules(): void
    {
        $this->get(route('help.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Help/Index')->has('topics', count(config('help'))));

        $this->delete(route('modules.deactivate', 'pos'));
        $this->get(route('help.index'))->assertInertia(fn (Assert $page) => $page->has('topics', count(config('help')) - 1));
        $this->get(route('help.show', 'selling-at-the-pos'))->assertNotFound();
        $this->get(route('help.show', 'no-such-topic'))->assertNotFound();
    }

    public function test_topic_page_has_steps_examples_faqs_and_working_links(): void
    {
        $this->get(route('help.show', 'sale-price-preferences'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Help/Show')
            ->where('topic.title', 'Sale price preferences')
            ->has('topic.steps', 4)
            ->has('topic.examples', 4)
            ->where('topic.steps.1.href', route('global-sale-price-preferences.index')));
    }

    public function test_links_to_switched_off_screens_are_removed(): void
    {
        config(['help.test-topic' => [
            'title' => 'Test', 'icon' => 'x', 'accent' => 'red', 'module' => 'core', 'summary' => 's',
            'steps' => [['Sell', 'text', 'cart', 'pos.index'], ['Taxes', 'text', 'percent', 'taxes.index']],
        ]]);
        $this->delete(route('modules.deactivate', 'pos'));

        $this->get(route('help.show', 'test-topic'))->assertInertia(fn (Assert $page) => $page
            ->where('topic.steps.0.href', null)
            ->where('topic.steps.1.href', route('taxes.index')));
    }

    public function test_sales_persons_can_read_help(): void
    {
        $cashier = User::create(['name' => 'Cashier', 'email' => 'help-cashier@example.test', 'password' => bcrypt('x')]);
        $cashier->assignRole('sales-person');
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $this->enterBusiness($this->business, $cashier);

        $this->get(route('help.show', 'selling-at-the-pos'))->assertOk();
    }

    public function test_every_route_named_in_help_exists(): void
    {
        foreach (config('help') as $key => $topic) {
            foreach ($topic['steps'] as $step) {
                if (isset($step[3])) {
                    $this->assertTrue(Route::has($step[3]), "help.{$key}: route [{$step[3]}] does not exist");
                }
            }
        }
    }
}
