<?php

use App\Livewire\PaymentIndex;
use App\Livewire\StudentDetail;
use App\Models\PaymentType;
use App\Models\StudentBill;
use App\Support\BillDisplayOrder;
use Illuminate\Support\Collection;
use Livewire\Livewire;

/**
 * Bangun koleksi StudentBill di memori (tanpa DB) sesuai urutan nama yang
 * diberikan, supaya kontrak stable-sort helper dapat diuji persis seperti
 * contoh kasus.
 *
 * @param  list<string>  $names
 */
function billDisplayOrderFixture(array $names): Collection
{
    return collect($names)->map(function (string $name): StudentBill {
        $type = new PaymentType;
        $type->name = $name;

        $bill = new StudentBill;
        $bill->setRelation('paymentType', $type);

        return $bill;
    });
}

/** @return list<string> */
function billDisplayOrderNames(Collection $bills): array
{
    return $bills->map(fn (StudentBill $bill): string => (string) $bill->paymentType->name)->values()->all();
}

it('menaruh SPP, Ekskul, OSIS di depan saat urutan awal acak', function () {
    expect(billDisplayOrderNames(BillDisplayOrder::sort(billDisplayOrderFixture(['Ekskul', 'OSIS', 'SPP']))))
        ->toBe(['SPP', 'Ekskul', 'OSIS']);
});

it('membiarkan tagihan SPP tunggal apa adanya', function () {
    expect(billDisplayOrderNames(BillDisplayOrder::sort(billDisplayOrderFixture(['SPP']))))
        ->toBe(['SPP']);
});

it('menaruh SPP sebelum Ekskul', function () {
    expect(billDisplayOrderNames(BillDisplayOrder::sort(billDisplayOrderFixture(['SPP', 'Ekskul']))))
        ->toBe(['SPP', 'Ekskul']);
});

it('menaruh SPP sebelum OSIS', function () {
    expect(billDisplayOrderNames(BillDisplayOrder::sort(billDisplayOrderFixture(['SPP', 'OSIS']))))
        ->toBe(['SPP', 'OSIS']);
});

it('menaruh SPP, Ekskul, OSIS di depan dan mempertahankan urutan relatif jenis lain', function () {
    expect(billDisplayOrderNames(BillDisplayOrder::sort(billDisplayOrderFixture(['Jemputan', 'OSIS', 'Infak', 'Ekskul', 'SPP']))))
        ->toBe(['SPP', 'Ekskul', 'OSIS', 'Jemputan', 'Infak']);
});

it('mengenali nama jenis pembayaran tanpa memedulikan huruf besar/kecil', function () {
    expect(billDisplayOrderNames(BillDisplayOrder::sort(billDisplayOrderFixture(['osis', 'EKSKUL', 'spp']))))
        ->toBe(['spp', 'EKSKUL', 'osis']);
});

it('tidak menambah atau menghilangkan jenis pembayaran saat mengurutkan', function () {
    $bills = billDisplayOrderFixture(['Uang Pangkal', 'spp', 'Infak', 'Ekskul']);
    $sorted = BillDisplayOrder::sort($bills);

    expect($sorted)->toHaveCount($bills->count())
        ->and(collect(billDisplayOrderNames($sorted))->sort()->values()->all())
        ->toBe(collect(billDisplayOrderNames($bills))->sort()->values()->all());
});

it('shared bill table mengurutkan ulang jenis pembayaran sesuai prioritas', function () {
    $student = makeBillStudent();

    $bills = collect(['Jemputan', 'OSIS', 'Infak', 'Ekskul', 'SPP'])->map(
        fn (string $name): StudentBill => makeMonthlyBill($student, makeBillType($name), 100000, 8, 2026)->load('paymentType')
    );

    $html = view('livewire.student.bill-table', ['bills' => $bills, 'readOnly' => true])->render();

    expect(preg_match_all('/<td[^>]*font-body-md text-on-surface whitespace-nowrap[^>]*>(.*?)<\/td>/s', $html, $matches))
        ->toBe(5)
        ->and($matches[1])->toBe(['SPP', 'Ekskul', 'OSIS', 'Jemputan', 'Infak']);
});

it('menampilkan SPP, Ekskul, OSIS di atas jenis lain pada tabel tagihan bulanan siswa', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    $ekskul = makeBillType('Ekskul');
    $osis = makeBillType('OSIS');
    $jemputan = makeBillType('Jemputan');

    makeBillRate($spp, 8, 1500000);
    makeBillRate($ekskul, 8, 200000);
    makeBillRate($osis, 8, 50000);
    makeBillRate($jemputan, 8, 550000);

    foreach ([$spp, $ekskul, $osis, $jemputan] as $type) {
        makeActiveSetting($student, $type);
    }

    makeMonthlyBill($student, $jemputan, 550000, 8, 2026);
    makeMonthlyBill($student, $osis, 50000, 8, 2026);
    makeMonthlyBill($student, $ekskul, 200000, 8, 2026);
    makeMonthlyBill($student, $spp, 1500000, 8, 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder(['Tagihan Agustus 2026', 'SPP', 'Ekskul', 'OSIS', 'Jemputan']);
});

it('mempertahankan pengelompokan bulanan setelah pengurutan tampilan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    $ekskul = makeBillType('Ekskul');
    makeBillRate($spp, 8, 1500000);
    makeBillRate($ekskul, 8, 200000);
    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);

    makeMonthlyBill($student, $spp, 1500000, 8, 2026);
    makeMonthlyBill($student, $ekskul, 200000, 9, 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder(['Tagihan Agustus 2026', 'SPP', 'Tagihan September 2026', 'Ekskul']);
});

it('tidak mengubah nominal, status, dan total setelah pengurutan tampilan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    $ekskul = makeBillType('Ekskul');
    $osis = makeBillType('OSIS');
    makeBillRate($spp, 8, 1500000);
    makeBillRate($ekskul, 8, 200000);
    makeBillRate($osis, 8, 50000);
    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);
    makeActiveSetting($student, $osis);

    $sppBill = makeMonthlyBill($student, $spp, 1500000, 8, 2026);
    makeMonthlyBill($student, $ekskul, 200000, 8, 2026);
    makeMonthlyBill($student, $osis, 50000, 8, 2026);

    payActiveBill($sppBill, 500000);

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    $component
        ->assertSeeInOrder(['SPP', 'Ekskul', 'OSIS'])
        ->assertSee('Rp 1.500.000')
        ->assertSee('Rp 1.000.000')
        ->assertSee('Sebagian');

    assertSummaryCards($component, 1750000, 500000, 1250000);

    $sppBill->refresh();

    expect((float) $sppBill->effective_amount)->toBe(1500000.0)
        ->and($sppBill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

it('Payment Workspace memakai urutan prioritas yang sama pada tabel tagihan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    $ekskul = makeBillType('Ekskul');
    $osis = makeBillType('OSIS');
    makeBillRate($spp, 8, 1500000);
    makeBillRate($ekskul, 8, 200000);
    makeBillRate($osis, 8, 50000);
    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);
    makeActiveSetting($student, $osis);

    makeMonthlyBill($student, $osis, 50000, 8, 2026);
    makeMonthlyBill($student, $ekskul, 200000, 8, 2026);
    makeMonthlyBill($student, $spp, 1500000, 8, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSeeInOrder(['Tagihan Agustus 2026', 'SPP', 'Ekskul', 'OSIS']);
});
