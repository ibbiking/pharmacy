{{-- Shared color scheme for purchase-entry forms. Used by:
       - resources/views/admin/purchases/create.blade.php
       - resources/views/admin/purchases/edit.blade.php
       - resources/views/admin/purchases/create_multiple.blade.php
     Any purchase/multi-purchase screen should @include this partial and use
     the .purchase-section-card--* classes below rather than inventing new
     colors — that's the whole point of sharing it. See CLAUDE.md
     ("Purchase form color scheme") for the convention this encodes:
       blue    = Medicine/product identification
       teal    = Pricing & stock details
       grey    = Purchase Tax Information
       green   = Sale Information & Sale Tax --}}
<style>
    .purchase-section-card {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        margin-bottom: 1.5rem;
        background: #ffffff;
    }

    .purchase-section-card .card-header {
        padding: 12px 20px;
    }

    .purchase-section-card--medicine {
        border: 1px solid #007bff;
    }
    .purchase-section-card--medicine .card-header {
        background: linear-gradient(135deg, #0056b3, #007bff);
        color: #fff;
    }

    .purchase-section-card--pricing {
        border: 1px solid #17a2b8;
    }
    .purchase-section-card--pricing .card-header {
        background: linear-gradient(135deg, #117a8b, #17a2b8);
        color: #fff;
    }

    .purchase-section-card--tax {
        border: 1px solid #6c757d;
    }
    .purchase-section-card--tax .card-header {
        background: #6c757d;
        color: #fff;
    }

    .purchase-section-card--sale {
        border: 1px solid #28a745;
    }
    .purchase-section-card--sale .card-header {
        background: linear-gradient(135deg, #1e7e34, #28a745);
        color: #fff;
    }

    .purchase-section-card .card-header .card-title,
    .purchase-section-card .card-header small {
        color: #fff;
    }

    .form-group label {
        font-weight: 600;
        color: #334155;
    }

    /* Smaller variant used for the per-item sub-sections inside each
       multi-purchase item card (create_multiple.blade.php) — same colors,
       tighter padding since several stack inside one outer item card. */
    .purchase-section-card--compact {
        margin-bottom: 1rem;
    }
    .purchase-section-card--compact .card-header {
        padding: 8px 16px;
    }
    .purchase-section-card--compact .card-header h6 {
        margin-bottom: 0;
        font-size: 0.9rem;
    }
    .purchase-section-card--compact .card-body {
        padding: 1rem;
    }
</style>
