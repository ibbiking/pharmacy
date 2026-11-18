import axios from 'axios';
import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import { formatNumber } from '@/lib/format';

interface Qty { category: string; quantity: number | string }
interface Summary { product_name: string; summary: { available: Qty[]; expired: Qty[] }; warehouses: { id: number; name: string; is_pos: boolean; available: Qty[] }[] }

const fmt = (v: number | string) => { const n = Number(v); return Number.isFinite(n) ? formatNumber(n, Number.isInteger(n) ? 0 : 2) : String(v); };

/** Stock summary of one product: available and expired per packaging unit, and per warehouse (Inventory Pro). */
export default function StockSummaryModal({ productId, onClose }: { productId: number | null; onClose: () => void }) {
    const [data, setData] = useState<Summary | null>(null);
    const [error, setError] = useState(false);

    useEffect(() => {
        if (!productId) return;
        setData(null); setError(false);
        axios.get<Summary>(route('products.stock-summary', productId)).then(({ data: d }) => setData(d)).catch(() => setError(true));
    }, [productId]);

    return (
        <Modal open={productId !== null} onClose={onClose} icon="boxes" width={520} title={data ? `Stock summary · ${data.product_name}` : 'Stock summary'}>
            {error ? <div className="alert alert-danger mb-0">Couldn’t load the stock summary.</div>
                : !data ? <div className="text-center text-muted py-4"><span className="spinner-border spinner-border-sm me-2" />Loading…</div>
                : <>
                    {data.summary.available.length === 0 && data.summary.expired.length === 0 && <div className="alert alert-warning mb-0">No stock in any unit.</div>}
                    {data.summary.available.length > 0 && <Table title="Available (all warehouses)" tone="success" rows={data.summary.available} />}
                    {data.summary.expired.length > 0 && <Table title="Expired" tone="danger" rows={data.summary.expired} />}
                    {data.warehouses.length > 0 && <>
                        <h6 className="fw-semibold mt-3 mb-2">By warehouse</h6>
                        <table className="table table-sm align-middle mb-0">
                            <thead><tr><th>Warehouse</th><th>Available</th></tr></thead>
                            <tbody>{data.warehouses.map((w) => (
                                <tr key={w.id}><td>{w.name}{w.is_pos && <span className="badge bg-primary-subtle text-primary ms-2">POS sells from here</span>}</td>
                                    <td className="tabular">{w.available.map((a) => `${fmt(a.quantity)} ${a.category}`).join(' · ')}</td></tr>
                            ))}</tbody>
                        </table>
                    </>}
                </>}
        </Modal>
    );
}

function Table({ title, tone, rows }: { title: string; tone: 'success' | 'danger'; rows: Qty[] }) {
    return <>
        <h6 className={`fw-semibold mb-2 text-${tone}`}>{title}</h6>
        <table className="table table-sm mb-3"><thead><tr><th>Unit</th><th className="text-end">Quantity</th></tr></thead>
            <tbody>{rows.map((r) => <tr key={r.category}><td>{r.category}</td><td className={`text-end fw-semibold tabular text-${tone}`}>{fmt(r.quantity)}</td></tr>)}</tbody></table>
    </>;
}
