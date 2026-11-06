import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';

interface Row { id: string; name: string; disk: string; size: number; created_at: string; downloadable: boolean }
interface Props { backups: Paginated<Row>; summary: { count: number; total_size: number; latest: string | null; disks: string[] } }

const size = (b: number) => (b >= 1 << 30 ? `${(b / (1 << 30)).toFixed(1)} GB` : b >= 1 << 20 ? `${(b / (1 << 20)).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

export default function Backups({ backups, summary }: Props) {
    const [running, setRunning] = useState(false);
    const [deleting, setDeleting] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);

    const run = () => router.post(route('backup.store'), {}, { preserveScroll: true, onStart: () => setRunning(true), onFinish: () => setRunning(false) });
    const remove = () => {
        if (!deleting) return;
        router.delete(route('backup.destroy', { file_name: deleting.name, disk: deleting.disk }), {
            preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setDeleting(null); },
        });
    };

    return (
        <>
            <Head title="Backups" />
            <PageHeader
                icon="cloud-arrow-down"
                title="Backups"
                description="Whole-platform backups: database and uploaded files of every business. Keep the downloads somewhere safe."
                actions={
                    <button type="button" className="btn btn-primary" onClick={run} disabled={running}>
                        {running ? <><span className="spinner-border spinner-border-sm me-2" />Backing up…</> : <><i className="bi bi-plus-lg me-1" />Back up now</>}
                    </button>
                }
            />
            <StatStrip
                stats={[
                    { key: 'count', icon: 'archive', label: 'Backups', value: summary.count, accent: 'var(--chart-1)' },
                    { key: 'size', icon: 'hdd', label: 'Space used', value: size(summary.total_size), accent: 'var(--chart-2)' },
                    { key: 'latest', icon: 'clock-history', label: 'Latest', value: summary.latest ?? 'Never', accent: summary.latest ? 'var(--chart-3)' : 'var(--app-danger)' },
                ]}
            />
            <SectionCard icon="list-ul" title="Backup files" subtitle={`Stored on: ${summary.disks.join(', ') || 'no disk configured'}`} flush>
                {backups.data.length ? (
                    <div className="table-responsive">
                        <table className="table align-middle mb-0">
                            <thead><tr><th>File</th><th>Created</th><th className="text-end">Size</th><th /></tr></thead>
                            <tbody>
                                {backups.data.map((b) => (
                                    <tr key={b.id}>
                                        <td className="fw-semibold text-break">{b.name}<small className="text-muted d-block">{b.disk}</small></td>
                                        <td className="text-muted">{b.created_at}</td>
                                        <td className="text-end tabular">{size(b.size)}</td>
                                        <td className="text-end text-nowrap">
                                            {b.downloadable && (
                                                <a className="btn btn-icon btn-soft me-1" href={route('backup.download', { file_name: b.name, disk: b.disk })} title="Download" aria-label={`Download ${b.name}`}><i className="bi bi-download" /></a>
                                            )}
                                            <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setDeleting(b)} title="Delete" aria-label={`Delete ${b.name}`}><i className="bi bi-trash" /></button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <EmptyState icon="cloud-arrow-down" title="No backups yet" hint="Run the first one with “Back up now”." />
                )}
                {backups.last_page > 1 && (
                    <div className="d-flex gap-2 justify-content-end p-3">
                        {backups.prev_page_url && <Link className="btn btn-soft btn-sm" href={backups.prev_page_url}>Previous</Link>}
                        {backups.next_page_url && <Link className="btn btn-soft btn-sm" href={backups.next_page_url}>Next</Link>}
                    </div>
                )}
            </SectionCard>
            <ConfirmDialog open={deleting !== null} title="Delete this backup?" message={deleting ? `“${deleting.name}” will be removed for good.` : ''} confirmLabel="Delete" danger busy={busy} onConfirm={remove} onClose={() => setDeleting(null)} />
        </>
    );
}
