<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Kwitansi Pembayaran - Annur Management</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 2mm; color: #172033; background: #ffffff; font-family: "DejaVu Sans", sans-serif; font-size: 9px; }
        .receipt-wrapper { width: 100%; border: 1px solid #d8dee8; border-radius: 10px; overflow: hidden; page-break-inside: avoid; break-inside: avoid; }
        .header, .information { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header { background: #f8fafc; border-bottom: 1px solid #d8dee8; }
        .header td { width: 50%; padding: 5px 12px; vertical-align: top; }
        .brand-table { border-collapse: collapse; }
        .brand-table td { width: auto; padding: 0; vertical-align: middle; }
        .brand-logo-cell { width: 38px !important; }
        .brand-logo { width: auto; height: 34px; }
        .brand-copy { padding-left: 8px !important; }
        .eyebrow { color: #1d4ed8; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .title { margin-top: 2px; color: #172033; font-size: 14px; font-weight: bold; }
        .muted { margin-top: 2px; color: #64748b; font-size: 8px; }
        .align-right { text-align: right; }
        .receipt-number { margin-top: 2px; font-size: 12px; font-weight: bold; }
        .badge { display: inline-block; margin-top: 3px; padding: 2px 7px; border-radius: 8px; background: #dbeafe; color: #1e3a8a; font-size: 7px; }
        .information { border-bottom: 1px solid #d8dee8; }
        .information td { width: 50%; padding: 4px 12px; vertical-align: middle; }
        .info-label { color: #64748b; font-size: 7px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .info-title { margin-top: 2px; font-size: 10px; font-weight: bold; }
        .info-copy { margin-top: 2px; color: #64748b; font-size: 8px; }
        .details { padding: 4px 12px 3px; }
        .details-heading { margin-bottom: 3px; color: #64748b; font-size: 7px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .payment-table { width: 100%; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid; }
        .details-table th, .details-table td { padding: 3px 5px; border-bottom: 1px solid #e5e9f0; font-size: 8px; }
        .details-table th { color: #64748b; text-align: left; }
        .details-table .number { width: 34px; text-align: center; }
        .details-table .amount { width: 145px; text-align: right; }
        .detail-context { color: #64748b; font-weight: normal; }
        .details-table tfoot td { padding-top: 4px; padding-bottom: 4px; border-top: 2px solid #cbd5e1; border-bottom: 0; }
        .total-row { page-break-inside: avoid; break-inside: avoid; }
        .total-label { color: #64748b; font-size: 9px !important; font-weight: bold; text-align: right; text-transform: uppercase; }
        .total { color: #1d4ed8; font-size: 12px !important; font-weight: bold; text-align: right; }
        .notes { margin-top: 4px; padding: 4px 6px; border-radius: 5px; background: #f8fafc; color: #64748b; font-size: 8px; page-break-inside: avoid; break-inside: avoid; }
        .notes strong { color: #172033; }
        .receipt-footer { width: 100%; margin-top: 3px; padding: 2px 22px 6px 12px; page-break-inside: avoid; break-inside: avoid; }
        .footer-layout { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .thank-you-cell { width: 58%; padding: 0 0 8px; vertical-align: bottom; color: #64748b; font-size: 8px; line-height: 1.45; }
        .authorization-cell { width: 42%; padding: 0; vertical-align: top; text-align: center; }
        .authorization-block { position: relative; right: 36px; height: 100px; }
        .authorization-heading { width: 165px; margin-left: auto; }
        .authorization-date { white-space: nowrap; font-size: 9px; }
        .authorization-label { margin-top: 3px; font-size: 9px; font-weight: bold; }
        .authorization-stamp { position: absolute; top: 2px; left: 124px; width: 96px; height: auto; }
        .authorization-identity { position: absolute; right: 0; bottom: 5px; width: 128px; text-align: left; }
        .authorization-name { position: relative; z-index: 2; padding-bottom: 3px; border-bottom: 1px solid #94a3b8; font-size: 10px; font-weight: bold; }
        .authorization-role { position: relative; z-index: 2; margin-top: 2px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <div class="receipt receipt-wrapper">
        <table class="header">
            <tr>
                <td>
                    <table class="brand-table">
                        <tr>
                            <td class="brand-logo-cell"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="brand-logo"></td>
                            <td class="brand-copy"><div class="eyebrow">Annur Management</div><div class="title">KWITANSI PEMBAYARAN</div><div class="muted">Kategori: {{ $receipt['category'] }}</div></td>
                        </tr>
                    </table>
                </td>
                <td class="align-right">
                    <div class="info-label">No. Kwitansi</div>
                    <div class="receipt-number">{{ $receipt['receiptNumber'] }}</div>
                    <div class="badge">{{ $receipt['badge'] }}</div>
                    <div class="muted">{{ $receipt['paymentDate'] }}</div>
                </td>
            </tr>
        </table>

        <table class="information">
            <tr>
                <td>
                    <div class="info-label">{{ $receipt['identityLabel'] }}</div>
                    <div class="info-title">{{ $receipt['identityName'] }}</div>
                    <div class="info-copy">{{ $receipt['identityContext'] }}</div>
                </td>
                <td class="align-right">
                    <div class="info-label">Metode Pembayaran</div>
                    <div class="info-title">{{ $receipt['bankName'] }}</div>
                    @if($receipt['bankAccountNumber'])<div class="info-copy">{{ $receipt['bankAccountNumber'] }}</div>@endif
                    @if($receipt['bankAccountName'])<div class="info-copy">{{ $receipt['bankAccountName'] }}</div>@endif
                </td>
            </tr>
        </table>

        <div class="details">
            <div class="details-heading">Rincian Pembayaran</div>
            <table class="details-table payment-table">
                <thead><tr><th class="number">No.</th><th>Jenis Pembayaran</th><th class="amount">Nominal</th></tr></thead>
                <tbody>
                    @foreach($receipt['details'] as $detail)
                        <tr><td class="number">{{ $loop->iteration }}</td><td><strong>{{ $detail['name'] }}</strong>@if($detail['description']) <span class="detail-context">({{ $detail['description'] }})</span>@endif</td><td class="amount">Rp {{ number_format($detail['amount'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="total-row"><td colspan="2" class="total-label">Total Pembayaran</td><td class="total">Rp {{ number_format($receipt['total'], 0, ',', '.') }}</td></tr></tfoot>
            </table>
            @if($receipt['notes'])
                <div class="notes"><strong>Catatan:</strong> {{ $receipt['notes'] }}</div>
            @endif
        </div>
    </div>
    @if($receipt['creatorName'])
        <div class="receipt-footer">
            <table class="footer-layout">
                <tr>
                    <td class="thank-you-cell">
                        <div>Terima kasih atas kepercayaannya.</div>
                        <div>Semoga Allah memberikan keberkahan.</div>
                    </td>
                    <td class="authorization-cell">
                        <div class="authorization-block">
                            <div class="authorization-heading">
                                <div class="authorization-date">Bekasi, {{ $receipt['authorizationDate'] }}</div>
                                <div class="authorization-label">Pembuat Kwitansi</div>
                            </div>
                            <img src="{{ public_path('images/stample.png') }}" alt="Stempel resmi Annur" class="authorization-stamp">
                            <div class="authorization-identity">
                                <div class="authorization-name">{{ $receipt['creatorName'] }}</div>
                                <div class="authorization-role">Admin Keuangan</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif
</body>
</html>
