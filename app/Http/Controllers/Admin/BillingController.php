<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing\BillingInvoice;
use App\Models\Billing\Subscription;
use App\Models\Business;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Services\Billing\BillingService;
use App\Services\Billing\Exceptions\BillingException;
use App\Services\Modules\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Settings → Billing: the business's plan, checkout, changes, cancellation and payment history (Roadmap 4e). */
class BillingController extends Controller
{
    private const STATUS_LABELS = [
        'incomplete' => 'Waiting for payment',
        'incomplete_expired' => 'Checkout expired',
        'trialing' => 'Free trial',
        'active' => 'Active',
        'past_due' => 'Payment failed: retrying',
        'unpaid' => 'Unpaid',
        'canceled' => 'Cancelled',
        'paused' => 'Paused',
    ];

    public function __construct(private readonly BillingService $billing, private readonly ModuleManager $modules)
    {
    }

    public function index(Request $request)
    {
        $business = $this->business();

        // Back from Stripe Checkout: confirm right away (the webhook may arrive later or, in dev, not at all).
        if ($request->query('checkout') === 'success' && is_string($sessionId = $request->query('session_id'))) {
            try {
                $sub = $this->billing->confirmCheckout($business, $sessionId);
                session()->flash($sub ? 'success' : 'info', $sub ? 'Payment received: your subscription is '.strtolower(self::STATUS_LABELS[$sub->status] ?? $sub->status).'.' : 'Your payment is still being processed. This page updates when Stripe confirms it.');
            } catch (BillingException $e) {
                session()->flash('error', $e->userMessage);
            }
        } elseif ($request->query('checkout') === 'cancelled') {
            session()->flash('info', 'Checkout cancelled. Nothing was charged.');
        }

        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 10;
        $sub = Subscription::currentFor($business->id);
        $prices = ModulePrice::query()->get()->keyBy('module');

        return Inertia::render('Billing/Index', [
            'provider' => $this->billing->status(),
            'subscription' => $sub ? $this->present($sub) : null,
            'plans' => Plan::query()->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->limit(12)->get()
                ->map(fn (Plan $p) => [
                    'id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'currency' => $p->currency,
                    'monthly_price' => $p->monthly_price, 'yearly_price' => $p->yearly_price, 'trial_days' => $p->trial_days,
                    'modules' => array_values(array_map(fn ($m) => ['name' => $this->modules->definition($m)['name'], 'available' => (bool) ($this->modules->definition($m)['available'] ?? true)], array_filter($p->modules, fn ($m) => $this->modules->exists($m) && $m !== 'core'))),
                    'module_keys' => $p->modules,
                    'limits' => $p->limits,
                ]),
            'modules' => collect($this->modules->all())->reject(fn ($m) => $m['core'] ?? false)->map(fn ($m, $key) => [
                'key' => $key, 'name' => $m['name'], 'icon' => $m['icon'], 'accent' => $m['accent'], 'description' => $m['description'],
                'available' => (bool) ($m['available'] ?? true), 'depends_on' => $m['depends_on'] ?? [],
                'monthly_price' => (float) ($prices[$key]->monthly_price ?? 0), 'yearly_price' => (float) ($prices[$key]->yearly_price ?? 0),
                'currency' => $prices[$key]->currency ?? 'USD', 'active' => $this->modules->isActive($business->id, $key),
            ])->values(),
            'invoices' => BillingInvoice::query()->where('business_id', $business->id)->latest('id')
                ->paginate($perPage, ['*'], 'page')->withQueryString()
                ->through(fn (BillingInvoice $i) => [
                    'id' => $i->id, 'number' => $i->number ?? '—', 'status' => $i->status,
                    'amount_due' => $i->amount_due, 'amount_paid' => $i->amount_paid, 'currency' => $i->currency,
                    'period' => $i->period_start ? $i->period_start->format('d M Y').' – '.$i->period_end?->format('d M Y') : null,
                    'paid_at' => $i->paid_at?->format('d M Y'), 'url' => $i->hosted_invoice_url, 'pdf' => $i->invoice_pdf,
                ]),
            'filters' => ['per_page' => $perPage],
            'preselect' => [
                'modules' => array_values(array_filter((array) $request->query('modules', $request->query('module') ? [$request->query('module')] : []), 'is_string')),
                'interval' => $request->query('interval') === 'yearly' ? 'yearly' : 'monthly',
            ],
        ]);
    }

    public function quote(Request $request)
    {
        [$plan, $modules, $interval] = $this->choice($request);

        try {
            return response()->json($this->billing->quote($plan, $modules, $interval));
        } catch (BillingException $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->userMessage], 422);
        }
    }

    public function subscribe(Request $request)
    {
        [$plan, $modules, $interval] = $this->choice($request);

        try {
            $result = $this->billing->subscribe($this->business(), $request->user(), $plan, $modules, $interval);
        } catch (BillingException $e) {
            return back()->with('error', $e->userMessage);
        }

        return isset($result['url'])
            ? Inertia::location($result['url'])
            : back()->with('success', 'Done: your modules are switched on. Nothing to pay.');
    }

    public function change(Request $request)
    {
        [$plan, $modules, $interval] = $this->choice($request);

        try {
            $this->billing->change($this->business(), $plan, $modules, $interval);
        } catch (BillingException $e) {
            return back()->with('error', $e->userMessage);
        }

        return back()->with('success', 'Plan changed. The difference is prorated on your next bill.');
    }

    public function cancel()
    {
        try {
            $sub = $this->billing->cancel($this->business());
        } catch (BillingException $e) {
            return back()->with('error', $e->userMessage);
        }

        return back()->with('success', $sub->stripe_subscription_id
            ? 'Subscription cancelled. You keep access until '.($sub->current_period_end?->format('d M Y') ?? 'the end of the period').'.'
            : 'Subscription cancelled.');
    }

    public function resume()
    {
        try {
            $this->billing->resume($this->business());
        } catch (BillingException $e) {
            return back()->with('error', $e->userMessage);
        }

        return back()->with('success', 'Welcome back: your subscription will continue.');
    }

    public function portal()
    {
        try {
            return Inertia::location($this->billing->portalUrl($this->business()));
        } catch (BillingException $e) {
            return back()->with('error', $e->userMessage);
        }
    }

    /** @return array{0: ?Plan, 1: string[], 2: string} */
    private function choice(Request $request): array
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->whereNull('deleted_at')->where('is_visible', true)],
            'modules' => ['array', 'max:20'],
            'modules.*' => ['string', Rule::in(array_keys($this->modules->all()))],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);
        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : null;
        $modules = array_values(array_filter($data['modules'] ?? [], fn ($m) => $m !== 'core' && ($this->modules->definition($m)['available'] ?? true)));
        if (! $plan && ! $modules) {
            throw \Illuminate\Validation\ValidationException::withMessages(['modules' => 'Choose a plan or at least one module.']);
        }

        return [$plan, $modules, $data['interval']];
    }

    private function present(Subscription $sub): array
    {
        return [
            'id' => $sub->id,
            'plan' => $sub->plan?->name,
            'plan_id' => $sub->plan_id,
            'modules' => array_values(array_map(fn ($m) => $this->modules->definition($m)['name'] ?? $m, array_filter($sub->modules ?? [], fn ($m) => $this->modules->exists($m) && $m !== 'core'))),
            'module_keys' => $sub->modules,
            'interval' => $sub->interval,
            'status' => $sub->status,
            'status_label' => self::STATUS_LABELS[$sub->status] ?? ucfirst($sub->status),
            'entitled' => $sub->isEntitled(),
            'amount' => $sub->amount,
            'currency' => $sub->currency,
            'trial_ends_at' => $sub->trial_ends_at?->format('d M Y'),
            'current_period_end' => $sub->current_period_end?->format('d M Y'),
            'cancel_at_period_end' => $sub->cancel_at_period_end,
            'stripe' => (bool) $sub->stripe_subscription_id,
        ];
    }

    private function business(): Business
    {
        return Business::findOrFail((int) (session('impersonate_business_id') ?? session('business_id')));
    }
}
