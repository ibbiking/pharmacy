import { Head, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import { BackLink, CancelLink } from '@/Components/Drawer';
import { toast } from '@/Components/Toaster';
import { formatMoney, formatNumber } from '@/lib/format';

interface Line { id: number; product: string | null; unit: string | null; ordered: number; received: number; left: number; unit_cost: number; sale_price: number; has_expiry?: boolean }
interface Props { po: { id: number; number: string; supplier: string | null; warehouse: Option | null }; lines: Line[]; inventoryPro: boolean; expiryRequired: boolean; today: string; currency: string }
interface Row { quantity: string; unit_sale_price: string; batch_no: string; expiry_date: string }

/** Receive goods against a purchase order: each line becomes a batch (goods received note). */
export default function Receive({ po, lines, inventoryPro, expiryRequired, today, currency }: Props) {
    const { errors } = usePage().props;
    const [date, setDate] = useState(today);
    const [warehouse, setWarehouse] = useState<Option | null>(po.warehouse);
    const [rows, setRows] = useState<Record<number, Row>>(() => Object.fromEntries(lines.map((l) => [l.id, { quantity: String(l.left), unit_sale_price: String(l.sale_price), batch_no: '', expiry_date: '' }])));
    const [busy, setBusy] = useState(false);
    const set = (id: number, patch: Partial<Row>) => setRows((r) => ({ ...r, [id]: { ...r[id]!, ...patch } }));
    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.post(route('buying.docs.receive', po.id), {
            date, warehouse_id: warehouse?.value ?? null,
            lines: lines.map((l) => ({ line_id: l.id, ...rows[l.id], expiry_date: rows[l.id]!.expiry_date || null, batch_no: rows[l.id]!.batch_no || null })),
        }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: () => toast('error', 'Some lines need attention.') });
    };
    const err = (i: number, f: string) => errors[`lines.${i}.${f}`];

    return (
        <>
            <Head title={`Receive ${po.number}`} />
            <BackLink href={route('buying.docs.show', po.id)}>{po.number}</BackLink>
            <PageHeader icon="box-arrow-in-down" title={`Receive goods · ${po.number}`} help="b2b-purchasing" description={`${po.supplier ?? ''} · each line becomes a batch you can sell at once. Set 0 for what didn’t arrive.`} />
            {errors.lines && <div className="alert alert-danger">{errors.lines}</div>}
            {errors.document && <div className="alert alert-danger">{errors.document}</div>}
            <form onSubmit={submit} noValidate>
                <SectionCard icon="calendar3" title="Receipt" accent="var(--app-success)">
                    <div className="row g-3">
                        <div className="col-md-4"><label className="form-label small fw-semibold" htmlFor="rc-date">Received on</label><input id="rc-date" type="date" max={today} className="form-control" value={date} onChange={(e) => setDate(e.target.value)} /></div>
                        {inventoryPro && <div className="col-md-5"><label className="form-label small fw-semibold" htmlFor="rc-wh">Warehouse</label><AsyncSelect entity="warehouses" inputId="rc-wh" value={warehouse} onChange={setWarehouse} isClearable={false} /></div>}
                    </div>
                </SectionCard>
                <SectionCard icon="list-ul" title="Lines" accent="var(--chart-1)">
                    <div className="d-grid gap-2">{lines.map((l, i) => (
                        <div key={l.id} className="line-row">
                            <div className="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong>{l.product}</strong><small className="text-muted">Ordered {formatNumber(l.ordered, 2)} {l.unit} · received {formatNumber(l.received, 2)} · cost {formatMoney(l.unit_cost, currency)} / {l.unit}</small></div>
                            <div className="row g-2">
                                <div className="col-6 col-md-3"><label className="form-label small mb-1" htmlFor={`rc-q-${l.id}`}>Quantity ({l.unit})</label><input id={`rc-q-${l.id}`} type="number" min="0" max={l.left} step="any" className={`form-control ${err(i, 'quantity') ? 'is-invalid' : ''}`} value={rows[l.id]!.quantity} onChange={(e) => set(l.id, { quantity: e.target.value })} />{err(i, 'quantity') && <div className="invalid-feedback d-block">{err(i, 'quantity')}</div>}</div>
                                <div className="col-6 col-md-3"><label className="form-label small mb-1" htmlFor={`rc-s-${l.id}`}>Sale price / {l.unit}</label><input id={`rc-s-${l.id}`} type="number" min="0" step="0.01" className={`form-control ${err(i, 'unit_sale_price') ? 'is-invalid' : ''}`} value={rows[l.id]!.unit_sale_price} onChange={(e) => set(l.id, { unit_sale_price: e.target.value })} />{err(i, 'unit_sale_price') && <div className="invalid-feedback d-block">{err(i, 'unit_sale_price')}</div>}</div>
                                <div className="col-6 col-md-3"><label className="form-label small mb-1" htmlFor={`rc-b-${l.id}`}>Batch <span className="text-muted">Optional</span></label><input id={`rc-b-${l.id}`} className="form-control" maxLength={100} value={rows[l.id]!.batch_no} onChange={(e) => set(l.id, { batch_no: e.target.value })} /></div>
                                <div className="col-6 col-md-3"><label className="form-label small mb-1" htmlFor={`rc-e-${l.id}`}>Expiry {!(l.has_expiry ?? expiryRequired) && <span className="text-muted">Optional</span>}</label><input id={`rc-e-${l.id}`} type="date" className={`form-control ${err(i, 'expiry_date') ? 'is-invalid' : ''}`} value={rows[l.id]!.expiry_date} onChange={(e) => set(l.id, { expiry_date: e.target.value })} />{err(i, 'expiry_date') && <div className="invalid-feedback d-block">{err(i, 'expiry_date')}</div>}</div>
                            </div>
                        </div>
                    ))}</div>
                </SectionCard>
                <div className="d-flex justify-content-end gap-2"><CancelLink href={route('buying.docs.show', po.id)} /><button type="submit" className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Receive into stock'}</button></div>
            </form>
        </>
    );
}
