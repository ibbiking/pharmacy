import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import ConfirmDialog from '@/Components/ConfirmDialog';

interface Sub { key: string; name: string; description: string; blocked: boolean }
interface ModuleRow {
    key: string; name: string; description: string; icon: string; accent: string;
    core: boolean; available: boolean; locked: boolean; active: boolean; blocked: boolean; subs: Sub[];
}
interface Member { id: number; name: string; email: string; role: string; owner: boolean; status: string }
interface Props {
    business: { id: number; name: string; type: string; type_name: string; phone: string | null; address: string | null; created_at: string | null };
    members: Member[];
    counts: Record<'products' | 'sales' | 'customers' | 'suppliers' | 'documents' | 'expenses', number>;
    modules: ModuleRow[];
}
type Filter = 'all' | 'on' | 'off' | 'blocked';

const COUNT_TILES: [keyof Props['counts'], string, string][] = [
    ['products', 'box-seam', 'Products'], ['sales', 'receipt', 'Sales'], ['customers', 'people', 'Customers'],
    ['suppliers', 'truck', 'Suppliers'], ['documents', 'file-earmark-text', 'Documents'], ['expenses', 'wallet2', 'Expenses'],
];

/** Super admin: one business, its people and every module with its switch, block and sub-module blocks. */
export default function BusinessShow({ business, members, counts, modules }: Props) {
    const [filter, setFilter] = useState<Filter>('all');
    const [q, setQ] = useState('');
    const [open, setOpen] = useState<string | null>(null);
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [entering, setEntering] = useState(false);
    const [confirmEnter, setConfirmEnter] = useState(false);

    const state = (m: ModuleRow) => (m.blocked ? 'blocked' : m.active ? 'on' : 'off');
    const shown = useMemo(() => modules.filter((m) => (filter === 'all' || state(m) === filter)
        && (!q || `${m.name} ${m.description} ${m.subs.map((s) => s.name).join(' ')}`.toLowerCase().includes(q.toLowerCase()))), [modules, filter, q]);
    const tally = { all: modules.length, on: modules.filter((m) => state(m) === 'on').length, off: modules.filter((m) => state(m) === 'off').length, blocked: modules.filter((m) => m.blocked).length };
    const blockedSubs = modules.reduce((n, m) => n + m.subs.filter((s) => s.blocked).length, 0);

    const post = (url: string, data: Record<string, string | boolean>, key: string) =>
        router.post(url, data, { preserveScroll: true, preserveState: true, onStart: () => setBusyKey(key), onFinish: () => setBusyKey(null) });
    const toggle = (m: ModuleRow) => post(route('platform.businesses.module', [business.id, m.key]), { on: !m.active }, `t:${m.key}`);
    const block = (key: string, blocked: boolean) => post(route('platform.businesses.block', business.id), { key, blocked }, `b:${key}`);
    const enter = () => router.post(route('platform.businesses.open', business.id), {}, { onStart: () => setEntering(true), onFinish: () => setEntering(false) });

    return (
        <>
            <Head title={business.name} />
            <PageHeader icon="shop" title={business.name} description={`${business.type_name} · joined ${business.created_at ?? '—'}${business.address ? ` · ${business.address}` : ''}`}
                actions={<>
                    <Link href={route('platform.businesses')} className="btn btn-light"><i className="bi bi-arrow-left me-1" />All businesses</Link>
                    <button type="button" className="btn btn-primary" onClick={() => setConfirmEnter(true)}><i className="bi bi-box-arrow-in-right me-1" />Open business</button>
                </>} />

            <div className="sa-tiles mb-3">
                {COUNT_TILES.map(([k, icon, label], i) => (
                    <div key={k} className="sa-tile" style={{ ['--accent' as string]: `var(--chart-${i + 1})` }}>
                        <span className="sa-tile-icon"><i className={`bi bi-${icon}`} /></span>
                        <div><small>{label}</small><strong className="tabular">{counts[k].toLocaleString()}</strong></div>
                    </div>
                ))}
            </div>

            <div className="row g-3">
                <div className="col-xl-8">
                    <SectionCard icon="puzzle" title="Modules & sub-modules" subtitle="Switch modules on or off, block a whole module, or block single sub-modules. A block wins over the owner's own settings.">
                        <div className="sa-mod-toolbar">
                            <div className="sa-chips" role="tablist" aria-label="Filter modules">
                                {(['all', 'on', 'off', 'blocked'] as Filter[]).map((f) => (
                                    <button key={f} type="button" role="tab" aria-selected={filter === f} className={`sa-chip ${filter === f ? 'active' : ''} is-${f}`} onClick={() => setFilter(f)}>
                                        {f === 'all' ? 'All' : f === 'on' ? 'On' : f === 'off' ? 'Off' : 'Blocked'}<span>{tally[f]}</span>
                                    </button>
                                ))}
                            </div>
                            <div className="sa-search"><i className="bi bi-search" /><input className="form-control form-control-sm" placeholder="Find a module or sub-module…" value={q} onChange={(e) => setQ(e.target.value)} /></div>
                        </div>
                        {blockedSubs > 0 && <div className="sa-note"><i className="bi bi-slash-circle" />{blockedSubs} sub-module{blockedSubs > 1 ? 's are' : ' is'} blocked in this business.</div>}

                        <div className="sa-modules">
                            {shown.map((m) => {
                                const st = state(m);
                                const expanded = open === m.key;
                                const subBlocked = m.subs.filter((s) => s.blocked).length;
                                return (
                                    <div key={m.key} className={`sa-mod is-${st} ${expanded ? 'open' : ''}`} style={{ ['--accent' as string]: m.accent }}>
                                        <div className="sa-mod-head">
                                            <button type="button" className="sa-mod-title" onClick={() => setOpen(expanded ? null : m.key)} aria-expanded={expanded}>
                                                <span className="sa-mod-icon"><i className={`bi bi-${m.icon}`} /></span>
                                                <span className="sa-mod-text">
                                                    <strong>{m.name}
                                                        <span className={`sa-pill is-${st}`}>{st === 'blocked' ? 'Blocked' : st === 'on' ? 'On' : 'Off'}</span>
                                                        {m.core && <span className="sa-pill is-core">Core</span>}
                                                        {m.locked && !m.core && <span className="sa-pill is-core">Part of the type</span>}
                                                        {!m.available && <span className="sa-pill is-soon">Coming soon</span>}
                                                    </strong>
                                                    <small>{m.description}</small>
                                                </span>
                                                {m.subs.length > 0 && <span className="sa-mod-count">{m.subs.length} sub-modules{subBlocked ? ` · ${subBlocked} blocked` : ''}<i className={`bi bi-chevron-${expanded ? 'up' : 'down'} ms-1`} /></span>}
                                            </button>
                                            <div className="sa-mod-actions">
                                                {!m.core && m.available && !m.blocked && (
                                                    <label className="form-check form-switch m-0" title={m.locked && m.active ? 'Part of the type: it stays on (block it instead)' : m.active ? 'Switch off' : 'Switch on'}>
                                                        <input className="form-check-input" type="checkbox" role="switch" checked={m.active} disabled={busyKey !== null || (m.locked && m.active)} onChange={() => toggle(m)} aria-label={`${m.name} on or off`} />
                                                    </label>
                                                )}
                                                {!m.core && (
                                                    <button type="button" className={`btn btn-sm ${m.blocked ? 'btn-success' : 'btn-outline-danger'}`} disabled={busyKey !== null} onClick={() => block(m.key, !m.blocked)}>
                                                        <i className={`bi bi-${m.blocked ? 'unlock' : 'slash-circle'} me-1`} />{m.blocked ? 'Unblock' : 'Block'}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        {expanded && (
                                            <div className="sa-subs">
                                                {m.subs.map((s) => (
                                                    <div key={s.key} className={`sa-sub ${s.blocked ? 'is-blocked' : ''}`}>
                                                        <div>
                                                            <strong>{s.name}</strong>
                                                            <small>{s.description}</small>
                                                        </div>
                                                        <button type="button" className={`sa-sub-toggle ${s.blocked ? 'is-blocked' : ''}`} disabled={busyKey !== null} onClick={() => block(s.key, !s.blocked)}
                                                            aria-pressed={s.blocked} title={s.blocked ? 'Blocked: nobody in this business can do this. Click to allow.' : 'Allowed (by role). Click to block for everyone.'}>
                                                            <i className={`bi bi-${s.blocked ? 'slash-circle-fill' : 'check-circle-fill'}`} />{s.blocked ? 'Blocked' : 'Allowed'}
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                            {shown.length === 0 && <p className="text-muted text-center py-4 mb-0">No module matches.</p>}
                        </div>
                    </SectionCard>
                </div>
                <div className="col-xl-4">
                    <SectionCard icon="people" title="People" subtitle={`${members.length} member${members.length === 1 ? '' : 's'}`}>
                        <ul className="sa-people">
                            {members.map((m) => (
                                <li key={m.id}>
                                    <span className="sa-avatar" style={{ ['--accent' as string]: `var(--chart-${(m.id % 8) + 1})` }}>{m.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()}</span>
                                    <span className="flex-grow-1 min-w-0"><strong className="d-block text-truncate">{m.name}</strong><small className="text-muted text-truncate d-block">{m.email}</small></span>
                                    <span className={`sa-pill ${m.owner ? 'is-core' : 'is-off'}`}>{m.role}</span>
                                </li>
                            ))}
                        </ul>
                    </SectionCard>
                    <SectionCard icon="info-circle" title="How blocks work">
                        <ul className="sa-help">
                            <li><b>Switch</b>: the same on/off the owner has on their Modules page.</li>
                            <li><b>Block a module</b>: it disappears for everyone in the business and the owner can't switch it back on.</li>
                            <li><b>Block a sub-module</b>: that one action (e.g. returns, discounts, exports) is refused for every role, the owner included.</li>
                            <li><b>Open business</b>: work inside it as its owner to view, add, edit or delete its data.</li>
                        </ul>
                    </SectionCard>
                </div>
            </div>

            <ConfirmDialog open={confirmEnter} title="Open this business?" confirmLabel="Open business" busy={entering}
                message={`You will work inside “${business.name}” with owner access. Everything you change is real. Use “Back to platform” at the top to leave.`}
                onConfirm={enter} onClose={() => setConfirmEnter(false)} />
        </>
    );
}
