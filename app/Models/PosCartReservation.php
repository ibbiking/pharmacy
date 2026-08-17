<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A live snapshot of what's currently sitting in a sales-person's
 * in-progress POS cart, shared business-wide so other sales-persons see
 * that stock as unavailable before any Invoice is saved. Rows are fully
 * replaced (delete + reinsert) on every cart sync rather than diffed, so
 * this table is always an exact mirror of session('pos_cart') for whoever
 * last synced — no soft deletes, hard-delete is fine for ephemeral rows.
 */
class PosCartReservation extends Model
{
    use \App\Traits\BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'user_id',
        'product_id',
        'category_id',
        'base_stock_sale_price_id',
        'price_group',
        'base_qty',
        'quantity',
    ];

    /**
     * A cart untouched for this long is treated as abandoned: excluded from
     * availability calculations at read time (correctness never depends on
     * the cleanup command having run) and eligible for deletion by it.
     */
    const STALE_MINUTES = 30;

    protected static function activeBusinessId()
    {
        return session('impersonate_business_id') ?? session('business_id');
    }

    /**
     * A query with the BelongsToBusiness global scope deliberately removed.
     * Every method below supplies its own explicit business_id filter (or,
     * for logout, deliberately none) — relying on the implicit trait scope
     * on top of that would either be redundant or, worse, silently override
     * an intentional "no business filter" call (e.g. releaseForUser() at
     * logout is meant to clear a user's holds across every business they
     * were in; the auto-scope would quietly narrow that to just the
     * current session's business instead).
     */
    protected static function unscoped()
    {
        return static::query()->withoutGlobalScopes();
    }

    /**
     * Cross-user reservation totals for a product, shaped identically to
     * ProductController::calculateAlreadyReserved() ('per_batch'/'per_price'
     * keyed by base_stock_sale_price_id / normalized price string) so the
     * two can be summed together before being handed to
     * getAvailableStockWithPriceGrouping(). $excludeUserId's own rows are
     * left out — their own live cart is already counted via their own
     * posted/session cart, counting both would double-reserve their own
     * stuff.
     *
     * business_id is filtered explicitly here (not left to the trait's
     * global scope) because BusinessScope deliberately skips itself for an
     * unimpersonated super-admin — a reservation from one business must
     * never leak into another business's availability math regardless of
     * who's asking.
     *
     * Pass $excludeUserId = null to total reservations across EVERY user
     * (used for the display-only "true remaining stock" figure) rather
     * than netting against one particular requester — a plain
     * `where('user_id', '!=', null)` would silently match zero rows in
     * SQL, so the exclusion is only applied when an id is actually given.
     */
    public static function reservedBucketsForProduct($productId, $excludeUserId = null): array
    {
        $businessId = static::activeBusinessId();

        $rows = static::unscoped()
            ->where('business_id', $businessId)
            ->where('product_id', $productId)
            ->when($excludeUserId, fn ($query) => $query->where('user_id', '!=', $excludeUserId))
            ->where('updated_at', '>=', now()->subMinutes(self::STALE_MINUTES))
            ->get(['base_stock_sale_price_id', 'price_group', 'base_qty']);

        $reserved = ['per_batch' => [], 'per_price' => []];

        foreach ($rows as $row) {
            if (!empty($row->base_stock_sale_price_id)) {
                $key = $row->base_stock_sale_price_id;
                $reserved['per_batch'][$key] = ($reserved['per_batch'][$key] ?? 0) + (float) $row->base_qty;
                continue;
            }

            if (!empty($row->price_group)) {
                $key = $row->price_group;
                $reserved['per_price'][$key] = ($reserved['per_price'][$key] ?? 0) + (float) $row->base_qty;
            }
        }

        return $reserved;
    }

    /**
     * Display-only breakdown of who else is holding a product, for the
     * hover-detail panel / stock-summary UI. Kept separate from
     * reservedBucketsForProduct() since the shape (per-user, with names) is
     * for showing to a human, not for feeding back into the netting math.
     */
    public static function heldByOthersForProduct($productId, $excludeUserId = null)
    {
        $businessId = static::activeBusinessId();

        // Every column below is explicitly table-qualified: once joined to
        // `users`, unqualified `updated_at` (both tables have one) becomes
        // an ambiguous-column SQL error, not just a correctness risk.
        $query = static::unscoped()
            ->where('pos_cart_reservations.business_id', $businessId)
            ->where('pos_cart_reservations.product_id', $productId)
            ->where('pos_cart_reservations.updated_at', '>=', now()->subMinutes(self::STALE_MINUTES));

        if ($excludeUserId) {
            $query->where('pos_cart_reservations.user_id', '!=', $excludeUserId);
        }

        return $query
            ->join('users', 'users.id', '=', 'pos_cart_reservations.user_id')
            ->groupBy('pos_cart_reservations.user_id', 'users.name')
            ->select('users.name as user_name')
            ->selectRaw('SUM(pos_cart_reservations.base_qty) as total_base_qty')
            ->selectRaw('SUM(pos_cart_reservations.quantity) as total_quantity')
            ->get();
    }

    /**
     * Full-replace sync: makes this table an exact mirror of the given
     * user's current cart. Wrapped in a transaction — under InnoDB, other
     * connections' plain SELECTs keep seeing the old committed rows until
     * commit, avoiding a transient "fully deleted, not yet reinserted"
     * window where a concurrent check-stock call would wrongly see this
     * user's hold as released mid-replace.
     */
    public static function syncForUser(int $userId, array $cartLines): void
    {
        $businessId = static::activeBusinessId();

        DB::transaction(function () use ($userId, $cartLines, $businessId) {
            static::unscoped()
                ->where('business_id', $businessId)
                ->where('user_id', $userId)
                ->delete();

            $rows = [];
            $now = now();

            foreach ($cartLines as $line) {
                $baseQty = (float) ($line['base_qty'] ?? 0);
                if ($baseQty <= 0) {
                    continue;
                }

                $rows[] = [
                    'business_id' => $businessId,
                    'user_id' => $userId,
                    'product_id' => $line['product_id'] ?? null,
                    'category_id' => $line['category_id'] ?? null,
                    'base_stock_sale_price_id' => $line['base_stock_sale_price_id'] ?? null,
                    'price_group' => $line['price_group'] ?? null,
                    'base_qty' => $baseQty,
                    'quantity' => $line['quantity'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Every row needs a product_id to be meaningful for netting.
            $rows = array_values(array_filter($rows, fn ($row) => !empty($row['product_id'])));

            if (!empty($rows)) {
                static::unscoped()->insert($rows);
            }
        });
    }

    /**
     * Releases a user's holds. Called with an explicit $businessId from
     * saveInvoice() (release only this business's hold). Called from
     * logout with no $businessId — deleting a user's own rows across every
     * business they were in on their way out is safe cleanup of their own
     * data, not a cross-tenant read/visibility leak.
     */
    public static function releaseForUser(int $userId, $businessId = null): void
    {
        static::unscoped()
            ->where('user_id', $userId)
            ->when($businessId, fn ($query) => $query->where('business_id', $businessId))
            ->delete();
    }
}
