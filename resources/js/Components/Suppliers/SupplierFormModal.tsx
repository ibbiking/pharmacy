import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent, type ReactNode } from 'react';
import Modal from '@/Components/Modal';

export interface SupplierFields { id: number; name: string; company: string | null; phone: string | null; email: string | null; address: string | null; product: string | null; comment: string | null }

const EMPTY = { name: '', company: '', phone: '', email: '', address: '', product: '', comment: '' };

/** Add / edit a supplier (used on the list and on the profile). */
export default function SupplierFormModal({ supplier, onClose }: { supplier: SupplierFields | 'new' | null; onClose: () => void }) {
    const isNew = supplier === 'new';
    const form = useForm({ ...EMPTY });

    useEffect(() => {
        form.clearErrors();
        if (supplier === 'new') form.setData({ ...EMPTY });
        else if (supplier) form.setData({
            name: supplier.name, company: supplier.company ?? '', phone: supplier.phone ?? '', email: supplier.email ?? '',
            address: supplier.address ?? '', product: supplier.product ?? '', comment: supplier.comment ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [supplier]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        if (isNew) form.post(route('suppliers.store'), opts);
        else if (supplier) form.put(route('suppliers.update', supplier.id), opts);
    };

    const field = (key: keyof typeof EMPTY, label: string, props: { type?: string; placeholder?: string; icon?: string; max?: number; hint?: string } = {}): ReactNode => (
        <div>
            <label className="form-label fw-semibold mb-1" htmlFor={`sup-${key}`}>{label}{props.hint && <small className="text-muted fw-normal ms-1">{props.hint}</small>}</label>
            <div className="input-icon-group">
                {props.icon && <i className={`bi bi-${props.icon} lead-icon`} />}
                <input id={`sup-${key}`} type={props.type ?? 'text'} className={`form-control ${form.errors[key] ? 'is-invalid' : ''}`} style={props.icon ? undefined : { paddingLeft: 12 }}
                    value={form.data[key]} maxLength={props.max ?? 191} placeholder={props.placeholder} onChange={(e) => form.setData(key, e.target.value)} />
            </div>
            {form.errors[key] && <div className="invalid-feedback d-block">{form.errors[key]}</div>}
        </div>
    );

    return (
        <Modal open={supplier !== null} onClose={onClose} icon="truck" width={640} title={isNew ? 'Add supplier' : `Edit ${typeof supplier === 'object' && supplier ? supplier.name : 'supplier'}`}
            footer={<>
                <button type="button" className="btn btn-light" onClick={onClose}>Cancel</button>
                <button type="submit" form="sup-form" className="btn btn-primary" disabled={form.processing}>{form.processing && <span className="spinner-border spinner-border-sm me-2" />}{isNew ? 'Add supplier' : 'Save'}</button>
            </>}>
            <form id="sup-form" onSubmit={submit} noValidate className="row g-3">
                <div className="col-md-6">{field('name', 'Name', { icon: 'person-badge', placeholder: 'e.g. Ali Traders' })}</div>
                <div className="col-md-6">{field('company', 'Company', { icon: 'building', hint: 'Optional', max: 200 })}</div>
                <div className="col-md-6">{field('phone', 'Phone', { icon: 'telephone', type: 'tel', placeholder: '+92 300 0000000', max: 30 })}</div>
                <div className="col-md-6">{field('email', 'Email', { icon: 'envelope', type: 'email', hint: 'For purchase orders later' })}</div>
                <div className="col-12">{field('address', 'Address', { icon: 'geo-alt', max: 500 })}</div>
                <div className="col-12">{field('product', 'What they supply', { icon: 'box-seam', hint: 'Optional', placeholder: 'e.g. Antibiotics, syrups', max: 255 })}</div>
                <div className="col-12">
                    <label className="form-label fw-semibold mb-1" htmlFor="sup-comment">Notes <small className="text-muted fw-normal">Optional</small></label>
                    <textarea id="sup-comment" rows={2} className={`form-control ${form.errors.comment ? 'is-invalid' : ''}`} maxLength={1000} value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} />
                    {form.errors.comment && <div className="invalid-feedback">{form.errors.comment}</div>}
                </div>
            </form>
        </Modal>
    );
}
