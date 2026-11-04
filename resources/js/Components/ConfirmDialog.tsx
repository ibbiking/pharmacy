import Modal from './Modal';

interface Props {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    danger?: boolean;
    busy?: boolean;
    onConfirm: () => void;
    onClose: () => void;
}

export default function ConfirmDialog({ open, title, message, confirmLabel = 'Confirm', danger, busy, onConfirm, onClose }: Props) {
    return (
        <Modal
            open={open}
            title={title}
            icon={danger ? 'exclamation-triangle' : 'question-circle'}
            onClose={onClose}
            width={440}
            footer={
                <>
                    <button type="button" className="btn btn-light" onClick={onClose} disabled={busy}>
                        Cancel
                    </button>
                    <button type="button" className={`btn ${danger ? 'btn-danger' : 'btn-primary'}`} onClick={onConfirm} disabled={busy} autoFocus>
                        {busy && <span className="spinner-border spinner-border-sm me-2" />}
                        {confirmLabel}
                    </button>
                </>
            }
        >
            <p className="mb-0">{message}</p>
        </Modal>
    );
}
