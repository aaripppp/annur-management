<style id="receipt-print-styles">
    .receipt-cancellation {
        width: 95%;
        margin: 0.75rem auto 0;
        box-sizing: border-box;
    }

    @media print {
        @page {
            margin: 0;
        }

        html,
        body {
            width: 210mm;
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            min-height: 0 !important;
            height: auto !important;
        }

        body > aside,
        body > header,
        .print\:hidden {
            display: none !important;
        }

        body > main {
            width: 210mm !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            display: block !important;
            max-width: none !important;
        }

        .receipt-sheet {
            box-sizing: border-box;
            width: 210mm !important;
            height: auto !important;
            margin: 0 !important;
            overflow: visible !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            color: #111827 !important;
            font-size: 9pt !important;
            line-height: 1.2 !important;
        }

        .receipt-header {
            padding: 2.5mm 5mm !important;
            gap: 3mm !important;
        }

        .receipt-brand-logo {
            height: 8mm !important;
            width: auto !important;
            max-width: 10mm !important;
            object-fit: contain !important;
        }

        .receipt-brand-name,
        .receipt-label {
            font-size: 7.5pt !important;
            line-height: 1.1 !important;
        }

        .receipt-title {
            margin-top: 0.5mm !important;
            font-size: 13pt !important;
            line-height: 1.1 !important;
        }

        .receipt-number {
            margin-top: 0.5mm !important;
            font-size: 11pt !important;
            line-height: 1.1 !important;
        }

        .receipt-badge {
            margin-top: 0.5mm !important;
            padding: 0.5mm 2mm !important;
            font-size: 7.5pt !important;
        }

        .receipt-meta {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }

        .receipt-meta-item {
            padding: 2mm 5mm !important;
            gap: 2mm !important;
        }

        .receipt-payment-meta {
            justify-content: flex-end !important;
            text-align: right !important;
        }

        .receipt-meta-icon {
            width: 7mm !important;
            height: 7mm !important;
        }

        .receipt-meta-title {
            margin-top: 0.5mm !important;
            font-size: 10pt !important;
            line-height: 1.15 !important;
        }

        .receipt-meta-copy {
            margin-top: 0.5mm !important;
            font-size: 8pt !important;
            line-height: 1.15 !important;
        }

        .receipt-details {
            padding: 2mm 5mm 1mm !important;
        }

        .receipt-details-heading {
            margin-bottom: 1mm !important;
            font-size: 7.5pt !important;
        }

        .receipt-sheet th,
        .receipt-sheet td {
            padding: 1mm 1.5mm !important;
            font-size: 8pt !important;
            line-height: 1.15 !important;
        }

        .receipt-sheet tfoot td {
            padding-top: 1.5mm !important;
            padding-bottom: 1.5mm !important;
        }

        .receipt-total {
            font-size: 11pt !important;
        }

        .receipt-sheet tr {
            break-inside: avoid;
        }

        .receipt-cancellation {
            width: 200mm !important;
            margin: 2mm 5mm 0 !important;
        }
    }
</style>
