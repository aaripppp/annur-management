<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\StudentCreationService;
use App\Services\StudentProfileUpdater;
use Livewire\Livewire;

/**
 * @param  array<string, int>  $monthlyAmounts
 * @return array{academicYear: AcademicYear, schoolClass: SchoolClass, monthlyTypes: array<string, PaymentType>, yearly: PaymentType, oneTime: PaymentType}
 */
function configureInitialJulyBilling(int $startYear, array $monthlyAmounts, int $classLevel = 7): array
{
    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->updateOrCreate([
        'year' => ($startYear - 1).'/'.$startYear,
    ], [
        'is_active' => true,
        'start_date' => ($startYear - 1).'-07-01',
        'end_date' => $startYear.'-06-30',
    ]);
    $academicYear = AcademicYear::query()->updateOrCreate([
        'year' => $startYear.'/'.($startYear + 1),
    ], [
        'is_active' => false,
        'start_date' => $startYear.'-07-01',
        'end_date' => ($startYear + 1).'-06-30',
    ]);
    $schoolClass = SchoolClass::factory()->create(['level' => $classLevel]);
    $schoolLevel = SchoolLevel::fromClassLevel($classLevel);
    $monthlyTypes = [];

    foreach ($monthlyAmounts as $name => $amount) {
        $type = makeBillType($name, auto: true, required: true);
        makeLevelDefault($type, $schoolLevel);
        makeBillRate($type, $classLevel, $amount, [
            'billing_frequency' => BillFrequency::Monthly,
            'effective_from' => $startYear.'-01-01',
        ]);
        $monthlyTypes[$name] = $type;
    }

    $yearly = makeBillType('Uang Buku', auto: true, required: true);
    $oneTime = makeBillType('Uang Pangkal', auto: true, required: true);
    makeBillRate($yearly, $classLevel, 500000, [
        'billing_frequency' => BillFrequency::Yearly,
        'effective_from' => $startYear.'-01-01',
    ]);
    makeBillRate($oneTime, $classLevel, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => $startYear.'-01-01',
    ]);

    return compact('academicYear', 'schoolClass', 'monthlyTypes', 'yearly', 'oneTime');
}

/**
 * @param  array<string, int>  $monthlyAmounts
 * @return array{student: Student, academicYear: AcademicYear, schoolClass: SchoolClass, monthlyTypes: array<string, PaymentType>, yearly: PaymentType, oneTime: PaymentType}
 */
function createStudentWithInitialJulyBills(
    int $startYear,
    array $monthlyAmounts = ['SPP' => 900000, 'Ekskul' => 100000, 'OSIS' => 80000],
    int $classLevel = 7,
): array {
    $catalog = configureInitialJulyBilling($startYear, $monthlyAmounts, $classLevel);
    $student = app(StudentCreationService::class)->create([
        'nis' => 'INITIAL-JULY-'.$startYear,
        'nama_lengkap' => 'Siswa Calon '.$startYear,
        'nama_panggilan' => 'Calon',
        'class_id' => $catalog['schoolClass']->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Pendidikan',
        'entry_academic_year_id' => $catalog['academicYear']->id,
    ]);

    return ['student' => $student, ...$catalog];
}

it('creates every applicable monthly component only for July of the selected academic year', function (int $startYear) {
    $this->travelTo(($startYear - 1).'-02-15');
    $catalog = createStudentWithInitialJulyBills($startYear);
    $monthlyBills = $catalog['student']->bills()
        ->where('billing_frequency', BillFrequency::Monthly)
        ->with('paymentType')
        ->get();

    expect($monthlyBills)->toHaveCount(3)
        ->and($monthlyBills->pluck('paymentType.name')->sort()->values()->all())->toBe(['Ekskul', 'OSIS', 'SPP'])
        ->and($monthlyBills->pluck('period_month')->unique()->all())->toBe([7])
        ->and($monthlyBills->pluck('period_year')->unique()->all())->toBe([$startYear])
        ->and($monthlyBills->pluck('academic_year')->filter()->isEmpty())->toBeTrue();
})->with([2026, 2027, 2028]);

it('discovers monthly components from configuration without relying on component names', function () {
    $this->travelTo('2027-02-20');
    $catalog = createStudentWithInitialJulyBills(2027, [
        'Iuran Digital' => 125000,
        'Laboratorium' => 75000,
    ]);
    $monthlyBills = $catalog['student']->bills()
        ->where('billing_frequency', BillFrequency::Monthly)
        ->with('paymentType')
        ->get();

    expect($monthlyBills)->toHaveCount(2)
        ->and($monthlyBills->pluck('paymentType.name')->sort()->values()->all())->toBe(['Iuran Digital', 'Laboratorium'])
        ->and($monthlyBills->pluck('amount')->map(fn ($amount) => (float) $amount)->sort()->values()->all())
        ->toBe([75000.0, 125000.0]);
});

it('creates only the monthly components configured for a different school level', function () {
    $catalog = createStudentWithInitialJulyBills(2027, [
        'SPP' => 700000,
        'Ekskul' => 50000,
    ], classLevel: 5);
    $monthlyBills = $catalog['student']->bills()
        ->where('billing_frequency', BillFrequency::Monthly)
        ->with('paymentType')
        ->get();

    expect($monthlyBills)->toHaveCount(2)
        ->and($monthlyBills->pluck('paymentType.name')->sort()->values()->all())->toBe(['Ekskul', 'SPP']);
});

it('preserves yearly and one-time initial bills', function () {
    $catalog = createStudentWithInitialJulyBills(2027);

    expect($catalog['student']->bills()->where('payment_type_id', $catalog['yearly']->id)->sole()->billing_frequency)
        ->toBe(BillFrequency::Yearly->value)
        ->and($catalog['student']->bills()->where('payment_type_id', $catalog['oneTime']->id)->sole()->billing_frequency)
        ->toBe(BillFrequency::OneTime->value)
        ->and($catalog['student']->bills()->where('billing_frequency', BillFrequency::Yearly)->count())->toBe(1)
        ->and($catalog['student']->bills()->where('billing_frequency', BillFrequency::OneTime)->count())->toBe(1);
});

it('fills only missing July components and never duplicates existing bills', function () {
    $catalog = createStudentWithInitialJulyBills(2027);
    $student = $catalog['student'];
    $originalSpp = $student->bills()->where('payment_type_id', $catalog['monthlyTypes']['SPP']->id)->sole();

    $student->bills()->whereIn('payment_type_id', [
        $catalog['monthlyTypes']['Ekskul']->id,
        $catalog['monthlyTypes']['OSIS']->id,
    ])->delete();
    $originalSpp->update(['amount' => 875000]);

    $service = app(BillGenerationService::class);
    $service->generateInitialAcademicYearBills($student, $catalog['academicYear']);
    $service->generateInitialAcademicYearBills($student, $catalog['academicYear']);

    expect($student->bills()->where('billing_frequency', BillFrequency::Monthly)->count())->toBe(3)
        ->and($originalSpp->fresh()->amount)->toBe('875000.00');

    foreach ($catalog['monthlyTypes'] as $type) {
        expect($student->bills()
            ->where('payment_type_id', $type->id)
            ->where('period_month', 7)
            ->where('period_year', 2027)
            ->count())->toBe(1);
    }
});

it('keeps July snapshots while regular generation uses changed rates for later months', function () {
    $catalog = createStudentWithInitialJulyBills(2027);
    $newAmounts = ['SPP' => 920000, 'Ekskul' => 110000, 'OSIS' => 80000];

    foreach ($newAmounts as $name => $amount) {
        $catalog['monthlyTypes'][$name]->rates()->update(['amount' => $amount]);
    }

    app(BillGenerationService::class)->generateInitialAcademicYearBills($catalog['student'], $catalog['academicYear']);
    $result = app(BillGenerationService::class)->generateMonthlyForAcademicYear($catalog['academicYear']);

    expect($result)->toBe(['created' => 33, 'skipped' => 3]);

    foreach (['SPP' => 900000, 'Ekskul' => 100000, 'OSIS' => 80000] as $name => $initialAmount) {
        $type = $catalog['monthlyTypes'][$name];
        $july = $catalog['student']->bills()
            ->where('payment_type_id', $type->id)
            ->where('period_month', 7)
            ->where('period_year', 2027)
            ->sole();
        $august = $catalog['student']->bills()
            ->where('payment_type_id', $type->id)
            ->where('period_month', 8)
            ->where('period_year', 2027)
            ->sole();

        expect((float) $july->amount)->toBe((float) $initialAmount)
            ->and((float) $august->amount)->toBe((float) $newAmounts[$name])
            ->and($catalog['student']->bills()->where('payment_type_id', $type->id)->count())->toBe(12);
    }
});

it('does not create bills when an existing candidate profile is edited', function () {
    $catalog = createStudentWithInitialJulyBills(2027);

    app(StudentProfileUpdater::class)->update($catalog['student'], [
        'nis' => $catalog['student']->nis,
        'nama_lengkap' => 'Siswa Calon Diperbarui',
        'nama_panggilan' => $catalog['student']->nama_panggilan,
        'class_id' => $catalog['student']->class_id,
        'jenis_kelamin' => $catalog['student']->jenis_kelamin,
        'alamat' => $catalog['student']->alamat,
        'entry_date' => null,
    ]);

    expect($catalog['student']->bills()->where('billing_frequency', BillFrequency::Monthly)->count())->toBe(3);
});

it('creates all configured July components through Livewire student creation', function () {
    $catalog = configureInitialJulyBilling(2027, ['SPP' => 900000, 'Ekskul' => 100000, 'OSIS' => 80000]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', 'LIVEWIRE-JULY')
        ->set('nama_lengkap', 'Calon Livewire')
        ->set('nama_panggilan', 'Livewire')
        ->set('class_id', $catalog['schoolClass']->id)
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Livewire')
        ->set('entry_academic_year_id', (string) $catalog['academicYear']->id)
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::query()->where('nis', 'LIVEWIRE-JULY')->sole();

    expect($student->bills()->where('billing_frequency', BillFrequency::Monthly)->count())->toBe(3)
        ->and($student->bills()->whereNot('period_month', 7)->exists())->toBeFalse();
});

it('shows all initial July components in normal billbook and payment selection grouping', function () {
    $catalog = createStudentWithInitialJulyBills(2027);

    Livewire::test(StudentDetail::class, ['student' => $catalog['student']])
        ->assertSee('Tagihan Juli 2027')
        ->assertSee('SPP')
        ->assertSee('Ekskul')
        ->assertSee('OSIS');

    $paymentComponent = Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $catalog['student']->id)
        ->assertSee('Tagihan Juli 2027')
        ->assertSee('SPP')
        ->assertSee('Ekskul')
        ->assertSee('OSIS');
    $julyBillIds = $catalog['student']->bills()
        ->where('billing_frequency', BillFrequency::Monthly)
        ->pluck('id');

    expect(collect($paymentComponent->get('outstandingBills'))->pluck('id'))
        ->toContain(...$julyBillIds);
});

it('pays an initial July component through the normal payment flow', function () {
    $this->travelTo('2027-02-20');
    $catalog = createStudentWithInitialJulyBills(2027);
    $spp = $catalog['monthlyTypes']['SPP'];
    $julyBill = $catalog['student']->bills()->where('payment_type_id', $spp->id)->sole();
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    Livewire::actingAs($user);
    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $catalog['student']->id)
        ->set('selectedBillIds', [$julyBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2027-02-20')
        ->call('save');

    $payment = Payment::query()->where('student_id', $catalog['student']->id)->sole();
    $detail = PaymentDetail::query()->where('payment_id', $payment->id)->sole();

    expect($detail->bill_id)->toBe($julyBill->id)
        ->and($detail->payment_type_id)->toBe($spp->id)
        ->and($detail->period_month)->toBe(7)
        ->and($detail->period_year)->toBe(2027)
        ->and($detail->academic_year)->toBeNull()
        ->and($julyBill->fresh()->status)->toBe(StudentBill::STATUS_PAID);
});
