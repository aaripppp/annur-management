<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Daftar Tagihan Siswa</title>
    @php
        $rupiah = static fn (float $value): string => 'Rp '.number_format($value, 0, ',', '.');
        $dashIfZero = static fn (float $value): string => $value <= 0 ? '-' : 'Rp '.number_format($value, 0, ',', '.');
        $statusClass = static fn (string $status): string => match (strtolower($status)) {
            'lunas' => 's-lunas',
            'sebagian' => 's-sebagian',
            'belum bayar' => 's-belum',
            default => 's-default',
        };
        $sectionHead = static fn (string $bg, string $text): string =>
            '<div class="section-head" style="background:'.$bg.'">'.$text.'</div>';
    @endphp
    <style>
        @page { size: 216mm 330mm; margin: 6mm; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 8px; line-height: 1.25; }
        .header { width: 100%; margin-bottom: 5px; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo { width: auto; height: 22px; }
        .title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        .subtitle { margin-top: 1px; text-align: center; font-size: 8.5px; }
        .info { width: 100%; margin-bottom: 6px; border-collapse: collapse; table-layout: fixed; }
        .info td { border: .5px solid #666; padding: 3px 6px; font-size: 8.5px; vertical-align: top; }
        .info-label { font-weight: bold; }
        .section-head { margin: 6px 0 3px; padding: 2px 6px; font-size: 9px; font-weight: bold; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { padding: 1.5px 3px; border: .4px solid #000; }
        .report-table th { background: #dfe7ef; text-align: center; font-size: 7.5px; }
        .month-cell { font-size: 8px; font-weight: bold; vertical-align: middle; text-align: center; background: #eef4fb; }
        .month-total td { background: #e7f0fa; font-weight: bold; font-size: 7.5px; }
        .money { text-align: right; white-space: nowrap; padding-left: 4px; }
        .status { text-align: center; }
        .pill { display: inline-block; padding: .5px 5px; font-size: 6.5px; font-weight: bold; border-radius: 5px; line-height: 1.4; }
        .s-lunas { background: #d9f4e3; color: #16794a; }
        .s-sebagian { background: #fdf0d3; color: #8f6600; }
        .s-belum { background: #fbdcdc; color: #b3261e; }
        .s-default { background: #eee; color: #333; }
        .no-col { text-align: center; }
        .period-col { white-space: nowrap; }
    </style>
</head>
<body>
    <table class="header"><tr>
        <td style="width: 18%;"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
        <td style="width: 64%;">
            <div class="title">DAFTAR TAGIHAN SISWA</div>
            <div class="subtitle">{{ strtoupper($report['academic_year_label']) }}</div>
        </td>
        <td style="width: 18%;"></td>
    </tr></table>

    <table class="info"><tr>
        <td style="width: 50%;">
            <span class="info-label">Nama&nbsp;:</span> {{ $report['student_name'] }}<br>
            <span class="info-label">NIS&nbsp;&nbsp;&nbsp;:</span> {{ $report['nis'] ?? '-' }}<br>
            <span class="info-label">Kelas&nbsp;:</span> {{ $report['class_name'] }}
        </td>
        <td style="width: 50%;">
            <span class="info-label">Tahun Ajaran&nbsp;&nbsp;:</span> {{ $report['academic_year_label'] }}<br>
            <span class="info-label">Tanggal Cetak&nbsp;:</span> {{ now()->locale('id')->translatedFormat('d F Y') }}
        </td>
    </tr></table>

    @php $monthly = $report['sections']['monthly']; @endphp
    @if (count($monthly) > 0)
        {!! $sectionHead('#e1eefb', 'TAGIHAN BULANAN') !!}
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Bulan</th>
                    <th style="width: 20%;">Jenis Pembayaran</th>
                    <th style="width: 16%;">Tagihan</th>
                    <th style="width: 17%;">Sudah Dibayar</th>
                    <th style="width: 16%;">Sisa</th>
                    <th style="width: 17%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($monthly as $month)
                    @php $span = count($month['rows']) + 1; $monthlyFirst = true; @endphp
                    @foreach ($month['rows'] as $row)
                        <tr>
                            @if ($monthlyFirst)
                                <td class="month-cell" rowspan="{{ $span }}">{{ $month['period_label'] }}</td>
                                @php $monthlyFirst = false; @endphp
                            @endif
                            <td>{{ $row['payment_type_name'] }}</td>
                            <td class="money">{{ $rupiah((float) $row['target']) }}</td>
                            <td class="money">{{ $dashIfZero((float) $row['paid']) }}</td>
                            <td class="money">{{ $dashIfZero((float) $row['remaining']) }}</td>
                            <td class="status"><span class="pill {{ $statusClass($row['status']) }}">{{ $row['status'] }}</span></td>
                        </tr>
                    @endforeach
                    <tr class="month-total">
                        <td>TOTAL</td>
                        <td class="money">{{ $rupiah((float) $month['totals']['target']) }}</td>
                        <td class="money">{{ $dashIfZero((float) $month['totals']['paid']) }}</td>
                        <td class="money">{{ $dashIfZero((float) $month['totals']['remaining']) }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $yearly = $report['sections']['yearly']; @endphp
    @if (count($yearly) > 0)
        {!! $sectionHead('#dff0e2', 'TAGIHAN TAHUNAN') !!}
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 26%;">Jenis Pembayaran</th>
                    <th style="width: 22%;">Periode</th>
                    <th style="width: 13%;">Tagihan</th>
                    <th style="width: 12%;">Sudah Dibayar</th>
                    <th style="width: 12%;">Sisa</th>
                    <th style="width: 9%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($yearly as $row)
                    <tr>
                        <td class="no-col">{{ $loop->iteration }}</td>
                        <td>{{ $row['payment_type_name'] }}</td>
                        <td class="period-col">{{ $row['period_label'] }}</td>
                        <td class="money">{{ $rupiah((float) $row['target']) }}</td>
                        <td class="money">{{ $dashIfZero((float) $row['paid']) }}</td>
                        <td class="money">{{ $dashIfZero((float) $row['remaining']) }}</td>
                        <td class="status"><span class="pill {{ $statusClass($row['status']) }}">{{ $row['status'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $oneTime = $report['sections']['one_time']; @endphp
    @if (count($oneTime) > 0)
        {!! $sectionHead('#ece2f6', 'TAGIHAN SEKALI BAYAR') !!}
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 26%;">Jenis Pembayaran</th>
                    <th style="width: 22%;">Periode</th>
                    <th style="width: 13%;">Tagihan</th>
                    <th style="width: 12%;">Sudah Dibayar</th>
                    <th style="width: 12%;">Sisa</th>
                    <th style="width: 9%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($oneTime as $row)
                    <tr>
                        <td class="no-col">{{ $loop->iteration }}</td>
                        <td>{{ $row['payment_type_name'] }}</td>
                        <td class="period-col">{{ $row['period_label'] }}</td>
                        <td class="money">{{ $rupiah((float) $row['target']) }}</td>
                        <td class="money">{{ $dashIfZero((float) $row['paid']) }}</td>
                        <td class="money">{{ $dashIfZero((float) $row['remaining']) }}</td>
                        <td class="status"><span class="pill {{ $statusClass($row['status']) }}">{{ $row['status'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>