import { Head } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { BackLink } from '@/Components/Drawer';
import { formatMoney, formatNumber } from '@/lib/format';

interface Props {
    kind: 'transfer' | 'adjustment';
    move: { id: number; number: string; date: string; note: string | null; by: string | null; from: string | null; to: string | null; warehouse: string | null; direction: 'increase' | 'decrease' | null; reason: string | null; value: number;
        lines: { product: string | null; unit: string | null; qty: number; value: number }[] };
    currency: string;
}

export default function MoveShow({ kind, move, currency }: Props) {
    const transfer = kind === 'transfer';
    return (
        <>
            <Head title={move.number} />
            <BackLink href={route(transfer ? 'transfers.index' : 'adjustments.index')}>{transfer ? 'All transfers' : 'All adjustments'}</BackLink>
            <PageHeader icon={transfer ? 'arrow-left-right' : 'sliders2-vertical'} title={`${transfer ? 'Transfer' : 'Adjustment'} ${move.number}`}
                description={[move.date, transfer ? `${move.from} → ${move.to}` : move.reason, move.warehouse, move.by && `by ${move.by}`].filter(Boolean).join(' · ')} />
            {!transfer && <div className="callout mb-3" style={{ ['--accent' as string]: move.direction === 'increase' ? 'var(--app-success)' : 'var(--app-danger)' }}><i className={`bi bi-${move.direction === 'increase' ? 'plus' : 'dash'}-circle`} /><div><strong>{move.direction === 'increase' ? 'Stock added' : 'Stock removed'}: {formatMoney(move.value, currency)} at cost</strong>{move.note && <p>{move.note}</p>}</div></div>}
            {transfer && move.note && <p className="text-muted">{move.note}</p>}
            <SectionCard icon="list-ul" title="Items" flush>
                <div className="table-responsive"><table className="table dt-table align-middle mb-0">
                    <thead><tr><th>Item</th><th className="text-end">Quantity</th>{!transfer && <th className="text-end">Value (cost)</th>}</tr></thead>
                    <tbody>{move.lines.map((l, i) => <tr key={i}><td className="fw-semibold">{l.product}</td><td className="text-end tabular">{formatNumber(l.qty, 2)} {l.unit}</td>{!transfer && <td className="text-end tabular">{formatMoney(l.value, currency)}</td>}</tr>)}</tbody>
                </table></div>
            </SectionCard>
        </>
    );
}
