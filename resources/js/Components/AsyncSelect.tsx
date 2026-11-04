import axios from 'axios';
import { AsyncPaginate, type LoadOptions } from 'react-select-async-paginate';

export interface Option {
    value: number;
    label: string;
    meta?: Record<string, unknown>;
}

interface Props {
    /** Lookup entity, e.g. "taxes", "suppliers" (see LookupController). */
    entity: string;
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
export default function AsyncSelect({ entity, value, onChange, placeholder = 'Search…', inputId, isClearable = true, isDisabled, invalid }: Props) {
    const loadOptions: LoadOptions<Option, never, { page: number }> = async (search, _loaded, additional) => {
        const page = additional?.page ?? 1;
        const { data } = await axios.get(route('lookup', { entity }), { params: { q: search, page } });
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
