<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Laporan Penerimaan Seluruh Unit</title>
    @php
        $categoryCount = max(count($report['categories']), 1);
        $bankCategoryWidth = number_format((100 - 18 - 11) / $categoryCount, 6, '.', '').'%';
        $cashCategoryWidth = number_format((100 - 11) / $categoryCount, 6, '.', '').'%';
        $categoryFontSize = $categoryCount <= 3 ? 7 : ($categoryCount <= 6 ? 6.6 : ($categoryCount <= 9 ? 6.2 : ($categoryCount <= 12 ? 5.8 : 5.3)));
    @endphp
    <style>
        @page { size: 330mm 216mm; margin: 6mm 6mm 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 7.5px; line-height: 1.08; }
        .document-header, .meta, .report-table, .signature-table, .summary-table { width: 100%; border-collapse: collapse; }
        .document-header { margin-bottom: 3px; }
        .document-header td { vertical-align: middle; }
        .logo { width: auto; height: 36px; }
        .title-cell { width: 70%; text-align: center; }
        .title { font-size: 11px; font-weight: bold; }
        .subtitle { margin-top: 1px; font-size: 7px; }
        .unit { margin-top: 1px; font-size: 8px; font-weight: bold; }
        .meta { margin-bottom: 3px; }
        .meta td { padding: 0; }
        .meta-label { width: 55px; }
        .meta-separator { width: 8px; text-align: center; }
        .section-title { margin: 4px 0 2px; font-size: 8px; font-weight: bold; }
        .report-table { table-layout: fixed; font-size: 7px; }
        .report-table th, .report-table td { padding: 1px 2px; border: .5px solid #000; vertical-align: middle; }
        .report-table th { text-align: center; }
        .bank { width: 18%; }
        .money { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-table th.money { white-space: normal; text-align: center; font-variant-numeric: normal; word-wrap: break-word; }
        .report-table td.money { font-size: {{ $categoryFontSize }}px; }
        .row-total { width: 11%; font-weight: bold; }
        .total-row { font-weight: bold; }
        .empty { padding: 5px !important; text-align: center; }
        .summary-table { width: 48%; margin: 5px 0 0 auto; page-break-inside: avoid; }
        .summary-table td { padding: 2px 4px; border: .5px solid #000; }
        .summary-table .amount { text-align: right; white-space: nowrap; font-weight: bold; }
        .summary-table .grand { background: #f2f2f2; font-weight: bold; }
        .signature-table { margin-top: 12px; page-break-inside: avoid; }
        .signature-table td { width: 50%; padding: 0 8px; text-align: center; vertical-align: top; }
        .signature-space { height: 34px; }
        .signature-name { display: inline-block; min-width: 130px; padding-top: 2px; border-top: .6px dotted #000; font-weight: bold; }
    </style>
</head>
<body>
    <table class="document-header"><tr>
        <td style="width: 15%;"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
        <td class="title-cell"><div class="title">LAPORAN PENERIMAAN SELURUH UNIT</div><div class="subtitle">(MONTHLY REPORT - ALL STUDENT UNITS)</div><div class="unit">{{ $document['unit'] }}</div></td>
        <td style="width: 15%;"></td>
    </tr></table>
    <table class="meta">
        <tr><td class="meta-label">BULAN</td><td class="meta-separator">:</td><td>{{ $document['month_label'] }}</td></tr>
        <tr><td class="meta-label">UNIT</td><td class="meta-separator">:</td><td>{{ $document['unit'] }}</td></tr>
    </table>

    @if($report['detail_count'] === 0)
        <div class="empty">Belum ada penerimaan siswa pada {{ $report['month_label'] }}.</div>
    @else
        <div class="section-title">PENERIMAAN BANK</div>
        <table class="report-table bank-table">
            <thead><tr><th class="bank">Bank</th>@foreach($report['categories'] as $category)<th class="money" style="width: {{ $bankCategoryWidth }}">{{ $category['name'] }}</th>@endforeach<th class="money row-total">Total</th></tr></thead>
            <tbody>@forelse($report['bank']['rows'] as $bank)<tr><td class="bank">{{ $bank['bank_label'] }}</td>@foreach($report['categories'] as $category)<td class="money">{{ $bank['amounts'][$category['key']] > 0 ? 'Rp '.number_format($bank['amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="money row-total">{{ $bank['total'] > 0 ? 'Rp '.number_format($bank['total'], 0, ',', '.') : '' }}</td></tr>@empty<tr><td class="empty" colspan="{{ count($report['categories']) + 2 }}">Tidak ada penerimaan bank</td></tr>@endforelse</tbody>
            <tfoot><tr class="total-row"><td>TOTAL PENERIMAAN BANK</td>@foreach($report['categories'] as $category)<td class="money">{{ $report['bank']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($report['bank']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="money">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td></tr></tfoot>
        </table>

        <div class="section-title">PENERIMAAN TUNAI</div>
        <table class="report-table cash-table">
            <thead><tr>@foreach($report['categories'] as $category)<th class="money" style="width: {{ $cashCategoryWidth }}">{{ $category['name'] }}</th>@endforeach<th class="money" style="width: 11%;">Total</th></tr></thead>
            <tbody><tr>@foreach($report['categories'] as $category)<td class="money">{{ $report['cash']['amounts'][$category['key']] > 0 ? 'Rp '.number_format($report['cash']['amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="money total-row">{{ $report['cash']['total'] > 0 ? 'Rp '.number_format($report['cash']['total'], 0, ',', '.') : '' }}</td></tr></tbody>
        </table>

        <div class="section-title">RINGKASAN TOTAL</div>
        <table class="summary-table">
            <tr><td>TOTAL PENERIMAAN BANK</td><td class="amount">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td></tr>
            <tr><td>TOTAL PENERIMAAN TUNAI</td><td class="amount">{{ $report['cash']['total'] > 0 ? 'Rp '.number_format($report['cash']['total'], 0, ',', '.') : '' }}</td></tr>
            <tr class="grand"><td>GRAND TOTAL</td><td class="amount">Rp {{ number_format($report['grand_total'], 0, ',', '.') }}</td></tr>
        </table>

        <table class="signature-table"><tr>
            <td>Menyetujui,<br>{{ $document['approval']['approver_title'] }}<div class="signature-space"></div><div class="signature-name">{{ $document['approval']['approver_name'] }}</div></td>
            <td>{{ $document['approval']['city_and_date'] }}<br>Mengetahui,<br>{{ $document['approval']['report_creator_title'] }}<div class="signature-space"></div><div class="signature-name">{{ $document['approval']['report_creator_name'] }}</div></td>
        </tr></table>
    @endif
</body>
</html>
