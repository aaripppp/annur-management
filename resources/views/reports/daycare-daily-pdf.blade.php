<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Laporan Kas Harian Daycare</title>
    <style>
        @page { size: 216mm 330mm; margin: 12mm 11mm 13mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font-family: "DejaVu Sans", sans-serif; font-size: 9px; line-height: 1.25; }
        .document-header { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .document-header td { vertical-align: middle; }
        .logo-cell { width: 25%; }
        .logo { width: auto; height: 52px; }
        .title-cell { width: 50%; text-align: center; }
        .title { font-size: 14px; font-weight: bold; }
        .title-note { margin-top: 2px; font-size: 9px; font-weight: normal; }
        .header-balance { width: 25%; }
        .meta { width: 100%; margin: 0 0 8px; border-collapse: collapse; }
        .meta td { padding: 1px 0; }
        .meta-label { width: 110px; }
        .meta-separator { width: 12px; text-align: center; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table tfoot { display: table-row-group; }
        .report-table th, .report-table td { border: .6px solid #000; padding: 3px 5px; vertical-align: middle; }
        .report-table th { font-weight: bold; text-align: center; background: #fff; }
        .col-number { width: 6%; text-align: center; }
        .col-receipt { width: 34%; }
        .col-detail { width: 8%; text-align: center; }
        .col-amount { width: 17.33%; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-table th.col-amount { text-align: center; }
        .channel-row { font-weight: bold; }
        .channel-row td { padding-top: 5px; padding-bottom: 5px; }
        .group-row { font-weight: bold; }
        .group-row td { padding-top: 4px; padding-bottom: 4px; }
        .category-name { padding-left: 18px !important; }
        .report-group { page-break-inside: avoid; }
        .total-row { page-break-inside: avoid; font-weight: bold; }
        .total-label { text-align: center; }
        .signature-table { width: 100%; margin-top: 22px; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 33.333%; padding: 0 12px; text-align: center; vertical-align: top; }
        .signature-role { min-height: 28px; }
        .signature-space { height: 56px; }
        .signature-name { display: inline-block; min-width: 125px; padding-top: 3px; border-top: .6px dotted #000; font-weight: bold; }
    </style>
</head>
<body>
    <table class="document-header">
        <tr>
            <td class="logo-cell"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
            <td class="title-cell">
                <div class="title">LAPORAN HARIAN DAYCARE</div>
                <div class="title-note">(DAILY REPORT)</div>
            </td>
            <td class="header-balance"></td>
        </tr>
    </table>

    <table class="meta">
        <tr><td class="meta-label">{{ $document['period_title'] }}</td><td class="meta-separator">:</td><td>{{ $document['period_label'] }}</td></tr>
        <tr><td class="meta-label">Unit</td><td class="meta-separator">:</td><td>{{ $document['unit'] }}</td></tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-number">No</th>
                <th class="col-receipt">Penerimaan (Debet / transfer)</th>
                <th class="col-detail">Rincian</th>
                <th class="col-amount">Terima</th>
                <th class="col-amount">Keluar</th>
                <th class="col-amount">Saldo</th>
            </tr>
        </thead>
        @php($groupNumber = 0)
        @foreach($report['channels'] as $channel)
            <tbody class="report-group">
                <tr class="channel-row">
                    <td colspan="2">{{ $channel['label'] }}</td>
                    <td class="col-detail">@if ($channel['transaction_count'] > 0){{ $channel['transaction_count'] }}@endif</td>
                    <td class="col-amount">@if ($channel['transaction_count'] > 0)Rp {{ number_format($channel['total'], 0, ',', '.') }}@endif</td>
                    <td></td>
                    <td></td>
                </tr>
                @forelse($channel['banks'] as $bank)
                    <tr class="group-row">
                        <td class="col-number">{{ ++$groupNumber }}</td>
                        <td>{{ $bank['bank_label'] }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    @foreach($bank['categories'] as $category)
                        <tr>
                            <td></td>
                            <td class="category-name">{{ $category['name'] }}</td>
                            <td class="col-detail">{{ $category['count'] }}</td>
                            <td class="col-amount">Rp {{ number_format($category['total'], 0, ',', '.') }}</td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2" class="total-label">TOTAL {{ $bank['bank_name'] }}</td>
                        <td class="col-detail">{{ $bank['transaction_count'] }}</td>
                        <td class="col-amount">Rp {{ number_format($bank['total'], 0, ',', '.') }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                @empty
                    <tr>
                        <td></td>
                        <td>Tidak ada transaksi</td>
                        <td class="col-detail"></td>
                        <td class="col-amount"></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforelse
            </tbody>
        @endforeach
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="total-label">TOTAL PENERIMAAN</td>
                <td class="col-detail">@if ($report['has_payments']){{ $report['transaction_count'] }}@endif</td>
                <td class="col-amount">@if ($report['has_payments'])Rp {{ number_format($report['grand_total'], 0, ',', '.') }}@endif</td>
                <td></td>
                <td class="col-amount">@if ($report['has_payments'])Rp {{ number_format($report['grand_total'], 0, ',', '.') }}@endif</td>
            </tr>
        </tfoot>
    </table>

    @include('reports.partials.signature', ['approval' => $document['approval']])
</body>
</html>
