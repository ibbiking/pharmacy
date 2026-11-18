import axios from 'axios';
import { AsyncPaginate, type LoadOptions } from 'react-select-async-paginate';
import { floatingMenu } from '@/Components/AsyncSelect';

export type LotMode = 'fefo' | 'fifo' | 'random';
export const LOT_MODE_SHORT: Record<LotMode, string> = { fefo: 'FEFO', fifo: 'FIFO', random: 'Any batch' };
const LOT_MODE_HINT: Record<LotMode, string> = { fefo: 'earliest expiry first', fifo: 'oldest purchase first', random: 'most stock first' };

/** What a cart row sells from: a rule ("Auto") or the exact batch the cashier picked / scanned. */
export type BatchChoice = { kind: 'mode'; mode: LotMode | null } | { kind: 'lot'; id: number; label: string };
interface Opt { value: string; label: string; choice: BatchChoice; hint?: string }

/**
 * One compact control per cart row: "Auto" follows the batch rule (business-wide, or the item's own setting), the
 * cashier may switch the rule for this row, or pick the batch printed on the pack in the customer's hand; the sale
 * then takes stock from that exact batch first. Batches page 20 at a time from the server (this till's warehouse only).
 */
export default function BatchPicker({ productId, value, ruleMode, onChange, label }: {
    productId: number; value: BatchChoice; ruleMode: LotMode; onChange: (c: BatchChoice) => void; label: string;
}) {
    const modes: Opt[] = [
        { value: 'mode:', label: `Auto · ${LOT_MODE_SHORT[ruleMode]}`, hint: 'item / business rule', choice: { kind: 'mode', mode: null } },
        ...(['fefo', 'fifo', 'random'] as LotMode[]).filter((m) => m !== ruleMode)
            .map((m) => ({ value: `mode:${m}`, label: `${LOT_MODE_SHORT[m]}`, hint: LOT_MODE_HINT[m], choice: { kind: 'mode', mode: m } as BatchChoice })),
    ];
    const loadOptions: LoadOptions<Opt, { label: string; options: Opt[] }, { page: number }> = async (search, _loaded, additional) => {
        const page = additional?.page ?? 1;
        const { data } = await axios.get(route('products.pos.batches', productId), { params: { q: search, page } });
        const lots: Opt[] = data.data.map((r: { id: number; label: string }) => ({ value: `lot:${r.id}`, label: r.label, choice: { kind: 'lot', id: r.id, label: r.label } }));
        const groups = page === 1
            ? [...(search.trim() ? [] : [{ label: 'Sell by rule', options: modes }]), { label: lots.length ? 'Batch on the pack' : 'No batch matches', options: lots }]
            : [{ label: 'Batch on the pack', options: lots }];
        return { options: groups, hasMore: data.next_page !== null, additional: { page: page + 1 } };
    };
    // Nothing chosen = empty field (the item / business rule applies silently); only a picked batch or rule shows.
    const current: Opt | null = value.kind === 'lot'
        ? { value: `lot:${value.id}`, label: value.label, choice: value }
        : value.mode === null ? null
        : modes.find((m) => m.choice.kind === 'mode' && m.choice.mode === value.mode) ?? { value: `mode:${value.mode}`, label: LOT_MODE_SHORT[value.mode], choice: value };

    return (
        <AsyncPaginate<Opt, { label: string; options: Opt[] }, { page: number }>
            classNamePrefix="app-select" className={`batch-picker ${value.kind === 'lot' ? 'is-pinned' : ''}`} aria-label={label}
            value={current} onChange={(o) => onChange(o ? o.choice : { kind: 'mode', mode: null })} isClearable loadOptions={loadOptions} additional={{ page: 1 }}
            debounceTimeout={250} isSearchable placeholder="Select batch" cacheUniqs={[productId]}
            formatOptionLabel={(o, meta) => meta.context === 'menu' && o.hint ? <span>{o.label} <small className="text-muted">· {o.hint}</small></span> : o.label}
            {...floatingMenu}
            styles={{ ...floatingMenu.styles, menu: (base: object) => ({ ...base, minWidth: 320, right: 0, left: 'auto' }) }}
        />
    );
}
