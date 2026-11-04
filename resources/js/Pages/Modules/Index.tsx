import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import ConfirmDialog from '@/Components/ConfirmDialog';

interface ModuleRow {
    key: string;
    name: string;
    description: string;
    icon: string;
    accent: string;
    core: boolean;
    available: boolean;
    active: boolean;
    source: string | null;
    activated_at: string | null;
    depends_on: string[];
    in_bundle: boolean;
    monthly_price: number | null;
    currency: string | null;
}

type Filter = 'all' | 'active' | 'available' | 'soon';

export default function ModulesIndex({ modules, highlight, canManage, businessType, free }: { modules: ModuleRow[]; highlight: string | null; canManage: boolean; businessType: string; free: boolean }) {
    const [filter, setFilter] = useState<Filter>('all');
    const [confirm, setConfirm] = useState<{ module: ModuleRow; action: 'on' | 'off' } | null>(null);
    const [busy, setBusy] = useState(false);

    const counts = useMemo(
        () => ({
            active: modules.filter((m) => m.active).length,
            available: modules.filter((m) => m.available && !m.active).length,
            soon: modules.filter((m) => !m.available).length,
        }),
        [modules],
    );

    const shown = modules.filter((m) =>
        filter === 'all' ? true : filter === 'active' ? m.active : filter === 'available' ? m.available && !m.active : !m.available,
    );

    const submit = () => {
        if (!confirm) return;
        const url = route(confirm.action === 'on' ? 'modules.activate' : 'modules.deactivate', confirm.module.key);
        const options = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setConfirm(null); } };
        if (confirm.action === 'on') router.post(url, {}, options);
        else router.delete(url, options);
    };

    return (
        <>
            <Head title="Modules" />
            <PageHeader
                icon="puzzle"
                title="Modules"
                description={free ? 'Switch on only what your business needs. Everything is free during launch.' : 'Switch on only what your business needs. Modules are priced per month; billing opens soon.'}
            />

            <StatStrip
                stats={[
                    { key: 'all', icon: 'grid', label: 'All modules', value: modules.length, accent: 'var(--chart-1)', onClick: () => setFilter('all'), selected: filter === 'all' },
                    { key: 'active', icon: 'check2-circle', label: 'Switched on', value: counts.active, accent: 'var(--chart-3)', onClick: () => setFilter('active'), selected: filter === 'active' },
                    { key: 'available', icon: 'plus-circle', label: 'Available', value: counts.available, accent: 'var(--chart-2)', onClick: () => setFilter('available'), selected: filter === 'available' },
                    { key: 'soon', icon: 'hourglass-split', label: 'Coming soon', value: counts.soon, accent: 'var(--chart-8)', onClick: () => setFilter('soon'), selected: filter === 'soon' },
                ]}
            />

            <div className="row g-3">
                {shown.map((m) => (
                    <div className="col-md-6 col-xl-4" key={m.key}>
                        <div className={`module-card ${highlight === m.key ? 'highlight' : ''} ${m.available ? '' : 'unavailable'}`} style={{ ['--accent' as string]: m.accent }}>
                            <div className="d-flex align-items-start gap-3">
                                <span className="m-icon">
                                    <i className={`bi bi-${m.icon}`} />
                                </span>
                                <div className="flex-grow-1">
                                    <h3>{m.name}</h3>
                                    <div className="d-flex flex-wrap gap-1 mt-1">
                                        {m.core && <span className="pill">Always on</span>}
                                        {!m.core && m.active && <span className="pill" style={{ ['--accent' as string]: 'var(--app-success)' }}>On{m.activated_at ? ` since ${m.activated_at}` : ''}</span>}
                                        {!m.available && <span className="pill" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Coming soon</span>}
                                        {m.in_bundle && !m.core && <span className="pill" style={{ ['--accent' as string]: 'var(--chart-2)' }}>Recommended for {businessType.replace('_', ' ')}</span>}
                                    </div>
                                </div>
                            </div>
                            <p>{m.description}</p>
                            {!m.core && m.monthly_price !== null && (
                                <div className="small">
                                    {free ? (
                                        <><s className="text-muted me-2">{m.currency} {m.monthly_price}/mo</s><span className="pill" style={{ ['--accent' as string]: 'var(--app-success)' }}>Free during launch</span></>
                                    ) : (
                                        <span className="fw-semibold">{m.currency} {m.monthly_price}/month</span>
                                    )}
                                </div>
                            )}
                            {m.depends_on.length > 0 && (
                                <small className="text-muted">
                                    <i className="bi bi-diagram-2 me-1" />
                                    Needs: {m.depends_on.join(', ')}
                                </small>
                            )}
                            {!m.core && m.available && canManage && (
                                <div>
                                    {m.active ? (
                                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setConfirm({ module: m, action: 'off' })}>
                                            <i className="bi bi-power me-1" />
                                            Switch off
                                        </button>
                                    ) : (
                                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setConfirm({ module: m, action: 'on' })}>
                                            <i className="bi bi-plus-lg me-1" />
                                            {free ? 'Switch on (free)' : 'Switch on'}
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            <ConfirmDialog
                open={confirm !== null}
                title={confirm?.action === 'on' ? `Switch on ${confirm?.module.name}?` : `Switch off ${confirm?.module.name}?`}
                message={
                    confirm?.action === 'on'
                        ? `${confirm.module.name} becomes available to everyone in this business.${confirm.module.depends_on.length ? ` ${confirm.module.depends_on.join(', ')} will be switched on too.` : ''}`
                        : `Its screens will be hidden. Nothing is deleted: everything comes back when you switch it on again.`
                }
                confirmLabel={confirm?.action === 'on' ? 'Switch on' : 'Switch off'}
                danger={confirm?.action === 'off'}
                busy={busy}
                onConfirm={submit}
                onClose={() => setConfirm(null)}
            />
        </>
    );
}
