import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { toast } from '@/Components/Toaster';
import { formatMoney, formatNumber } from '@/lib/format';

type Status = 'available' | 'mine' | 'queued' | 'suggested' | 'approved' | 'pending' | 'rejected' | 'archived' | null;
interface Row { id: number; name: string; company: string | null; type: string | null; strengths: string[]; formulas: string[]; status: string; product_id: number | null; queued: boolean; editable: boolean }
interface Batch { id: number; status: string; items: number; imported: number; skipped: number; failed: number; error: string | null; at: string }
interface Details extends Omit<Row, 'queued' | 'editable'> { barcode: string | null; description: string | null; chain: { unit: string; quantity: number; purchase_price: number | null; sale_price: number | null }[] }
interface Props {
    rows: Paginated<Row>;
    filters: TableFilters & { status?: Status };
    stats: Record<string, number>;
    batches: Batch[];
    admin: boolean;
    canImport: boolean;
}

const STATUS_ACCENT: Record<string, string> = { approved: 'var(--app-success)', pending: 'var(--app-warning)', rejected: 'var(--app-danger)', archived: 'var(--chart-8)' };

export default function GenericIndex({ rows, filters, stats, batches, admin, canImport }: Props) {
    const { wording, business } = usePage().props;
    const plural = wording.generic_catalog ?? 'Generic catalogue';
    const products = (wording.products ?? 'Products').toLowerCase();
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [details, setDetails] = useState<Details | null>(null);
    const [loadingId, setLoadingId] = useState<number | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [confirmAll, setConfirmAll] = useState(false);
    const [busy, setBusy] = useState(false);
    const active = batches.some((b) => b.status === 'pending' || b.status === 'processing');

    // While imports run, refresh the list, counters and batch status every 4 seconds.
    useEffect(() => {
        if (!active) return;
        const t = window.setInterval(() => router.reload({ only: ['rows', 'stats', 'batches'] }), 4000);
        return () => window.clearInterval(t);
    }, [active]);

    const filter = (status: Status) => router.get(route('generic_products.index'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });
    const selectable = (r: Row) => (admin ? r.status === 'pending' : canImport && r.status === 'approved' && !r.product_id && !r.queued);
    const review = (action: 'approve' | 'reject', ids: number[]) => router.post(route('generic_products.review'), { action, ids }, {
        preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: () => setSelected(new Set()),
    });
    const toggle = (id: number) => setSelected((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
    const pageIds = rows.data.filter(selectable).map((r) => r.id);
    const allOnPage = pageIds.length > 0 && pageIds.every((id) => selected.has(id));

    const openDetails = (id: number) => {
        setLoadingId(id);
        axios.get<Details>(route('generic_products.details', id))
            .then(({ data }) => setDetails(data))
            .catch(() => toast('error', 'Couldn’t load the details. Please try again.'))
            .finally(() => setLoadingId(null));
    };
    const importOne = (id: number) => router.post(route('generic_products.import'), { product_id: id }, {
        preserveScroll: true, only: ['rows', 'stats', 'flash', 'batches'], onStart: () => setBusyId(id), onFinish: () => setBusyId(null),
        onSuccess: () => setDetails((d) => (d && d.id === id ? null : d)),
    });
    const importSelected = () => router.post(route('generic_products.bulkImport'), { product_ids: [...selected] }, {
        preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: () => setSelected(new Set()),
        onError: (e) => toast('error', Object.values(e)[0] ?? 'Couldn’t queue the import.'),
    });

    const columns: Column<Row>[] = [
        ...(canImport || admin ? [{
            key: 'select', label: '', render: (r: Row) => selectable(r)
                ? <input type="checkbox" className="form-check-input" aria-label={`Select ${r.name}`} checked={selected.has(r.id)} onChange={() => toggle(r.id)} />
                : null,
        }] : []),
        {
            key: 'product_name', label: wording.product ?? 'Product', sortable: true, render: (r) => (
                <button type="button" className="btn btn-link p-0 text-start text-reset text-decoration-none" onClick={() => openDetails(r.id)}>
                    <span className="fw-semibold d-block text-break">{r.name}{r.strengths.length > 0 && <small className="text-muted fw-normal ms-1">{r.strengths.join(', ')}</small>}
                        {admin && <span className="pill ms-2" style={{ ['--accent' as string]: STATUS_ACCENT[r.status] ?? 'var(--chart-8)' }}>{r.status}</span>}</span>
                    <small className="text-muted d-block text-truncate" style={{ maxWidth: 420 }}>{[r.formulas.join(' + '), r.company].filter(Boolean).join(' · ') || '—'}</small>
                </button>
            ),
        },
        { key: 'type', label: wording.product_type ?? 'Type', render: (r) => <span className="text-muted">{r.type ?? '—'}</span> },
        {
            key: 'actions', label: '', align: 'end', render: (r) => (
                <div className="d-inline-flex gap-1 align-items-center">
                    <button type="button" className="btn btn-icon btn-soft" title="Details" aria-label={`Details of ${r.name}`} onClick={() => openDetails(r.id)} disabled={loadingId === r.id}>
                        {loadingId === r.id ? <span className="spinner-border spinner-border-sm" /> : <i className="bi bi-eye" />}
                    </button>
                    {r.product_id ? <Link href={route('products.show', r.product_id)} className="btn btn-sm btn-light"><i className="bi bi-check2 me-1 text-success" />In your list</Link>
                        : r.queued ? <span className="btn btn-sm btn-light disabled"><span className="spinner-grow spinner-grow-sm me-1" />Importing</span>
                            : canImport && r.status === 'approved' && <button type="button" className="btn btn-sm btn-primary" onClick={() => importOne(r.id)} disabled={busyId === r.id}>{busyId === r.id ? <span className="spinner-border spinner-border-sm" /> : <><i className="bi bi-download me-1" />Import</>}</button>}
                    {admin && r.status === 'pending' && <>
                        <button type="button" className="btn btn-icon btn-soft text-success" title="Approve" aria-label={`Approve ${r.name}`} onClick={() => review('approve', [r.id])} disabled={busy}><i className="bi bi-check-lg" /></button>
                        <button type="button" className="btn btn-icon btn-soft text-danger" title="Reject" aria-label={`Reject ${r.name}`} onClick={() => review('reject', [r.id])} disabled={busy}><i className="bi bi-x-lg" /></button>
                    </>}
                    {!admin && r.status !== 'approved' && <span className="pill" style={{ ['--accent' as string]: STATUS_ACCENT[r.status] ?? 'var(--chart-8)' }}>{r.status === 'pending' ? 'Waiting for review' : r.status}</span>}
                    {r.editable && <Link href={route('generic_products.edit', r.id)} className="btn btn-icon btn-soft" title="Edit" aria-label={`Edit ${r.name}`}><i className="bi bi-pencil" /></Link>}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={plural} />
            <PageHeader icon="collection" title={plural} help="products"
                description={admin ? 'The shared catalogue every business imports from. Review suggestions before they appear for businesses.'
                    : `A ready-made list of ${products} with their packaging and prices. Import what you sell instead of typing it in.`}
                actions={admin ? <>
                    <button type="button" className="btn btn-light" onClick={() => filter('pending')}><i className="bi bi-inbox me-1" />Review{stats.pending ? ` (${stats.pending})` : ''}</button>
                    <Link href={route('generic_products.suggest')} className="btn btn-primary"><i className="bi bi-plus-lg me-1" />Add to catalogue</Link>
                </> : <>
                    <Link href={route('generic_products.suggest')} className="btn btn-light"><i className="bi bi-lightbulb me-1" />Suggest a product</Link>
                    {canImport && <button type="button" className="btn btn-primary" onClick={() => setConfirmAll(true)} disabled={!stats.available}><i className="bi bi-cloud-download me-1" />Import everything{stats.available ? ` (${formatNumber(stats.available, 0)})` : ''}</button>}
                </>} />

            {!admin && batches.length > 0 && (
                <div className={`callout mb-3`} style={{ ['--accent' as string]: active ? 'var(--app-info)' : batches[0]!.failed ? 'var(--app-danger)' : 'var(--app-success)' }}>
                    <i className={`bi ${active ? 'bi-arrow-repeat' : batches[0]!.failed ? 'bi-exclamation-triangle' : 'bi-check2-circle'}`} />
                    <div className="flex-grow-1">
                        <strong>{active ? 'Importing…' : 'Latest imports'}</strong>
                        <ul className="list-unstyled small mb-0 mt-1">
                            {batches.slice(0, 3).map((b) => (
                                <li key={b.id}>
                                    {b.at}: {formatNumber(b.items, 0)} item(s) · {b.status === 'completed' ? <>
                                        {b.imported} imported{b.skipped > 0 && `, ${b.skipped} already in your list`}{b.failed > 0 && <span className="text-danger">, {b.failed} failed</span>}
                                    </> : b.status === 'processing' ? 'in progress' : 'waiting to start'}
                                    {b.error && <div className="text-danger text-truncate" title={b.error}>Last error: {b.error}</div>}
                                </li>
                            ))}
                        </ul>
                        {active && <small className="text-muted">Imported items get their packaging and prices from the catalogue; ones without packaging arrive as drafts.</small>}
                    </div>
                </div>
            )}

            <StatStrip stats={admin ? [
                { key: 'all', icon: 'collection', label: 'All entries', value: stats.total ?? 0, accent: 'var(--chart-1)', onClick: () => filter(null), selected: !filters.status },
                { key: 'approved', icon: 'check2-circle', label: 'Approved', value: stats.approved ?? 0, accent: 'var(--app-success)', onClick: () => filter('approved'), selected: filters.status === 'approved' },
                { key: 'pending', icon: 'hourglass', label: 'Waiting for review', value: stats.pending ?? 0, accent: 'var(--app-warning)', onClick: () => filter('pending'), selected: filters.status === 'pending' },
                { key: 'rejected', icon: 'x-octagon', label: 'Rejected', value: stats.rejected ?? 0, accent: 'var(--app-danger)', onClick: () => filter('rejected'), selected: filters.status === 'rejected' },
                { key: 'archived', icon: 'archive', label: 'Archived', value: stats.archived ?? 0, accent: 'var(--chart-8)', onClick: () => filter('archived'), selected: filters.status === 'archived' },
            ] : [
                { key: 'all', icon: 'collection', label: 'In the catalogue', value: stats.total ?? 0, accent: 'var(--chart-1)', onClick: () => filter(null), selected: !filters.status },
                { key: 'available', icon: 'cloud-download', label: 'Not in your list yet', value: stats.available ?? 0, accent: 'var(--chart-3)', onClick: () => filter('available'), selected: filters.status === 'available' },
                { key: 'mine', icon: 'check2-circle', label: 'Already in your list', value: stats.mine ?? 0, accent: 'var(--app-success)', onClick: () => filter('mine'), selected: filters.status === 'mine' },
                { key: 'queued', icon: 'arrow-repeat', label: 'Being imported', value: stats.queued ?? 0, accent: 'var(--app-info)', onClick: () => filter('queued'), selected: filters.status === 'queued' },
                { key: 'suggested', icon: 'lightbulb', label: 'My suggestions', value: stats.suggested ?? 0, accent: 'var(--app-warning)', onClick: () => filter('suggested'), selected: filters.status === 'suggested' },
            ]} />

            <SectionCard icon="table" title={plural} subtitle={admin ? 'Click an entry for its packaging and prices' : `Search by name, ${(wording.company ?? 'manufacturer').toLowerCase()}, formula or strength`} flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search name, manufacturer, formula, strength or barcode…"
                    toolbar={(canImport || admin) && (
                        <div className="d-flex align-items-center gap-2">
                            {pageIds.length > 0 && <button type="button" className="btn btn-sm btn-light" onClick={() => setSelected((s) => { const n = new Set(s); pageIds.forEach((id) => (allOnPage ? n.delete(id) : n.add(id))); return n; })}>{allOnPage ? 'Clear this page' : 'Select this page'}</button>}
                            {admin && selected.size > 0 && <>
                                <button type="button" className="btn btn-sm btn-success" onClick={() => review('approve', [...selected])} disabled={busy}><i className="bi bi-check-lg me-1" />Approve {selected.size}</button>
                                <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => review('reject', [...selected])} disabled={busy}>Reject {selected.size}</button>
                            </>}
                            {!admin && selected.size > 0 && <button type="button" className="btn btn-sm btn-primary" onClick={importSelected} disabled={busy}>{busy ? <span className="spinner-border spinner-border-sm me-1" /> : <i className="bi bi-download me-1" />}Import {selected.size} selected</button>}
                        </div>
                    )}
                    empty={filters.q || filters.status ? undefined : <EmptyState icon="collection" title="The catalogue is empty" hint={admin ? 'Approve suggestions to fill it.' : 'Suggest a product to get it started.'} />} />
            </SectionCard>

            <Modal open={details !== null} onClose={() => setDetails(null)} icon="capsule" width={620} title={details?.name ?? ''}
                footer={details && <>
                    <button type="button" className="btn btn-light" onClick={() => setDetails(null)}>Close</button>
                    {details.product_id ? <Link href={route('products.show', details.product_id)} className="btn btn-primary">Open in your list</Link>
                        : canImport && <button type="button" className="btn btn-primary" onClick={() => importOne(details.id)} disabled={busyId === details.id}>{busyId === details.id && <span className="spinner-border spinner-border-sm me-2" />}Import</button>}
                </>}>
                {details && (
                    <div className="d-grid gap-3">
                        <dl className="row small mb-0">
                            <dt className="col-4 text-muted fw-normal">{wording.company ?? 'Manufacturer'}</dt><dd className="col-8">{details.company ?? '—'}</dd>
                            <dt className="col-4 text-muted fw-normal">{wording.product_type ?? 'Type'}</dt><dd className="col-8">{details.type ?? '—'}</dd>
                            <dt className="col-4 text-muted fw-normal">{wording.strengths ?? 'Strengths'}</dt><dd className="col-8">{details.strengths.join(', ') || '—'}</dd>
                            <dt className="col-4 text-muted fw-normal">{wording.formulas ?? 'Formulas'}</dt><dd className="col-8">{details.formulas.join(' + ') || '—'}</dd>
                            <dt className="col-4 text-muted fw-normal">Barcode</dt><dd className="col-8">{details.barcode ?? '—'}</dd>
                            {admin && <><dt className="col-4 text-muted fw-normal">Status</dt><dd className="col-8">{details.status}</dd></>}
                        </dl>
                        <div>
                            <div className="fw-semibold small mb-2">Packaging and suggested prices{business?.currency ? ` (${business.currency})` : ''}</div>
                            {details.chain.length === 0 ? <div className="small text-warning">No packaging in the catalogue: it will arrive as a draft for you to set up.</div> : (
                                <table className="table table-sm small mb-0">
                                    <thead><tr><th>Unit</th><th className="text-end">Per unit above</th><th className="text-end">Buy</th><th className="text-end">Sell</th></tr></thead>
                                    <tbody>{details.chain.map((c, i) => (
                                        <tr key={i}><td>{c.unit}</td><td className="text-end tabular">{i === 0 ? '—' : formatNumber(c.quantity, 0)}</td>
                                            <td className="text-end tabular">{c.purchase_price !== null ? formatMoney(c.purchase_price) : '—'}</td>
                                            <td className="text-end tabular">{c.sale_price !== null ? formatMoney(c.sale_price) : '—'}</td></tr>
                                    ))}</tbody>
                                </table>
                            )}
                        </div>
                        {details.description && <p className="small text-muted mb-0">{details.description}</p>}
                    </div>
                )}
            </Modal>

            <ConfirmDialog open={confirmAll} title="Import everything?" busy={busy} confirmLabel={`Import ${formatNumber(stats.available ?? 0, 0)}`}
                message={`All ${formatNumber(stats.available ?? 0, 0)} ${products} not in your list yet will be imported in the background. Ones you already have (same name, type and strength) are linked, not copied.`}
                onConfirm={() => router.post(route('generic_products.importAll'), {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setConfirmAll(false); } })}
                onClose={() => setConfirmAll(false)} />
        </>
    );
}
