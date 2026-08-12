{{-- Shared receipt styling: used by the live POS preview, the sale receipt
     print view, and the return receipt print view. Keep these three in sync
     by editing this partial rather than the pages that include it — that's
     the whole point of sharing it.

     Margins: the outer .pmx-receipt padding is the ONLY place left/right
     spacing is set, and it is intentionally symmetric (same value on both
     sides) so the printed receipt sits centered on thermal paper regardless
     of printer driver margins. Do not add one-off padding-right hacks to
     column cells to "fix" alignment — fix the column width instead. --}}
<style>
    .pmx-receipt {
        font-family: 'Segoe UI', 'Helvetica Neue', Arial, 'Consolas', 'Courier New', monospace;
        font-size: 11px;
        font-weight: 400;
        line-height: 1.35;
        color: #000;
        width: 3in;
        max-width: 100%;
        margin: 0 auto;
        padding: 10px 12px;
        box-sizing: border-box;
        background: #fff;
    }

    .pmx-receipt * {
        box-sizing: border-box;
    }

    .pmx-receipt .pmx-divider {
        border-top: 1px dashed #000;
        margin: 6px 0;
    }

    .pmx-receipt .pmx-divider--solid {
        border-top: 2px solid #000;
    }

    /* ---------- Header ---------- */
    .pmx-receipt .pmx-header {
        text-align: center;
    }

    .pmx-receipt .pmx-brand {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    .pmx-receipt .pmx-brand-line {
        font-size: 9.5px;
        margin-top: 2px;
        word-wrap: break-word;
    }

    .pmx-receipt .pmx-doc-title {
        display: inline-block;
        margin-top: 6px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        padding: 2px 10px;
        border: 1px solid #000;
        border-radius: 2px;
    }

    /* ---------- Meta rows (Invoice #, Date, Return #...) ---------- */
    .pmx-receipt .pmx-meta {
        margin-top: 6px;
    }

    .pmx-receipt .pmx-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 8px;
    }

    .pmx-receipt .pmx-row .pmx-label {
        font-weight: 400;
        white-space: nowrap;
    }

    .pmx-receipt .pmx-row .pmx-value {
        font-weight: 700;
        text-align: right;
        font-variant-numeric: tabular-nums;
        word-break: break-all;
    }

    /* ---------- Items table ---------- */
    .pmx-receipt table.pmx-items {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .pmx-receipt table.pmx-items th {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 0 0 4px 0;
        border-bottom: 1.5px solid #000;
        vertical-align: bottom;
    }

    .pmx-receipt table.pmx-items td {
        font-size: 10px;
        font-weight: 400;
        padding: 3px 0;
        vertical-align: top;
        border-bottom: 1px dotted #bbb;
    }

    .pmx-receipt table.pmx-items tr:last-child td {
        border-bottom: none;
    }

    .pmx-receipt .col-sr { width: 8%; text-align: left; }
    .pmx-receipt .col-item { width: 38%; text-align: left; padding-right: 4px !important; }
    .pmx-receipt .col-qty { width: 12%; text-align: center; }
    .pmx-receipt .col-price { width: 16%; text-align: right; }
    .pmx-receipt .col-disc { width: 12%; text-align: right; font-size: 8.5px; color: #333; }
    .pmx-receipt .col-total { width: 14%; text-align: right; }

    .pmx-receipt table.pmx-items td.col-sr,
    .pmx-receipt table.pmx-items td.col-qty,
    .pmx-receipt table.pmx-items td.col-price,
    .pmx-receipt table.pmx-items td.col-total {
        font-variant-numeric: tabular-nums;
    }

    .pmx-receipt .item-name {
        display: block;
        font-weight: 700;
        word-wrap: break-word;
        white-space: normal;
        line-height: 1.2;
    }

    .pmx-receipt .item-type {
        display: block;
        font-size: 8px;
        font-weight: 400;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #444;
        margin-top: 1px;
    }

    .pmx-receipt .pmx-empty {
        text-align: center;
        padding: 14px 0;
        color: #555;
    }

    /* ---------- Totals ---------- */
    .pmx-receipt .pmx-totals {
        margin-top: 2px;
    }

    .pmx-receipt .pmx-totals .pmx-row {
        padding: 2px 0;
    }

    .pmx-receipt .pmx-totals .pmx-row--muted .pmx-label,
    .pmx-receipt .pmx-totals .pmx-row--muted .pmx-value {
        font-size: 9.5px;
        font-weight: 400;
        color: #333;
    }

    .pmx-receipt .pmx-totals .pmx-row--grand {
        margin-top: 4px;
        padding-top: 5px;
        border-top: 2px solid #000;
    }

    .pmx-receipt .pmx-totals .pmx-row--grand .pmx-label,
    .pmx-receipt .pmx-totals .pmx-row--grand .pmx-value {
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* ---------- Footer ---------- */
    .pmx-receipt .pmx-footer {
        text-align: center;
        margin-top: 8px;
        padding-top: 6px;
        font-size: 9.5px;
    }

    .pmx-receipt .pmx-footer .pmx-thanks {
        font-weight: 700;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pmx-receipt .pmx-footer div {
        margin-top: 2px;
    }

    @media print {
        @page {
            size: 3in auto;
            margin: 0;
        }

        body {
            margin: 0;
        }

        .pmx-receipt {
            width: 3in;
            margin: 0;
        }

        .no-print {
            display: none !important;
        }
    }
</style>
