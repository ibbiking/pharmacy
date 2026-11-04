import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import StatStrip from '@/Components/StatStrip';
import DataTable, { type Column } from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { formatNumber } from '@/lib/format';

interface ModulePriceRow { module: string; name: string; icon: string; accent: string; available: boolean; monthly_price: number; yearly_price: number; currency: string }
interface PlanRow {
    id: number; name: string; slug: string; description: string | null; modules: string[];
    monthly_price: number; yearly_price: number; currency: string; limits: Record<string, number | null> | null;
    trial_days: number; is_visible: boolean; sort_order: number;
}
interface Props {
    modulePrices: ModulePriceRow[];
    plans: Paginated<PlanRow>;
    filters: TableFilters;
    settings: { free_for_everyone: boolean; free_grace_until: string | null; free_now: boolean };
    limitKeys: string[];
}

const LIMIT_LABEL: Record<string, string> = { users: 'Users', businesses: 'Businesses', products: 'Products / items', invoices_per_month: 'Invoices per month', warehouses: 'Warehouses' };

export default function Billing({ modulePrices, plans, filters, settings, limitKeys }: Props) {
    const [editing, setEditing] = useState<PlanRow | 'new' | null>(null);
    const [archiving, setArchiving] = useState<PlanRow | null>(null);
    const moduleName = Object.fromEntries(modulePrices.map((m) => [m.module, m.name]));

    const columns: Column<PlanRow>[] = [
        { key: 'name', label: 'Plan', render: (p) => (<div><strong>{p.name}</strong>{!p.is_visible && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Hidden</span>}<div className="small text-muted">{p.description}</div></div>) },
        {
            key: 'modules', label: 'Modules', render: (p) => (
                <div className="d-flex flex-wrap gap-1" title={p.modules.map((m) => moduleName[m] ?? m).join(', ')}>
                    {p.modules.slice(0, 3).map((m) => <span key={m} className="pill text-nowrap">{moduleName[m] ?? m}</span>)}
                    {p.modules.length > 3 && <span className="pill" style={{ ['--accent' as string]: 'var(--chart-8)' }}>+{p.modules.length - 3} more</span>}
                </div>
            ),
        },
        { key: 'monthly_price', label: 'Monthly', align: 'end', className: 'tabular fw-semibold text-nowrap', render: (p) => `${p.currency} ${formatNumber(p.monthly_price)}` },
        { key: 'yearly_price', label: 'Yearly', align: 'end', className: 'tabular text-nowrap', render: (p) => `${p.currency} ${formatNumber(p.yearly_price)}` },
        { key: 'trial_days', label: 'Trial', align: 'end', className: 'text-nowrap', render: (p) => (p.trial_days ? `${p.trial_days} days` : '—') },
        {
            key: 'actions', label: '', align: 'end', render: (p) => (
                <div className="d-inline-flex gap-1">
                    <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(p)} aria-label={`Edit ${p.name}`}><i className="bi bi-pencil" /></button>
                    <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(p)} aria-label={`Archive ${p.name}`}><i className="bi bi-archive" /></button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Plans & pricing" />
            <PageHeader icon="tags" title="Plans & pricing" description="What the platform sells: module prices for custom plans, ready-made plans, and launch pricing."
                actions={<button type="button" className="btn btn-primary" onClick={() => setEditing('new')}><i className="bi bi-plus-lg me-1" />New plan</button>} />

            <StatStrip stats={[
                { key: 'free', icon: settings.free_now ? 'gift' : 'credit-card', label: 'Pricing now', value: settings.free_now ? 'Free for everyone' : 'Paid plans', accent: settings.free_now ? 'var(--app-success)' : 'var(--chart-4)' },
                { key: 'plans', icon: 'collection', label: 'Plans', value: plans.total, accent: 'var(--chart-1)' },
                { key: 'modules', icon: 'puzzle', label: 'Sellable modules', value: modulePrices.length, accent: 'var(--chart-2)' },
            ]} />

            <LaunchPricing settings={settings} />

            <div className="row g-4">
                <div className="col-12">
                    <SectionCard icon="puzzle" title="Module prices" subtitle="Used for custom plans and add-ons" accent="var(--chart-2)" flush>
                        <div className="table-responsive">
                            <table className="table dt-table">
                                <thead><tr><th>Module</th><th className="text-end">Monthly</th><th className="text-end">Yearly</th><th /></tr></thead>
                                <tbody>{modulePrices.map((m) => <ModulePriceEditor key={m.module} row={m} />)}</tbody>
                            </table>
                        </div>
                    </SectionCard>
                </div>
                <div className="col-12">
                    <SectionCard icon="collection" title="Plans" subtitle="Bundles customers can buy" flush>
                        <DataTable page={plans} columns={columns} filters={filters} only={['plans', 'filters']} searchPlaceholder="Search plans…" />
                    </SectionCard>
                </div>
            </div>

            <PlanModal plan={editing} modulePrices={modulePrices} limitKeys={limitKeys} onClose={() => setEditing(null)} />
            <ConfirmDialog open={archiving !== null} title="Archive plan?" danger confirmLabel="Archive"
                message={`“${archiving?.name}” will no longer be offered. Businesses already on it keep it.`}
                onConfirm={() => archiving && router.delete(route('superadmin.billing.plans.destroy', archiving.id), { preserveScroll: true, onFinish: () => setArchiving(null) })}
                onClose={() => setArchiving(null)} />
        </>
    );
}

function LaunchPricing({ settings }: { settings: Props['settings'] }) {
    const form = useForm({ free_for_everyone: settings.free_for_everyone, free_grace_until: settings.free_grace_until ?? '' });
    useEffect(() => form.setData({ free_for_everyone: settings.free_for_everyone, free_grace_until: settings.free_grace_until ?? '' }), [settings]); // eslint-disable-line react-hooks/exhaustive-deps
    const save = (e: FormEvent) => { e.preventDefault(); form.put(route('superadmin.billing.settings'), { preserveScroll: true }); };

    return (
        <SectionCard icon="gift" title="Launch pricing" subtitle="While ON, every module is free for every business" accent={form.data.free_for_everyone ? 'var(--app-success)' : 'var(--chart-4)'}>
            <form onSubmit={save} className="d-flex flex-wrap align-items-center gap-4">
                <div className="form-check form-switch m-0">
                    <input className="form-check-input" type="checkbox" role="switch" id="free" style={{ width: '3rem', height: '1.5rem' }}
                        checked={form.data.free_for_everyone} onChange={(e) => form.setData('free_for_everyone', e.target.checked)} />
                    <label className="form-check-label fw-semibold ms-2 mt-1" htmlFor="free">Free for everyone</label>
                </div>
                {!form.data.free_for_everyone && (
                    <div>
                        <label className="form-label small mb-1" htmlFor="grace">Keep it free until (grace period, optional)</label>
                        <input id="grace" type="date" className={`form-control ${form.errors.free_grace_until ? 'is-invalid' : ''}`} value={form.data.free_grace_until} onChange={(e) => form.setData('free_grace_until', e.target.value)} />
                        {form.errors.free_grace_until && <div className="invalid-feedback">{form.errors.free_grace_until}</div>}
                    </div>
                )}
                <button type="submit" className="btn btn-primary ms-auto" disabled={form.processing || !form.isDirty}>Save</button>
            </form>
        </SectionCard>
    );
}

function ModulePriceEditor({ row }: { row: ModulePriceRow }) {
    const form = useForm({ monthly_price: row.monthly_price, yearly_price: row.yearly_price, currency: row.currency });
    const save = () => form.put(route('superadmin.billing.module-price', row.module), { preserveScroll: true });

    return (
        <tr>
            <td>
                <span className="d-flex align-items-center gap-2">
                    <span className="pill" style={{ ['--accent' as string]: row.accent }}><i className={`bi bi-${row.icon}`} /></span>
                    <span>{row.name}{!row.available && <small className="d-block text-muted">Coming soon</small>}</span>
                </span>
            </td>
            <td className="text-end" style={{ width: 120 }}>
                <input type="number" min={0} step="0.01" aria-label={`${row.name} monthly price`} className={`form-control form-control-sm text-end ${form.errors.monthly_price ? 'is-invalid' : ''}`} value={form.data.monthly_price} onChange={(e) => form.setData('monthly_price', Number(e.target.value))} />
            </td>
            <td className="text-end" style={{ width: 120 }}>
                <input type="number" min={0} step="0.01" aria-label={`${row.name} yearly price`} className={`form-control form-control-sm text-end ${form.errors.yearly_price ? 'is-invalid' : ''}`} value={form.data.yearly_price} onChange={(e) => form.setData('yearly_price', Number(e.target.value))} />
            </td>
            <td className="text-end">
                <button type="button" className="btn btn-sm btn-soft" onClick={save} disabled={!form.isDirty || form.processing}>Save</button>
            </td>
        </tr>
    );
}

function PlanModal({ plan, modulePrices, limitKeys, onClose }: { plan: PlanRow | 'new' | null; modulePrices: ModulePriceRow[]; limitKeys: string[]; onClose: () => void }) {
    const empty = { name: '', slug: '', description: '', modules: [] as string[], monthly_price: 0, yearly_price: 0, currency: 'USD', limits: {} as Record<string, number | null | ''>, trial_days: 0, is_visible: true, sort_order: 0 };
    const form = useForm(empty);

    useEffect(() => {
        form.clearErrors();
        if (plan === 'new') form.setData(empty);
        else if (plan) form.setData({ ...plan, description: plan.description ?? '', limits: plan.limits ?? {} });
    }, [plan]); // eslint-disable-line react-hooks/exhaustive-deps

    const sumOfModules = modulePrices.filter((m) => form.data.modules.includes(m.module)).reduce((t, m) => t + m.monthly_price, 0);
    const toggle = (key: string) => form.setData('modules', form.data.modules.includes(key) ? form.data.modules.filter((k) => k !== key) : [...form.data.modules, key]);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        if (plan === 'new') form.post(route('superadmin.billing.plans.store'), opts);
        else if (plan) form.put(route('superadmin.billing.plans.update', plan.id), opts);
    };

    return (
        <Modal open={plan !== null} title={plan === 'new' ? 'New plan' : 'Edit plan'} icon="collection" onClose={onClose} width={760}
            footer={<><button type="button" className="btn btn-light" onClick={onClose}>Cancel</button><button type="submit" form="plan-form" className="btn btn-primary" disabled={form.processing}>Save plan</button></>}>
            <form id="plan-form" onSubmit={submit} noValidate className="row g-3">
                <div className="col-md-7">
                    <label className="form-label fw-semibold" htmlFor="plan-name">Name</label>
                    <input id="plan-name" className={`form-control ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                </div>
                <div className="col-md-5">
                    <label className="form-label fw-semibold" htmlFor="plan-slug">Slug <small className="text-muted fw-normal">(auto from name)</small></label>
                    <input id="plan-slug" className={`form-control ${form.errors.slug ? 'is-invalid' : ''}`} value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
                    {form.errors.slug && <div className="invalid-feedback">{form.errors.slug}</div>}
                </div>
                <div className="col-12">
                    <label className="form-label fw-semibold" htmlFor="plan-desc">Description</label>
                    <input id="plan-desc" className="form-control" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={500} />
                </div>
                <div className="col-12">
                    <label className="form-label fw-semibold d-block">Modules <small className="text-muted fw-normal">(what they depend on is added automatically)</small></label>
                    <div className="d-flex flex-wrap gap-2">
                        {modulePrices.map((m) => (
                            <button type="button" key={m.module} onClick={() => toggle(m.module)} className={`btn btn-sm ${form.data.modules.includes(m.module) ? 'btn-primary' : 'btn-outline-secondary'}`} aria-pressed={form.data.modules.includes(m.module)}>
                                <i className={`bi bi-${m.icon} me-1`} />{m.name}
                            </button>
                        ))}
                    </div>
                    {form.errors.modules && <div className="text-danger small mt-1">{form.errors.modules}</div>}
                    <div className="form-text">Sum of module prices: {formatNumber(sumOfModules)} / month. A plan is usually cheaper.</div>
                </div>
                <div className="col-md-4">
                    <label className="form-label fw-semibold" htmlFor="pm">Monthly price</label>
                    <input id="pm" type="number" min={0} step="0.01" className={`form-control ${form.errors.monthly_price ? 'is-invalid' : ''}`} value={form.data.monthly_price} onChange={(e) => form.setData('monthly_price', Number(e.target.value))} />
                </div>
                <div className="col-md-4">
                    <label className="form-label fw-semibold" htmlFor="py">Yearly price</label>
                    <input id="py" type="number" min={0} step="0.01" className={`form-control ${form.errors.yearly_price ? 'is-invalid' : ''}`} value={form.data.yearly_price} onChange={(e) => form.setData('yearly_price', Number(e.target.value))} />
                </div>
                <div className="col-md-4">
                    <label className="form-label fw-semibold" htmlFor="pc">Currency</label>
                    <input id="pc" maxLength={3} className={`form-control text-uppercase ${form.errors.currency ? 'is-invalid' : ''}`} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value.toUpperCase())} />
                </div>
                <div className="col-12">
                    <label className="form-label fw-semibold d-block">Limits <small className="text-muted fw-normal">(empty = unlimited)</small></label>
                    <div className="row g-2">
                        {limitKeys.map((k) => (
                            <div className="col-6 col-md-4" key={k}>
                                <div className="input-group input-group-sm">
                                    <span className="input-group-text">{LIMIT_LABEL[k] ?? k}</span>
                                    <input type="number" min={1} className="form-control" value={form.data.limits[k] ?? ''} onChange={(e) => form.setData('limits', { ...form.data.limits, [k]: e.target.value === '' ? null : Number(e.target.value) })} aria-label={LIMIT_LABEL[k] ?? k} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="col-md-4">
                    <label className="form-label fw-semibold" htmlFor="trial">Trial days</label>
                    <input id="trial" type="number" min={0} max={90} className={`form-control ${form.errors.trial_days ? 'is-invalid' : ''}`} value={form.data.trial_days} onChange={(e) => form.setData('trial_days', Number(e.target.value))} />
                </div>
                <div className="col-md-4">
                    <label className="form-label fw-semibold" htmlFor="order">Order on pricing page</label>
                    <input id="order" type="number" min={0} className="form-control" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} />
                </div>
                <div className="col-md-4 d-flex align-items-end">
                    <div className="form-check form-switch">
                        <input className="form-check-input" type="checkbox" id="visible" checked={form.data.is_visible} onChange={(e) => form.setData('is_visible', e.target.checked)} />
                        <label className="form-check-label" htmlFor="visible">Visible to customers</label>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
