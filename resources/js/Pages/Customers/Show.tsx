import { Head } from '@inertiajs/react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import EmptyState from '@/Components/EmptyState';
import { BackLink, DrawerLink, useEmbedded } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';
import { statusPill } from '@/Components/Documents/status';

interface Doc { id: number; number: string; type: string; label: string; date: string | null; status: string; total: number; balance: number }
interface Props {
    customer: { id: number; name: string; company: string | null; email: string | null; phone: string | null; address: string | null; tax_number: string | null; payment_terms_days: number; credit_limit: number | null; opening_balance: number; notes: string | null; balance: number };
    documents: Paginated<Doc> | null;
    canStatement: boolean;
    currency: string;
}

/** Customer profile (opened in the side drawer from the list). */
export default function CustomerShow({ customer: c, documents, canStatement, currency }: Props) {
    const { embedded, visit } = useEmbedded();
    const money = (v: number) => formatMoney(v, currency);
    return (
        <>
            <Head title={c.name} />
            <BackLink href={route('customers.index')}>All customers</BackLink>
            <PageHeader icon="person-vcard" title={c.name} description={[c.company, c.phone, c.email].filter(Boolean).join(' · ') || undefined}
                actions={canStatement && <a href={route('customers.statement', c.id)} target="_blank" rel="noreferrer" className="btn btn-light"><i className="bi bi-file-earmark-text me-1" />Statement</a>} />
            <div className="row g-3 mb-3">
                <div className="col-sm-4"><KpiCard icon="wallet2" label="Balance (they owe you)" value={money(c.balance)} accent={c.balance > 0 ? 'var(--app-warning)' : 'var(--app-success)'} /></div>
                <div className="col-sm-4"><KpiCard icon="calendar2-week" label="Payment terms" value={c.payment_terms_days ? `Net ${c.payment_terms_days} days` : 'On receipt'} accent="var(--chart-1)" /></div>
                <div className="col-sm-4"><KpiCard icon="speedometer" label="Credit limit" value={c.credit_limit ? money(c.credit_limit) : 'None'} accent="var(--chart-6)" /></div>
            </div>
            <div className="row g-3">
                <div className="col-xl-8">
                    <SectionCard icon="files" title="Documents" flush>
                        {!documents ? <EmptyState icon="briefcase" title="B2B Sales is off" hint="Switch on B2B Sales to send quotations and invoices to this customer." /> :
                            documents.data.length === 0 ? <EmptyState icon="files" title="No documents yet" /> : (
                                <div className="table-responsive"><table className="table dt-table align-middle mb-0">
                                    <thead><tr><th>Document</th><th>Date</th><th>Status</th><th className="text-end">Total</th><th className="text-end">Open</th></tr></thead>
                                    <tbody>{documents.data.map((d) => (
                                        <tr key={d.id}>
                                            <td><DrawerLink href={route('selling.docs.show', d.id)} className="fw-semibold" aria-label={`${d.label} ${d.number}`}>{d.number}</DrawerLink><small className="d-block text-muted">{d.label}</small></td>
                                            <td className="text-muted text-nowrap">{d.date}</td>
                                            <td>{statusPill(d.status)}</td>
                                            <td className="text-end tabular">{money(d.total)}</td>
                                            <td className="text-end tabular">{d.balance > 0 ? money(d.balance) : '—'}</td>
                                        </tr>
                                    ))}</tbody>
                                </table></div>
                            )}
                        {documents && (documents.prev_page_url || documents.next_page_url) && (
                            <div className="d-flex justify-content-end gap-2 p-2">
                                {documents.prev_page_url && <button type="button" className="btn btn-sm btn-light" onClick={() => embedded ? visit(documents.prev_page_url!) : window.location.assign(documents.prev_page_url!)}>Newer</button>}
                                {documents.next_page_url && <button type="button" className="btn btn-sm btn-light" onClick={() => embedded ? visit(documents.next_page_url!) : window.location.assign(documents.next_page_url!)}>Older</button>}
                            </div>
                        )}
                    </SectionCard>
                </div>
                <div className="col-xl-4">
                    <SectionCard icon="info-circle" title="Details" accent="var(--chart-4)">
                        <dl className="row small mb-0">
                            <dt className="col-5 text-muted fw-normal">Address</dt><dd className="col-7">{c.address ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Tax number</dt><dd className="col-7">{c.tax_number ?? '—'}</dd>
                            <dt className="col-5 text-muted fw-normal">Opening balance</dt><dd className="col-7">{money(c.opening_balance)}</dd>
                        </dl>
                        {c.notes && <p className="small text-muted mt-2 mb-0">{c.notes}</p>}
                    </SectionCard>
                </div>
            </div>
        </>
    );
}
