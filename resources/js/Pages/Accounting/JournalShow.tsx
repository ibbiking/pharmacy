import { Head } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { BackLink, DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';
import { SOURCES } from './Journals';

interface Props { entry: { id: number; number: string | null; date: string; memo: string | null; source: string | null; manual: boolean; by: string | null; amount: number;
    lines: { account: string; account_id: number; debit: number; credit: number; description: string | null }[] }; currency: string }

export default function JournalShow({ entry: e, currency }: Props) {
    const money = (v: number) => (v ? formatMoney(v, currency) : '');
    return (
        <>
            <Head title={`Journal ${e.number ?? e.id}`} />
            <BackLink href={route('accounting.journals.index')}>Journals</BackLink>
            <PageHeader icon="journal-text" title={`Journal ${e.number ?? `#${e.id}`}`} description={[e.date, SOURCES[e.source ?? 'manual']?.[0], e.by && `by ${e.by}`].filter(Boolean).join(' · ')} />
            {e.memo && <p className="text-muted">{e.memo}</p>}
            <SectionCard icon="list-ul" title="Lines" flush>
                <table className="table dt-table align-middle mb-0">
                    <thead><tr><th>Account</th><th className="text-end">Debit</th><th className="text-end">Credit</th></tr></thead>
                    <tbody>{e.lines.map((l, i) => <tr key={i}><td><DrawerLink href={route('accounting.ledger', l.account_id)} title={l.account}>{l.account}</DrawerLink>{l.description && <small className="d-block text-muted">{l.description}</small>}</td><td className="text-end tabular">{money(l.debit)}</td><td className="text-end tabular">{money(l.credit)}</td></tr>)}</tbody>
                    <tfoot><tr className="fw-bold"><td>Total</td><td className="text-end tabular">{formatMoney(e.amount, currency)}</td><td className="text-end tabular">{formatMoney(e.amount, currency)}</td></tr></tfoot>
                </table>
            </SectionCard>
        </>
    );
}
