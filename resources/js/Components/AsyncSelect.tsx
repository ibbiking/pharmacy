import axios from 'axios';
import type { ComponentType } from 'react';
import Creatable from 'react-select/creatable';
import { AsyncPaginate, withAsyncPaginate, type LoadOptions } from 'react-select-async-paginate';

export interface Option {
    value: number;
    label: string;
    meta?: Record<string, unknown>;
}

interface Props {
    /** Lookup entity, e.g. "taxes", "suppliers" (see LookupController). */
    entity: string;
    /** Custom endpoint with the same { data, next_page } contract (default: /admin/lookup/{entity}). */
    url?: string;
    value: Option | null;
    onChange: (option: Option | null) => void;
    placeholder?: string;
    inputId?: string;
    isClearable?: boolean;
    isDisabled?: boolean;
    invalid?: boolean;
}

/**
 * Searchable dropdown that pages through GET /admin/lookup/{entity} (20 rows at a time).
 * Global rule: dropdowns never load a full table.
 */
export default function AsyncSelect({ entity, url, value, onChange, placeholder = 'Search…', inputId, isClearable = true, isDisabled, invalid }: Props) {
    const loadOptions: LoadOptions<Option, never, { page: number }> = async (search, _loaded, additional) => {
        const page = additional?.page ?? 1;
        const { data } = await axios.get(url ?? route('lookup', { entity }), { params: { q: search, page } });
        return {
            options: data.data.map((row: { id: number; label: string; meta?: Record<string, unknown> }) => ({ value: row.id, label: row.label, meta: row.meta })),
            hasMore: data.next_page !== null,
            additional: { page: page + 1 },
        };
    };

    return (
        <AsyncPaginate<Option, never, { page: number }>
            inputId={inputId}
            classNamePrefix="app-select"
            className={invalid ? 'is-invalid' : undefined}
            value={value}
            onChange={(v) => onChange((v as Option) ?? null)}
            loadOptions={loadOptions}
            additional={{ page: 1 }}
            debounceTimeout={300}
            placeholder={placeholder}
            isClearable={isClearable}
            isDisabled={isDisabled}
            cacheUniqs={[entity]}
        />
    );
}

// The library's generics don't model multi + creatable together; props are checked by PickerProps instead.
type Loose = ComponentType<Record<string, unknown>>;
const CreatablePaginate = withAsyncPaginate(Creatable) as unknown as Loose;
const Paginate = AsyncPaginate as unknown as Loose;

/** True for an option the user typed (not yet saved): the server creates it, or reuses an existing one with that name. */
export const isNewOption = (o: Option) => o.value < 0;
/** Picker value → request payload: {id} for an existing entry, {name} for a typed one. */
export const toRef = (o: Option) => (isNewOption(o) ? { name: o.label } : { id: o.value });

interface PickerProps {
    entity: string;
    value: Option[];
    onChange: (options: Option[]) => void;
    /** Allow several values (strengths, formulas); otherwise value holds 0 or 1 option. */
    isMulti?: boolean;
    /** Allow typing a new name ("Add “…”"). */
    creatable?: boolean;
    placeholder?: string;
    inputId?: string;
    invalid?: boolean;
    isDisabled?: boolean;
}

/**
 * Paged lookup like AsyncSelect, plus multi-select and "add a new name" (used by forms that may create master data).
 * Still never loads a full table.
 */
export function LookupPicker({ entity, value, onChange, isMulti, creatable, placeholder = 'Search…', inputId, invalid, isDisabled }: PickerProps) {
    const loadOptions: LoadOptions<Option, never, { page: number }> = async (search, _loaded, additional) => {
        const page = additional?.page ?? 1;
        const { data } = await axios.get(route('lookup', { entity }), { params: { q: search, page } });
        return {
            options: data.data.map((row: { id: number; label: string }) => ({ value: row.id, label: row.label })),
            hasMore: data.next_page !== null,
            additional: { page: page + 1 },
        };
    };
    const common = {
        inputId, classNamePrefix: 'app-select', className: invalid ? 'is-invalid' : undefined, loadOptions, additional: { page: 1 },
        debounceTimeout: 300, placeholder, isClearable: true, isDisabled, cacheUniqs: [entity], isMulti: isMulti as false,
        value: isMulti ? value : (value[0] ?? null),
        onChange: (v: unknown) => onChange(Array.isArray(v) ? (v as Option[]) : v ? [v as Option] : []),
    };

    if (!creatable) return <Paginate {...common} />;

    return (
        <CreatablePaginate
            {...common}
            formatCreateLabel={(input: string) => `Add “${input.trim()}”`}
            isValidNewOption={(input: string, _v: unknown, options: readonly Option[]) =>
                input.trim().length > 0 && input.trim().length <= 100 && !options.some((o) => o.label.toLowerCase() === input.trim().toLowerCase())}
            getNewOptionData={(input: string) => ({ value: -Date.now(), label: input.trim() }) as Option}
        />
    );
}
