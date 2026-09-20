<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Laporan Penerimaan Per Jenjang</title>
    @php
        $categoryCount = max(count($report['categories']), 1);
        $bankCategoryWidth = number_format((100 - 9 - 15 - 10 - 10) / $categoryCount, 6, '.', '').'%';
        $cashCategoryWidth = number_format((100 - 12 - 11) / $categoryCount, 6, '.', '').'%';
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
        .level { width: 9%; text-align: center; vertical-align: middle; font-weight: bold; }
        .bank { width: 15%; }
        .money { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-table th.money { white-space: normal; text-align: center; font-variant-numeric: normal; word-wrap: break-word; }
        .report-table td.money { font-size: {{ $categoryFontSize }}px; }
        .row-total, .level-total { width: 10%; font-weight: bold; }
        .level-group { page-break-inside: avoid; }
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
        <td class="title-cell"><div class="title">LAPORAN PENERIMAAN PER JENJANG</div><div class="subtitle">(MONTHLY REPORT BY SCHOOL LEVEL)</div><div class="unit">{{ $document['unit'] }}</div></td>
        <td style="width: 15%;"></td>
    </tr></table>
    <table class="meta">
        <tr><td class="meta-label">BULAN</td><td class="meta-separator">:</td><td>{{ $document['month_label'] }}</td></tr>
        <tr><td class="meta-label">UNIT</td><td class="meta-separator">:</td><td>{{ $document['unit'] }}</td></tr>
    </table>

    <div class="section-title">PENERIMAAN BANK</div>
    <table class="report-table bank-table">
        <thead><tr><th class="level">Jenjang</th><th class="bank">Bank</th>@foreach($report['categories'] as $category)<th class="money" style="width: {{ $bankCategoryWidth }}">{{ $category['name'] }}</th>@endforeach<th class="money row-total">Total</th><th class="money level-total">Total Jenjang</th></tr></thead>
        @if($report['bank']['account_count'] === 0)
            <tbody><tr><td class="empty" colspan="{{ count($report['categories']) + 4 }}">Belum ada rekening bank yang dikonfigurasi</td></tr></tbody>
        @else
            @forelse($report['bank']['levels'] as $level)
                <tbody class="level-group">@foreach($level['banks'] as $bank)<tr>
                    @if($loop->first)<td class="level" rowspan="{{ $level['bank_count'] }}">{{ $level['level_label'] }}</td>@endif
                    <td class="bank">{{ $bank['bank_label'] }}</td>
                    @foreach($report['categories'] as $category)<td class="money">{{ $bank['amounts'][$category['key']] > 0 ? 'Rp '.number_format($bank['amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach
                    <td class="money row-total">{{ $bank['total'] > 0 ? 'Rp '.number_format($bank['total'], 0, ',', '.') : '' }}</td>
                    @if($loop->first)<td class="money level-total" rowspan="{{ $level['bank_count'] }}">Rp {{ number_format($level['bank_total'], 0, ',', '.') }}</td>@endif
                </tr>@endforeach</tbody>
            @empty
                <tbody><tr><td class="empty" colspan="{{ count($report['categories']) + 4 }}">Tidak ada penerimaan bank</td></tr></tbody>
            @endforelse
        @endif
        <tfoot><tr class="total-row"><td colspan="2">TOTAL PENERIMAAN BANK</td>@foreach($report['categories'] as $category)<td class="money">{{ $report['bank']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($report['bank']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="money">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td><td class="money">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td></tr></tfoot>
    </table>

    <div class="section-title">PENERIMAAN TUNAI</div>
    <table class="report-table cash-table">
        <thead><tr><th class="level" style="width: 12%;">Jenjang</th>@foreach($report['categories'] as $category)<th class="money" style="width: {{ $cashCategoryWidth }}">{{ $category['name'] }}</th>@endforeach<th class="money" style="width: 11%;">Total</th></tr></thead>
        <tbody>@forelse($report['cash']['levels'] as $level)<tr><td class="level">{{ $level['level_label'] }}</td>@foreach($report['categories'] as $category)<td class="money">{{ $level['cash_amounts'][$category['key']] > 0 ? 'Rp '.number_format($level['cash_amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="money total-row">Rp {{ number_format($level['cash_total'], 0, ',', '.') }}</td></tr>@empty<tr><td class="empty" colspan="{{ count($report['categories']) + 2 }}">Tidak ada penerimaan tunai</td></tr>@endforelse</tbody>
        <tfoot><tr class="total-row"><td>TOTAL PENERIMAAN TUNAI</td>@foreach($report['categories'] as $category)<td class="money">{{ $report['cash']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($report['cash']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="money">{{ $report['cash']['total'] > 0 ? 'Rp '.number_format($report['cash']['total'], 0, ',', '.') : '' }}</td></tr></tfoot>
    </table>

    <div class="section-title">RINGKASAN TOTAL</div>
    <table class="summary-table">
        <tr><td>TOTAL PENERIMAAN BANK</td><td class="amount">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td></tr>
        <tr><td>TOTAL PENERIMAAN TUNAI</td><td class="amount">{{ $report['cash']['total'] > 0 ? 'Rp '.number_format($report['cash']['total'], 0, ',', '.') : '' }}</td></tr>
        <tr class="grand"><td>GRAND TOTAL</td><td class="amount">{{ $report['grand_total'] > 0 ? 'Rp '.number_format($report['grand_total'], 0, ',', '.') : '' }}</td></tr>
    </table>

    <table class="signature-table"><tr>
        <td>Menyetujui,<br>{{ $document['approval']['approver_title'] }}<div class="signature-space"></div><div class="signature-name">{{ $document['approval']['approver_name'] }}</div></td>
        <td>{{ $document['approval']['city_and_date'] }}<br>Mengetahui,<br>{{ $document['approval']['report_creator_title'] }}<div class="signature-space"></div><div class="signature-name">{{ $document['approval']['report_creator_name'] }}</div></td>
    </tr></table>
</body>
</html>
