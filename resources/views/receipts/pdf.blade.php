<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Kwitansi Pembayaran - YPI Nurrahim An-Nur</title>
    <style>
        @page { size: 216mm 330mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 2mm; color: #172033; background: #ffffff; font-family: "DejaVu Sans", sans-serif; font-size: 9px; }
        .receipt-wrapper { width: 100%; border: 1px solid #d8dee8; border-radius: 10px; overflow: hidden; page-break-inside: avoid; break-inside: avoid; }
        .header, .information { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header { background: #f8fafc; border-bottom: 1px solid #d8dee8; }
        .header td { width: 50%; padding: 8px 14px; vertical-align: top; }
        .brand-table { border-collapse: collapse; }
        .brand-table td { width: auto; padding: 0; vertical-align: middle; }
        .brand-logo-cell { width: 58px !important; }
        .brand-logo { width: auto; height: 52px; }
        .brand-copy { padding-left: 12px !important; }
        .eyebrow { color: #1d4ed8; font-size: 11px; font-weight: bold; letter-spacing: 1.5px; }
        .title { margin-top: 4px; color: #172033; font-size: 23px; font-weight: bold; }
        .muted { margin-top: 4px; color: #64748b; font-size: 13px; }
        .align-right { text-align: right; }
        .receipt-number { margin-top: 4px; font-size: 19px; font-weight: bold; }
        .badge { display: inline-block; margin-top: 5px; padding: 4px 10px; border-radius: 12px; background: #dbeafe; color: #1e3a8a; font-size: 11px; }
        .information { border-bottom: 1px solid #d8dee8; }
        .information td { width: 50%; padding: 7px 14px; vertical-align: middle; }
        .info-label { color: #64748b; font-size: 11px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .info-title { margin-top: 4px; font-size: 16px; font-weight: bold; }
        .info-copy { margin-top: 4px; color: #64748b; font-size: 13px; }
        .details { padding: 5px 14px 4px; }
        .details-heading { margin-bottom: 4px; color: #64748b; font-size: 11px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .payment-table { width: 100%; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid; }
        .details-table th, .details-table td { padding: 3px 5px; border-bottom: 1px solid #e5e9f0; font-size: 8.5px; }
        .details-table th { color: #64748b; text-align: left; }
        .details-table .number { width: 38px; text-align: center; }
        .details-table .amount { width: 160px; text-align: right; }
        .detail-context { color: #64748b; font-weight: normal; }
        .details-table tfoot td { padding-top: 6px; padding-bottom: 6px; border-top: 2px solid #cbd5e1; border-bottom: 0; }
        .total-row { page-break-inside: avoid; break-inside: avoid; }
        .total-label { color: #64748b; font-size: 14px !important; font-weight: bold; text-align: right; text-transform: uppercase; }
        .total { color: #1d4ed8; font-size: 19px !important; font-weight: bold; text-align: right; }
        .notes { margin-top: 6px; padding: 5px 10px; border-radius: 8px; background: #f8fafc; color: #64748b; font-size: 13px; page-break-inside: avoid; break-inside: avoid; }
        .notes strong { color: #172033; }
        .receipt-footer { width: 100%; margin-top: 5px; padding: 4px 30px 12px 12px; page-break-inside: avoid; break-inside: avoid; }
        .footer-layout { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .thank-you-cell { width: 58%; padding: 0 0 10px; vertical-align: bottom; color: #64748b; font-size: 13px; line-height: 1.5; }
        .authorization-cell { width: 42%; padding: 0; vertical-align: top; text-align: center; }
        .authorization-block { position: relative; right: 40px; height: 140px; }
        .authorization-heading { width: 240px; margin-left: auto; }
        .authorization-date { white-space: nowrap; font-size: 14px; }
        .authorization-label { margin-top: 5px; font-size: 15px; font-weight: bold; }
        .authorization-stamp { position: absolute; top: 16px; left: 68px; width: 118px; height: auto; }
        .authorization-identity { position: absolute; right: 8px; bottom: 6px; width: 152px; text-align: left; }
        .authorization-name { position: relative; z-index: 2; padding-top: 5px; border-top: 1px solid #94a3b8; font-size: 16px; font-weight: bold; }
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
                            <td class="brand-copy"><div class="eyebrow">YPI Nurrahim An-Nur</div><div class="title">KWITANSI PEMBAYARAN</div><div class="muted">Kategori: {{ $receipt['category'] }}</div></td>
                        </tr>
                    </table>
                </td>
                <td class="align-right">
                    <div class="info-label">No. Kwitansi</div>
                    <div class="receipt-number">{{ $receipt['receiptNumber'] }}</div>
                    <div class="badge">{{ $receipt['badge'] }}</div>
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
                    <div class="info-title">{{ $receipt['bankName'] }}@if(filled($receipt['bankAccountNumber'])) - {{ $receipt['bankAccountNumber'] }}@endif</div>
                    <div class="info-copy payment-date">{{ $receipt['paymentDate'] }}</div>
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
                                <div class="authorization-label">{{ $receipt['creatorPosition'] }}</div>
                            </div>
                            <img src="{{ public_path('images/stample.png') }}" alt="Stempel resmi Annur" class="authorization-stamp">
                            <div class="authorization-identity">
                                <div class="authorization-name">{{ $receipt['creatorName'] }}</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif
</body>
</html>
