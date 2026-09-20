<?php

use App\Livewire\Dashboard;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function makeDashboardStudentPayment(Bank $bank, User $user, string $name, string $description, int $amount, CarbonInterface $createdAt, ?string $paymentDate = null): Payment
{
    $student = Student::factory()->create(['nama_lengkap' => $name]);
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-ST-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate ?? $createdAt->format('Y-m-d'),
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'description' => $description,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $payment;
}

function makeDashboardDaycarePayment(Bank $bank, User $user, string $name, array $descriptions, int $amount, CarbonInterface $createdAt): DaycarePayment
{
    $child = DaycareChild::factory()->create(['nama_lengkap' => $name, 'kelas' => 'C']);
    $payment = DaycarePayment::factory()->create([
        'receipt_number' => 'KWT-DC-'.uniqid(),
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => $createdAt->format('Y-m-d'),
        'total_amount' => $amount,
        'created_by' => $user->id,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    foreach ($descriptions as $index => $description) {
        DaycarePaymentDetail::factory()->create([
            'daycare_payment_id' => $payment->id,
            'description' => $description,
            'amount' => intdiv($amount, count($descriptions)) + ($index === 0 ? $amount % count($descriptions) : 0),
        ]);
    }

    return $payment;
}

it('menampilkan transaksi Siswa dan Daycare beserta badge data dan link yang tepat', function () {
    $bank = Bank::factory()->create(['name' => 'BSI']);
    $user = User::factory()->create(['name' => 'Admin Annur']);
    $studentPayment = makeDashboardStudentPayment($bank, $user, 'Muhammad Ridwan', 'SPP + OSIS', 975000, Carbon::parse('2026-08-25 15:20:00'));
    $daycarePayment = makeDashboardDaycarePayment($bank, $user, 'Ahmad Fauzan', ['Penitipan Agustus', 'Kegiatan Daycare'], 875000, Carbon::parse('2026-08-25 15:30:00'));

    $component = Livewire::test(Dashboard::class)
        ->assertSee('Tanggal TF')
        ->assertSeeHtml('class="inline-flex items-center gap-1.5 whitespace-nowrap"')
        ->assertDontSeeHtml('class="flex flex-wrap items-center gap-1.5"')
        ->assertSee('Muhammad Ridwan')
        ->assertSee('Siswa')
        ->assertSee('SPP + OSIS')
        ->assertSee('Rp 975.000')
        ->assertSee('Ahmad Fauzan')
        ->assertSee('Daycare')
        ->assertSeeHtml('bg-blue-100 text-blue-800">Siswa</span>')
        ->assertSeeHtml('bg-purple-100 text-purple-800">Daycare</span>')
        ->assertSee('Penitipan Agustus + Kegiatan Daycare')
        ->assertSee('Rp 875.000')
        ->assertSee('BSI')
        ->assertSeeHtml('href="'.route('pembayaran.show', $studentPayment).'"')
        ->assertSeeHtml('href="'.route('daycare.payment.show', $daycarePayment).'"');

    $document = new DOMDocument;
    $previousUseInternalErrors = libxml_use_internal_errors(true);
    $document->loadHTML($component->html());
    libxml_clear_errors();
    libxml_use_internal_errors($previousUseInternalErrors);
    $xpath = new DOMXPath($document);
    $studentRow = '//tr[td[2][contains(normalize-space(.), "'.$studentPayment->receipt_number.'")]]';
    $daycareRow = '//tr[td[2][contains(normalize-space(.), "'.$daycarePayment->receipt_number.'")]]';

    expect($xpath->query($studentRow.'/td[2]//span[normalize-space()="Siswa"]')->length)->toBe(1)
        ->and($xpath->query($studentRow.'/td[4]//span[normalize-space()="Siswa"]')->length)->toBe(0)
        ->and($xpath->query($daycareRow.'/td[2]//span[normalize-space()="Daycare"]')->length)->toBe(1)
        ->and($xpath->query($daycareRow.'/td[4]//span[normalize-space()="Daycare"]')->length)->toBe(0);
});

it('merekap Student ke BSI dan Daycare ke BCA secara terpisah', function () {
    $bsi = Bank::factory()->create(['name' => 'BSI', 'is_active' => true]);
    $bca = Bank::factory()->create(['name' => 'BCA', 'is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bsi, $user, 'Siswa BSI', 'SPP', 1035000, now());
    makeDashboardDaycarePayment($bca, $user, 'Daycare BCA', ['Penitipan'], 1500000, now());

    $component = Livewire::test(Dashboard::class)
        ->assertSee('Rp 1.035.000')
        ->assertSee('Rp 1.500.000');
    $bankTotals = $component->viewData('bankTotals');

    expect($bankTotals->get($bsi->id))->toMatchArray([
        'student_total' => 1035000.0,
        'daycare_total' => 0.0,
        'combined_total' => 1035000.0,
    ])->and($bankTotals->get($bca->id))->toMatchArray([
        'student_total' => 0.0,
        'daycare_total' => 1500000.0,
        'combined_total' => 1500000.0,
    ]);
});

it('menghitung Total Pemasukan Student-only', function () {
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bank, $user, 'Siswa', 'SPP', 1035000, now());

    Livewire::test(Dashboard::class)
        ->assertSee('Rp 1.035.000')
        ->assertViewHas('totalPemasukan', 1035000.0);
});

it('menghitung Total Transaksi Student-only', function () {
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bank, $user, 'Siswa Pertama', 'SPP', 100000, now());
    makeDashboardStudentPayment($bank, $user, 'Siswa Kedua', 'SPP', 200000, now());

    Livewire::test(Dashboard::class)
        ->assertSee('2 Transaksi')
        ->assertViewHas('totalTransaksi', 2);
});

it('menghitung Total Pemasukan Daycare-only', function () {
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    makeDashboardDaycarePayment($bank, $user, 'Anak Daycare', ['Penitipan'], 1500000, now());

    Livewire::test(Dashboard::class)
        ->assertSee('Rp 1.500.000')
        ->assertViewHas('totalPemasukan', 1500000.0);
});

it('menghitung Total Transaksi Daycare-only', function () {
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    makeDashboardDaycarePayment($bank, $user, 'Daycare Pertama', ['Penitipan'], 100000, now());
    makeDashboardDaycarePayment($bank, $user, 'Daycare Kedua', ['Penitipan'], 200000, now());

    Livewire::test(Dashboard::class)
        ->assertSee('2 Transaksi')
        ->assertViewHas('totalTransaksi', 2);
});

it('menjumlahkan Total Pemasukan Student dan Daycare', function () {
    $bsi = Bank::factory()->create(['is_active' => true]);
    $bca = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bsi, $user, 'Siswa', 'SPP', 1035000, now());
    makeDashboardDaycarePayment($bca, $user, 'Anak Daycare', ['Penitipan'], 1500000, now());

    Livewire::test(Dashboard::class)
        ->assertSee('Rp 2.535.000')
        ->assertViewHas('totalPemasukan', 2535000.0);
});

it('menjumlahkan Total Transaksi Student dan Daycare', function () {
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bank, $user, 'Siswa', 'SPP', 100000, now());
    makeDashboardDaycarePayment($bank, $user, 'Daycare Pertama', ['Penitipan'], 200000, now());
    makeDashboardDaycarePayment($bank, $user, 'Daycare Kedua', ['Kegiatan'], 300000, now());

    Livewire::test(Dashboard::class)
        ->assertSee('3 Transaksi')
        ->assertViewHas('totalTransaksi', 3);
});

it('menjumlahkan Student dan beberapa Daycare pada Bank yang sama', function () {
    $bsi = Bank::factory()->create(['name' => 'BSI', 'is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bsi, $user, 'Siswa BSI', 'SPP', 500000, now());
    makeDashboardDaycarePayment($bsi, $user, 'Daycare Pertama', ['Penitipan'], 200000, now());
    makeDashboardDaycarePayment($bsi, $user, 'Daycare Kedua', ['Kegiatan'], 335000, now());

    $component = Livewire::test(Dashboard::class)->assertSee('Rp 1.035.000');
    $totals = $component->viewData('bankTotals')->get($bsi->id);

    expect($totals)->toMatchArray([
        'student_total' => 500000.0,
        'daycare_total' => 535000.0,
        'combined_total' => 1035000.0,
    ]);
});

it('includes cash in income count recap and recent transactions by Bank type', function () {
    $cash = Bank::factory()->cash()->create(['name' => 'Kas Utama', 'is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($cash, $user, 'Siswa Tunai', 'SPP', 400000, now());
    makeDashboardDaycarePayment($cash, $user, 'Daycare Tunai', ['Penitipan'], 600000, now());

    $component = Livewire::test(Dashboard::class)
        ->assertViewHas('totalPemasukan', 1000000.0)
        ->assertViewHas('totalTransaksi', 2)
        ->assertSee('Kas Utama')
        ->assertSee('Tunai / Cash')
        ->assertSee('Tunai')
        ->assertSee('Rp 1.000.000');

    expect($component->viewData('bankTotals')->get($cash->id))->toMatchArray([
        'student_total' => 400000.0,
        'daycare_total' => 600000.0,
        'combined_total' => 1000000.0,
    ]);
});

it('mempertahankan semantic Student dengan hanya menghitung pembayaran aktif', function () {
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bank, $user, 'Siswa Aktif', 'SPP', 400000, now());
    $cancelled = makeDashboardStudentPayment($bank, $user, 'Siswa Batal', 'SPP', 600000, now());
    $cancelled->update(['status' => Payment::STATUS_CANCELLED]);

    $component = Livewire::test(Dashboard::class)->assertViewHas('totalTransaksi', 1);
    $totals = $component->viewData('bankTotals')->get($bank->id);

    expect($totals)->toMatchArray([
        'student_total' => 400000.0,
        'daycare_total' => 0.0,
        'combined_total' => 400000.0,
    ]);
});

it('mengurutkan transaksi kedua domain secara global berdasarkan waktu terbaru', function () {
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    makeDashboardStudentPayment($bank, $user, 'Siswa Lama', 'SPP', 100000, Carbon::parse('2026-08-25 14:30:00'));
    makeDashboardDaycarePayment($bank, $user, 'Daycare Tengah', ['Penitipan'], 200000, Carbon::parse('2026-08-25 14:55:00'));
    makeDashboardStudentPayment($bank, $user, 'Siswa Baru', 'Kegiatan', 300000, Carbon::parse('2026-08-25 15:20:00'));
    makeDashboardDaycarePayment($bank, $user, 'Daycare Terbaru', ['Kegiatan Daycare'], 400000, Carbon::parse('2026-08-25 15:30:00'));

    Livewire::test(Dashboard::class)
        ->assertSeeInOrder(['Daycare Terbaru', 'Siswa Baru', 'Daycare Tengah', 'Siswa Lama']);
});

it('menampilkan Tanggal TF dari payment_date sambil tetap mengurutkan berdasarkan created_at', function () {
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $recordedEarlier = makeDashboardStudentPayment($bank, $user, 'Transfer Baru', 'SPP', 100000, Carbon::parse('2026-09-07 13:00:00'), '2026-09-07');
    $recordedLater = makeDashboardStudentPayment($bank, $user, 'Transfer Lama Dicatat Belakangan', 'SPP', 200000, Carbon::parse('2026-09-07 14:48:00'), '2026-08-24');

    Livewire::test(Dashboard::class)
        ->assertSee('Tanggal TF')
        ->assertSee('24 Agt 2026')
        ->assertSee('07 Sep 2026')
        ->assertSeeInOrder([$recordedLater->receipt_number, $recordedEarlier->receipt_number])
        ->assertDontSee('14:48 WIB')
        ->assertDontSee('13:00 WIB');
});

it('menggunakan source eksplisit saat id Student dan Daycare sama', function () {
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $studentPayment = makeDashboardStudentPayment($bank, $user, 'Siswa ID Sama', 'SPP', 100000, now());
    $daycarePayment = makeDashboardDaycarePayment($bank, $user, 'Daycare ID Sama', ['Penitipan'], 200000, now());

    expect($studentPayment->id)->toBe($daycarePayment->id);

    Livewire::test(Dashboard::class)
        ->assertSeeHtml('wire:key="recent-transaction-student-'.$studentPayment->id.'"')
        ->assertSeeHtml('wire:key="recent-transaction-daycare-'.$daycarePayment->id.'"')
        ->assertSeeHtml('href="'.route('pembayaran.show', $studentPayment).'"')
        ->assertSeeHtml('href="'.route('daycare.payment.show', $daycarePayment).'"');
});

it('meringkas lebih dari dua rincian Daycare secara compact', function () {
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    makeDashboardDaycarePayment($bank, $user, 'Anak Daycare', ['Penitipan Agustus', 'Kegiatan', 'Konsumsi'], 300000, now());

    Livewire::test(Dashboard::class)
        ->assertSee('Penitipan Agustus + 2 lainnya')
        ->assertDontSee('Penitipan Agustus + Kegiatan + Konsumsi');
});

it('membatasi gabungan transaksi terbaru menjadi sepuluh row', function () {
    $bank = Bank::factory()->create();
    $user = User::factory()->create();

    foreach (range(1, 6) as $index) {
        makeDashboardStudentPayment($bank, $user, 'Siswa '.$index, 'SPP '.$index, 100000, now()->subMinutes($index * 2));
        makeDashboardDaycarePayment($bank, $user, 'Daycare '.$index, ['Item '.$index], 100000, now()->subMinutes($index * 2 - 1));
    }

    $component = Livewire::test(Dashboard::class);

    expect(substr_count($component->html(), 'wire:key="recent-transaction-'))->toBe(10);
});

it('render Dashboard tidak memutasi domain dan menggabungkan summary serta rekap Bank', function () {
    $bank = Bank::factory()->create(['name' => 'Bank Terpisah']);
    $user = User::factory()->create();
    makeDashboardStudentPayment($bank, $user, 'Siswa Summary', 'SPP', 100000, now());
    makeDashboardDaycarePayment($bank, $user, 'Daycare Summary', ['Penitipan'], 200000, now());
    $studentCount = Payment::query()->count();
    $daycareCount = DaycarePayment::query()->count();
    $detailCount = DaycarePaymentDetail::query()->count();
    $studentRecords = Payment::query()->get()->toArray();
    $daycareRecords = DaycarePayment::query()->get()->toArray();
    $detailRecords = DaycarePaymentDetail::query()->get()->toArray();

    $component = Livewire::test(Dashboard::class);
    $dashboardBanks = $component->viewData('banks');

    expect(Payment::query()->count())->toBe($studentCount)
        ->and(DaycarePayment::query()->count())->toBe($daycareCount)
        ->and(DaycarePaymentDetail::query()->count())->toBe($detailCount)
        ->and(Payment::query()->get()->toArray())->toBe($studentRecords)
        ->and(DaycarePayment::query()->get()->toArray())->toBe($daycareRecords)
        ->and(DaycarePaymentDetail::query()->get()->toArray())->toBe($detailRecords)
        ->and((float) $component->viewData('totalPemasukan'))->toBe(300000.0)
        ->and($component->viewData('totalTransaksi'))->toBe(2)
        ->and($component->viewData('bankTotals')->get($bank->id))->toMatchArray([
            'student_total' => 100000.0,
            'daycare_total' => 200000.0,
            'combined_total' => 300000.0,
        ])
        ->and($dashboardBanks->first()->id)->toBe($bank->id);
});
