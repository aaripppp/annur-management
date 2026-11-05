<?php

use App\Livewire\PpdbAcademicYearSettingsManagement;
use App\Models\AcademicYear;
use App\Models\PpdbAcademicYearSetting;
use App\Models\PpdbDefaultClassMapping;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function createPpdbAcademicYear(string $year, array $attributes = []): AcademicYear
{
    $startYear = (int) substr($year, 0, 4);

    return AcademicYear::query()->create(array_merge([
        'year' => $year,
        'is_active' => false,
        'start_date' => Carbon::create($startYear, 7, 1),
        'end_date' => Carbon::create($startYear + 1, 6, 30),
    ], $attributes));
}

function ppdbSettingsAdmin(string $role = User::ROLE_ADMIN): User
{
    return User::factory()->create([
        'is_active' => true,
        'role' => $role,
    ]);
}

it('redirects guests to login', function () {
    $this->get(route('ppdb.academic-year-settings.index'))
        ->assertRedirect(route('login'));
});

it('forbids authenticated non-admin users', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    $this->get(route('ppdb.academic-year-settings.index'))->assertForbidden();
});

it('allows admins and super admins to view settings', function (string $role) {
    $this->actingAs(ppdbSettingsAdmin($role));

    $this->get(route('ppdb.academic-year-settings.index'))
        ->assertOk()
        ->assertSee('Pengaturan Tahun Ajaran PPDB');
})->with([
    'admin' => User::ROLE_ADMIN,
    'super admin' => User::ROLE_SUPER_ADMIN,
]);

it('treats missing settings and newly created settings as closed', function () {
    $yearWithoutSetting = createPpdbAcademicYear('2031/2032');
    $yearWithDefaultSetting = createPpdbAcademicYear('2032/2033');
    $setting = PpdbAcademicYearSetting::query()->create([
        'academic_year_id' => $yearWithDefaultSetting->id,
    ]);

    expect($yearWithoutSetting->ppdbSetting)->toBeNull()
        ->and($setting->is_open)->toBeFalse()
        ->and(AcademicYear::query()->openForPpdb()->count())->toBe(0);
});

it('opens one year and persists the setting after reload', function () {
    $this->actingAs(ppdbSettingsAdmin());
    $year = createPpdbAcademicYear('2033/2034');

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.'.$year->id, true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Pengaturan tahun ajaran PPDB berhasil disimpan.');

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->assertSet('openYears.'.$year->id, true);

    expect(AcademicYear::query()->openForPpdb()->sole()->is($year))->toBeTrue();
});

it('supports multiple simultaneously open years', function () {
    $this->actingAs(ppdbSettingsAdmin());
    $yearA = createPpdbAcademicYear('2034/2035');
    $yearB = createPpdbAcademicYear('2035/2036');

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.'.$yearA->id, true)
        ->set('openYears.'.$yearB->id, true)
        ->call('save')
        ->assertHasNoErrors();

    expect(AcademicYear::query()->openForPpdb()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$yearA->id, $yearB->id])->sort()->values()->all());
});

it('can close an open year without affecting another open year', function () {
    $this->actingAs(ppdbSettingsAdmin());
    $yearA = createPpdbAcademicYear('2036/2037');
    $yearB = createPpdbAcademicYear('2037/2038');
    PpdbAcademicYearSetting::query()->insert([
        ['academic_year_id' => $yearA->id, 'is_open' => true, 'created_at' => now(), 'updated_at' => now()],
        ['academic_year_id' => $yearB->id, 'is_open' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.'.$yearA->id, false)
        ->call('save')
        ->assertHasNoErrors();

    expect($yearA->ppdbSetting()->sole()->is_open)->toBeFalse()
        ->and($yearB->ppdbSetting()->sole()->is_open)->toBeTrue();
});

it('does not change active status or academic year dates', function () {
    $this->actingAs(ppdbSettingsAdmin());
    $year = createPpdbAcademicYear('2038/2039', ['is_active' => true]);
    $snapshot = $year->only(['is_active', 'start_date', 'end_date']);

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.'.$year->id, true)
        ->call('save');

    $year->refresh();

    expect($year->is_active)->toBe($snapshot['is_active'])
        ->and($year->start_date->toDateString())->toBe($snapshot['start_date']->toDateString())
        ->and($year->end_date->toDateString())->toBe($snapshot['end_date']->toDateString());
});

it('does not change applicants bills or payments', function () {
    $this->actingAs(ppdbSettingsAdmin());
    $year = createPpdbAcademicYear('2039/2040');
    $prospect = ProspectiveStudent::factory()->create(['academic_year_id' => $year->id]);
    $bill = ProspectiveStudentBill::factory()->create(['prospective_student_id' => $prospect->id]);
    $payment = ProspectiveStudentPayment::factory()->create(['prospective_student_id' => $prospect->id]);
    $snapshots = [
        'prospect' => $prospect->refresh()->getAttributes(),
        'bill' => $bill->refresh()->getAttributes(),
        'payment' => $payment->refresh()->getAttributes(),
    ];

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.'.$year->id, true)
        ->call('save');

    expect($prospect->fresh()->getAttributes())->toEqual($snapshots['prospect'])
        ->and($bill->fresh()->getAttributes())->toEqual($snapshots['bill'])
        ->and($payment->fresh()->getAttributes())->toEqual($snapshots['payment']);
});

it('does not change global default class mappings', function () {
    $this->actingAs(ppdbSettingsAdmin());
    $year = createPpdbAcademicYear('2040/2041');
    $class = SchoolClass::factory()->create(['level' => 7]);
    $mapping = PpdbDefaultClassMapping::query()->create([
        'class_level' => 7,
        'school_class_id' => $class->id,
    ]);

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.'.$year->id, true)
        ->call('save');

    expect($mapping->fresh()->only(['class_level', 'school_class_id']))
        ->toBe(['class_level' => 7, 'school_class_id' => $class->id]);
});

it('rejects unknown and stale academic year ids', function () {
    $this->actingAs(ppdbSettingsAdmin());
    $year = createPpdbAcademicYear('2041/2042');

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.999999', true)
        ->call('save')
        ->assertHasErrors('openYears');

    $component = Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->set('openYears.'.$year->id, true);

    $year->delete();

    $component->call('save')->assertHasErrors('openYears');

    expect(PpdbAcademicYearSetting::query()->count())->toBe(0);
});

it('loads years and settings without n plus one queries', function () {
    $this->actingAs(ppdbSettingsAdmin());

    foreach (range(2042, 2046) as $startYear) {
        $year = createPpdbAcademicYear($startYear.'/'.($startYear + 1));
        PpdbAcademicYearSetting::query()->create([
            'academic_year_id' => $year->id,
            'is_open' => $startYear % 2 === 0,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(PpdbAcademicYearSettingsManagement::class)->assertOk();

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "academic_years"'))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "ppdb_academic_year_settings"'))->count())->toBe(1);
});

it('keeps both ppdb settings routes and sidebar links functional', function () {
    $this->actingAs(ppdbSettingsAdmin());

    $this->get(route('ppdb.academic-year-settings.index'))
        ->assertOk()
        ->assertSee('href="'.route('ppdb.academic-year-settings.index').'"', false)
        ->assertSee('href="'.route('ppdb.default-class-mappings.index').'"', false)
        ->assertSee('Tahun Ajaran PPDB')
        ->assertSee('Kelas Default PPDB');

    $this->get(route('ppdb.default-class-mappings.index'))->assertOk();
});

it('enforces schema uniqueness and restricted academic year deletion', function () {
    expect(Schema::hasTable('ppdb_academic_year_settings'))->toBeTrue()
        ->and(Schema::hasColumns('ppdb_academic_year_settings', ['id', 'academic_year_id', 'is_open', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasIndex('ppdb_academic_year_settings', 'ppdb_ays_ay_unique', 'unique'))->toBeTrue()
        ->and(Schema::hasForeignKey('ppdb_academic_year_settings', ['academic_year_id']))->toBeTrue();

    $year = createPpdbAcademicYear('2047/2048');
    PpdbAcademicYearSetting::query()->create(['academic_year_id' => $year->id]);

    expect(fn () => PpdbAcademicYearSetting::query()->create([
        'academic_year_id' => $year->id,
    ]))->toThrow(QueryException::class)
        ->and(fn () => $year->delete())->toThrow(QueryException::class);
});

it('shows a clear empty state when there are no academic years', function () {
    $this->actingAs(ppdbSettingsAdmin());
    AcademicYear::query()->delete();

    Livewire::test(PpdbAcademicYearSettingsManagement::class)
        ->assertSee('Belum ada tahun ajaran')
        ->assertSee('Tambahkan tahun ajaran terlebih dahulu');
});
