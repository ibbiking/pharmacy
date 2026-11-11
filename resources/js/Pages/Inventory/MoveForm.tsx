import { Head, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import LineEditor, { blankLine, type EditorLine } from '@/Components/LineEditor';
import { BackLink, CancelLink } from '@/Components/Drawer';
import { toast } from '@/Components/Toaster';

interface Props { kind: 'transfer' | 'adjustment'; warehouse: Option | null; reasons: { value: string; label: string }[]; today: string }

/** New stock transfer or adjustment (Inventory Pro). The server checks stock and moves batches FEFO. */
export default function MoveForm({ kind, warehouse, reasons, today }: Props) {
    const { errors, activeModules } = usePage().props;
    const transfer = kind === 'transfer';
    const multi = activeModules.includes('inventory_pro');
    const [from, setFrom] = useState<Option | null>(warehouse);
    const [to, setTo] = useState<Option | null>(null);
    const [direction, setDirection] = useState<'decrease' | 'increase'>('decrease');
    const [reason, setReason] = useState(reasons[0]?.value ?? 'damaged');
    const [date, setDate] = useState(today);
    const [note, setNote] = useState('');
    const [lines, setLines] = useState<EditorLine[]>([blankLine()]);
    const [busy, setBusy] = useState(false);
    const base = transfer ? 'transfers' : 'adjustments';

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const payload = {
            date, note: note || null, lines: lines.map((l) => ({ product_id: l.product?.value ?? null, category_id: l.category_id, quantity: l.quantity })),
            ...(transfer ? { from_warehouse_id: from?.value ?? null, to_warehouse_id: to?.value ?? null } : { warehouse_id: from?.value ?? null, direction, reason }),
        };
        router.post(route(`${base}.store`), payload, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: () => toast('error', 'Some lines need attention: see the red messages.') });
    };
    const fb = (k: string) => errors[k] && <div className="invalid-feedback d-block">{errors[k]}</div>;

    return (
        <>
            <Head title={transfer ? 'New transfer' : 'New adjustment'} />
            <BackLink href={route(`${base}.index`)}>{transfer ? 'All transfers' : 'All adjustments'}</BackLink>
            <PageHeader icon={transfer ? 'arrow-left-right' : 'sliders2-vertical'} title={transfer ? 'New stock transfer' : 'New stock adjustment'} help="inventory-pro"
                description={transfer ? 'Earliest-expiry batches move first; they keep their cost and expiry.' : 'Removing takes the earliest-expiry batches first. Adding tops up the latest batch of the item.'} />
            <form onSubmit={submit} noValidate>
                <SectionCard icon="info-circle" title="Details" accent="var(--chart-6)">
                    <div className="row g-3">
                        {(transfer || multi) && (
                            <div className="col-md-4">
                                <label className="form-label fw-semibold small" htmlFor="mv-from">{transfer ? 'From warehouse' : 'Warehouse'}</label>
                                <AsyncSelect entity="warehouses" inputId="mv-from" value={from} onChange={setFrom} isClearable={false} invalid={!!(errors.from_warehouse_id || errors.warehouse_id)} />
                                {fb(transfer ? 'from_warehouse_id' : 'warehouse_id')}
                            </div>
                        )}
                        {transfer ? (
                            <div className="col-md-4">
                                <label className="form-label fw-semibold small" htmlFor="mv-to">To warehouse</label>
                                <AsyncSelect entity="warehouses" inputId="mv-to" value={to} onChange={setTo} isClearable={false} invalid={!!errors.to_warehouse_id} />
                                {fb('to_warehouse_id')}
                            </div>
                        ) : (
                            <>
                                <div className="col-md-4">
                                    <span className="form-label fw-semibold small d-block">Direction</span>
                                    <div className="btn-group w-100" role="group" aria-label="Direction">
                                        <button type="button" className={`btn ${direction === 'decrease' ? 'btn-danger' : 'btn-light'}`} onClick={() => setDirection('decrease')}><i className="bi bi-dash-circle me-1" />Remove</button>
                                        <button type="button" className={`btn ${direction === 'increase' ? 'btn-success' : 'btn-light'}`} onClick={() => setDirection('increase')}><i className="bi bi-plus-circle me-1" />Add</button>
                                    </div>
                                </div>
                                <div className="col-md-4">
                                    <label className="form-label fw-semibold small" htmlFor="mv-reason">Reason</label>
                                    <select id="mv-reason" className="form-select" value={reason} onChange={(e) => setReason(e.target.value)}>{reasons.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}</select>
                                </div>
                            </>
                        )}
                        <div className="col-md-4">
                            <label className="form-label fw-semibold small" htmlFor="mv-date">Date</label>
                            <input id="mv-date" type="date" className={`form-control ${errors.date ? 'is-invalid' : ''}`} max={today} value={date} onChange={(e) => setDate(e.target.value)} />
                            {fb('date')}
                        </div>
                        <div className={transfer ? 'col-md-8' : 'col-12'}>
                            <label className="form-label fw-semibold small" htmlFor="mv-note">Note <span className="text-muted fw-normal">Optional</span></label>
                            <input id="mv-note" className="form-control" maxLength={500} value={note} onChange={(e) => setNote(e.target.value)} />
                        </div>
                    </div>
                </SectionCard>
                <SectionCard icon="list-ul" title="Items" accent="var(--chart-1)">
                    {errors.lines && <div className="alert alert-danger py-2">{errors.lines}</div>}
                    <LineEditor lines={lines} onChange={setLines} mode="qty" errors={errors} warehouseId={from?.value ?? null} />
                </SectionCard>
                <div className="d-flex justify-content-end gap-2">
                    <CancelLink href={route(`${base}.index`)} />
                    <button type="submit" className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : transfer ? 'Move stock' : 'Save adjustment'}</button>
                </div>
            </form>
        </>
    );
}
