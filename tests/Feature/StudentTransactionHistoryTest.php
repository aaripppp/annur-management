<?php

use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

function makeHistoryStudent(array $overrides = []): Student
{
    return Student::factory()->create(array_merge([
        'nama_lengkap' => 'Rizky Pratama',
        'nama_panggilan' => 'Rizky',
        'nis' => 'NIS-HISTORY-001',
    ], $overrides));
}

function makeHistoryDaycareChild(array $overrides = []): DaycareChild
{
    return DaycareChild::factory()->create(array_merge([
        'nama_lengkap' => 'Aisyah Putri',
        'nama_panggilan' => 'Aisyah',
        'kelas' => 'A',
    ], $overrides));
}

function makeHistoryStudentPayment(Student $student, Bank $bank, User $user, string $receiptNumber, string $paymentDate, string $kind = Payment::KIND_BILL, string $status = Payment::STATUS_ACTIVE, ?string $createdAt = null): Payment
{
    $payment = Payment::create([
        'receipt_number' => $receiptNumber,
        'payment_kind' => $kind,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => 500000,
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);

    if ($kind === Payment::KIND_MANUAL) {
        PaymentDetail::create([
            'payment_id' => $payment->id,
            'bill_id' => null,
            'payment_type_id' => null,
            'description' => 'Donasi',
            'amount' => 500000,
        ]);
    }

    if ($createdAt !== null) {
        $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
    }

    return $payment->refresh();
}

function makeHistoryDaycarePayment(DaycareChild $child, Bank $bank, User $user, string $receiptNumber, string $paymentDate, float $amount = 1500000): DaycarePayment
{
    $payment = DaycarePayment::factory()->create([
        'receipt_number' => $receiptNumber,
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'created_by' => $user->id,
    ]);

    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $payment->id,
        'description' => 'Penitipan Agustus',
        'amount' => $amount,
    ]);

    return $payment;
}

it('riwayat siswa hanya menampilkan transaksi Student bill', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-2026-000001', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Tanggal TF')
        ->assertSeeHtml('class="inline-flex items-center gap-1.5 whitespace-nowrap"')
        ->assertDontSeeHtml('class="flex flex-wrap items-center gap-1.5"')
        ->assertSee('KWT-2026-000001')
        ->assertSee('Tagihan')
        ->assertSee($student->nama_lengkap);
});

it('menampilkan transaksi siswa manual dalam riwayat', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-2026-000002', '2026-08-26', Payment::KIND_MANUAL);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-2026-000002')
        ->assertSee('Manual')
        ->assertSee($student->nama_lengkap);
});

it('identitas siswa menampilkan NIS dan kelas tanpa label daycare', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-ID-STUDENT', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('NIS '.$student->nis)
        ->assertSee('Kelas')
        ->assertDontSee('Daycare •');
});

it('detail display siswa bill menggunakan payment description', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    $payment = makeHistoryStudentPayment($student, $bank, $user, 'KWT-DETAIL-001', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Rp '.number_format($payment->total_amount, 0, ',', '.'));
});

it('mengurutkan riwayat siswa berdasarkan created_at terbaru meski payment_date lebih lama', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-RECORDED-EARLIER', '2026-09-07', createdAt: now()->toDateString().' 09:00:00');
    makeHistoryStudentPayment($student, $bank, $user, 'KWT-RECORDED-LATER', '2026-09-05', createdAt: now()->toDateString().' 10:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Tanggal TF')
        ->assertSee('05 Sep 2026')
        ->assertSee('07 Sep 2026')
        ->assertSeeInOrder([
            'KWT-RECORDED-LATER',
            'KWT-RECORDED-EARLIER',
        ]);
});

it('mengurutkan payment_date yang sama berdasarkan created_at terbaru', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-SAME-DATE-EARLY', '2026-09-07', createdAt: now()->toDateString().' 09:00:00');
    makeHistoryStudentPayment($student, $bank, $user, 'KWT-SAME-DATE-LATE', '2026-09-07', createdAt: now()->toDateString().' 10:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeInOrder(['KWT-SAME-DATE-LATE', 'KWT-SAME-DATE-EARLY']);
});

it('menggunakan id terbesar sebagai tie-breaker created_at yang sama', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    $first = makeHistoryStudentPayment($student, $bank, $user, 'KWT-SAME-TIME-FIRST', '2026-09-07', createdAt: now()->toDateString().' 10:00:00');
    $second = makeHistoryStudentPayment($student, $bank, $user, 'KWT-SAME-TIME-SECOND', '2026-09-05', createdAt: now()->toDateString().' 10:00:00');

    expect($second->id)->toBeGreaterThan($first->id);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeInOrder(['KWT-SAME-TIME-SECOND', 'KWT-SAME-TIME-FIRST']);
});

it('mencari berdasarkan nama siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent(['nama_lengkap' => 'Muhammad Al Farisi']);

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-SRCH-001', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('search', 'Muhammad Al')
        ->assertSee('KWT-SRCH-001');
});

it('mencari berdasarkan NIS siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent(['nis' => '2026-NIS-999']);

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-NIS-001', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('search', '2026-NIS-999')
        ->assertSee('KWT-NIS-001');
});

it('mencari berdasarkan nomor kwitansi siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-SEARCH-REC-001', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('search', 'SEARCH-REC-001')
        ->assertSee('KWT-SEARCH-REC-001');
});

it('filter bank menampilkan siswa dari bank yang dipilih', function () {
    $user = User::factory()->create();
    $bca = Bank::factory()->create(['name' => 'BCA']);
    $mandiri = Bank::factory()->create(['name' => 'Mandiri']);
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bca, $user, 'KWT-BCA-S', '2026-08-26');
    makeHistoryStudentPayment($student, $mandiri, $user, 'KWT-MDR-S', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('bankId', (string) $bca->id)
        ->assertSee('KWT-BCA-S')
        ->assertDontSee('KWT-MDR-S');
});

it('membedakan rekening bernama sama di filter dan tetap memfilter berdasarkan bank id', function () {
    $user = User::factory()->create();
    $firstBsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '1111111111']);
    $secondBsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '2222222222']);
    $student = makeHistoryStudent();
    $firstPayment = makeHistoryStudentPayment($student, $firstBsi, $user, 'KWT-BSI-FIRST', '2026-09-07');
    $secondPayment = makeHistoryStudentPayment($student, $secondBsi, $user, 'KWT-BSI-SECOND', '2026-09-07');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('<option value="'.$firstBsi->id.'">BSI — 1111111111</option>')
        ->assertSeeHtml('<option value="'.$secondBsi->id.'">BSI — 2222222222</option>')
        ->set('bankId', (string) $firstBsi->id)
        ->assertSee('history-'.$firstPayment->id, false)
        ->assertDontSee('history-'.$secondPayment->id, false)
        ->assertSeeHtml('<div class="text-body-sm text-on-surface-variant font-numeric-data">1111111111</div>');
});

it('menampilkan bank tanpa nomor rekening dan Tunai secara aman di riwayat siswa', function () {
    $user = User::factory()->create();
    $student = makeHistoryStudent();
    $cash = Bank::factory()->cash()->create(['name' => 'Kas Loket']);
    $bankWithoutAccount = Bank::factory()->create(['name' => 'BSI Tanpa Nomor', 'account_number' => null]);

    makeHistoryStudentPayment($student, $cash, $user, 'KWT-CASH-LABEL', '2026-09-07');
    makeHistoryStudentPayment($student, $bankWithoutAccount, $user, 'KWT-MISSING-ACCOUNT', '2026-09-07');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('<option value="'.$cash->id.'">Tunai</option>')
        ->assertSeeHtml('<option value="'.$bankWithoutAccount->id.'">BSI Tanpa Nomor</option>')
        ->assertSee('Tunai')
        ->assertSee('BSI Tanpa Nomor')
        ->assertDontSee('Tunai —')
        ->assertDontSee('BSI Tanpa Nomor —');
});

it('filter status cancelled hanya menampilkan pembayaran siswa yang dibatalkan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-STATUS-ACTIVE', '2026-08-26', Payment::KIND_BILL, Payment::STATUS_ACTIVE);
    makeHistoryStudentPayment($student, $bank, $user, 'KWT-STATUS-CANCELLED', '2026-08-26', Payment::KIND_BILL, Payment::STATUS_CANCELLED);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('status', 'cancelled')
        ->assertSee('KWT-STATUS-CANCELLED')
        ->assertDontSee('KWT-STATUS-ACTIVE');
});

it('filter status active menampilkan siswa aktif', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-ACTIVE-S', '2026-08-26', Payment::KIND_BILL, Payment::STATUS_ACTIVE);
    makeHistoryStudentPayment($student, $bank, $user, 'KWT-ACTIVE-CANCEL', '2026-08-26', Payment::KIND_BILL, Payment::STATUS_CANCELLED);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('status', 'active')
        ->assertSee('KWT-ACTIVE-S')
        ->assertDontSee('KWT-ACTIVE-CANCEL');
});

it('filter tanggal mulai bekerja pada history siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-DATE-EARLY', '2026-07-15', createdAt: '2026-07-15 09:00:00');
    makeHistoryStudentPayment($student, $bank, $user, 'KWT-DATE-ON', '2026-08-26', createdAt: '2026-08-26 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->assertSee('KWT-DATE-ON')
        ->assertDontSee('KWT-DATE-EARLY');
});

it('filter tanggal akhir bekerja pada history siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-END-ON', '2026-08-10', createdAt: '2026-08-10 09:00:00');
    makeHistoryStudentPayment($student, $bank, $user, 'KWT-END-LATE', '2026-09-15', createdAt: '2026-09-15 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->set('endDate', '2026-08-31')
        ->assertSee('KWT-END-ON')
        ->assertDontSee('KWT-END-LATE');
});

it('filter range tanggal tepat bekerja pada history siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    makeHistoryStudentPayment($student, $bank, $user, 'KWT-RANGE-S', '2026-08-26', createdAt: '2026-08-26 09:00:00');
    makeHistoryStudentPayment($student, $bank, $user, 'KWT-RANGE-OUT', '2026-08-27', createdAt: '2026-08-27 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-26')
        ->set('endDate', '2026-08-26')
        ->assertSee('KWT-RANGE-S')
        ->assertDontSee('KWT-RANGE-OUT');
});

it('row student hanya menggunakan route pembayaran siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    $studentPayment = makeHistoryStudentPayment($student, $bank, $user, 'KWT-ROUTE-S', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee(route('pembayaran.show', $studentPayment->id), false);
});

it('pagination beroperasi pada timeline history siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    for ($i = 1; $i <= 15; $i++) {
        makeHistoryStudentPayment(
            $student,
            $bank,
            $user,
            'KWT-PAGE-S-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            '2026-09-01',
            createdAt: now()->toDateString().' '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00:00',
        );
    }

    $component = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-PAGE-S-15')
        ->assertSee('KWT-PAGE-S-14')
        ->assertDontSee('KWT-PAGE-S-05')
        ->call('gotoPage', 2)
        ->assertSeeInOrder(['KWT-PAGE-S-05', 'KWT-PAGE-S-04', 'KWT-PAGE-S-01'])
        ->assertDontSee('KWT-PAGE-S-15');
});

it('penomoran baris history siswa global di halaman kedua dimulai dari 11', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    for ($i = 1; $i <= 15; $i++) {
        makeHistoryStudentPayment(
            $student,
            $bank,
            $user,
            'KWT-NO-S-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            '2026-09-01',
            createdAt: now()->toDateString().' '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00:00',
        );
    }

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertDontSeeHtml('>11</td>');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('gotoPage', 2)
        ->assertSeeHtml('>11</td>')
        ->assertSee('KWT-NO-S-05')
        ->assertDontSee('KWT-NO-S-15');
});

it('mengganti pencarian history siswa mereset kembali ke halaman pertama', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();
    $other = makeHistoryStudent(['nama_lengkap' => 'Zaid bin Tsabit']);

    for ($i = 1; $i <= 11; $i++) {
        makeHistoryStudentPayment(
            $student,
            $bank,
            $user,
            'KWT-RESET-S-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            '2026-09-01',
            createdAt: now()->toDateString().' '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00:00',
        );
    }

    makeHistoryStudentPayment(
        $other,
        $bank,
        $user,
        'KWT-RESET-SEARCH-ONLY',
        '2026-09-01',
        createdAt: now()->toDateString().' 12:00:00',
    );

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('gotoPage', 2)
        ->assertSee('KWT-RESET-S-01')
        ->assertDontSee('KWT-RESET-S-11')
        ->set('search', 'Zaid')
        ->assertSee('KWT-RESET-SEARCH-ONLY')
        ->assertDontSee('KWT-RESET-S-11')
        ->assertDontSee('KWT-RESET-S-01');
});

it('history siswa tidak mengubah data pembayaran atau tagihan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();

    $studentPayment = makeHistoryStudentPayment($student, $bank, $user, 'KWT-READONLY-S', '2026-08-26');

    $snapshotColumns = ['total_amount', 'status', 'student_id', 'payment_date', 'created_at', 'updated_at'];
    $snapshot = $studentPayment->fresh()->getRawOriginal();
    $snapshot = array_intersect_key($snapshot, array_flip($snapshotColumns));
    $count = Payment::query()->count();

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-READONLY-S');

    $freshValues = array_intersect_key($studentPayment->fresh()->getRawOriginal(), array_flip($snapshotColumns));

    expect($freshValues)->toBe($snapshot)
        ->and(Payment::query()->count())->toBe($count);
});

it('row student menampilkan tombol hapus', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();
    $studentPayment = makeHistoryStudentPayment($student, $bank, $user, 'KWT-YES-DEL-S', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('wire:click="confirmDelete('.$studentPayment->id.')"');
});

it('riwayat siswa TIDAK menampilkan transaksi daycare', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $child = makeHistoryDaycareChild();

    makeHistoryStudentPayment(makeHistoryStudent(), $bank, $user, 'KWT-STUDENT-ONLY', '2026-08-26');
    $daycarePayment = makeHistoryDaycarePayment($child, $bank, $user, 'KWT-DC-NOT-IN-STUDENT', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-STUDENT-ONLY')
        ->assertDontSee('KWT-DC-NOT-IN-STUDENT')
        ->assertDontSee($child->nama_lengkap)
        ->assertDontSee(route('daycare.payment.show', $daycarePayment), false);
});

it('riwayat siswa tidak menampilkan badge Daycare', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $child = makeHistoryDaycareChild();

    makeHistoryStudentPayment(makeHistoryStudent(), $bank, $user, 'KWT-ST', '2026-08-26');
    makeHistoryDaycarePayment($child, $bank, $user, 'KWT-DC-BADGE', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertDontSeeHtml('bg-purple-100 text-purple-800">Daycare</span>');
});

it('pencarian siswa tidak pernah mengembalikan transaksi daycare', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $child = makeHistoryDaycareChild(['nama_lengkap' => 'Fatimah Azzahra']);

    makeHistoryStudentPayment(makeHistoryStudent(), $bank, $user, 'KWT-SRCH-S', '2026-08-26');
    makeHistoryDaycarePayment($child, $bank, $user, 'KWT-SRCH-DC-002', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('search', 'Fatimah')
        ->assertDontSee('KWT-SRCH-DC-002')
        ->assertDontSee('KWT-SRCH-S');
});

it('filter bank history siswa tidak menyertakan daycare', function () {
    $user = User::factory()->create();
    $bca = Bank::factory()->create(['name' => 'BCA']);
    $child = makeHistoryDaycareChild();

    makeHistoryStudentPayment(makeHistoryStudent(), $bca, $user, 'KWT-BCA-S', '2026-08-26');
    makeHistoryDaycarePayment($child, $bca, $user, 'KWT-BCA-DC', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('bankId', (string) $bca->id)
        ->assertSee('KWT-BCA-S')
        ->assertDontSee('KWT-BCA-DC');
});

it('range tanggal history siswa tidak menyertakan daycare', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $child = makeHistoryDaycareChild();

    makeHistoryStudentPayment(makeHistoryStudent(), $bank, $user, 'KWT-DATE-S', '2026-08-26', createdAt: '2026-08-26 09:00:00');
    makeHistoryDaycarePayment($child, $bank, $user, 'KWT-DATE-DC', '2026-08-26');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->set('endDate', '2026-08-31')
        ->assertSee('KWT-DATE-S')
        ->assertDontSee('KWT-DATE-DC');
});

it('id payment dan daycare_payment yang overlapping tidak bocor ke riwayat siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeHistoryStudent();
    $child = makeHistoryDaycareChild();

    $studentPayment = makeHistoryStudentPayment($student, $bank, $user, 'KWT-OVERLAP-S', '2026-08-26');

    $daycarePayment = DaycarePayment::factory()->create([
        'id' => $studentPayment->id,
        'receipt_number' => 'KWT-OVERLAP-DC',
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-26',
        'total_amount' => 1500000,
        'created_by' => $user->id,
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-OVERLAP-S')
        ->assertDontSee('KWT-OVERLAP-DC')
        ->assertSee(route('pembayaran.show', $studentPayment->id), false)
        ->assertDontSee(route('daycare.payment.show', $daycarePayment), false);
});
