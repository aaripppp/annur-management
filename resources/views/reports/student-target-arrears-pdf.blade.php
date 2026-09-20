<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Target & Tunggakan Siswa</title>
    <style>
        @page { size: 216mm 330mm; margin: 10mm; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 9px; }
        .header { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo { width: auto; height: 44px; }
        .title { text-align: center; font-size: 14px; font-weight: bold; }
        .subtitle { margin-top: 2px; text-align: center; font-size: 9px; }
        .meta { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .meta td { padding: 1px 0; }
        .meta-label { width: 65px; }
        .summary { width: 100%; margin-bottom: 8px; border-collapse: collapse; table-layout: fixed; }
        .summary td { width: 25%; padding: 5px; border: .5px solid #777; }
        .summary-label { font-size: 7px; font-weight: bold; }
        .summary-value { margin-top: 3px; font-size: 11px; font-weight: bold; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .section-title { margin: 8px 0 3px; padding: 3px 5px; background: #e8eef3; font-weight: bold; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { padding: 4px; border: .5px solid #000; }
        .report-table th { background: #e8eef3; text-align: center; }
        .money, .percentage { text-align: right; white-space: nowrap; }
        .total { font-weight: bold; background: #f2f2f2; }
        .empty { padding: 12px !important; text-align: center; }
        .signature { width: 100%; margin-top: 24px; border-collapse: collapse; page-break-inside: avoid; }
        .signature td { width: 33.333%; text-align: center; vertical-align: top; }
        .signature-space { height: 48px; }
        .signature-name { display: inline-block; min-width: 140px; padding-top: 2px; border-top: .6px dotted #000; font-weight: bold; }
    </style>
</head>
<body>
    <table class="header"><tr>
        <td style="width: 15%;"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
        <td style="width: 70%;"><div class="title">TARGET & TUNGGAKAN SISWA</div><div class="subtitle">{{ strtoupper($report['mode_label']) }} • {{ strtoupper($document['unit']) }}</div></td>
        <td style="width: 15%;"></td>
    </tr></table>

    <table class="meta">
        <tr><td class="meta-label">PERIODE</td><td>: {{ $report['period_label'] }}</td></tr>
        <tr><td class="meta-label">JENJANG</td><td>: {{ $report['school_level_label'] }}</td></tr>
        <tr><td class="meta-label">UNIT</td><td>: {{ $document['unit'] }}</td></tr>
    </table>

    <table class="summary"><tr>
        <td><div class="summary-label">TOTAL TARGET</div><div class="summary-value">Rp {{ number_format($report['totals']['target'], 0, ',', '.') }}</div></td>
        <td><div class="summary-label">SUDAH TERBAYAR</div><div class="summary-value">Rp {{ number_format($report['totals']['paid'], 0, ',', '.') }}</div></td>
        <td><div class="summary-label">TUNGGAKAN</div><div class="summary-value">Rp {{ number_format($report['totals']['outstanding'], 0, ',', '.') }}</div></td>
        <td><div class="summary-label">CAPAIAN</div><div class="summary-value">{{ $report['totals']['achievement_label'] }}</div></td>
    </tr></table>

    @php $renderedSections = $report['sections'] ?? [$report['mode'] => $report]; @endphp
    @foreach($renderedSections as $section)
        @if($report['mode'] === 'all')
            <div class="section-title">TAGIHAN {{ strtoupper($section['mode_label']) }} - {{ $section['period_label'] }}</div>
        @endif
        <table class="report-table">
            <thead><tr><th style="width: 36%;">Jenis Tagihan</th><th>Target</th><th>Terbayar</th><th>Tunggakan</th><th>Capaian</th></tr></thead>
            <tbody>
                @forelse($section['rows'] as $row)
                    <tr><td>{{ $row['payment_type_name'] }}</td><td class="money">Rp {{ number_format($row['target'], 0, ',', '.') }}</td><td class="money">Rp {{ number_format($row['paid'], 0, ',', '.') }}</td><td class="money">Rp {{ number_format($row['outstanding'], 0, ',', '.') }}</td><td class="percentage">{{ $row['achievement_label'] }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada tagihan pada periode ini.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="total"><td>TOTAL</td><td class="money">Rp {{ number_format($section['totals']['target'], 0, ',', '.') }}</td><td class="money">Rp {{ number_format($section['totals']['paid'], 0, ',', '.') }}</td><td class="money">Rp {{ number_format($section['totals']['outstanding'], 0, ',', '.') }}</td><td class="percentage">{{ $section['totals']['achievement_label'] }}</td></tr></tfoot>
        </table>
    @endforeach

    <table class="signature"><tr>
        <td>{{ $document['approval']['approver_title'] }}<div class="signature-space"></div><div class="signature-name">{{ $document['approval']['approver_name'] }}</div></td>
        <td>{{ $document['approval']['reviewer_title'] }}<div class="signature-space"></div><div class="signature-name">{{ $document['approval']['reviewer_name'] }}</div></td>
        <td>{{ $document['approval']['city_and_date'] }}<br>{{ $document['approval']['footer_unit'] }}<div class="signature-space"></div><div class="signature-name">{{ $document['approval']['report_creator_name'] }}</div></td>
    </tr></table>
</body>
</html>
