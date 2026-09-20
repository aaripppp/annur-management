<?php

use App\Livewire\AcademicYearManagement;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\ClassPromotionService;
use Livewire\Livewire;

beforeEach(function () {
    $this->activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $this->newYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $this->sppType = makeBillType('SPP', auto: true, required: true);
    $this->ekskulType = makeBillType('Ekskul', auto: true, required: true);
});

/*
|--------------------------------------------------------------------------
| Test 1: Future enrollment in target year does NOT make promotion processed
|--------------------------------------------------------------------------
*/
it('does not treat future student enrollment as proof of promotion', function () {
    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    expect($this->newYear->fresh()->promotion_processed_at)->toBeNull();

    $service = app(ClassPromotionService::class);
    expect($service->isAlreadyProcessed($this->newYear))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 2: Preview remains accessible with future student enrollment
|--------------------------------------------------------------------------
*/
it('allows promotion preview when future student exists in target year', function () {
    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSet('isAlreadyProcessed', false)
        ->assertSet('isPreviewOpen', true);
});

/*
|--------------------------------------------------------------------------
| Test 3: Future student remains excluded from source-year promotion
|--------------------------------------------------------------------------
*/
it('excludes future student from promotion preview', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);

    $currentStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $currentStudent->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $service = app(ClassPromotionService::class);
    $preview = $service->getPreviewData($this->activeYear, $this->newYear);

    $studentIds = $preview['students']->pluck('id')->toArray();
    expect($studentIds)->toContain($currentStudent->id);
    expect($studentIds)->not->toContain($futureStudent->id);
});

/*
|--------------------------------------------------------------------------
| Test 4: Successful promotion sets promotion_processed_at
|--------------------------------------------------------------------------
*/
it('sets promotion_processed_at after successful promotion', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $service->processPromotion($this->activeYear, $this->newYear);

    $this->newYear->refresh();
    expect($this->newYear->promotion_processed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Test 5: Second promotion attempt is blocked
|--------------------------------------------------------------------------
*/
it('blocks second promotion attempt as already processed', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $result1 = $service->processPromotion($this->activeYear, $this->newYear);
    expect($result1['promoted'])->toBe(1);

    $result2 = $service->processPromotion($this->activeYear, $this->newYear);
    expect($result2['promoted'])->toBe(0)
        ->and($result2['graduated'])->toBe(0);

    $enrollments = StudentAcademicEnrollment::where('academic_year_id', $this->newYear->id)->count();
    expect($enrollments)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Test 6: Livewire shows Sudah Diproses only after promotion_processed_at is set
|--------------------------------------------------------------------------
*/
it('shows already processed message only after promotion is marked', function () {
    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSet('isAlreadyProcessed', false)
        ->assertSet('isPreviewOpen', true);

    $this->newYear->update(['promotion_processed_at' => now()]);

    Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSet('isAlreadyProcessed', true)
        ->assertSet('isPreviewOpen', false);
});

/*
|--------------------------------------------------------------------------
| Test 7: Existing target-year future enrollment remains untouched
|--------------------------------------------------------------------------
*/
it('preserves future student enrollment after promotion', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $currentStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $currentStudent->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);
    $futureEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $service->processPromotion($this->activeYear, $this->newYear);

    $futureEnrollment->refresh();
    expect($futureEnrollment->school_class_id)->toBe($class7->id);
    expect($futureEnrollment->status)->toBe('active');

    $currentStudent->refresh();
    expect($currentStudent->class_id)->toBe($class8->id);
});

/*
|--------------------------------------------------------------------------
| Test 8: isAlreadyProcessed uses promotion_processed_at exclusively
|--------------------------------------------------------------------------
*/
it('uses promotion_processed_at as sole source of truth', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $service = app(ClassPromotionService::class);
    expect($service->isAlreadyProcessed($this->newYear))->toBeFalse();

    $this->newYear->update(['promotion_processed_at' => now()]);
    expect($service->isAlreadyProcessed($this->newYear))->toBeTrue();

    $this->newYear->update(['promotion_processed_at' => null]);
    expect($service->isAlreadyProcessed($this->newYear))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 9: Full scenario with future student + current student promotion
|--------------------------------------------------------------------------
*/
it('promotes current student while preserving future student', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $currentStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $currentStudent->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $result = $service->processPromotion($this->activeYear, $this->newYear);

    expect($result['promoted'])->toBe(1);

    $currentStudent->refresh();
    expect($currentStudent->class_id)->toBe($class8->id);

    $futureEnrollment = StudentAcademicEnrollment::where('student_id', $futureStudent->id)
        ->where('academic_year_id', $this->newYear->id)
        ->first();
    expect($futureEnrollment->school_class_id)->toBe($class7->id);

    $this->newYear->refresh();
    expect($this->newYear->promotion_processed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Test 10: Dropdown still returns all academic years
|--------------------------------------------------------------------------
*/
it('dropdown returns all academic years including future', function () {
    Livewire::test(AcademicYearManagement::class)
        ->assertSee('2026/2027')
        ->assertSee('2027/2028');
});
