<?php

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Found by the live health check (2026-11-21): requests the till itself never sends must not end in a server error. */
class PosRobustnessTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public function test_a_sale_without_an_invoice_discount_type_is_saved_as_amount(): void
    {
        [$p, $u] = $this->makeProduct('Panadol', ['Piece' => 1], ['Piece' => [50, 40]]);
        $this->purchase($p, $u['Piece'], 10, 40, 50, now()->addYear()->toDateString());

        $response = $this->saveInvoice([$this->line($p, $u['Piece'], 1, 50)], ['invoice_discount_type' => null])->assertOk();

        $this->assertSame('amount', $this->invoiceFrom($response)->invoice_discount_type);
    }

    public function test_the_old_add_pos_address_opens_the_till_and_dead_pos_routes_are_gone(): void
    {
        $this->get('/admin/pos/create')->assertRedirect(route('pos.index'));
        $this->assertFalse(\Route::has('pos.store'));
        $this->assertFalse(\Route::has('pos.destroy'));
    }
}
