import { Head, router } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import EmptyState from '@/Components/EmptyState';
import { BackLink, DrawerLink, useEmbedded } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';
import type { Paginated } from '@/types';

interface Row { id: number; date: string; number: string | null; memo: string | null; debit: number; credit: number; balance: number; source: string | null; entry_id: number }
interface Props { account: { id: number; code: string; name: string; type: string }; opening: number; closing: number; rows: Paginated<Row>; filters: { from: string; to: string }; currency: string }

/** One account's ledger with a running balance. */
export default function Ledger({ account, opening, closing, rows, filters, currency }: Props) {
    const { embedded, visit } = useEmbedded();
    const money = (v: number) => formatMoney(v, currency);
    const go = (patch: Record<string, unknown>) => {
        const url = route('accounting.ledger', { id: account.id, ...filters, ...patch });
        embedded ? visit(url) : router.get(url, {}, { preserveState: true, replace: true });
    };
    return (
        <>
            <Head title={`Ledger · ${account.name}`} />
            <BackLink href={route('accounting.accounts.index')}>Chart of accounts</BackLink>
            <PageHeader icon="journal-bookmark" title={`${account.code} · ${account.name}`} help="accounting" description="Every line posted to this account, with the balance after each one." />
            <div className="d-flex flex-wrap gap-2 align-items-center mb-3">
                <label className="small fw-semibold" htmlFor="lg-from">From</label><input id="lg-from" type="date" className="form-control form-control-sm" style={{ width: 160 }} value={filters.from} onChange={(e) => go({ from: e.target.value, page: 1 })} />
                <label className="small fw-semibold" htmlFor="lg-to">To</label><input id="lg-to" type="date" className="form-control form-control-sm" style={{ width: 160 }} value={filters.to} onChange={(e) => go({ to: e.target.value, page: 1 })} />
            </div>
            <div className="row g-3 mb-3">
                <div className="col-6"><KpiCard icon="skip-start" label="Opening balance" value={money(opening)} accent="var(--chart-8)" /></div>
                <div className="col-6"><KpiCard icon="skip-end" label="Closing balance" value={money(closing)} accent="var(--chart-1)" /></div>
            </div>
            <SectionCard icon="table" title="Lines" flush>
                {rows.data.length === 0 ? <EmptyState icon="journal" title="Nothing posted in this period" /> : (
                    <div className="table-responsive"><table className="table dt-table align-middle mb-0">
                        <thead><tr><th>Date</th><th>Journal</th><th>Memo</th><th className="text-end">Debit</th><th className="text-end">Credit</th><th className="text-end">Balance</th></tr></thead>
                        <tbody>{rows.data.map((r) => (
                            <tr key={r.id}><td className="text-muted text-nowrap">{r.date}</td><td><DrawerLink href={route('accounting.journals.show', r.entry_id)} title={`Journal ${r.number ?? r.entry_id}`}>{r.number ?? `#${r.entry_id}`}</DrawerLink></td>
                                <td className="text-truncate" style={{ maxWidth: 320 }}>{r.memo}</td><td className="text-end tabular">{r.debit ? money(r.debit) : ''}</td><td className="text-end tabular">{r.credit ? money(r.credit) : ''}</td><td className="text-end tabular fw-semibold">{money(r.balance)}</td></tr>
                        ))}</tbody>
                    </table></div>
                )}
                {(rows.prev_page_url || rows.next_page_url) && <div className="d-flex justify-content-end gap-2 p-2">
                    {rows.prev_page_url && <button type="button" className="btn btn-sm btn-light" onClick={() => go({ page: rows.current_page - 1 })}>Previous</button>}
                    {rows.next_page_url && <button type="button" className="btn btn-sm btn-light" onClick={() => go({ page: rows.current_page + 1 })}>Next</button>}
                </div>}
            </SectionCard>
        </>
    );
}
