<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Laporan Bulanan Daycare</title>
@php
        $reportCategoryCount = max(count($report['categories']), 1);
        $reportCategoryWidth = number_format((100 - 17 - 15 - 15 - 15) / $reportCategoryCount, 6, '.', '').'%';
        $cashCategoryWidth = number_format((100 - 17 - 15) / $reportCategoryCount, 6, '.', '').'%';
        $categoryFontSize = $reportCategoryCount <= 3 ? 7 : ($reportCategoryCount <= 6 ? 6.6 : ($reportCategoryCount <= 9 ? 6.2 : ($reportCategoryCount <= 12 ? 5.8 : 5.3)));
    @endphp
    <style>
        @page { size: 216mm 330mm; margin: 6mm 6mm 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font-family: "DejaVu Sans", sans-serif; font-size: 7.5px; line-height: 1.08; }
        .document-header { width: 100%; margin-bottom: 3px; border-collapse: collapse; }
        .document-header td { vertical-align: middle; }
        .logo-cell { width: 15%; }
        .logo { width: auto; height: 36px; }
        .title-cell { width: 70%; text-align: center; }
        .title { font-size: 11px; font-weight: bold; }
        .subtitle { margin-top: 1px; font-size: 7px; }
        .unit { margin-top: 1px; font-size: 8px; font-weight: bold; }
        .spacer-cell { width: 15%; }
        .meta { width: 100%; margin: 0 0 3px; border-collapse: collapse; }
        .meta td { padding: 0; }
        .meta-label { width: 55px; }
        .meta-separator { width: 8px; text-align: center; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7px; line-height: 1.05; }
        .report-table thead { display: table-header-group; }
        .report-table th, .report-table td { border: .5px solid #000; padding: 1px 2px; vertical-align: middle; }
        .report-table th { font-weight: bold; text-align: center; background: #fff; }
        .report-table tfoot { display: table-row-group; }
        .section-title { margin: 4px 0 2px; font-size: 8px; font-weight: bold; }
        .date-group { page-break-inside: avoid; }
        .col-date { width: 17%; font-weight: bold; }
        .col-bank { width: 15%; }
        .col-category { width: auto; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-table td.col-category { padding-right: 1px; padding-left: 1px; font-size: {{ $categoryFontSize }}px; line-height: 1.08; white-space: nowrap; }
        .col-total { width: 15%; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-weight: bold; }
        .col-daily-total { width: 15%; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-weight: bold; }
        .report-table th.col-category, .report-table th.col-total, .report-table th.col-daily-total, .report-table th.cash-total { text-align: center; }
        .report-table th.col-category { white-space: normal; }
        .cash-date { width: 17%; font-weight: bold; }
        .cash-total { width: 15%; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-weight: bold; }
        .total-row { page-break-inside: avoid; font-weight: bold; }
        .empty-row { page-break-inside: avoid; text-align: center; }
        .summary-table { width: 48%; margin: 5px 0 0 auto; border-collapse: collapse; page-break-inside: avoid; }
        .summary-table td { border: .5px solid #000; padding: 2px 4px; }
        .summary-table .amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-weight: bold; }
        .summary-table .grand-total { font-weight: bold; background: #f2f2f2; }
        .signature-table { width: 100%; margin-top: 12px; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 33.333%; padding: 0 8px; text-align: center; vertical-align: top; }
        .signature-role { min-height: 20px; font-size: 8px; }
        .signature-space { height: 34px; }
        .signature-name { display: inline-block; min-width: 130px; padding-top: 2px; border-top: .6px dotted #000; font-weight: bold; }
    </style>
</head>
<body>
    <table class="document-header">
        <tr>
            <td class="logo-cell"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
            <td class="title-cell">
                <div class="title">LAPORAN BULANAN DAYCARE</div>
                <div class="subtitle">(MONTHLY REPORT)</div>
                <div class="unit">{{ $document['unit'] }}</div>
            </td>
            <td class="spacer-cell"></td>
        </tr>
    </table>

    <table class="meta">
        <tr><td class="meta-label">PERIODE</td><td class="meta-separator">:</td><td>{{ $document['month_label'] }}</td></tr>
        <tr><td class="meta-label">KATEGORI</td><td class="meta-separator">:</td><td>{{ $report['category'] }}</td></tr>
    </table>

    <div class="section-title">PENERIMAAN BANK</div>
    <table class="report-table bank-table">
        <colgroup>
            <col style="width: 17%">
            <col style="width: 15%">
            @foreach($report['categories'] as $category)<col style="width: {{ $reportCategoryWidth }}">@endforeach
            <col style="width: 15%">
            <col style="width: 15%">
        </colgroup>
        <thead>
            <tr>
                <th class="col-date">Tanggal</th>
                <th class="col-bank">Bank</th>
                @foreach($report['categories'] as $category)
                    <th class="col-category" style="width: {{ $reportCategoryWidth }}">{{ $category['name'] }}</th>
                @endforeach
                <th class="col-total">Total</th>
                <th class="col-daily-total">Total Harian</th>
            </tr>
        </thead>
        @if($report['bank']['account_count'] === 0)
            <tbody><tr class="empty-row"><td colspan="{{ count($report['categories']) + 4 }}">Belum ada rekening bank yang dikonfigurasi</td></tr></tbody>
        @else
            @forelse($report['bank']['dates'] as $dateGroup)
                <tbody class="date-group">
                    @foreach($dateGroup['banks'] as $bank)
                        <tr>
                            @if($loop->first)
                                <td class="col-date" rowspan="{{ $dateGroup['bank_count'] }}">{{ $dateGroup['date']->settings(['locale' => 'id'])->translatedFormat('l, d M Y') }}</td>
                            @endif
                            <td class="col-bank">{{ $bank['bank_label'] }}</td>
                            @foreach($report['categories'] as $category)
                                <td class="col-category">{{ $bank['amounts'][$category['key']] > 0 ? 'Rp '.number_format($bank['amounts'][$category['key']], 0, ',', '.') : '' }}</td>
                            @endforeach
                            <td class="col-total">{{ $bank['total'] > 0 ? 'Rp '.number_format($bank['total'], 0, ',', '.') : '' }}</td>
                            @if($loop->first)
                                <td class="col-daily-total" rowspan="{{ $dateGroup['bank_count'] }}">{{ $dateGroup['total'] > 0 ? 'Rp '.number_format($dateGroup['total'], 0, ',', '.') : '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody><tr class="empty-row"><td colspan="{{ count($report['categories']) + 4 }}">Tidak ada transaksi</td></tr></tbody>
            @endforelse
        @endif
        <tfoot>
            <tr class="total-row">
                <td colspan="2">TOTAL PENERIMAAN BANK</td>
                @foreach($report['categories'] as $category)
                    <td class="col-category">{{ $report['bank']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($report['bank']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>
                @endforeach
                <td class="col-total">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td>
                <td class="col-daily-total">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">PENERIMAAN TUNAI</div>
    <table class="report-table cash-table">
        <colgroup>
            <col style="width: 17%">
            @foreach($report['categories'] as $category)<col style="width: {{ $cashCategoryWidth }}">@endforeach
            <col style="width: 15%">
        </colgroup>
        <thead>
            <tr>
                <th class="cash-date">Tanggal</th>
                @foreach($report['categories'] as $category)
                    <th class="col-category" style="width: {{ $cashCategoryWidth }}">{{ $category['name'] }}</th>
                @endforeach
                <th class="cash-total">Total</th>
            </tr>
        </thead>
        @forelse($report['cash']['dates'] as $cashDate)
            <tbody class="date-group">
                <tr>
                    <td class="cash-date">{{ $cashDate['date']->settings(['locale' => 'id'])->translatedFormat('l, d M Y') }}</td>
                    @foreach($report['categories'] as $category)
                        <td class="col-category">{{ $cashDate['amounts'][$category['key']] > 0 ? 'Rp '.number_format($cashDate['amounts'][$category['key']], 0, ',', '.') : '' }}</td>
                    @endforeach
                    <td class="cash-total">{{ $cashDate['total'] > 0 ? 'Rp '.number_format($cashDate['total'], 0, ',', '.') : '' }}</td>
                </tr>
            </tbody>
        @empty
            <tbody><tr class="empty-row"><td colspan="{{ count($report['categories']) + 2 }}">Tidak ada transaksi</td></tr></tbody>
        @endforelse
        <tfoot>
            <tr class="total-row">
                <td>GRAND TOTAL TUNAI</td>
                @foreach($report['categories'] as $category)
                    <td class="col-category">{{ $report['cash']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($report['cash']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>
                @endforeach
                <td class="cash-total">{{ $report['cash']['total'] > 0 ? 'Rp '.number_format($report['cash']['total'], 0, ',', '.') : '' }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">RINGKASAN TOTAL</div>
    <table class="summary-table">
        <tr><td>TOTAL PENERIMAAN BANK</td><td class="amount">{{ $report['bank']['total'] > 0 ? 'Rp '.number_format($report['bank']['total'], 0, ',', '.') : '' }}</td></tr>
        <tr><td>TOTAL PENERIMAAN TUNAI</td><td class="amount">{{ $report['cash']['total'] > 0 ? 'Rp '.number_format($report['cash']['total'], 0, ',', '.') : '' }}</td></tr>
        <tr class="grand-total"><td>GRAND TOTAL</td><td class="amount">{{ $report['grand_total'] > 0 ? 'Rp '.number_format($report['grand_total'], 0, ',', '.') : '' }}</td></tr>
    </table>

    @include('reports.partials.signature', ['approval' => $document['approval']])
</body>
</html>