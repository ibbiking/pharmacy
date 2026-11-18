import axios from 'axios';
import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';

interface Row {
    category: string; active_purchase_price: string; active_purchase_tax: string; active_sale_price: string; active_sale_tax: string;
    static_purchase: string; static_sale: string; stock_purchase: string; stock_purchase_tax: string; stock_sale: string; stock_sale_tax: string;
}
interface Batch { date: string; batch_no: string; remaining_stock: number | string; category_name: string; prices: string; purchase_prices: string; is_expired: boolean; is_zero_qty: boolean }
interface Summary { product_name: string; active_preference: string; tax_included: string; summary: Row[]; batches: Batch[] }

/**
 * Price summary of one product (restored from the Blade products list): which sale price preference is active and
 * whether tax is included, the active / static / last-purchase prices per unit, and every batch's prices.
 */
export default function PriceSummaryModal({ productId, onClose }: { productId: number | null; onClose: () => void }) {
    const [data, setData] = useState<Summary | null>(null);
    const [error, setError] = useState(false);

    useEffect(() => {
        if (!productId) return;
        setData(null); setError(false);
        axios.get<Summary>(route('products.price-summary', productId)).then(({ data: d }) => setData(d)).catch(() => setError(true));
    }, [productId]);

    return (
        <Modal open={productId !== null} onClose={onClose} icon="cash-coin" width={1040} title={data ? `Price summary · ${data.product_name}` : 'Price summary'}>
            {error ? <div className="alert alert-danger mb-0">Couldn’t load the price summary.</div>
                : !data ? <div className="text-center text-muted py-4"><span className="spinner-border spinner-border-sm me-2" />Loading…</div>
                : <>
                    <div className="d-flex flex-wrap gap-2 mb-3">
                        <span className="pd-chip pd-chip--primary"><i className="bi bi-sliders me-1" />Active sale preference: <strong>{data.active_preference}</strong></span>
                        <span className={`pd-chip ${data.tax_included === 'Yes' ? 'pd-chip--ok' : ''}`}>Tax included: <strong>{data.tax_included}</strong></span>
                    </div>
                    {data.summary.length === 0 ? <p className="text-muted">No packaging / prices set up yet.</p> : (
                        <div className="table-responsive mb-3">
                            <table className="table table-sm table-bordered align-middle text-center mb-0 price-summary">
                                <thead>
                                    <tr><th rowSpan={2} className="align-middle">Unit</th><th colSpan={4} className="ps-active">Active price (what the POS uses)</th><th colSpan={2} className="ps-static">Static price</th><th colSpan={4} className="ps-stock">Last purchase price</th></tr>
                                    <tr>{['Purchase', 'Pur. tax', 'Sale', 'Sale tax'].map((h) => <th key={`a${h}`} className="ps-active">{h}</th>)}
                                        {['Purchase', 'Sale'].map((h) => <th key={`s${h}`} className="ps-static">{h}</th>)}
                                        {['Purchase', 'Pur. tax', 'Sale', 'Sale tax'].map((h) => <th key={`p${h}`} className="ps-stock">{h}</th>)}</tr>
                                </thead>
                                <tbody className="tabular">{data.summary.map((r) => (
                                    <tr key={r.category}><th scope="row">{r.category}</th>
                                        <td>{r.active_purchase_price}</td><td>{r.active_purchase_tax}</td><td className="fw-bold">{r.active_sale_price}</td><td>{r.active_sale_tax}</td>
                                        <td>{r.static_purchase}</td><td>{r.static_sale}</td>
                                        <td>{r.stock_purchase}</td><td>{r.stock_purchase_tax}</td><td>{r.stock_sale}</td><td>{r.stock_sale_tax}</td></tr>
                                ))}</tbody>
                            </table>
                        </div>
                    )}
                    {data.batches.length > 0 && <>
                        <h6 className="fw-semibold">Batches (stock-wise prices)</h6>
                        <div className="table-responsive">
                            <table className="table table-sm align-middle mb-1">
                                <thead><tr><th>Date</th><th>Batch / bill</th><th className="text-end">Left (base units)</th><th>Unit</th><th>Sale prices</th><th>Purchase prices</th></tr></thead>
                                <tbody>{data.batches.map((b, i) => (
                                    <tr key={i} className={b.is_expired ? 'table-danger' : b.is_zero_qty ? 'table-warning' : ''}>
                                        <td className="text-nowrap">{b.date}</td><td>{b.batch_no}{b.is_expired && <span className="badge bg-danger ms-2">expired</span>}{!b.is_expired && b.is_zero_qty && <span className="badge bg-warning text-dark ms-2">sold out</span>}</td>
                                        <td className="text-end tabular">{b.remaining_stock}</td><td>{b.category_name}</td><td className="small">{b.prices}</td><td className="small">{b.purchase_prices}</td>
                                    </tr>
                                ))}</tbody>
                            </table>
                        </div>
                        <small className="text-muted">Red = expired, yellow = sold out.</small>
                    </>}
                </>}
        </Modal>
    );
}
