<?php

use App\Enums\PpdbApplicantCategory;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use Illuminate\Support\Facades\Schema;

it('adds a nullable applicant category column without a default', function () {
    $column = collect(Schema::getColumns('prospective_students'))
        ->firstWhere('name', 'applicant_category');

    expect($column)->not->toBeNull()
        ->and($column['type_name'])->toBe('varchar')
        ->and($column['nullable'])->toBeTrue()
        ->and($column['default'])->toBeNull();
});

it('defines only the two supported applicant categories with Indonesian labels', function () {
    expect(PpdbApplicantCategory::cases())->toHaveCount(2)
        ->and(PpdbApplicantCategory::ANNUR_FAMILY->value)->toBe('annur_family')
        ->and(PpdbApplicantCategory::ANNUR_FAMILY->label())->toBe('Keluarga Annur')
        ->and(PpdbApplicantCategory::NON_ANNUR_FAMILY->value)->toBe('non_annur_family')
        ->and(PpdbApplicantCategory::NON_ANNUR_FAMILY->label())->toBe('Non-Keluarga Annur');
});

it('saves and casts Keluarga Annur', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);

    expect($prospectiveStudent->getRawOriginal('applicant_category'))->toBe('annur_family')
        ->and($prospectiveStudent->fresh()->applicant_category)->toBe(PpdbApplicantCategory::ANNUR_FAMILY);
});

it('saves and casts Non-Keluarga Annur', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY,
    ]);

    expect($prospectiveStudent->getRawOriginal('applicant_category'))->toBe('non_annur_family')
        ->and($prospectiveStudent->fresh()->applicant_category)->toBe(PpdbApplicantCategory::NON_ANNUR_FAMILY);
});

it('keeps historical prospective students unclassified by default', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();

    expect($prospectiveStudent->getRawOriginal('applicant_category'))->toBeNull()
        ->and($prospectiveStudent->fresh()->applicant_category)->toBeNull();
});

it('keeps the converted student relationship without copying applicant category', function () {
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
        'converted_student_id' => $student->id,
    ]);

    expect($student->fresh()->prospectiveStudent->is($prospectiveStudent))->toBeTrue()
        ->and($student->prospectiveStudent->applicant_category)->toBe(PpdbApplicantCategory::ANNUR_FAMILY)
        ->and($student->categories()->count())->toBe(0);
});
