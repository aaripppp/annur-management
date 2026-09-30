<?php

use App\Models\AcademicYear;
use App\Models\User;

it('renders every school report tab over HTTP without a tampilkan button', function () {
    AcademicYear::deactivateAll();
    AcademicYear::query()->updateOrCreate(
        ['year' => '2026/2027'],
        [
            'is_active' => true,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ],
    );
    $this->actingAs(User::factory()->create());

    $content = $this->get(route('laporan.index', ['tab' => 'daily']))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('Dari Tanggal')
        ->assertSee('Unduh Excel')
        ->assertSee('Cetak PDF')
        ->getContent();

    $this->assertLessThan(strpos($content, 'Unduh Excel'), strpos($content, 'Cetak Riwayat Transaksi'));
    $this->assertLessThan(strpos($content, 'Cetak PDF'), strpos($content, 'Unduh Excel'));

    $this->get(route('laporan.index', [
        'tab' => 'monthly',
        'bulan' => 1,
        'tahun' => 2026,
        'tahun_ajaran' => '2026/2027',
    ]))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('Laporan Bulanan')
        ->assertSee('Tahun Ajaran')
        ->assertSee('Januari 2027')
        ->assertSee('Unduh Excel')
        ->assertSee('Cetak PDF');

    $this->get(route('laporan.index', ['tab' => 'bank']))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('Dari Tanggal')
        ->assertSee('Sampai Tanggal')
        ->assertSee('Unduh Excel');

    $this->get(route('laporan.index', [
        'tab' => 'target',
        'target_month' => 9,
        'target_year' => 2026,
        'target_academic_year' => '2026/2027',
    ]))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('Target & Tunggakan')
        ->assertSee('Unduh Excel')
        ->assertSee('Cetak PDF');

    $this->get(route('laporan.index', ['tab' => 'class']))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('Rekap Per Kelas');
});

it('renders every daycare report tab over HTTP without a tampilkan button', function () {
    $this->actingAs(User::factory()->create());

    $content = $this->get(route('daycare.report.daily', ['tab' => 'daily']))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('Dari Tanggal')
        ->assertSee('Unduh Excel')
        ->assertSee('Cetak PDF')
        ->getContent();

    $this->assertLessThan(strpos($content, 'Unduh Excel'), strpos($content, 'Cetak Riwayat Transaksi'));
    $this->assertLessThan(strpos($content, 'Cetak PDF'), strpos($content, 'Unduh Excel'));

    $this->get(route('daycare.report.daily', ['tab' => 'monthly']))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('Bulan')
        ->assertSee('Unduh Excel')
        ->assertSee('Cetak PDF');
});

it('renders the dashboard over HTTP without a tampilkan button', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Tampilkan')
        ->assertSee('UNIT')
        ->assertSee('PERIODE');
});
