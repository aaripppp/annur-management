<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Rekap Bank Sekolah</title>
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
        .header-balance { width: 25%; }
        .meta { width: 100%; margin: 0 0 10px; border-collapse: collapse; }
        .meta td { padding: 1px 0; }
        .section { margin-bottom: 12px; }
        .section-title { margin: 0; padding: 4px 5px; border: .6px solid #000; font-size: 10px; }
        .section-note { font-weight: normal; }
        .date-group { margin-top: 3px; page-break-inside: avoid; }
        .date-group-header { width: 100%; border-collapse: collapse; margin-bottom: 1px; }
        .date-group-header td { padding: 2px 0; }
        .date-group-label { text-align: left; font-weight: bold; }
        .date-group-total { text-align: right; font-weight: bold; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table th, .report-table td { border: .6px solid #000; padding: 3px 5px; vertical-align: middle; }
        .report-table th { text-align: center; }
        .col-no { width: 4%; text-align: center; }
        .col-name { width: 28%; }
        .col-source { width: 9%; text-align: center; }
        .col-transfer-date { width: 12%; text-align: center; }
        .col-recorded-at { width: 16%; text-align: center; }
        .col-receipt { width: 15%; text-align: center; }
        .col-amount { width: 16%; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-table th.amount { text-align: center; }
        .badge { display: inline-block; padding: 1px 5px; font-size: 7px; font-weight: bold; border-radius: 4px; line-height: 1.5; }
        .badge-student { background: #f1f5f9; color: #334155; }
        .badge-daycare { background: #e0e7ff; color: #3730a3; }
        .badge-prospective { background: #ecfdf5; color: #065f46; }
        .bank-total { width: 100%; margin-top: 2px; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
        .bank-total td { border: .6px solid #000; padding: 3px 5px; font-weight: bold; vertical-align: middle; }
        .grand-total { width: 100%; margin-top: 6px; border-collapse: collapse; font-size: 10px; font-weight: bold; }
        .grand-total td { border: .8px solid #000; padding: 5px; }
        .signature { width: 100%; margin-top: 24px; border-collapse: collapse; page-break-inside: avoid; }
        .signature td { width: 50%; text-align: center; vertical-align: top; }
        .signature-space { height: 55px; }
        .signature-name { display: inline-block; min-width: 140px; border-top: .6px dotted #000; font-weight: bold; }
    </style>
</head>
<body>
    <table class="document-header">
        <tr>
            <td class="logo-cell"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
            <td class="title-cell"><div class="title">REKAP BANK SEKOLAH</div></td>
            <td class="header-balance"></td>
        </tr>
    </table>
    <table class="meta">
        <tr><td style="width: 65px">Periode</td><td style="width: 12px">:</td><td>{{ $report['period_label'] }}</td></tr>
        @if($report['bank_filter_kind'] !== 'all')
            <tr><td style="width: 65px">Bank / Channel</td><td style="width: 12px">:</td><td>{{ $report['bank_filter_label'] }}</td></tr>
        @endif
    </table>

    @foreach($report['sections'] as $section)
        @if($section['transaction_count'] > 0)
            <div class="section">
                <h2 class="section-title">{{ $section['bank_name'] }} @if($section['bank_id'] !== null)<span class="section-note">({{ $section['bank_label'] }})</span>@endif</h2>
                @foreach($section['rows'] as $row)
                    <div class="date-group">
                        <table class="date-group-header">
                            <tr>
                                <td class="date-group-label">Tanggal Transfer: {{ $row['date']->locale('id')->translatedFormat('d F Y') }}</td>
                                <td class="date-group-total">Total {{ $row['date']->locale('id')->translatedFormat('d F Y') }}: Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                            </tr>
                        </table>
                        <table class="report-table">
                            <colgroup>
                                <col class="col-no">
                                <col class="col-name">
                                <col class="col-source">
                                <col class="col-transfer-date">
                                <col class="col-recorded-at">
                                <col class="col-receipt">
                                <col class="col-amount">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-no">No.</th>
                                    <th class="col-name">Nama</th>
                                    <th class="col-source">Kategori</th>
                                    <th class="col-transfer-date">Tanggal TF</th>
                                    <th class="col-recorded-at">Tanggal Dicatat</th>
                                    <th class="col-receipt">No. Kwitansi</th>
                                    <th class="col-amount">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($row['details'] as $index => $detail)
                                    <tr>
                                        <td class="col-no">{{ $index + 1 }}</td>
                                        <td class="col-name">{{ $detail['name'] }}</td>
                                        <td class="col-source"><span class="badge badge-{{ $detail['source'] }}">{{ $detail['source'] === 'daycare' ? 'DAYCARE' : ($detail['source'] === 'prospective' ? 'CALON SISWA' : 'SISWA') }}</span></td>
                                        <td class="col-transfer-date">{{ $detail['payment_date']->locale('id')->translatedFormat('d M Y') }}</td>
                                        <td class="col-recorded-at">{{ $detail['recorded_at']->locale('id')->translatedFormat('d M Y H:i') }}</td>
                                        <td class="col-receipt">{{ $detail['receipt_number'] }}</td>
                                        <td class="col-amount">Rp {{ number_format($detail['amount'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
                <table class="bank-total">
                    <colgroup>
                        <col class="col-name">
                        <col class="col-amount">
                    </colgroup>
                    <tr>
                        <td>TOTAL {{ strtoupper($section['bank_name']) }}</td>
                        <td class="col-amount">Rp {{ number_format($section['total'], 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        @endif
    @endforeach

    <table class="grand-total"><tr><td>GRAND TOTAL</td><td class="amount">Rp {{ number_format($report['grand_total'], 0, ',', '.') }}</td></tr></table>
    <table class="signature">
        <tr>
            <td><div>Mengetahui,<br>Kepala Tata Usaha</div><div class="signature-space"></div><div class="signature-name">Windiarti, SE</div></td>
            <td><div>{{ $document['city_and_date'] }}<br>Admin</div><div class="signature-space"></div><div class="signature-name">{{ $document['creator_name'] }}</div></td>
        </tr>
    </table>
</body>
</html>
