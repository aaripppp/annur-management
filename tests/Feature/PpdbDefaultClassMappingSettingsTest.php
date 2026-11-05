<?php

use App\Livewire\PpdbDefaultClassMappingManagement;
use App\Models\AcademicYear;
use App\Models\PpdbDefaultClassMapping;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createYearForSettings(array $attributes = []): AcademicYear
{
    $now = now();
    $year = $attributes['year'] ?? (($now->year - 1).'/'.$now->year);
    if (isset($attributes['year'])) {
        $year = $attributes['year'];
    }

    return AcademicYear::create(array_merge([
        'year' => $year,
        'is_active' => false,
        'start_date' => $attributes['start_date'] ?? Carbon::create((int) substr($year, 0, 4), 7, 1),
        'end_date' => $attributes['end_date'] ?? Carbon::create((int) substr($year, 0, 4) + 1, 6, 30),
    ], $attributes));
}

it('guest redirects to login', function () {
    $this->get(route('ppdb.default-class-mappings.index'))->assertRedirect(route('login'));
});

it('authenticated non-admin gets 403', function () {
    $user = User::factory()->create(['is_active' => true]);
    $this->actingAs($user);

    $this->get(route('ppdb.default-class-mappings.index'))->assertStatus(403);
});

it('admin can access page', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $this->get(route('ppdb.default-class-mappings.index'))->assertStatus(200);
});

it('super admin can access page', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'super_admin']);
    $this->actingAs($user);

    $this->get(route('ppdb.default-class-mappings.index'))->assertStatus(200);
});

it('does not render an academic year selector', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->assertDontSeeHtml('selectedAcademicYearId')
        ->assertDontSee('Tahun Ajaran')
        ->assertSee('Pengaturan berlaku untuk semua tahun ajaran.');
});

it('loads existing global mappings without selecting a year', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $class = SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);
    PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => $class->id,
    ]);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->assertSet('selections.7', (string) $class->id);
});

it('leaves missing mappings unconfigured without selecting a fallback', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->assertSet('selections.7', null);
});

it('loads global mappings and class options with stable query counts', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $class = SchoolClass::factory()->create(['level' => 7]);
    PpdbDefaultClassMapping::create(['class_level' => 7, 'school_class_id' => $class->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(PpdbDefaultClassMappingManagement::class)->assertStatus(200);

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "ppdb_default_class_mappings"'))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "school_classes"'))->count())->toBe(1);
});

it('can create mapping', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $class = SchoolClass::factory()->create(['level' => 1]);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->set('selections.1', (string) $class->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Pengaturan kelas default PPDB berhasil disimpan.');

    $this->assertDatabaseHas('ppdb_default_class_mappings', [
        'class_level' => 1,
        'school_class_id' => $class->id,
    ]);
});

it('can update mapping', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $class1 = SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);
    $class2 = SchoolClass::factory()->create(['level' => 7, 'name' => '7B']);

    PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => $class1->id,
    ]);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->set('selections.7', (string) $class2->id)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('ppdb_default_class_mappings', [
        'class_level' => 7,
        'school_class_id' => $class2->id,
    ]);
    $this->assertDatabaseMissing('ppdb_default_class_mappings', [
        'class_level' => 7,
        'school_class_id' => $class1->id,
    ]);
});

it('rejects wrong level class', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $class = SchoolClass::factory()->create(['level' => 8]);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->set('selections.7', (string) $class->id)
        ->call('save')
        ->assertHasErrors('selections.7');
});

it('rejects an invalid class id', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->set('selections.1', '999999')
        ->call('save')
        ->assertHasErrors('selections.1');

    expect(PpdbDefaultClassMapping::query()->count())->toBe(0);
});

it('does not delete a mapping when its dropdown is cleared', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $class = SchoolClass::factory()->create(['level' => 1]);
    $mapping = PpdbDefaultClassMapping::create([
        'class_level' => 1,
        'school_class_id' => $class->id,
    ]);

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->set('selections.1', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('ppdb_default_class_mappings', [
        'id' => $mapping->id,
        'class_level' => 1,
        'school_class_id' => $class->id,
    ]);
});

it('keeps applicants students bills and payments unchanged', function () {
    $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
    $this->actingAs($user);

    $year = createYearForSettings(['is_active' => true]);
    $existingClass = SchoolClass::factory()->create(['level' => 1, 'name' => '1A']);
    $newDefaultClass = SchoolClass::factory()->create(['level' => 1, 'name' => '1B']);
    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $existingClass->id,
    ]);
    $student = Student::factory()->create(['class_id' => $existingClass->id]);

    $databaseCounts = [
        'student_bills' => DB::table('student_bills')->count(),
        'payments' => DB::table('payments')->count(),
        'prospective_student_bills' => DB::table('prospective_student_bills')->count(),
        'prospective_student_payments' => DB::table('prospective_student_payments')->count(),
    ];

    Livewire::test(PpdbDefaultClassMappingManagement::class)
        ->set('selections.1', (string) $newDefaultClass->id)
        ->call('save');

    expect($prospect->fresh()->school_class_id)->toBe($existingClass->id)
        ->and($prospect->fresh()->academic_year_id)->toBe($year->id)
        ->and($student->fresh()->class_id)->toBe($existingClass->id)
        ->and(DB::table('student_bills')->count())->toBe($databaseCounts['student_bills'])
        ->and(DB::table('payments')->count())->toBe($databaseCounts['payments'])
        ->and(DB::table('prospective_student_bills')->count())->toBe($databaseCounts['prospective_student_bills'])
        ->and(DB::table('prospective_student_payments')->count())->toBe($databaseCounts['prospective_student_payments']);
});

it('enforces the global class level unique constraint', function () {
    $classA = SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);
    $classB = SchoolClass::factory()->create(['level' => 7, 'name' => '7B']);

    PpdbDefaultClassMapping::create(['class_level' => 7, 'school_class_id' => $classA->id]);

    expect(fn () => PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => $classB->id,
    ]))->toThrow(QueryException::class);
});

it('aborts the global migration before schema changes when duplicate levels exist', function () {
    $yearA = createYearForSettings(['year' => '2036/2037']);
    $yearB = createYearForSettings(['year' => '2037/2038']);
    $classA = SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);
    $classB = SchoolClass::factory()->create(['level' => 7, 'name' => '7B']);

    Schema::drop('ppdb_default_class_mappings');
    Schema::create('ppdb_default_class_mappings', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnUpdate()->restrictOnDelete();
        $table->smallInteger('class_level');
        $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnUpdate()->restrictOnDelete();
        $table->timestamps();
        $table->unique(['academic_year_id', 'class_level'], 'ppdb_dcm_ay_cl_unique');
        $table->unique(['academic_year_id', 'school_class_id'], 'ppdb_dcm_ay_sc_unique');
        $table->index('school_class_id', 'ppdb_dcm_sc_idx');
    });

    DB::table('ppdb_default_class_mappings')->insert([
        ['academic_year_id' => $yearA->id, 'class_level' => 7, 'school_class_id' => $classA->id],
        ['academic_year_id' => $yearB->id, 'class_level' => 7, 'school_class_id' => $classB->id],
    ]);

    $migration = require database_path('migrations/2026_10_27_152949_make_ppdb_default_class_mappings_global.php');

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'Resolve duplicate class levels manually first: 7');

    expect(Schema::hasColumn('ppdb_default_class_mappings', 'academic_year_id'))->toBeTrue()
        ->and(DB::table('ppdb_default_class_mappings')->count())->toBe(2);
});

it('preserves mapping ids and class choices while making mappings global', function () {
    $year = createYearForSettings(['year' => '2040/2041']);
    $class = SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);

    Schema::drop('ppdb_default_class_mappings');
    Schema::create('ppdb_default_class_mappings', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnUpdate()->restrictOnDelete();
        $table->smallInteger('class_level');
        $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnUpdate()->restrictOnDelete();
        $table->timestamps();
        $table->unique(['academic_year_id', 'class_level'], 'ppdb_dcm_ay_cl_unique');
        $table->unique(['academic_year_id', 'school_class_id'], 'ppdb_dcm_ay_sc_unique');
        $table->index('school_class_id', 'ppdb_dcm_sc_idx');
    });

    DB::table('ppdb_default_class_mappings')->insert([
        'id' => 99,
        'academic_year_id' => $year->id,
        'class_level' => 7,
        'school_class_id' => $class->id,
    ]);

    $migration = require database_path('migrations/2026_10_27_152949_make_ppdb_default_class_mappings_global.php');
    $migration->up();

    expect(Schema::hasColumn('ppdb_default_class_mappings', 'academic_year_id'))->toBeFalse()
        ->and(Schema::hasIndex('ppdb_default_class_mappings', 'ppdb_dcm_cl_unique', 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('ppdb_default_class_mappings', 'ppdb_dcm_sc_idx'))->toBeTrue();

    $this->assertDatabaseHas('ppdb_default_class_mappings', [
        'id' => 99,
        'class_level' => 7,
        'school_class_id' => $class->id,
    ]);
});
