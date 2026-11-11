<?php

namespace Tests\Feature\Platform;

use App\Jobs\SendBusinessMail;
use App\Models\Business;
use App\Models\BusinessMailSetting;
use App\Models\Preference;
use App\Services\Mail\BusinessMailer;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** R4 (2026-11-12): each business has its own SMTP settings, and every business email goes through them. */
class BusinessSmtpTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    public function test_two_businesses_of_one_owner_each_use_their_own_account(): void
    {
        $this->setUpBusiness('Alpha');
        $beta = Business::create(['name' => 'Beta']);
        $this->owner->businesses()->attach($beta->id, ['role' => 'owner']);
        foreach ([[$this->business, 'smtp.alpha-mail.com', 'alpha@alpha.example'], [$beta, 'smtp.beta-mail.com', 'beta@beta.example']] as [$b, $host, $from]) {
            BusinessMailSetting::create(['business_id' => $b->id, 'driver' => 'smtp', 'smtp_host' => $host, 'smtp_port' => 587, 'smtp_encryption' => 'tls',
                'smtp_username' => $from, 'smtp_password' => 'secret', 'from_name' => $b->name, 'from_address' => $from]);
        }
        $mailer = app(BusinessMailer::class);
        $config = fn (Business $b) => $mailer->transportConfig(BusinessMailSetting::where('business_id', $b->id)->first());
        $this->assertSame('smtp.alpha-mail.com', $config($this->business)['host']);
        $this->assertSame('smtp.beta-mail.com', $config($beta)['host']);
    }

    public function test_stock_alert_and_expense_emails_go_through_the_business_account(): void
    {
        Bus::fake([SendBusinessMail::class]);
        $this->setUpBusiness('Alpha');
        [$product, $unit] = $this->makeProduct('Paracetamol', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6]]);
        $this->purchase($product, $unit['Box'], 1, 60, 100, now()->addYear()->toDateString());
        Preference::create(['business_id' => $this->business->id, 'type' => 'business', 'slug' => 'global_min_indicated_qty', 'preference' => 0.5]);
        $this->saveInvoice([$this->line($product, $unit['Tablet'], 6, 10)])->assertOk(); // 10 → 4: crosses the level
        Bus::assertDispatched(SendBusinessMail::class, fn ($job) => $job->business->is($this->business) && $job->to === $this->owner->email);

        app(ModuleManager::class)->activate($this->business, 'expense');
        $approver = \App\Models\User::create(['name' => 'Approver', 'email' => 'approver@example.test', 'password' => bcrypt('x')]);
        $role = \App\Models\BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Approver']);
        $role->syncPermissions(['expense.submit', 'expense.approve']);
        $approver->businesses()->attach($this->business->id, ['role' => 'staff', 'business_role_id' => $role->id]);
        $category = \App\Models\Expense\ExpenseCategory::firstOrFail();
        $this->post(route('expenses.store'), ['date' => today()->toDateString(), 'expense_category_id' => $category->id, 'amount' => 500, 'paid_by' => 'employee', 'submit' => 1])
            ->assertSessionHasNoErrors();
        Bus::assertDispatched(SendBusinessMail::class, fn ($job) => $job->to === 'approver@example.test' && $job->business->is($this->business));
        Bus::assertNotDispatched(SendBusinessMail::class, fn ($job) => $job->to === $this->owner->email && str_contains($job->mailable->mailSubject, 'approval'));
    }
}
