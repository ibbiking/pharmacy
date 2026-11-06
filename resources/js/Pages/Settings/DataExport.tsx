import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import EmptyState from '@/Components/EmptyState';

interface Row {
    id: number; status: 'pending' | 'running' | 'done' | 'failed'; requested_by: string | null; created_at: string | null;
    size: number | null; tables: number | null; rows: number | null; error: string | null; expires_at: string | null; downloadable: boolean;
}

const size = (b: number | null) => (b === null ? '—' : b >= 1 << 20 ? `${(b / (1 << 20)).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);
const STATUS: Record<Row['status'], [string, string]> = {
    pending: ['Waiting', 'var(--app-info)'], running: ['Preparing…', 'var(--app-info)'], done: ['Ready', 'var(--app-success)'], failed: ['Failed', 'var(--app-danger)'],
};

export default function DataExport({ exports, tableCount, busy }: { exports: Paginated<Row>; tableCount: number; busy: boolean }) {
    const [starting, setStarting] = useState(false);

    // While an export is being prepared, check again every 5 seconds.
    useEffect(() => {
        if (!busy) return;
        const t = window.setInterval(() => router.reload({ only: ['exports', 'busy'] }), 5000);
        return () => window.clearInterval(t);
    }, [busy]);

    const start = () => router.post(route('settings.export.store'), {}, { preserveScroll: true, onStart: () => setStarting(true), onFinish: () => setStarting(false) });

    return (
        <>
            <Head title="Data export" />
            <PageHeader
                icon="file-earmark-zip"
                title="Data export"
                description="Download everything this business has stored: products, purchases, sales, returns, settings and team."
                actions={
                    <button type="button" className="btn btn-primary" onClick={start} disabled={starting || busy}>
                        {busy ? <><span className="spinner-border spinner-border-sm me-2" />Preparing…</> : <><i className="bi bi-download me-1" />Export my data</>}
                    </button>
                }
            />
            <div className="row g-4">
                <div className="col-lg-8">
                    <SectionCard icon="clock-history" title="Your exports" subtitle="Each file is kept for 7 days" flush>
                        {exports.data.length ? (
                            <div className="table-responsive">
                                <table className="table align-middle mb-0">
                                    <thead><tr><th>Requested</th><th>Status</th><th className="text-end">Size</th><th /></tr></thead>
                                    <tbody>
                                        {exports.data.map((e) => (
                                            <tr key={e.id}>
                                                <td>{e.created_at}<small className="text-muted d-block">{e.requested_by ?? ''}</small></td>
                                                <td>
                                                    <span className="pill" style={{ ['--accent' as string]: STATUS[e.status][1] }}>{STATUS[e.status][0]}</span>
                                                    {e.status === 'done' && <small className="text-muted d-block">{e.tables} files · {e.rows?.toLocaleString()} rows{e.expires_at ? ` · until ${e.expires_at}` : ''}</small>}
                                                    {e.error && <small className="d-block" style={{ color: 'var(--app-danger)' }}>{e.error}</small>}
                                                </td>
                                                <td className="text-end tabular">{size(e.size)}</td>
                                                <td className="text-end">
                                                    {e.downloadable ? (
                                                        <a className="btn btn-sm btn-soft" href={route('settings.export.download', e.id)}><i className="bi bi-download me-1" />Download</a>
                                                    ) : e.status === 'done' ? <span className="text-muted small">Expired</span> : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <EmptyState icon="file-earmark-zip" title="No exports yet" hint="“Export my data” prepares a zip file in the background." />
                        )}
                        {exports.last_page > 1 && (
                            <div className="d-flex gap-2 justify-content-end p-3">
                                {exports.prev_page_url && <Link className="btn btn-soft btn-sm" href={exports.prev_page_url} preserveScroll>Newer</Link>}
                                {exports.next_page_url && <Link className="btn btn-soft btn-sm" href={exports.next_page_url} preserveScroll>Older</Link>}
                            </div>
                        )}
                    </SectionCard>
                </div>
                <div className="col-lg-4">
                    <SectionCard icon="info-circle" title="What is in the file" accent="var(--chart-4)">
                        <ul className="small text-muted ps-3 mb-0">
                            <li className="mb-2">A zip with {tableCount} CSV files (open them in Excel or Google Sheets) and a README.</li>
                            <li className="mb-2">Only this business’s data, archived rows included.</li>
                            <li className="mb-2">Passwords, tokens and payment details are never included.</li>
                            <li>Only owners can create and download exports.</li>
                        </ul>
                    </SectionCard>
                </div>
            </div>
        </>
    );
}
