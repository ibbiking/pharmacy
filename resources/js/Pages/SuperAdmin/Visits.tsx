import { Head, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import Chart from '@/Components/Chart';

interface Point { day: string; visitors: number; users: number; demo: number; logins: number; views: number }
interface Slice { label: string; n: number }
interface Login { at: string; name: string | null; email: string | null; demo: boolean; browser: string | null; os: string | null; device: string | null; ip: string | null; visitor: string }
interface VisitRow { day: string; last: string; visitor: string; email: string | null; demo: boolean; hits: number; path: string | null; referrer: string | null; browser: string | null; os: string | null; device: string | null; ip: string | null }
interface Act { at: string; method: string; what: string; refused: boolean; status: number | null }
interface DemoSession { id: number; visitor: string | null; role: string | null; browser: string | null; os: string | null; device: string | null; ip: string | null; started: string | null; last_seen: string | null; ended: boolean; page_views: number; actions: number; activity: Act[] }
interface Sandbox { id: number; status: string; visitor: string | null; browser: string | null; os: string | null; device: string | null; ip: string | null; started: string | null; last_seen: string | null; minutes_left: number | null }
interface Props {
    days: number;
    series: Point[];
    totals: { visitors: number; today: number; logins: number; demo: number; views: number };
    browsers: Slice[]; systems: Slice[]; devices: Slice[];
    logins: Login[];
    visitors: VisitRow[];
    sandboxes: { ready: number; active: Sandbox[]; today: number; total: number; history: DemoSession[] } | null;
}

const DEVICE_ICON: Record<string, string> = { desktop: 'laptop', mobile: 'phone', tablet: 'tablet' };

/** Super admin: how many people use the product per day, how they come in, and the live demo sessions. */
export default function Visits({ days, series, totals, browsers, systems, devices, logins, visitors, sandboxes }: Props) {
    const [tab, setTab] = useState<'logins' | 'visitors'>('logins');
    const [openSession, setOpenSession] = useState<number | null>(null);
    const verb = (m: string) => (m === 'DELETE' ? 'Deleted' : m === 'POST' ? 'Saved' : 'Changed');
    const range = (d: number) => router.get(route('superadmin.visits'), { days: d }, { preserveScroll: true, preserveState: true, replace: true });

    const option = useMemo(() => ({
        tooltip: { trigger: 'axis' as const },
        legend: { top: 0 },
        grid: { left: 36, right: 12, top: 36, bottom: 28 },
        xAxis: { type: 'category' as const, data: series.map((p) => p.day), boundaryGap: false },
        yAxis: { type: 'value' as const, minInterval: 1 },
        series: [
            { name: 'Visitors', type: 'line' as const, smooth: true, areaStyle: { opacity: 0.12 }, data: series.map((p) => p.visitors) },
            { name: 'Signed-in users', type: 'line' as const, smooth: true, data: series.map((p) => p.users) },
            { name: 'Demo visitors', type: 'line' as const, smooth: true, data: series.map((p) => p.demo) },
            { name: 'Sign-ins', type: 'bar' as const, barMaxWidth: 14, data: series.map((p) => p.logins) },
        ],
    }), [series]);

    const tiles: [string, string, number, string][] = [
        ['people', `Visitors (${days} days)`, totals.visitors, 'var(--chart-1)'],
        ['sun', 'Visitors today', totals.today, 'var(--chart-4)'],
        ['box-arrow-in-right', 'Sign-ins', totals.logins, 'var(--chart-3)'],
        ['easel', 'Demo visitors', totals.demo, 'var(--chart-5)'],
        ['eye', 'Page views', totals.views, 'var(--chart-2)'],
    ];

    return (
        <>
            <Head title="Visits" />
            <PageHeader icon="graph-up-arrow" title="Visits" description="Every browser counted once a day (an anonymous id in a cookie), every sign-in, and the demo sessions running now."
                actions={<div className="sa-chips">{[7, 30, 90].map((d) => <button key={d} type="button" className={`sa-chip ${days === d ? 'active' : ''}`} onClick={() => range(d)}>{d} days</button>)}</div>} />

            <div className="sa-tiles mb-3">
                {tiles.map(([icon, label, value, accent]) => (
                    <div key={label} className="sa-tile" style={{ ['--accent' as string]: accent }}>
                        <span className="sa-tile-icon"><i className={`bi bi-${icon}`} /></span>
                        <div><small>{label}</small><strong className="tabular">{value.toLocaleString()}</strong></div>
                    </div>
                ))}
            </div>

            <div className="row g-3">
                <div className="col-xl-8">
                    <SectionCard icon="activity" title="Per day" subtitle="Visitors, signed-in users, demo visitors and sign-ins">
                        <Chart option={option} height={300} ariaLabel="Visitors and sign-ins per day" />
                    </SectionCard>
                </div>
                <div className="col-xl-4">
                    <SectionCard icon="pie-chart" title="How they come in">
                        {([['Browser', browsers], ['System', systems], ['Device', devices]] as [string, Slice[]][]).map(([title, list]) => {
                            const max = Math.max(1, ...list.map((s) => s.n));
                            return (
                                <div key={title} className="sa-bars mb-3">
                                    <small className="fw-semibold text-muted d-block mb-1">{title}</small>
                                    {list.length === 0 && <small className="text-muted">No visits yet.</small>}
                                    {list.map((s) => (
                                        <div key={s.label} className="sa-bar"><span>{title === 'Device' && <i className={`bi bi-${DEVICE_ICON[s.label] ?? 'question-circle'} me-1`} />}{s.label}</span>
                                            <span className="sa-bar-track"><span style={{ width: `${(s.n / max) * 100}%` }} /></span><b className="tabular">{s.n}</b></div>
                                    ))}
                                </div>
                            );
                        })}
                    </SectionCard>
                </div>
            </div>

            {sandboxes && (
                <SectionCard icon="easel" title="Demo sessions" subtitle={`${sandboxes.active.length} running now · ${sandboxes.ready} ready copies waiting · ${sandboxes.today} started today · ${sandboxes.total} in total`}>
                    {sandboxes.active.length === 0 ? <p className="text-muted mb-0">Nobody is using the demo right now.</p> : (
                        <div className="table-responsive"><table className="table table-sm align-middle mb-0">
                            <thead><tr><th>Visitor</th><th>Browser</th><th>Started</th><th>Last active</th><th className="text-end">Cleared in</th></tr></thead>
                            <tbody>{sandboxes.active.map((s) => (
                                <tr key={s.id}><td><code>{s.visitor}</code> <small className="text-muted">{s.ip}</small></td><td>{s.browser} · {s.os} <i className={`bi bi-${DEVICE_ICON[s.device ?? ''] ?? 'question-circle'}`} /></td>
                                    <td>{s.started}</td><td>{s.last_seen}</td><td className="text-end tabular">{s.minutes_left !== null ? `${s.minutes_left} min` : '—'}</td></tr>
                            ))}</tbody>
                        </table></div>
                    )}
                </SectionCard>
            )}

            {sandboxes && (
                <SectionCard icon="journal-richtext" title="Demo visitors: what they did" subtitle="Kept for good, also after their demo data is deleted. Click a row to see its actions.">
                    {sandboxes.history.length === 0 ? <p className="text-muted mb-0">No demo visitors yet.</p> : (
                        <div className="table-responsive"><table className="table table-sm align-middle mb-0">
                            <thead><tr><th>Started</th><th>Browser id</th><th>Browser</th><th>Signed in as</th><th className="text-end">Pages</th><th className="text-end">Actions</th><th>Last active</th><th /></tr></thead>
                            <tbody>{sandboxes.history.map((h) => (
                                <Fragment key={h.id}>
                                    <tr className="sa-row-click" onClick={() => setOpenSession(openSession === h.id ? null : h.id)}>
                                        <td className="text-nowrap">{h.started}</td><td><code>{h.visitor}</code> <small className="text-muted d-block">{h.ip}</small></td>
                                        <td>{h.browser} · {h.os} <i className={`bi bi-${DEVICE_ICON[h.device ?? ''] ?? 'question-circle'}`} /></td>
                                        <td className="text-capitalize">{h.role ?? '—'}</td><td className="text-end tabular">{h.page_views}</td><td className="text-end tabular">{h.actions}</td>
                                        <td className="text-nowrap">{h.last_seen} {h.ended ? <span className="sa-pill is-off ms-1">Cleared</span> : <span className="sa-pill is-on ms-1">Live</span>}</td>
                                        <td><i className={`bi bi-chevron-${openSession === h.id ? 'up' : 'down'}`} /></td>
                                    </tr>
                                    {openSession === h.id && (
                                        <tr><td colSpan={8} className="bg-body-tertiary">
                                            {h.activity.length === 0 ? <small className="text-muted">Only looked around (no saves).</small> : (
                                                <ul className="sa-acts">{h.activity.map((a, i) => (
                                                    <li key={i} className={a.refused ? 'is-refused' : ''}><span className="tabular">{a.at}</span><b>{a.refused ? 'Refused' : verb(a.method)}</b><code>{a.what}</code>{a.status && a.status >= 400 && !a.refused && <span className="sa-pill is-soon">{a.status}</span>}</li>
                                                ))}</ul>
                                            )}
                                        </td></tr>
                                    )}
                                </Fragment>
                            ))}</tbody>
                        </table></div>
                    )}
                </SectionCard>
            )}

            <SectionCard icon="list-ul" title="Latest activity" actions={<div className="sa-chips">
                <button type="button" className={`sa-chip ${tab === 'logins' ? 'active' : ''}`} onClick={() => setTab('logins')}>Sign-ins</button>
                <button type="button" className={`sa-chip ${tab === 'visitors' ? 'active' : ''}`} onClick={() => setTab('visitors')}>Visitors</button>
            </div>}>
                <div className="table-responsive">
                    {tab === 'logins' ? (
                        <table className="table table-sm align-middle mb-0">
                            <thead><tr><th>When</th><th>Who</th><th>Browser</th><th>IP</th><th>Browser id</th></tr></thead>
                            <tbody>{logins.map((l, i) => (
                                <tr key={i}><td className="text-nowrap">{l.at}</td><td>{l.name ?? '—'} <small className="text-muted d-block">{l.email}</small>{l.demo && <span className="sa-pill is-soon">Demo</span>}</td>
                                    <td>{l.browser} · {l.os} <i className={`bi bi-${DEVICE_ICON[l.device ?? ''] ?? 'question-circle'}`} /></td><td><small>{l.ip}</small></td><td><code>{l.visitor}</code></td></tr>
                            ))}{logins.length === 0 && <tr><td colSpan={5} className="text-muted text-center py-3">No sign-ins yet.</td></tr>}</tbody>
                        </table>
                    ) : (
                        <table className="table table-sm align-middle mb-0">
                            <thead><tr><th>Day</th><th>Browser id</th><th>Account</th><th className="text-end">Pages</th><th>Came to</th><th>Browser</th><th>IP</th></tr></thead>
                            <tbody>{visitors.map((v, i) => (
                                <tr key={i}><td className="text-nowrap">{v.day} <small className="text-muted">{v.last}</small></td><td><code>{v.visitor}</code></td>
                                    <td>{v.email ?? <span className="text-muted">Guest</span>}{v.demo && <span className="sa-pill is-soon ms-1">Demo</span>}</td><td className="text-end tabular">{v.hits}</td>
                                    <td><small>{v.path}</small>{v.referrer && <small className="text-muted d-block text-truncate" style={{ maxWidth: 220 }}>from {v.referrer}</small>}</td>
                                    <td>{v.browser} · {v.os}</td><td><small>{v.ip}</small></td></tr>
                            ))}{visitors.length === 0 && <tr><td colSpan={7} className="text-muted text-center py-3">No visits yet.</td></tr>}</tbody>
                        </table>
                    )}
                </div>
            </SectionCard>
        </>
    );
}
