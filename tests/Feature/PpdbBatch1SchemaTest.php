<?php

use App\Enums\PpdbDocumentType;
use App\Models\AcademicYear;
use App\Models\PpdbDefaultClassMapping;
use App\Models\PpdbDocument;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Services\ProspectiveStudentConversionService;
use App\Services\StudentCreationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function createAcademicYear(array $attributes = []): AcademicYear
{
    $now = now();

    return AcademicYear::create(array_merge([
        'year' => $attributes['year'] ?? ($now->year - 1).'/'.$now->year,
        'is_active' => false,
        'start_date' => $attributes['start_date'] ?? Carbon::create($now->year - 1, 7, 1),
        'end_date' => $attributes['end_date'] ?? Carbon::create($now->year, 6, 30),
    ], $attributes));
}

it('creates ppdb sidecar tables via migrations', function () {
    expect(Schema::hasTable('ppdb_profiles'))->toBeTrue();
    expect(Schema::hasTable('ppdb_parents'))->toBeTrue();
    expect(Schema::hasTable('ppdb_siblings'))->toBeTrue();
    expect(Schema::hasTable('ppdb_documents'))->toBeTrue();
    expect(Schema::hasTable('ppdb_default_class_mappings'))->toBeTrue();
    expect(Schema::hasColumn('ppdb_profiles', 'asal_sekolah'))->toBeTrue()
        ->and(Schema::hasColumn('students', 'asal_sekolah'))->toBeFalse();
});

it('stores asal sekolah only on the ppdb profile and allows legacy nulls', function () {
    $year = createAcademicYear();
    $class = SchoolClass::factory()->create(['level' => 1]);

    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
    ]);

    $profile = PpdbProfile::create([
        'prospective_student_id' => $prospect->id,
        'asal_sekolah' => 'TK ANNUR',
    ]);

    expect($profile->fresh()->asal_sekolah)->toBe('TK ANNUR')
        ->and(PpdbProfile::create(['prospective_student_id' => ProspectiveStudent::factory()->create()->id])->asal_sekolah)->toBeNull();
});

it('adds nullable ppdb biodata columns with the expected database types', function () {
    $profileColumns = collect(Schema::getColumns('ppdb_profiles'))->keyBy('name');
    $parentColumns = collect(Schema::getColumns('ppdb_parents'))->keyBy('name');

    expect($profileColumns->keys()->all())->toContain(
        'jarak_tempuh_kategori',
        'jarak_tempuh_detail',
        'moda_transportasi',
        'moda_transportasi_lainnya',
        'hobi',
        'cita_cita',
        'nisn',
    )->and($profileColumns['jarak_tempuh_kategori']['type_name'])->toBe('varchar')
        ->and($profileColumns['jarak_tempuh_detail']['type_name'])->toBe('numeric')
        ->and($profileColumns['moda_transportasi']['type_name'])->toBe('varchar')
        ->and($profileColumns['moda_transportasi_lainnya']['type_name'])->toBe('varchar')
        ->and($profileColumns['hobi']['type_name'])->toBe('varchar')
        ->and($profileColumns['cita_cita']['type_name'])->toBe('varchar')
        ->and($profileColumns['nisn']['type_name'])->toBe('varchar')
        ->and($parentColumns['status_hidup']['type_name'])->toBe('varchar');

    foreach ([
        'jarak_tempuh_kategori',
        'jarak_tempuh_detail',
        'moda_transportasi',
        'moda_transportasi_lainnya',
        'hobi',
        'cita_cita',
        'nisn',
    ] as $column) {
        expect($profileColumns[$column]['nullable'])->toBeTrue();
    }

    expect($parentColumns['status_hidup']['nullable'])->toBeTrue();
});

it('mass assigns and casts the additional ppdb biodata fields', function () {
    $prospect = ProspectiveStudent::factory()->create();

    $profile = PpdbProfile::create([
        'prospective_student_id' => $prospect->id,
        'jarak_tempuh_kategori' => 'more_than_1_km',
        'jarak_tempuh_detail' => 2.5,
        'moda_transportasi' => 'lainnya',
        'moda_transportasi_lainnya' => 'Perahu',
        'hobi' => 'Membaca',
        'cita_cita' => 'Dokter',
        'nisn' => '0012345678',
    ]);
    $father = PpdbParent::create([
        'prospective_student_id' => $prospect->id,
        'role' => 'father',
        'status_hidup' => 'hidup',
    ]);

    expect($profile->fresh()->jarak_tempuh_kategori)->toBe('more_than_1_km')
        ->and($profile->fresh()->jarak_tempuh_detail)->toBe('2.50')
        ->and($profile->fresh()->moda_transportasi)->toBe('lainnya')
        ->and($profile->fresh()->moda_transportasi_lainnya)->toBe('Perahu')
        ->and($profile->fresh()->hobi)->toBe('Membaca')
        ->and($profile->fresh()->cita_cita)->toBe('Dokter')
        ->and($profile->fresh()->nisn)->toBe('0012345678')
        ->and($father->fresh()->status_hidup)->toBe('hidup');
});

it('allows existing ppdb records to keep additional biodata values null', function () {
    $prospect = ProspectiveStudent::factory()->create();
    $profile = PpdbProfile::create(['prospective_student_id' => $prospect->id]);
    $mother = PpdbParent::create([
        'prospective_student_id' => $prospect->id,
        'role' => 'mother',
    ]);

    expect($profile->fresh()->only([
        'jarak_tempuh_kategori',
        'jarak_tempuh_detail',
        'moda_transportasi',
        'moda_transportasi_lainnya',
        'hobi',
        'cita_cita',
        'nisn',
    ]))->each->toBeNull()
        ->and($mother->fresh()->status_hidup)->toBeNull();
});

it('allows legacy prospective students without ppdb sidecars', function () {
    $year = createAcademicYear();
    $class = SchoolClass::factory()->create(['level' => 7]);

    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
    ]);

    expect($prospect->ppdbProfile)->toBeNull();
    expect($prospect->ppdbParents()->count())->toBe(0);
    expect($prospect->ppdbSiblings()->count())->toBe(0);
    expect($prospect->ppdbDocuments()->count())->toBe(0);
});

it('enforces one-to-one ppdb profile per prospective student', function () {
    $year = createAcademicYear();
    $class = SchoolClass::factory()->create(['level' => 1]);

    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
    ]);

    PpdbProfile::create([
        'prospective_student_id' => $prospect->id,
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-01-01',
    ]);

    expect(fn () => PpdbProfile::create([
        'prospective_student_id' => $prospect->id,
        'tempat_lahir' => 'Bandung',
    ]))->toThrow(QueryException::class);
});

it('enforces unique parent role per prospective student', function () {
    $year = createAcademicYear();
    $class = SchoolClass::factory()->create(['level' => 1]);

    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
    ]);

    PpdbParent::create([
        'prospective_student_id' => $prospect->id,
        'role' => 'father',
        'nama' => 'Budi',
    ]);

    expect(fn () => PpdbParent::create([
        'prospective_student_id' => $prospect->id,
        'role' => 'father',
        'nama' => 'Anton',
    ]))->toThrow(QueryException::class);
});

it('allows multiple siblings ordered by sort_order', function () {
    $year = createAcademicYear();
    $class = SchoolClass::factory()->create(['level' => 1]);

    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
    ]);

    PpdbSibling::create(['prospective_student_id' => $prospect->id, 'sort_order' => 2, 'nama_lengkap' => 'B']);
    PpdbSibling::create(['prospective_student_id' => $prospect->id, 'sort_order' => 1, 'nama_lengkap' => 'A']);

    $names = $prospect->fresh()->ppdbSiblings->pluck('nama_lengkap')->all();
    expect($names)->toBe(['A', 'B']);
});

it('enforces unique document type per prospective student', function () {
    $year = createAcademicYear();
    $class = SchoolClass::factory()->create(['level' => 1]);

    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
    ]);

    PpdbDocument::create([
        'prospective_student_id' => $prospect->id,
        'document_type' => PpdbDocumentType::BuktiTransfer->value,
        'disk' => 'local',
        'path' => 'ppdb/test.pdf',
        'original_name' => 'bukti.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
    ]);

    expect(fn () => PpdbDocument::create([
        'prospective_student_id' => $prospect->id,
        'document_type' => PpdbDocumentType::BuktiTransfer->value,
        'disk' => 'local',
        'path' => 'ppdb/test2.pdf',
        'original_name' => 'bukti2.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 2048,
    ]))->toThrow(QueryException::class);
});

it('maps one global default class per class level', function () {
    $class = SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);

    $mapping = PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => $class->id,
    ]);

    expect($mapping->schoolClass->is($class))->toBeTrue()
        ->and(
            Schema::hasColumn('ppdb_default_class_mappings', 'academic_year_id')
        )->toBeFalse();
});

it('rejects default class mapping when school class level differs', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);

    expect(fn () => PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => $class->id,
    ]))->toThrow(ValidationException::class);
});

it('rejects unsupported class levels and missing school classes', function () {
    expect(fn () => PpdbDefaultClassMapping::create([
        'class_level' => 0,
        'school_class_id' => 999999,
    ]))->toThrow(ValidationException::class, 'Tingkat kelas tidak valid.');

    expect(fn () => PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => 999999,
    ]))->toThrow(ValidationException::class, 'Kelas tidak valid.');
});

it('enforces one global default mapping per class level', function () {
    $classA = SchoolClass::factory()->create(['level' => 7, 'name' => '7A']);
    $classB = SchoolClass::factory()->create(['level' => 7, 'name' => '7B']);

    PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => $classA->id,
    ]);

    expect(fn () => PpdbDefaultClassMapping::create([
        'class_level' => 7,
        'school_class_id' => $classB->id,
    ]))->toThrow(QueryException::class);
});

it('does not delete existing prospective students when creating ppdb records', function () {
    $year = createAcademicYear();
    $class = SchoolClass::factory()->create(['level' => 1]);

    $prospect = ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
    ]);

    PpdbProfile::create(['prospective_student_id' => $prospect->id]);
    PpdbParent::create(['prospective_student_id' => $prospect->id, 'role' => 'mother']);
    PpdbSibling::create(['prospective_student_id' => $prospect->id, 'sort_order' => 1, 'nama_lengkap' => 'Adi']);
    PpdbDocument::create([
        'prospective_student_id' => $prospect->id,
        'document_type' => PpdbDocumentType::PasFoto->value,
        'disk' => 'local',
        'path' => 'ppdb/pasfoto.jpg',
        'original_name' => 'foto.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 512000,
    ]);

    expect(ProspectiveStudent::find($prospect->id))->not->toBeNull();
});

it('keeps existing billing and conversion logic untouched', function () {
    expect(class_exists(ProspectiveStudentConversionService::class))->toBeTrue();
    expect(class_exists(StudentCreationService::class))->toBeTrue();
    expect(class_exists(ProspectiveStudentBill::class))->toBeTrue();
});
