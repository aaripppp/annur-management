<?php

use App\Enums\BillFrequency;
use App\Livewire\JemputanDriverManagement;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverDeductionRate;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverDeductionRateResolver;
use App\Services\JemputanDriverSalaryService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function deductionRateUiUser(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
}

function deductionRateUiYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

function deductionRateUiDriver(array $attributes = []): JemputanDriver
{
    return JemputanDriver::factory()->create($attributes);
}

function deductionRateUiRate(
    JemputanDriver $driver,
    int $year,
    int $month,
    float $percentage,
    ?string $notes = null,
    ?int $createdBy = null,
): JemputanDriverDeductionRate {
    return JemputanDriverDeductionRate::factory()->create([
        'jemputan_driver_id' => $driver->id,
        'effective_year' => $year,
        'effective_month' => $month,
        'percentage' => $percentage,
        'notes' => $notes,
        'created_by' => $createdBy,
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-11-11 09:00:00');
    deductionRateUiYear();
    $this->actingAs(deductionRateUiUser());
});

it('opens and closes the deduction rate modal with the driver context', function () {
    $driver = deductionRateUiDriver(['name' => 'Sopir Modal']);
    deductionRateUiRate($driver, 2026, 7, 7);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->assertSee('Ubah Persentase')
        ->call('openDeductionRateModal')
        ->assertSet('isDeductionRateModalOpen', true)
        ->assertSee('Ubah Persentase Yayasan')
        ->assertSee('Sopir Modal')
        ->assertSee('Persentase Baru (%)')
        ->assertSee('Bulan Mulai Berlaku')
        ->assertSee('Catatan')
        ->assertSee('Preview dampak perubahan')
        ->assertSee('Oktober 2026 tetap 7%')
        ->set('deductionRatePercentage', '12')
        ->assertSee('November 2026 menjadi 12%')
        ->call('closeDeductionRateModal')
        ->assertSet('isDeductionRateModalOpen', false)
        ->assertSet('deductionRatePercentage', '');
});

it('saves a valid rate, records the author, and keeps zero separate from null', function () {
    $driver = deductionRateUiDriver(['name' => 'Sopir Nol']);
    $actor = auth()->user();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->assertSee('Belum diatur')
        ->call('openDeductionRateModal')
        ->set('deductionRatePercentage', '0')
        ->set('deductionRateEffectiveMonth', '2026-11')
        ->set('deductionRateNotes', 'Tarif nol')
        ->call('saveDeductionRate')
        ->assertHasNoErrors()
        ->assertSet('isDeductionRateModalOpen', false)
        ->assertDispatched('annur-toast', type: 'success')
        ->assertSee('0%');

    $rate = JemputanDriverDeductionRate::query()->where('jemputan_driver_id', $driver->id)->firstOrFail();

    expect($rate->percentage)->toBe('0.00')
        ->and($rate->effective_year)->toBe(2026)
        ->and($rate->effective_month)->toBe(11)
        ->and($rate->notes)->toBe('Tarif nol')
        ->and($rate->created_by)->toBe($actor->id)
        ->and($driver->refresh()->foundation_deduction_percentage)->toBeNull();

    $otherDriver = deductionRateUiDriver(['name' => 'Sopir Belum Ada Tarif']);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $otherDriver->id)
        ->assertSee('Belum diatur')
        ->assertViewHas('selectedDriverCurrentRate', fn (array $rate): bool => $rate['percentage'] === null
            && $rate['configured'] === false
            && $rate['source'] === JemputanDriverDeductionRateResolver::SOURCE_NONE);
});

it('rejects percentages outside zero to one hundred', function (string $percentage) {
    $driver = deductionRateUiDriver();
    $initialCount = JemputanDriverDeductionRate::query()->count();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openDeductionRateModal')
        ->set('deductionRatePercentage', $percentage)
        ->set('deductionRateEffectiveMonth', '2026-11')
        ->call('saveDeductionRate')
        ->assertHasErrors('deductionRatePercentage')
        ->assertSet('isDeductionRateModalOpen', true)
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriverDeductionRate::query()->count())->toBe($initialCount);
})->with(['-0.01', '100.01']);

it('rejects percentages with more than two decimals', function () {
    $driver = deductionRateUiDriver();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openDeductionRateModal')
        ->set('deductionRatePercentage', '10.123')
        ->set('deductionRateEffectiveMonth', '2026-11')
        ->call('saveDeductionRate')
        ->assertHasErrors('deductionRatePercentage')
        ->assertSet('isDeductionRateModalOpen', true);

    expect(JemputanDriverDeductionRate::query()->where('jemputan_driver_id', $driver->id)->exists())->toBeFalse();
});

it('requires an effective month and keeps the modal open when validation fails', function () {
    $driver = deductionRateUiDriver();

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openDeductionRateModal')
        ->set('deductionRatePercentage', '12')
        ->set('deductionRateEffectiveMonth', '')
        ->call('saveDeductionRate')
        ->assertHasErrors('deductionRateEffectiveMonth')
        ->assertSet('isDeductionRateModalOpen', true)
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriverDeductionRate::query()->where('jemputan_driver_id', $driver->id)->exists())->toBeFalse();
});

it('rejects a duplicate period without overwriting the existing rate', function () {
    $driver = deductionRateUiDriver();
    $existing = deductionRateUiRate($driver, 2026, 11, 12, 'Tarif lama');

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openDeductionRateModal')
        ->set('deductionRatePercentage', '15')
        ->set('deductionRateEffectiveMonth', '2026-11')
        ->call('saveDeductionRate')
        ->assertHasErrors('deductionRateEffectiveMonth')
        ->assertSet('isDeductionRateModalOpen', true)
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriverDeductionRate::query()->where('jemputan_driver_id', $driver->id)->count())->toBe(1)
        ->and($existing->refresh()->percentage)->toBe('12.00')
        ->and($existing->notes)->toBe('Tarif lama');
});

it('keeps future rates out of the current rate and preserves earlier periods', function () {
    $driver = deductionRateUiDriver();
    deductionRateUiRate($driver, 2026, 7, 7);
    deductionRateUiRate($driver, 2026, 11, 12);

    $component = Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->assertViewHas('selectedDriverCurrentRate', fn (array $rate): bool => $rate['percentage'] === 12.0
            && $rate['effective_year'] === 2026
            && $rate['effective_month'] === 11)
        ->call('openDeductionRateModal')
        ->set('deductionRatePercentage', '15')
        ->set('deductionRateEffectiveMonth', '2027-02')
        ->call('saveDeductionRate')
        ->assertHasNoErrors()
        ->assertViewHas('selectedDriverCurrentRate', fn (array $rate): bool => $rate['percentage'] === 12.0
            && $rate['effective_month'] === 11);

    $resolver = app(JemputanDriverDeductionRateResolver::class);

    expect($resolver->resolve($driver, 2026, 10)['percentage'])->toBe(7.0)
        ->and($resolver->resolve($driver, 2026, 12)['percentage'])->toBe(12.0)
        ->and($resolver->resolve($driver, 2027, 3)['percentage'])->toBe(15.0)
        ->and($component->instance()->selectedDriverId)->toBe($driver->id);
});

it('requires confirmation before saving a retroactive rate', function () {
    $driver = deductionRateUiDriver();
    deductionRateUiRate($driver, 2026, 7, 7);
    $actor = auth()->user();

    $component = Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->call('openDeductionRateModal')
        ->set('deductionRatePercentage', '12')
        ->set('deductionRateEffectiveMonth', '2026-10')
        ->call('saveDeductionRate')
        ->assertSet('deductionRateRequiresConfirmation', true)
        ->assertSet('isDeductionRateModalOpen', true)
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriverDeductionRate::query()->where('jemputan_driver_id', $driver->id)->count())->toBe(1);

    $component
        ->call('confirmRetroactiveDeductionRate')
        ->assertSet('deductionRateRequiresConfirmation', false)
        ->assertSet('isDeductionRateModalOpen', false)
        ->assertDispatched('annur-toast', type: 'success');

    $retroactive = JemputanDriverDeductionRate::query()
        ->where('jemputan_driver_id', $driver->id)
        ->where('effective_year', 2026)
        ->where('effective_month', 10)
        ->firstOrFail();

    expect($retroactive->percentage)->toBe('12.00')
        ->and($retroactive->created_by)->toBe($actor->id)
        ->and(app(JemputanDriverDeductionRateResolver::class)->resolve($driver, 2026, 9)['percentage'])->toBe(7.0)
        ->and(app(JemputanDriverDeductionRateResolver::class)->resolve($driver, 2026, 10)['percentage'])->toBe(12.0);
});

it('requires an effective month when a new driver starts with a percentage', function () {
    Livewire::test(JemputanDriverManagement::class)
        ->call('openCreateDriver')
        ->set('driverName', 'Sopir Tanpa Bulan')
        ->set('driverFoundationDeductionPercentage', '10')
        ->set('driverDeductionEffectiveMonth', '')
        ->call('saveDriver')
        ->assertHasErrors('driverDeductionEffectiveMonth')
        ->assertDispatched('annur-toast', type: 'error');

    expect(JemputanDriver::query()->where('name', 'Sopir Tanpa Bulan')->exists())->toBeFalse();
});

it('labels a legacy percentage without claiming a known effective month', function () {
    $driver = deductionRateUiDriver([
        'name' => 'Sopir Legacy',
        'foundation_deduction_percentage' => 9.5,
    ]);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->assertSee('9,5%')
        ->assertSee('Persentase lama — bulan berlaku belum ditentukan')
        ->assertSee('Belum ada riwayat tarif')
        ->assertViewHas('selectedDriverCurrentRate', fn (array $rate): bool => $rate['source'] === JemputanDriverDeductionRateResolver::SOURCE_LEGACY
            && $rate['effective_year'] === null);
});

it('shows the rate history in period order', function () {
    $driver = deductionRateUiDriver();
    deductionRateUiRate($driver, 2027, 2, 15, 'Tarif ketiga');
    deductionRateUiRate($driver, 2026, 7, 7, 'Tarif awal');
    deductionRateUiRate($driver, 2026, 11, 12);

    Livewire::test(JemputanDriverManagement::class)
        ->call('viewGroup', $driver->id)
        ->assertViewHas('selectedDriverRateHistory', fn ($history): bool => collect($history)->pluck('period_label')->all() === [
            'Juli 2026',
            'November 2026',
            'Februari 2027',
        ])
        ->assertSee('Juli 2026')
        ->assertSee('November 2026')
        ->assertSee('Februari 2027')
        ->assertSee('Tarif awal')
        ->assertSee('Tarif ketiga');
});

it('loads list rates without a query per driver', function () {
    JemputanDriver::factory()->count(15)->create()->each(function (JemputanDriver $driver): void {
        deductionRateUiRate($driver, 2026, 7, 7);
        deductionRateUiRate($driver, 2026, 11, 12);
    });

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(JemputanDriverManagement::class)->assertOk();

    $rateQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'jemputan_driver_deduction_rates'));

    DB::disableQueryLog();

    expect($rateQueries->count())->toBeLessThanOrEqual(2);
});

it('keeps the salary page and export percentage consistent after a history change', function () {
    $year = deductionRateUiYear();
    $driver = deductionRateUiDriver();
    deductionRateUiRate($driver, 2026, 7, 7);
    deductionRateUiRate($driver, 2026, 11, 12);

    $class = SchoolClass::query()->firstOrCreate(['name' => 'VIII Riwayat'], ['level' => 8]);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat', 'class_id' => $class->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => '2026-07-01',
        'effective_until' => null,
    ]);
    $paymentType = PaymentType::factory()->create(['name' => 'Jemputan']);
    StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $paymentType->id,
        'period_month' => 10,
        'period_year' => 2026,
        'billing_frequency' => BillFrequency::Monthly->value,
        'amount' => 1000000,
    ]);

    $service = app(JemputanDriverSalaryService::class);
    $report = $service->generate($year->id, $driver->id, '2026-10');
    $export = $service->exportData($year->id, $driver->id, '2026-10');

    expect($report['summary']['percentage'])->toBe(7.0)
        ->and($export['summary']['percentage'])->toBe(7.0)
        ->and($report['summary']['deduction'])->toBe(70000)
        ->and($export['summary']['deduction'])->toBe(70000)
        ->and($report['summary']['percentage_source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_HISTORY)
        ->and($export['summary']['percentage_source'])->toBe(JemputanDriverDeductionRateResolver::SOURCE_HISTORY);
});
