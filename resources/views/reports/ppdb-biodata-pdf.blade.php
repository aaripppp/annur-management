<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Biodata Calon Siswa PPDB - {{ $registration_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 9mm 9mm 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1b2733; font-family: "DejaVu Sans", sans-serif; font-size: 8pt; line-height: 1.22; }
        .header { width: 100%; margin-bottom: 5px; border-collapse: collapse; border-bottom: 1.2pt solid #173a5e; page-break-after: avoid; }
        .header td { padding: 0 0 4px; vertical-align: middle; }
        .logo-cell { width: 15%; }
        .logo { width: auto; height: 34px; }
        .title-cell { width: 64%; text-align: center; }
        .brand { color: #173a5e; font-size: 7.2pt; font-weight: bold; letter-spacing: 0.5px; }
        .title { margin-top: 1px; color: #173a5e; font-size: 11pt; font-weight: bold; letter-spacing: 0.3px; }
        .subtitle { margin-top: 1px; color: #53697d; font-size: 6.6pt; }
        .registration-cell { width: 21%; text-align: right; }
        .registration-box { display: inline-block; padding: 3px 6px; border: 0.5pt solid #a9b8c6; background: #f4f7fa; text-align: left; }
        .registration-label { color: #53697d; font-size: 6.2pt; text-transform: uppercase; letter-spacing: 0.3px; }
        .registration-number { margin-top: 1px; color: #173a5e; font-size: 8pt; font-weight: bold; word-wrap: break-word; }
        .section { margin-top: 5px; }
        .section-title { padding: 2px 5px; border: 0.5pt solid #173a5e; background: #173a5e; color: #fff; font-size: 8pt; font-weight: bold; page-break-after: avoid; break-after: avoid; }
        .card-title { padding: 2px 5px; border: 0.5pt solid #a9b8c6; border-bottom: 0; background: #e8f0f7; color: #173a5e; font-size: 7.6pt; font-weight: bold; page-break-after: avoid; break-after: avoid; }
        .subsection-title { padding: 2px 5px; border: 0.5pt solid #a9b8c6; border-bottom: 0; background: #e8f0f7; color: #173a5e; font-size: 7.6pt; font-weight: bold; page-break-after: avoid; break-after: avoid; }
        table.layout { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.layout > tbody > tr > td { padding: 0; vertical-align: top; }
        table.kv, table.data, table.siblings { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.kv td, table.data td, table.siblings th, table.siblings td { padding: 1.5px 4px; border: 0.5pt solid #a9b8c6; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        table.kv td.label, table.data td.label { background: #f4f7fa; color: #34495e; font-size: 7.4pt; font-weight: bold; }
        table.data td.label { width: 38%; }
        table.data td.value { width: 62%; }
        table.kv td.label { width: 18%; }
        table.kv td.value { width: 32%; }
        .pre-line { white-space: pre-line; }
        .side-by-side { page-break-inside: avoid; break-inside: avoid; }
        .parents-group { page-break-inside: avoid; break-inside: avoid; }
        .parents-row { page-break-inside: avoid; break-inside: avoid; }
        .parent-card { page-break-inside: avoid; break-inside: avoid; }
        .guardian-block { page-break-inside: avoid; break-inside: avoid; }
        table.siblings thead { display: table-header-group; }
        table.siblings th { background: #e8f0f7; color: #173a5e; font-size: 7.4pt; text-align: left; }
        table.siblings tr { page-break-inside: avoid; break-inside: avoid; }
        table.siblings td.number { width: 5%; text-align: center; }
        table.siblings th:nth-child(2), table.siblings td:nth-child(2) { width: 33%; }
        table.siblings th:nth-child(3), table.siblings td:nth-child(3) { width: 18%; }
        table.siblings th:nth-child(4), table.siblings td:nth-child(4) { width: 15%; }
        table.siblings th:nth-child(5), table.siblings td:nth-child(5) { width: 29%; }
        .empty-row { text-align: center; color: #53697d; }
        .footer { margin-top: 7px; padding-top: 3px; border-top: 0.5pt solid #a9b8c6; color: #53697d; font-size: 6.8pt; page-break-inside: avoid; break-inside: avoid; }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { padding: 0; vertical-align: top; }
        .footer-right { text-align: right; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="logo-cell"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
            <td class="title-cell">
                <div class="brand">YPI NURRAHIM AN-NUR</div>
                <div class="title">BIODATA CALON SISWA PPDB</div>
                <div class="subtitle">Dokumen biodata pendaftaran peserta didik</div>
            </td>
            <td class="registration-cell">
                <div class="registration-box">
                    <div class="registration-label">No. Pendaftaran</div>
                    <div class="registration-number">{{ $registration['number'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">A. INFORMASI PENDAFTARAN</div>
        <table class="kv">
            <colgroup>
                <col style="width: 17%">
                <col style="width: 33%">
                <col style="width: 17%">
                <col style="width: 33%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="label">Nomor Pendaftaran</td>
                    <td class="value">{{ $registration['number'] }}</td>
                    <td class="label">Tahun Ajaran</td>
                    <td class="value">{{ $registration['academic_year'] }}</td>
                </tr>
                <tr>
                    <td class="label">Jenjang Tujuan</td>
                    <td class="value">{{ $registration['target_level'] }}</td>
                    <td class="label">Kelas Tujuan</td>
                    <td class="value">{{ $registration['target_class'] }}</td>
                </tr>
                <tr>
                    <td class="label">Kategori Pendaftaran</td>
                    <td class="value" colspan="3">{{ $registration['category'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">B. IDENTITAS CALON SISWA</div>
        <table class="kv">
            <colgroup>
                <col style="width: 17%">
                <col style="width: 33%">
                <col style="width: 17%">
                <col style="width: 33%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="label">Nama Lengkap</td>
                    <td class="value">{{ $identity['nama_lengkap'] }}</td>
                    <td class="label">Nama Panggilan</td>
                    <td class="value">{{ $identity['nama_panggilan'] }}</td>
                </tr>
                <tr>
                    <td class="label">Jenis Kelamin</td>
                    <td class="value">{{ $identity['jenis_kelamin'] }}</td>
                    <td class="label">NIK Siswa</td>
                    <td class="value">{{ $identity['nik_siswa'] }}</td>
                </tr>
                <tr>
                    <td class="label">Nomor KK</td>
                    <td class="value">{{ $identity['nomor_kk'] }}</td>
                    <td class="label">Nomor Akta Kelahiran</td>
                    <td class="value">{{ $identity['nomor_akta_kelahiran'] }}</td>
                </tr>
                <tr>
                    <td class="label">Tempat Lahir</td>
                    <td class="value">{{ $identity['tempat_lahir'] }}</td>
                    <td class="label">Tanggal Lahir</td>
                    <td class="value">{{ $identity['tanggal_lahir'] }}</td>
                </tr>
                <tr>
                    <td class="label">Asal Sekolah</td>
                    <td class="value">{{ $identity['asal_sekolah'] }}</td>
                    <td class="label">Anak ke-</td>
                    <td class="value">{{ $identity['anak_ke'] }}</td>
                </tr>
                <tr>
                    <td class="label">Jumlah Saudara</td>
                    <td class="value">{{ $identity['jumlah_saudara'] }}</td>
                    <td class="label">Status Anak</td>
                    <td class="value">{{ $identity['status_anak'] }}</td>
                </tr>
                <tr>
                    <td class="label">Agama</td>
                    <td class="value">{{ $identity['agama'] }}</td>
                    <td class="label">Bahasa Sehari-hari</td>
                    <td class="value">{{ $identity['bahasa_sehari_hari'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section side-by-side">
        <table class="layout">
            <tr>
                <td style="width: 49%">
                    <div class="section-title">C. ALAMAT TEMPAT TINGGAL</div>
                    <table class="data">
                        <tr><td class="label">Alamat Lengkap</td><td class="value pre-line">{{ $address['alamat'] }}</td></tr>
                        <tr><td class="label">RT</td><td class="value">{{ $address['rt'] }}</td></tr>
                        <tr><td class="label">RW</td><td class="value">{{ $address['rw'] }}</td></tr>
                        <tr><td class="label">Kelurahan / Desa</td><td class="value">{{ $address['kelurahan'] }}</td></tr>
                        <tr><td class="label">Kecamatan</td><td class="value">{{ $address['kecamatan'] }}</td></tr>
                        <tr><td class="label">Kabupaten / Kota</td><td class="value">{{ $address['kabupaten_kota'] }}</td></tr>
                        <tr><td class="label">Provinsi</td><td class="value">{{ $address['provinsi'] }}</td></tr>
                        <tr><td class="label">Kode Pos</td><td class="value">{{ $address['kode_pos'] }}</td></tr>
                    </table>
                </td>
                <td style="width: 2%"></td>
                <td style="width: 49%">
                    <div class="section-title">D. KEADAAN JASMANI &amp; KESEHATAN</div>
                    <table class="data">
                        <tr><td class="label">Tinggi Badan (cm)</td><td class="value">{{ $health['tinggi_badan'] }}</td></tr>
                        <tr><td class="label">Berat Badan (kg)</td><td class="value">{{ $health['berat_badan'] }}</td></tr>
                        <tr><td class="label">Lingkar Kepala (cm)</td><td class="value">{{ $health['lingkar_kepala'] }}</td></tr>
                        <tr><td class="label">Golongan Darah</td><td class="value">{{ $health['golongan_darah'] }}</td></tr>
                        <tr><td class="label">Riwayat Penyakit</td><td class="value pre-line">{{ $health['riwayat_penyakit'] }}</td></tr>
                        <tr><td class="label">Berkebutuhan Khusus</td><td class="value">{{ $health['berkebutuhan_khusus'] }}</td></tr>
                        @if($health['berkebutuhan_khusus'] === 'Ya')
                            <tr><td class="label">Keterangan Kebutuhan Khusus</td><td class="value pre-line">{{ $health['keterangan_kebutuhan_khusus'] }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="parents-group">
            <div class="section-title">E. DATA ORANG TUA / WALI</div>
            <table class="layout parents-row">
                <tr>
                    <td class="parent-card" style="width: 49%">
                        <div class="card-title">Ayah</div>
                        <table class="data">
                            <tr><td class="label">Nama</td><td class="value">{{ $father['nama'] }}</td></tr>
                            <tr><td class="label">NIK</td><td class="value">{{ $father['nik'] }}</td></tr>
                            <tr><td class="label">Tempat Lahir</td><td class="value">{{ $father['tempat_lahir'] }}</td></tr>
                            <tr><td class="label">Tanggal Lahir</td><td class="value">{{ $father['tanggal_lahir'] }}</td></tr>
                            <tr><td class="label">Pendidikan Terakhir</td><td class="value">{{ $father['pendidikan_terakhir'] }}</td></tr>
                            <tr><td class="label">Pekerjaan</td><td class="value">{{ $father['pekerjaan'] }}</td></tr>
                            <tr><td class="label">Penghasilan</td><td class="value">{{ $father['penghasilan'] }}</td></tr>
                            <tr><td class="label">Nomor WhatsApp</td><td class="value">{{ $father['no_telp'] }}</td></tr>
                            <tr><td class="label">Email</td><td class="value">{{ $father['email'] }}</td></tr>
                        </table>
                    </td>
                    <td style="width: 2%"></td>
                    <td class="parent-card" style="width: 49%">
                        <div class="card-title">Ibu</div>
                        <table class="data">
                            <tr><td class="label">Nama</td><td class="value">{{ $mother['nama'] }}</td></tr>
                            <tr><td class="label">NIK</td><td class="value">{{ $mother['nik'] }}</td></tr>
                            <tr><td class="label">Tempat Lahir</td><td class="value">{{ $mother['tempat_lahir'] }}</td></tr>
                            <tr><td class="label">Tanggal Lahir</td><td class="value">{{ $mother['tanggal_lahir'] }}</td></tr>
                            <tr><td class="label">Pendidikan Terakhir</td><td class="value">{{ $mother['pendidikan_terakhir'] }}</td></tr>
                            <tr><td class="label">Pekerjaan</td><td class="value">{{ $mother['pekerjaan'] }}</td></tr>
                            <tr><td class="label">Penghasilan</td><td class="value">{{ $mother['penghasilan'] }}</td></tr>
                            <tr><td class="label">Nomor WhatsApp</td><td class="value">{{ $mother['no_telp'] }}</td></tr>
                            <tr><td class="label">Email</td><td class="value">{{ $mother['email'] }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        @if($guardian !== null)
            <div class="guardian-block">
                <div class="subsection-title">Wali</div>
                <table class="kv">
                    <colgroup>
                        <col style="width: 17%">
                        <col style="width: 33%">
                        <col style="width: 17%">
                        <col style="width: 33%">
                    </colgroup>
                    <tbody>
                        <tr>
                            <td class="label">Nama</td>
                            <td class="value">{{ $guardian['nama'] }}</td>
                            <td class="label">NIK</td>
                            <td class="value">{{ $guardian['nik'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Tempat Lahir</td>
                            <td class="value">{{ $guardian['tempat_lahir'] }}</td>
                            <td class="label">Tanggal Lahir</td>
                            <td class="value">{{ $guardian['tanggal_lahir'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Pendidikan Terakhir</td>
                            <td class="value">{{ $guardian['pendidikan_terakhir'] }}</td>
                            <td class="label">Pekerjaan</td>
                            <td class="value">{{ $guardian['pekerjaan'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Penghasilan</td>
                            <td class="value">{{ $guardian['penghasilan'] }}</td>
                            <td class="label">Nomor WhatsApp</td>
                            <td class="value">{{ $guardian['no_telp'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Email</td>
                            <td class="value" colspan="3">{{ $guardian['email'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">F. DATA SAUDARA KANDUNG</div>
        <table class="siblings">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Lengkap</th>
                    <th>Tanggal Lahir</th>
                    <th>Jenis Kelamin</th>
                    <th>Pendidikan / Pekerjaan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($siblings as $sibling)
                    <tr>
                        <td class="number">{{ $loop->iteration }}</td>
                        <td>{{ $sibling['nama_lengkap'] }}</td>
                        <td>{{ $sibling['tanggal_lahir'] }}</td>
                        <td>{{ $sibling['jenis_kelamin'] }}</td>
                        <td>{{ $sibling['pendidikan_pekerjaan'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-row">Tidak ada data saudara kandung.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>Dicetak pada {{ $printed_at }}</td>
                <td class="footer-right">Biodata PPDB {{ $registration_number }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
