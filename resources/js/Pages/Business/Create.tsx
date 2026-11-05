import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import OnboardingLayout from '@/Layouts/OnboardingLayout';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import BusinessTypePicker, { type BusinessType } from '@/Components/BusinessTypePicker';

type TypeDef = BusinessType;
interface ModuleDef { key: string; name: string; description: string; icon: string; accent: string; core: boolean; available: boolean; depends_on: string[]; monthly_price: number; currency: string }
interface Props {
    types: TypeDef[];
    modules: ModuleDef[];
    preselectType: string | null;
    free: boolean;
    isFirstBusiness: boolean;
    defaultCurrency: { id: number; currency_code: string; name: string; symbol: string } | null;
}

const STEPS = ['Basics', 'Business type', 'Modules', 'Review'];
const money = (n: number, c: string) => {
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: c, maximumFractionDigits: 2 }).format(n); } catch { return `${c} ${n}`; }
};

export default function CreateBusiness({ types, modules, preselectType, free, isFirstBusiness, defaultCurrency }: Props) {
    const [step, setStep] = useState(0);
    const [currency, setCurrency] = useState<Option | null>(
        defaultCurrency ? { value: defaultCurrency.id, label: `${defaultCurrency.currency_code} — ${defaultCurrency.name} (${defaultCurrency.symbol})` } : null,
    );
    const firstType = types.find((t) => t.key === preselectType) ?? types[0]!;
    const form = useForm({
        name: '',
        phone: '',
        address: '',
        business_type: firstType.key,
        currency_id: defaultCurrency?.id ?? (null as number | null),
        modules: firstType.bundle.filter((k) => modules.find((m) => m.key === k && m.available && !m.core)),
    });

    const byKey = useMemo(() => Object.fromEntries(modules.map((m) => [m.key, m])), [modules]);

    /** Modules switched on because a chosen module needs them. */
    const required = useMemo(() => {
        const need = new Set<string>();
        const walk = (key: string) => byKey[key]?.depends_on.forEach((d) => { need.add(d); walk(d); });
        form.data.modules.forEach(walk);
        return need;
    }, [form.data.modules, byKey]);

    const chooseType = (t: TypeDef) => {
        form.setData((d) => ({ ...d, business_type: t.key, modules: t.bundle.filter((k) => byKey[k]?.available && !byKey[k]?.core) }));
    };

    const toggleModule = (m: ModuleDef) => {
        if (m.core || !m.available || required.has(m.key)) return;
        form.setData('modules', form.data.modules.includes(m.key) ? form.data.modules.filter((k) => k !== m.key) : [...form.data.modules, m.key]);
    };

    const next = () => {
        if (step === 0 && !form.data.name.trim()) {
            form.setError('name', 'Give your business a name.');
            return;
        }
        form.clearErrors();
        setStep((s) => Math.min(s + 1, STEPS.length - 1));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (step < STEPS.length - 1) {
            next();
            return;
        }
        form.post(route('business.store'), {
            onError: (errors) => {
                if (errors.name || errors.phone || errors.address || errors.currency_id) setStep(0);
                else if (errors.business_type) setStep(1);
                else setStep(2);
            },
        });
    };

    const finalModules = modules.filter((m) => m.core || form.data.modules.includes(m.key) || required.has(m.key));
    const type = types.find((t) => t.key === form.data.business_type)!;

    return (
        <>
            <Head title="Create business" />
            <div className="text-center mb-2">
                <h1 className="fw-bold mb-1" style={{ fontSize: '1.8rem' }}>{isFirstBusiness ? 'Welcome! Let’s set up your business' : 'Create a new business'}</h1>
                <p className="text-muted mb-0">Takes a minute. You can change everything later in Business settings and Modules.</p>
            </div>

            <div className="wizard-steps justify-content-center" aria-label="Steps">
                {STEPS.map((label, i) => (
                    <button
                        type="button"
                        key={label}
                        className={`wizard-step ${i === step ? 'current' : ''} ${i < step ? 'done' : ''}`}
                        onClick={() => i < step && setStep(i)}
                        disabled={i > step}
                        aria-current={i === step ? 'step' : undefined}
                    >
                        <span className="n">{i < step ? <i className="bi bi-check" /> : i + 1}</span>
                        {label}
                    </button>
                ))}
            </div>

            <form onSubmit={submit} className="app-card section-card" noValidate>
                <div className="section-body p-4">
                    {step === 0 && (
                        <div className="row g-3">
                            <Field label="Business name" error={form.errors.name} className="col-12">
                                <input className={`form-control form-control-lg ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. Rana Medical Store" autoFocus maxLength={255} />
                            </Field>
                            <Field label="Phone" hint="Optional" error={form.errors.phone} className="col-md-6">
                                <input className={`form-control ${form.errors.phone ? 'is-invalid' : ''}`} value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="+92 300 0000000" maxLength={50} />
                            </Field>
                            <Field label="Currency" hint="Prices and reports use this" error={form.errors.currency_id} className="col-md-6">
                                <AsyncSelect
                                    entity="currencies"
                                    url={route('business.create.currencies')}
                                    value={currency}
                                    onChange={(o) => {
                                        setCurrency(o);
                                        form.setData('currency_id', o?.value ?? null);
                                    }}
                                    placeholder="Search currency…"
                                    invalid={!!form.errors.currency_id}
                                />
                            </Field>
                            <Field label="Address" hint="Optional, printed on receipts" error={form.errors.address} className="col-12">
                                <textarea className={`form-control ${form.errors.address ? 'is-invalid' : ''}`} rows={2} value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} maxLength={500} />
                            </Field>
                        </div>
                    )}

                    {step === 1 && (
                        <>
                            <p className="text-muted">The type sets the wording (e.g. “Medicines” or “Items”) and which modules are recommended.</p>
                            <BusinessTypePicker types={types} value={form.data.business_type} onChange={(key) => chooseType(types.find((t) => t.key === key)!)} />
                            {form.errors.business_type && <div className="text-danger small mt-2">{form.errors.business_type}</div>}
                        </>
                    )}

                    {step === 2 && (
                        <>
                            <p className="text-muted">
                                Recommended modules for <strong>{type.name}</strong> are selected. {free ? <>All modules are <strong>free during launch</strong>; switch any on or off later.</> : <>After creating the business you’ll choose how to pay for them on the Billing page (Core is always free).</>}
                            </p>
                            <div className="row g-3">
                                {modules.map((m) => {
                                    const on = m.core || form.data.modules.includes(m.key) || required.has(m.key);
                                    const locked = m.core || required.has(m.key);
                                    return (
                                        <div className="col-md-6 col-lg-4" key={m.key}>
                                            <button type="button" className={`choice-card ${on ? 'selected' : ''}`} style={{ ['--accent' as string]: m.accent }} onClick={() => toggleModule(m)} disabled={!m.available} aria-pressed={on}>
                                                {on && <i className={`bi bi-${locked ? 'lock-fill' : 'check-circle-fill'} check`} />}
                                                <span className="c-icon">
                                                    <i className={`bi bi-${m.icon}`} />
                                                </span>
                                                <strong>{m.name}</strong>
                                                <p>{m.description}</p>
                                                <small className="text-muted">
                                                    {m.core ? 'Always included' : !m.available ? 'Coming soon' : required.has(m.key) ? 'Needed by another module you chose' : ''}
                                                    {!free && !m.core && m.available && m.monthly_price > 0 && <span className="d-block fw-semibold">{money(m.monthly_price, m.currency)} / month</span>}
                                                </small>
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                            {form.errors.modules && <div className="text-danger small mt-2">{form.errors.modules}</div>}
                        </>
                    )}

                    {step === 3 && (
                        <div className="row g-4">
                            <div className="col-md-6">
                                <h2 className="h6 fw-bold">Business</h2>
                                <dl className="mb-0">
                                    <Review label="Name">{form.data.name}</Review>
                                    <Review label="Type">{type.name}</Review>
                                    <Review label="Currency">{currency?.label ?? 'Not set'}</Review>
                                    <Review label="Phone">{form.data.phone || '—'}</Review>
                                    <Review label="Address">{form.data.address || '—'}</Review>
                                </dl>
                            </div>
                            <div className="col-md-6">
                                <h2 className="h6 fw-bold">Modules ({finalModules.length})</h2>
                                <div className="d-flex flex-wrap gap-2">
                                    {finalModules.map((m) => (
                                        <span key={m.key} className="pill" style={{ ['--accent' as string]: m.accent }}>
                                            <i className={`bi bi-${m.icon}`} />
                                            {m.name}
                                        </span>
                                    ))}
                                </div>
                                <p className="small text-muted mt-3 mb-0">You will be the owner of this business. Invite staff from Settings → Team afterwards.{!free && ' Next you’ll choose a plan for the paid modules.'}</p>
                            </div>
                        </div>
                    )}
                </div>

                <div className="d-flex align-items-center gap-2 px-4 pb-4">
                    {step > 0 && (
                        <button type="button" className="btn btn-light" onClick={() => setStep((s) => s - 1)} disabled={form.processing}>
                            <i className="bi bi-arrow-left me-1" />
                            Back
                        </button>
                    )}
                    <span className="ms-auto small text-muted">Step {step + 1} of {STEPS.length}</span>
                    <button type="submit" className="btn btn-primary px-4" disabled={form.processing}>
                        {form.processing && <span className="spinner-border spinner-border-sm me-2" />}
                        {step < STEPS.length - 1 ? 'Continue' : 'Create business'}
                        {step < STEPS.length - 1 && <i className="bi bi-arrow-right ms-1" />}
                    </button>
                </div>
            </form>
        </>
    );
}

CreateBusiness.layout = (page: ReactNode) => <OnboardingLayout>{page}</OnboardingLayout>;

function Field({ label, hint, error, className, children }: { label: string; hint?: string; error?: string; className?: string; children: ReactNode }) {
    return (
        <div className={className}>
            <label className="form-label fw-semibold mb-1">
                {label} {hint && <small className="text-muted fw-normal ms-1">{hint}</small>}
            </label>
            {children}
            {error && <div className="invalid-feedback d-block">{error}</div>}
        </div>
    );
}

function Review({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="d-flex gap-3 py-1 border-bottom" style={{ borderColor: 'var(--app-border)' }}>
            <dt className="text-muted fw-normal" style={{ width: 90 }}>{label}</dt>
            <dd className="mb-0 fw-semibold text-break">{children}</dd>
        </div>
    );
}
