{{-- Shared receipt styling. The ONE source is resources/css/receipt.css: the React POS live receipt imports it
     through Vite and the printed receipts inline it here, so screen and paper always match. Edit that file only.
     Margins: the outer .pmx-receipt padding is the only place left/right spacing is set (thermal printers). --}}
<style>
{!! file_get_contents(resource_path('css/receipt.css')) !!}
    /* Paper only (kept out of receipt.css so the app's own pages never print at receipt size). */
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
