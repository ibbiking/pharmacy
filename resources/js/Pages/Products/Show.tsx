import { Head, Link, router, usePage } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import EmptyState from '@/Components/EmptyState';
import { formatMoney, formatNumber } from '@/lib/format';
import { expiryAccent } from './Index';

interface Level { category_id: number; name: string; quantity: number; purchase_price: number | null; sale_price: number | null; base_units: number }
interface Lot { id: number; batch: string | null; supplier: string | null; remaining: number; received: number; expiry: string | null; days: number | null; sale_price: number; date: string | null }
interface Props {
    product: {
        id: number; name: string; strengths: string[]; formulas: string[]; company: string | null; type: string | null; barcode: string | null; rack: string | null;
        description: string | null; draft: boolean; archived: boolean; stock: number; expired_stock: number; unit: string | null; next_expiry: string | null; sold_30d: number; created_at: string | null;
    };
    chain: Level[];
    lots: Lot[];
    currency: string;
    locked: string | null;
    canManage: boolean;
    canBuy: boolean;
    canPrefs: boolean;
}

export default function ProductShow({ product, chain, lots, currency, locked, canManage, canBuy, canPrefs }: Props) {
    const { wording } = usePage().props;
    const top = chain[0];
    const inTop = top && top.base_units > 1 ? product.stock / top.base_units : null;

    return (
        <>
            <Head title={product.name} />
            <div className="mb-2"><Link href={route('products.index')} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />All {(wording.products ?? 'products').toLowerCase()}</Link></div>
            <PageHeader icon="capsule" title={product.name} help="products"
                description={[product.strengths.join(', '), product.formulas.join(' + '), product.company].filter(Boolean).join(' · ') || undefined}
                actions={<>
                    {canManage && product.archived && <button type="button" className="btn btn-primary" onClick={() => router.post(route('products.restore', product.id), {}, { preserveScroll: true })}><i className="bi bi-arrow-counterclockwise me-1" />Restore</button>}
                    {canManage && !product.archived && <Link href={route('products.edit', product.id)} className="btn btn-light"><i className="bi bi-pencil me-1" />Edit details</Link>}
                    {canManage && <Link href={route('products.setup-wizard', product.id)} className="btn btn-light"><i className="bi bi-boxes me-1" />Packaging & prices</Link>}
                    {canPrefs && !product.archived && <Link href={route('products.sale-price-preferences', product.id)} className="btn btn-light"><i className="bi bi-sliders me-1" />Sale preferences</Link>}
                    {canBuy && !product.draft && !product.archived && <a href={route('purchases.create', { product_id: product.id })} className="btn btn-primary"><i className="bi bi-box-arrow-in-down me-1" />Add stock</a>}
                </>} />

            {product.archived && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--chart-6)' }}><i className="bi bi-archive" /><div><strong>Archived</strong><p>Not offered at the POS or on new purchases. Past invoices and purchases keep it.</p></div></div>
            )}
            {product.draft && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-warning)' }}><i className="bi bi-pencil-square" /><div><strong>Draft: not sellable yet</strong><p>Set up the packaging to activate it. <Link href={route('products.setup-wizard', product.id)}>Finish setup</Link></p></div></div>
            )}

            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="box-seam" label="In stock" accent={product.stock > 0 ? 'var(--app-success)' : 'var(--app-danger)'}
                    value={<>{formatNumber(product.stock, 0)} <small className="fs-6 text-muted">{product.unit}</small>{inTop !== null && product.stock > 0 && <small className="d-block fs-6 text-muted fw-normal">≈ {formatNumber(inTop, 1)} {top!.name}</small>}</>} /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="calendar2-week" label="Next expiry" value={product.next_expiry ?? '—'} accent="var(--app-warning)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="calendar-x" label="Expired (not sellable)" value={<>{formatNumber(product.expired_stock, 0)} <small className="fs-6 text-muted">{product.unit}</small></>} accent="var(--chart-5)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="graph-up-arrow" label="Sold (30 days)" value={formatMoney(product.sold_30d, currency)} accent="var(--chart-1)" /></div>
            </div>

            <div className="row g-3">
                <div className="col-xl-8">
                    <SectionCard icon="stack" title="Batches in stock" subtitle="Sold first-expiry-first (FEFO) unless set otherwise; expired batches are skipped" flush>
                        {lots.length === 0 ? (
                            <EmptyState icon="box-seam" title="No stock" hint={product.draft ? 'Finish the setup first.' : 'Record a purchase to add a batch.'}
                                action={canBuy && !product.draft && <a href={route('purchases.create', { product_id: product.id })} className="btn btn-primary btn-sm">Add stock</a>} />
                        ) : (
                            <div className="table-responsive">
                                <table className="table dt-table align-middle mb-0">
                                    <thead><tr><th>Batch</th><th>Expiry</th><th style={{ width: '28%' }}>Remaining</th><th className="text-end">Sale price / {product.unit}</th></tr></thead>
                                    <tbody>
                                        {lots.map((l) => (
                                            <tr key={l.id}>
                                                <td><span className="fw-semibold">{l.batch ?? '—'}</span><div className="small text-muted">{[l.supplier, l.date].filter(Boolean).join(' · ')}</div></td>
                                                <td>{l.expiry ? <span className="expiry-chip" style={{ ['--accent' as string]: expiryAccent(l.days) }}>{l.days !== null && l.days <= 0 ? 'Expired ' : ''}{l.expiry}{l.days !== null && l.days > 0 && l.days <= 90 ? ` · ${l.days}d` : ''}</span> : <span className="text-muted">No expiry</span>}</td>
                                                <td>
                                                    <div className="d-flex align-items-center gap-2">
                                                        <div className="lot-bar flex-grow-1" style={{ ['--accent' as string]: expiryAccent(l.days) }}><span style={{ width: `${Math.min(100, (l.remaining / Math.max(l.received, 1)) * 100)}%` }} /></div>
                                                        <span className="tabular small text-nowrap">{formatNumber(l.remaining, 0)} / {formatNumber(l.received, 0)}</span>
                                                    </div>
                                                </td>
                                                <td className="text-end tabular">{formatMoney(l.sale_price, currency)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                </div>
                <div className="col-xl-4">
                    <SectionCard icon="boxes" title="Packaging" accent="var(--chart-1)" actions={canManage && <Link href={route('products.setup-wizard', product.id)} className="btn btn-sm btn-light">{locked ? 'Prices' : 'Edit'}</Link>}>
                        {chain.length === 0 ? <EmptyState icon="boxes" title="Not set up yet" /> : (
                            <ul className="list-unstyled mb-0 d-grid gap-2">
                                {chain.map((l, i) => (
                                    <li key={l.category_id} className="d-flex align-items-center gap-2">
                                        <span className="chain-n m-0" style={{ width: 24, height: 24 }}>{i + 1}</span>
                                        <span className="flex-grow-1"><strong>{l.name}</strong>{i < chain.length - 1 && <small className="text-muted ms-1">= {formatNumber(l.base_units, 0)} {chain[chain.length - 1]!.name}</small>}</span>
                                        {l.sale_price !== null && <span className="small tabular text-muted">{formatMoney(l.sale_price, currency)}</span>}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {locked && <p className="small text-muted mt-3 mb-0"><i className="bi bi-lock me-1" />{locked} Packaging is locked.</p>}
                    </SectionCard>
                    <SectionCard icon="info-circle" title="Details" accent="var(--chart-6)">
                        <dl className="row small mb-0">
                            <dt className="col-5 text-muted fw-normal">{wording.product_type ?? 'Type'}</dt><dd className="col-7">{product.type ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">{wording.company ?? 'Company'}</dt><dd className="col-7">{product.company ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Barcode</dt><dd className="col-7">{product.barcode ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Rack</dt><dd className="col-7">{product.rack ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Added</dt><dd className="col-7">{product.created_at ?? '—'}</dd>
                        </dl>
                        {product.description && <p className="small text-muted mt-2 mb-0">{product.description}</p>}
                    </SectionCard>
                </div>
            </div>
        </>
    );
}
