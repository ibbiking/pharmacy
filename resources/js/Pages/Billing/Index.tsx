import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';

type Interval = 'monthly' | 'yearly';
interface Provider { configured: boolean; mode: 'test' | 'live' | null; webhooks: boolean; free: boolean }
interface Sub {
    id: number; plan: string | null; plan_id: number | null; modules: string[]; module_keys: string[]; interval: Interval;
    status: string; status_label: string; entitled: boolean; amount: number; currency: string; trial_ends_at: string | null;
    current_period_end: string | null; cancel_at_period_end: boolean; stripe: boolean;
}
interface PlanCard { id: number; name: string; description: string | null; currency: string; monthly_price: number; yearly_price: number; trial_days: number; modules: { name: string; available: boolean }[]; module_keys: string[]; limits: Record<string, number | null> | null }
interface ModuleOpt { key: string; name: string; icon: string; accent: string; description: string; available: boolean; depends_on: string[]; monthly_price: number; yearly_price: number; currency: string; active: boolean }
interface InvoiceRow { id: number; number: string; status: string; amount_due: number; amount_paid: number; currency: string; period: string | null; paid_at: string | null; url: string | null; pdf: string | null }
interface Quote { lines: { type: string; key: string; name: string; price: number; currency: string; added_for_dependency?: boolean }[]; total: number; currency: string; interval: Interval; trial_days: number; modules: string[] }

interface Props {
    provider: Provider; subscription: Sub | null; plans: PlanCard[]; modules: ModuleOpt[];
    invoices: Paginated<InvoiceRow>; filters: TableFilters; preselect: { modules: string[]; interval: Interval };
}

const STATUS_ACCENT: Record<string, string> = {
    active: 'var(--app-success)', trialing: 'var(--app-info)', past_due: 'var(--app-warning)', incomplete: 'var(--app-warning)',
    unpaid: 'var(--app-danger)', canceled: 'var(--chart-8)', incomplete_expired: 'var(--chart-8)', paid: 'var(--app-success)', open: 'var(--app-warning)', void: 'var(--chart-8)', uncollectible: 'var(--app-danger)', draft: 'var(--chart-8)',
};

const LIMIT_LABELS: Record<string, [string, string]> = {
    users: ['user', 'users'], businesses: ['business', 'businesses'], products: ['product', 'products'],
    invoices_per_month: ['invoice a month', 'invoices a month'], warehouses: ['warehouse', 'warehouses'],
};
const limitLabel = (key: string, n: number) => (LIMIT_LABELS[key] ?? [key.replace(/_/g, ' '), key.replace(/_/g, ' ')])[n === 1 ? 0 : 1];

const money = (n: number, c: string) => {
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: c, maximumFractionDigits: 2 }).format(n); } catch { return `${c} ${n.toFixed(2)}`; }
};

export default function BillingIndex({ provider, subscription, plans, modules, invoices, filters, preselect }: Props) {
    const [interval, setBillingInterval] = useState<Interval>(subscription?.interval ?? preselect.interval);
    const [planId, setPlanId] = useState<number | null>(null);
    const [picked, setPicked] = useState<string[]>(preselect.modules);
    const [quote, setQuote] = useState<Quote | null>(null);
    const [quoteError, setQuoteError] = useState<string | null>(null);
    const [quoting, setQuoting] = useState(false);
    const [reviewing, setReviewing] = useState(false);
    const [busy, setBusy] = useState(false);
    const [confirmCancel, setConfirmCancel] = useState(false);
    const builderRef = useRef<HTMLDivElement>(null);
    const entitled = !!subscription?.entitled;
    const hasChoice = planId !== null || picked.length > 0;

    // Arriving with modules preselected (from the Modules page or the wizard): scroll to the builder.
    useEffect(() => {
        if (preselect.modules.length) builderRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Live quote from the server (the server is the authority for prices and dependencies).
    useEffect(() => {
        if (!hasChoice) { setQuote(null); setQuoteError(null); return; }
        setQuoting(true);
        const ctrl = new AbortController();
        const t = window.setTimeout(() => {
            axios.post(route('billing.quote'), { plan_id: planId, modules: picked, interval }, { signal: ctrl.signal })
                .then(({ data }) => { setQuote(data); setQuoteError(null); })
                .catch((e) => { if (!axios.isCancel(e)) { setQuote(null); setQuoteError(e.response?.data?.message ?? 'Could not price this choice. Please try again.'); } })
                .finally(() => setQuoting(false));
        }, 250);
        return () => { ctrl.abort(); window.clearTimeout(t); };
    }, [planId, picked, interval, hasChoice]);

    const toggleModule = (key: string) => setPicked((p) => (p.includes(key) ? p.filter((k) => k !== key) : [...p, key]));
    const choosePlan = (id: number) => { setPlanId((cur) => (cur === id ? null : id)); builderRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }); };

    const yearlySaving = useMemo(() => {
        const p = plans.find((x) => x.monthly_price > 0 && x.yearly_price > 0);
        return p ? Math.round((1 - p.yearly_price / (p.monthly_price * 12)) * 100) : 0;
    }, [plans]);

    const payable = (quote?.total ?? 0) > 0;
    const blockedByProvider = payable && !provider.configured;

    const submit = () => {
        setBusy(true);
        router.post(route(entitled ? 'billing.change' : 'billing.subscribe'), { plan_id: planId, modules: picked, interval }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setReviewing(false); },
            onSuccess: () => { setPlanId(null); setPicked([]); },
        });
    };

    const invoiceColumns: Column<InvoiceRow>[] = [
        { key: 'number', label: 'Bill', render: (r) => <strong>{r.number}</strong> },
        { key: 'period', label: 'Period', render: (r) => r.period ?? '—' },
        { key: 'status', label: 'Status', render: (r) => <span className="pill" style={{ ['--accent' as string]: STATUS_ACCENT[r.status] ?? 'var(--chart-8)' }}>{r.status}</span> },
        { key: 'amount', label: 'Amount', align: 'end', className: 'tabular', render: (r) => money(r.status === 'paid' ? r.amount_paid : r.amount_due, r.currency) },
        { key: 'paid_at', label: 'Paid on', render: (r) => r.paid_at ?? '—' },
        {
            key: 'actions', label: '', align: 'end', render: (r) => (
                <div className="d-inline-flex gap-1">
                    {r.url && <a className="btn btn-icon btn-soft" href={r.url} target="_blank" rel="noreferrer" aria-label={`Open bill ${r.number}`}><i className="bi bi-box-arrow-up-right" /></a>}
                    {r.pdf && <a className="btn btn-icon btn-soft" href={r.pdf} target="_blank" rel="noreferrer" aria-label={`Download bill ${r.number}`}><i className="bi bi-file-earmark-pdf" /></a>}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Billing" />
            <PageHeader icon="credit-card" title="Billing" help="billing"
                description="Your plan, payments and bills. Payments are handled securely by Stripe; card details never touch this app."
                actions={subscription?.stripe && (
                    <button type="button" className="btn btn-light" onClick={() => router.post(route('billing.portal'))}>
                        <i className="bi bi-wallet2 me-1" />Payment method & bills
                    </button>
                )} />

            <div className="d-flex flex-column gap-3 mb-3">
                {provider.free && (
                    <div className="callout" style={{ ['--accent' as string]: 'var(--app-success)' }}>
                        <i className="bi bi-gift" />
                        <div><strong>Free during launch</strong><p>Every module is free right now, so you don't need a plan. The plans below show the prices that apply after launch.</p></div>
                    </div>
                )}
                {!provider.configured && (
                    <div className="callout" style={{ ['--accent' as string]: 'var(--app-warning)' }}>
                        <i className="bi bi-plug" />
                        <div><strong>Online payments are not connected yet</strong><p>The platform owner still has to add the Stripe keys. You can browse plans and prices; paying opens as soon as payments are connected. Free plans work already.</p></div>
                    </div>
                )}
                {provider.mode === 'test' && (
                    <div className="callout" style={{ ['--accent' as string]: 'var(--app-info)' }}>
                        <i className="bi bi-cone-striped" />
                        <div><strong>Test mode</strong><p>No real money moves. Pay with card <code>4242 4242 4242 4242</code>, any future date and any CVC.</p></div>
                    </div>
                )}
            </div>

            {subscription ? <SubscriptionHero sub={subscription} onCancel={() => setConfirmCancel(true)} /> : (
                <div className="app-card mb-3 compact-empty">
                    <EmptyState icon="stars" title="No plan yet" hint={provider.free ? 'You are using the free launch pricing. Choose a plan below whenever you are ready.' : 'Choose a plan or build your own set of modules below.'} />
                </div>
            )}

            <div className="d-flex align-items-center flex-wrap gap-2 mt-4 mb-3">
                <h2 className="h5 fw-bold m-0">{entitled ? 'Change your plan' : 'Choose a plan'}</h2>
                <div className="segmented ms-auto" role="group" aria-label="Billing interval">
                    {(['monthly', 'yearly'] as Interval[]).map((i) => (
                        <button key={i} type="button" className={interval === i ? 'active' : ''} onClick={() => setBillingInterval(i)} aria-pressed={interval === i}>
                            {i === 'monthly' ? 'Monthly' : 'Yearly'}{i === 'yearly' && yearlySaving > 0 && <span className="save">save {yearlySaving}%</span>}
                        </button>
                    ))}
                </div>
            </div>

            {plans.length === 0 ? (
                <div className="app-card p-4"><EmptyState icon="tags" title="No plans published yet" hint="Build your own set of modules below." /></div>
            ) : (
                <div className="row g-3">
                    {plans.map((p, idx) => {
                        const price = interval === 'yearly' ? p.yearly_price : p.monthly_price;
                        const current = entitled && subscription?.plan_id === p.id && subscription.interval === interval;
                        const selected = planId === p.id;
                        const featured = idx === 1 && plans.length > 2;
                        return (
                            <div className="col-md-6 col-xl-3" key={p.id}>
                                <div className={`plan-card ${current ? 'current' : ''} ${featured ? 'featured' : ''}`} style={selected ? { borderColor: 'var(--app-primary)', boxShadow: '0 0 0 4px var(--app-primary-soft)' } : undefined}>
                                    {current ? <span className="ribbon" style={{ ['--accent' as string]: 'var(--app-success)' }}>Current plan</span> : featured && <span className="ribbon">Most popular</span>}
                                    <div>
                                        <h3 className="h6 fw-bold mb-1">{p.name}</h3>
                                        {p.description && <p className="small text-muted mb-0">{p.description}</p>}
                                    </div>
                                    <div className="price">{price > 0 ? money(price, p.currency) : 'Free'}{price > 0 && <small> / {interval === 'yearly' ? 'year' : 'month'}</small>}</div>
                                    {p.trial_days > 0 && <span className="pill align-self-start" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-hourglass-split" />{p.trial_days}-day free trial</span>}
                                    <ul>
                                        <li><i className="bi bi-check2-circle" />Core (always included)</li>
                                        {p.modules.map((m) => <li key={m.name} className={m.available ? '' : 'text-muted'}><i className={`bi bi-${m.available ? 'check2-circle' : 'clock-history'}`} style={m.available ? undefined : { color: 'var(--app-text-muted)' }} />{m.name}{!m.available && <small> (coming soon)</small>}</li>)}
                                        {p.limits && Object.entries(p.limits).filter(([, v]) => v).map(([k, v]) => <li key={k} className="text-muted"><i className="bi bi-dot" />Up to {v} {limitLabel(k, Number(v))}</li>)}
                                    </ul>
                                    <button type="button" className={`btn ${selected ? 'btn-primary' : 'btn-outline-primary'}`} disabled={current} onClick={() => choosePlan(p.id)}>
                                        {current ? 'Your plan' : selected ? <><i className="bi bi-check-lg me-1" />Selected</> : entitled ? 'Switch to this' : 'Choose'}
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <div ref={builderRef} className="row g-3 mt-2" style={{ scrollMarginTop: 90 }}>
                <div className="col-lg-8">
                    <SectionCard icon="puzzle" title={planId ? 'Add extra modules' : 'Or build your own'} subtitle="Pick modules; what they need is added automatically.">
                        <div className="row g-2">
                            {modules.map((m) => {
                                const inPlan = planId !== null && (plans.find((p) => p.id === planId)?.module_keys ?? []).includes(m.key);
                                const on = picked.includes(m.key) || inPlan;
                                const price = interval === 'yearly' ? m.yearly_price : m.monthly_price;
                                return (
                                    <div className="col-md-6" key={m.key}>
                                        <button type="button" className={`module-toggle ${on ? 'on' : ''}`} style={{ ['--accent' as string]: m.accent }} disabled={!m.available || inPlan}
                                            onClick={() => toggleModule(m.key)} aria-pressed={on}>
                                            <span className="m-icon"><i className={`bi bi-${m.icon}`} /></span>
                                            <span className="flex-grow-1">
                                                <span className="fw-semibold d-block">{m.name}{m.active && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--app-success)' }}>On</span>}</span>
                                                <small className="text-muted">{!m.available ? 'Coming soon' : inPlan ? 'Included in the plan' : price > 0 ? `${money(price, m.currency)} / ${interval === 'yearly' ? 'year' : 'month'}` : 'Free'}</small>
                                            </span>
                                            <i className={`tick bi bi-${on ? 'check-circle-fill' : 'circle'}`} />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    </SectionCard>
                </div>
                <div className="col-lg-4">
                    <div className="app-card p-3 quote-panel">
                        <div className="d-flex align-items-center mb-2">
                            <strong>Summary</strong>
                            {quoting && <span className="spinner-border spinner-border-sm ms-auto text-primary" role="status" aria-label="Updating price" />}
                        </div>
                        {!hasChoice && <p className="small text-muted mb-0">Choose a plan or modules to see the price.</p>}
                        {quoteError && <div className="small text-danger">{quoteError}</div>}
                        {quote && hasChoice && (
                            <>
                                {quote.lines.map((l) => (
                                    <div className="quote-line" key={`${l.type}-${l.key}`}>
                                        <span>{l.name}{l.added_for_dependency && <small className="text-muted d-block">needed by your choice</small>}</span>
                                        <span className="tabular">{l.price > 0 ? money(l.price, l.currency) : 'Free'}</span>
                                    </div>
                                ))}
                                <div className="quote-total"><span>Total</span><span className="tabular">{money(quote.total, quote.currency)}<small className="text-muted fw-semibold fs-6"> / {quote.interval === 'yearly' ? 'yr' : 'mo'}</small></span></div>
                                {quote.trial_days > 0 && !entitled && <p className="small text-muted mt-2 mb-0"><i className="bi bi-hourglass-split me-1" />Starts with a {quote.trial_days}-day free trial (first subscription only).</p>}
                                {entitled && payable && <p className="small text-muted mt-2 mb-0">Changes are prorated: you pay or get credit for the difference on your next bill.</p>}
                                {blockedByProvider && <p className="small text-warning mt-2 mb-0"><i className="bi bi-plug me-1" />Paying opens when online payments are connected.</p>}
                                <button type="button" className="btn btn-primary w-100 mt-3" disabled={quoting || !!quoteError || blockedByProvider || busy} onClick={() => setReviewing(true)}>
                                    {payable ? (entitled ? 'Review change' : 'Continue to secure checkout') : 'Switch on for free'}
                                </button>
                                <button type="button" className="btn btn-link btn-sm w-100" onClick={() => { setPlanId(null); setPicked([]); }}>Clear</button>
                            </>
                        )}
                    </div>
                </div>
            </div>

            <div className="mt-4">
                <SectionCard icon="receipt-cutoff" title="Payment history" subtitle="Bills from Stripe, newest first." flush>
                    <DataTable page={invoices} columns={invoiceColumns} filters={filters} only={['invoices', 'filters']} perPageOptions={[10, 20, 50]}
                        empty={<EmptyState icon="receipt" title="No bills yet" hint="Bills appear here after your first payment." />} />
                </SectionCard>
            </div>

            <Modal open={reviewing && !!quote} title={entitled ? 'Confirm plan change' : payable ? 'Ready to pay' : 'Confirm'} icon="shield-check" onClose={() => setReviewing(false)}
                footer={<><button type="button" className="btn btn-light" onClick={() => setReviewing(false)}>Back</button>
                    <button type="button" className="btn btn-primary" disabled={busy} onClick={submit}>
                        {busy && <span className="spinner-border spinner-border-sm me-2" />}{entitled ? 'Confirm change' : payable ? 'Go to Stripe checkout' : 'Switch on'}
                    </button></>}>
                {quote && (
                    <>
                        {quote.lines.map((l) => <div className="quote-line" key={`r-${l.type}-${l.key}`}><span>{l.name}</span><span className="tabular">{l.price > 0 ? money(l.price, l.currency) : 'Free'}</span></div>)}
                        <div className="quote-total"><span>{quote.interval === 'yearly' ? 'Per year' : 'Per month'}</span><span className="tabular">{money(quote.total, quote.currency)}</span></div>
                        <p className="small text-muted mt-3 mb-0">
                            {entitled ? 'Your subscription is updated right away; the difference is prorated on your next bill.'
                                : payable ? 'You will be taken to Stripe’s secure page to pay. Coupons can be entered there. Your modules switch on as soon as the payment is confirmed.'
                                    : 'Nothing to pay: the modules switch on right away.'}
                        </p>
                    </>
                )}
            </Modal>

            <ConfirmDialog open={confirmCancel} title="Cancel subscription?" danger confirmLabel="Cancel subscription" busy={busy}
                message={subscription?.stripe ? `You keep everything until ${subscription.current_period_end ?? 'the end of the paid period'}. After that, paid modules switch off; your data is kept and comes back if you subscribe again.` : 'Paid modules switch off; your data is kept.'}
                onConfirm={() => { setBusy(true); router.post(route('billing.cancel'), {}, { preserveScroll: true, onFinish: () => { setBusy(false); setConfirmCancel(false); } }); }}
                onClose={() => setConfirmCancel(false)} />
        </>
    );
}

function SubscriptionHero({ sub, onCancel }: { sub: Sub; onCancel: () => void }) {
    return (
        <div className="sub-hero mb-3">
            <div className="d-flex flex-wrap gap-3 align-items-start position-relative" style={{ zIndex: 1 }}>
                <div className="flex-grow-1">
                    <div className="muted small fw-semibold text-uppercase">Current plan</div>
                    <div className="h4 fw-bold mb-2">{sub.plan ?? 'Custom plan'}</div>
                    <div className="d-flex flex-wrap gap-2">
                        <span className="chip" style={{ background: `color-mix(in srgb, ${STATUS_ACCENT[sub.status] ?? '#fff'} 45%, transparent)` }}><i className="bi bi-circle-fill" style={{ fontSize: 8 }} />{sub.status_label}</span>
                        {sub.modules.map((m) => <span className="chip" key={m}><i className="bi bi-puzzle" />{m}</span>)}
                    </div>
                </div>
                <div className="text-end">
                    <div className="amount">{sub.amount > 0 ? money(sub.amount, sub.currency) : 'Free'}</div>
                    {sub.amount > 0 && <div className="muted small">per {sub.interval === 'yearly' ? 'year' : 'month'}</div>}
                </div>
            </div>
            <div className="d-flex flex-wrap gap-2 align-items-center mt-3 position-relative" style={{ zIndex: 1 }}>
                <span className="muted small">
                    {sub.status === 'trialing' && sub.trial_ends_at ? `Free trial until ${sub.trial_ends_at}. ` : ''}
                    {sub.status === 'past_due' ? 'Your last payment failed. Stripe retries automatically; update your card to keep access. ' : ''}
                    {sub.status === 'incomplete' ? 'Waiting for the payment to be confirmed. ' : ''}
                    {sub.cancel_at_period_end ? `Ends on ${sub.current_period_end ?? 'the end of the period'}.` : sub.current_period_end ? `Renews on ${sub.current_period_end}.` : ''}
                </span>
                <div className="ms-auto d-flex gap-2">
                    {sub.cancel_at_period_end ? (
                        <button type="button" className="btn btn-light btn-sm" onClick={() => router.post(route('billing.resume'), {}, { preserveScroll: true })}><i className="bi bi-arrow-counterclockwise me-1" />Keep my subscription</button>
                    ) : sub.entitled && (
                        <button type="button" className="btn btn-outline-light btn-sm" onClick={onCancel}>Cancel subscription</button>
                    )}
                </div>
            </div>
        </div>
    );
}
