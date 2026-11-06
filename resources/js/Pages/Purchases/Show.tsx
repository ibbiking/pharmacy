import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import EmptyState from '@/Components/EmptyState';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { formatMoney, formatNumber } from '@/lib/format';
import { expiryAccent } from '@/Pages/Products/Index';
import { RemainingBar, type PurchaseRow } from './Index';

interface TaxRow { name: string; rate: number; unit: number; total: number }
interface Sale { invoice_id: number; invoice_no: string; date: string; qty: number; returned: number; unit_price: number }
interface Props {
    purchase: PurchaseRow & {
        manufacturing_date: string | null; unit_cost: number; paid_unit_cost: number; extra_percent: number; unit_sale: number;
        total_cost: number; total_sale: number; purchase_tax_total: number; sale_tax_total: number; margin_percent: number | null;
        purchase_taxes: TaxRow[]; sale_taxes: TaxRow[]; supplier_id: number | null; product_id: number;
    };
    sales: Paginated<Sale>;
    currency: string;
    archiveBlocker: string | null;
    canManage: boolean;
}

export default function PurchaseShow({ purchase: p, sales, currency, archiveBlocker, canManage }: Props) {
    const [archiving, setArchiving] = useState(false);
    const [busy, setBusy] = useState(false);
    const money = (v: number) => formatMoney(v, currency);
    const line = (label: string, value: string, hint?: string, strong = false) => (
        <div className="d-flex justify-content-between gap-3 py-2 border-bottom">
            <span className="text-muted">{label}{hint && <small className="d-block">{hint}</small>}</span>
            <span className={`tabular text-end ${strong ? 'fw-bold' : ''}`}>{value}</span>
        </div>
    );
    const taxes = (title: string, rows: TaxRow[], empty: string) => (
        <div className="mt-3">
            <div className="fw-semibold small mb-1">{title}</div>
            {rows.length === 0 ? <div className="small text-muted">{empty}</div> : rows.map((t) => (
                <div key={t.name} className="d-flex justify-content-between small py-1"><span>{t.name} <span className="text-muted">({formatNumber(t.rate, 2)}%)</span></span><span className="tabular">{money(t.unit)} / unit · {money(t.total)}</span></div>
            ))}
        </div>
    );

    return (
        <>
            <Head title={`Purchase: ${p.product}`} />
            <div className="mb-2"><Link href={route('purchases.index')} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />All purchases</Link></div>
            <PageHeader icon="box-seam" title={p.product}
                description={[`${formatNumber(p.quantity, 2)} ${p.unit ?? ''}`, p.batch && `batch ${p.batch}`, p.supplier, p.date].filter(Boolean).join(' · ')}
                actions={canManage && !p.archived && <>
                    <button type="button" className="btn btn-light" onClick={() => setArchiving(true)}><i className="bi bi-archive me-1" />Archive</button>
                    <a href={route('purchases.edit', p.id)} className="btn btn-primary"><i className="bi bi-pencil me-1" />Edit</a>
                </>} />

            {p.archived && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--chart-8)' }}><i className="bi bi-archive" /><div><strong>Archived</strong><p>This batch was removed from stock. It stays here for history.</p></div></div>}

            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="box-arrow-in-down" label="Received" value={<>{formatNumber(p.base_quantity, 0)} <small className="fs-6 text-muted">{p.base_unit}</small></>} accent="var(--chart-1)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="cart-check" label="Sold (net of returns)" value={<>{formatNumber(p.sold, 0)} <small className="fs-6 text-muted">{p.base_unit}</small></>} accent="var(--chart-3)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="stack" label="Left" value={<RemainingBar row={p} />} accent="var(--app-success)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="calendar2-week" label="Expiry" accent={expiryAccent(p.expiry_days)}
                    value={<>{p.expiry ?? '—'}{p.expiry_days !== null && <small className="d-block fs-6 text-muted fw-normal">{p.expiry_days <= 0 ? 'Expired: not sellable' : `${p.expiry_days} days left`}</small>}</>} /></div>
            </div>

            <div className="row g-3">
                <div className="col-xl-5">
                    <SectionCard icon="receipt" title="Cost & price" subtitle="Per unit, as bought" accent="var(--chart-1)">
                        {line(`Unit cost (${p.unit ?? 'unit'})`, money(p.unit_cost))}
                        {line('Paid per unit', money(p.paid_unit_cost), p.extra_percent > 0 ? `Includes ${formatNumber(p.extra_percent, 2)}% sales tax paid (GST, printed on receipts)` : undefined)}
                        {line('Sale price per unit', money(p.unit_sale), p.margin_percent !== null ? `Margin over what you paid: ${formatNumber(p.margin_percent, 1)}%` : undefined)}
                        {line('Total paid', money(p.total), undefined, true)}
                        {taxes('Purchase taxes', p.purchase_taxes, 'None')}
                        {taxes('Sale taxes (charged when sold)', p.sale_taxes, 'None: sold at the sale price')}
                    </SectionCard>
                    <SectionCard icon="info-circle" title="Details" accent="var(--chart-6)">
                        <dl className="row small mb-0">
                            <dt className="col-5 text-muted fw-normal">Supplier</dt><dd className="col-7">{p.supplier_id ? <Link href={route('suppliers.show', p.supplier_id)}>{p.supplier}</Link> : '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Bill no</dt><dd className="col-7">{p.invoice_no ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Batch</dt><dd className="col-7">{p.batch ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Manufactured</dt><dd className="col-7">{p.manufacturing_date ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Product</dt><dd className="col-7"><Link href={route('products.show', p.product_id)}>{p.product}</Link></dd>
                        </dl>
                    </SectionCard>
                </div>
                <div className="col-xl-7">
                    <SectionCard icon="receipt-cutoff" title="Sold from this batch" subtitle="Invoices that took stock from it" flush>
                        {sales.data.length === 0 ? <EmptyState icon="cart" title="Nothing sold from this batch yet" /> : (
                            <div className="table-responsive">
                                <table className="table dt-table align-middle mb-0">
                                    <thead><tr><th>Invoice</th><th>Date</th><th className="text-end">Qty ({p.base_unit})</th><th className="text-end">Price / {p.base_unit}</th></tr></thead>
                                    <tbody>
                                        {sales.data.map((s, i) => (
                                            <tr key={`${s.invoice_id}-${i}`}>
                                                <td><a href={route('invoices.show', s.invoice_no)} className="fw-semibold">{s.invoice_no}</a></td>
                                                <td className="text-muted">{s.date}</td>
                                                <td className="text-end tabular">{formatNumber(s.qty, 2)}{s.returned > 0 && <small className="d-block text-warning">{formatNumber(s.returned, 2)} returned</small>}</td>
                                                <td className="text-end tabular">{money(s.unit_price)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {sales.last_page > 1 && (
                            <div className="d-flex justify-content-end gap-2 p-3">
                                {sales.prev_page_url && <Link href={sales.prev_page_url} preserveScroll only={['sales']} className="btn btn-sm btn-light">Newer</Link>}
                                {sales.next_page_url && <Link href={sales.next_page_url} preserveScroll only={['sales']} className="btn btn-sm btn-light">Older</Link>}
                            </div>
                        )}
                    </SectionCard>
                </div>
            </div>

            <ConfirmDialog open={archiving} title={archiveBlocker ? 'Can’t archive this purchase' : 'Archive purchase?'} danger={!archiveBlocker} busy={busy}
                confirmLabel={archiveBlocker ? 'OK' : 'Archive'}
                message={archiveBlocker ?? `Its ${formatNumber(p.remaining, 0)} ${p.base_unit ?? ''} left will be removed from stock. Use this for a purchase entered by mistake.`}
                onConfirm={() => archiveBlocker ? setArchiving(false) : router.delete(route('purchases.destroy', p.id), { onStart: () => setBusy(true), onFinish: () => { setBusy(false); setArchiving(false); } })}
                onClose={() => setArchiving(false)} />
        </>
    );
}
