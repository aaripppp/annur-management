<?php

use App\Models\AcademicYear;
use App\Models\PpdbParent;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\PpdbApplicantDataUpdater;
use App\Services\ProspectiveStudentBulkUpdateService;
use App\Services\StudentBulkUpdateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function ensureNameNormSchema(): void
{
    if (! Schema::hasTable('academic_years')) {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('school_classes')) {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level')->nullable();
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('students')) {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('nis')->nullable();
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->foreignId('class_id')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('no_telp_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('no_telp_ibu')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->date('entry_date')->nullable();
            $table->string('foto')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    } else {
        try {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('class_id');
            });
        } catch (Throwable $e) {
        }
        try {
            Schema::table('students', function (Blueprint $table) {
                $table->foreignId('class_id')->nullable()->change();
            });
        } catch (Throwable $e) {
        }
    }
    if (! Schema::hasTable('prospective_students')) {
        Schema::create('prospective_students', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number');
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('nama_orang_tua')->nullable();
            $table->string('no_telp_orang_tua')->nullable();
            $table->text('alamat')->nullable();
            $table->string('applicant_category')->nullable();
            $table->foreignId('academic_year_id');
            $table->foreignId('school_class_id');
            $table->string('status')->default('registered');
            $table->foreignId('converted_student_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('ppdb_parents')) {
        Schema::create('ppdb_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id');
            $table->string('role');
            $table->string('nik')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('email')->nullable();
            $table->string('nama')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('occupation')->nullable();
            $table->string('income_range')->nullable();
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('ppdb_siblings')) {
        Schema::create('ppdb_siblings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id');
            $table->integer('sort_order')->default(1);
            $table->string('nama_lengkap');
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('pendidikan_pekerjaan')->nullable();
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('ppdb_profiles')) {
        Schema::create('ppdb_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id')->unique();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('nik_siswa')->nullable();
            $table->string('nomor_kk')->nullable();
            $table->string('nomor_akta_kelahiran')->nullable();
            $table->string('status_anak')->nullable();
            $table->integer('anak_ke')->nullable();
            $table->integer('jumlah_saudara')->nullable();
            $table->string('agama')->nullable();
            $table->string('bahasa_sehari_hari')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('tinggi_badan')->nullable();
            $table->string('berat_badan')->nullable();
            $table->string('lingkar_kepala')->nullable();
            $table->string('golongan_darah')->nullable();
            $table->string('riwayat_penyakit')->nullable();
            $table->boolean('berkebutuhan_khusus')->nullable();
            $table->string('keterangan_kebutuhan_khusus')->nullable();
            $table->timestamps();
        });
    }
}

beforeEach(function () {
    ensureNameNormSchema();
    DB::table('ppdb_siblings')->delete();
    DB::table('ppdb_parents')->delete();
    DB::table('ppdb_profiles')->delete();
    DB::table('prospective_students')->delete();
    DB::table('students')->delete();
    DB::table('school_classes')->delete();
    DB::table('academic_years')->delete();
});

it('normalizes all nine person name fields on create and update', function () {
    ensureNameNormSchema();
    $academicYear = AcademicYear::create(['year' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $class = SchoolClass::create(['name' => '1A', 'level' => 'sd']);

    $student = Student::create([
        'nama_lengkap' => ' ahmad fauzi ',
        'nama_panggilan' => 'fauzi',
        'nama_ayah' => "muhammad o'neill",
        'nama_ibu' => 'siti nur-aini',
    ]);
    expect($student->nama_lengkap)->toBe('AHMAD FAUZI');
    expect($student->nama_panggilan)->toBe('FAUZI');
    expect($student->nama_ayah)->toBe("MUHAMMAD O'NEILL");
    expect($student->nama_ibu)->toBe('SITI NUR-AINI');

    $student->update(['nama_panggilan' => '  fauz  ']);
    expect($student->fresh()->nama_panggilan)->toBe('FAUZ');

    $prospect = ProspectiveStudent::create([
        'registration_number' => 'PPDB-001',
        'nama_lengkap' => ' budi santoso ',
        'nama_panggilan' => 'budi',
        'nama_orang_tua' => 'santoso',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'registered',
    ]);
    expect($prospect->nama_lengkap)->toBe('BUDI SANTOSO');
    expect($prospect->nama_panggilan)->toBe('BUDI');
    expect($prospect->nama_orang_tua)->toBe('SANTOSO');

    $parent = PpdbParent::create([
        'prospective_student_id' => $prospect->id,
        'role' => 'father',
        'nama' => " ahmad d'arcy ",
    ]);
    expect($parent->nama)->toBe("AHMAD D'ARCY");

    $sibling = PpdbSibling::create([
        'prospective_student_id' => $prospect->id,
        'sort_order' => 1,
        'nama_lengkap' => ' rani putri ',
    ]);
    expect($sibling->nama_lengkap)->toBe('RANI PUTRI');
});

it('keeps NULL and preserves non-name text casing', function () {
    ensureNameNormSchema();
    $academicYear = AcademicYear::create(['year' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $class = SchoolClass::create(['name' => '1A', 'level' => 'sd']);

    $student = Student::create([
        'nama_lengkap' => 'AHMAD',
        'nama_panggilan' => null,
        'alamat' => 'Jl. Merdeka No. 1',
    ]);
    expect($student->nama_panggilan)->toBeNull();
    expect($student->fresh()->alamat)->toBe('Jl. Merdeka No. 1');

    $prospect = ProspectiveStudent::create([
        'registration_number' => 'PPDB-002',
        'nama_lengkap' => 'BUDI',
        'nama_orang_tua' => null,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'registered',
        'alamat' => 'Some Address',
    ]);
    expect($prospect->nama_orang_tua)->toBeNull();
    expect($prospect->fresh()->alamat)->toBe('Some Address');
});

it('does not change legacy name on unrelated model update and uppercases on explicit change', function () {
    ensureNameNormSchema();
    $student = Student::create([
        'nama_lengkap' => 'Ahmad',
        'nama_panggilan' => 'ahmad',
        'alamat' => 'old',
    ]);
    DB::table('students')->where('id', $student->id)->update(['nama_lengkap' => 'Ahmad Legacy', 'nama_panggilan' => 'ali legacy']);
    $student->refresh();
    expect($student->nama_lengkap)->toBe('Ahmad Legacy');
    expect($student->nama_panggilan)->toBe('ali legacy');

    $student->alamat = 'new';
    $student->save();
    $student->refresh();
    expect($student->nama_lengkap)->toBe('Ahmad Legacy');
    expect($student->nama_panggilan)->toBe('ali legacy');

    $student->nama_panggilan = 'ali changed';
    $student->save();
    $student->refresh();
    expect($student->nama_panggilan)->toBe('ALI CHANGED');
});

it('normalizes in student and prospective bulk updates only for supplied name fields', function () {
    ensureNameNormSchema();
    $student = Student::create(['nama_lengkap' => 'ORIGINAL', 'nama_ayah' => 'OLD FATHER']);
    $studentBulk = app(StudentBulkUpdateService::class);
    $updated = $studentBulk->apply([
        [
            'row_number' => 1,
            'id_sistem' => (string) $student->id,
            'nama_ayah' => '  new father  ',
            'alamat' => 'keep-case',
        ],
    ]);
    expect($updated)->toBe(1);
    $student->refresh();
    expect($student->nama_ayah)->toBe('NEW FATHER');
    expect($student->alamat)->toBe('keep-case');

    $academicYear = AcademicYear::create(['year' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $class = SchoolClass::create(['name' => '1A', 'level' => 'sd']);
    $prospect = ProspectiveStudent::create([
        'registration_number' => 'PPDB-003',
        'nama_lengkap' => 'BUDI',
        'nama_orang_tua' => 'OLD',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'registered',
    ]);
    $prospectBulk = app(ProspectiveStudentBulkUpdateService::class);
    $updated2 = $prospectBulk->apply([
        [
            'row_number' => 1,
            'id_sistem' => (string) $prospect->id,
            'nama_orang_tua' => ' new parent ',
            'alamat' => 'Some Address',
        ],
    ]);
    expect($updated2)->toBe(1);
    $prospect->refresh();
    expect($prospect->nama_orang_tua)->toBe('NEW PARENT');
    expect($prospect->alamat)->toBe('Some Address');
});

it('preserves unchanged sibling names during replace and uppercases changed/new ones', function () {
    ensureNameNormSchema();
    $academicYear = AcademicYear::create(['year' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $class = SchoolClass::create(['name' => '1A', 'level' => 'sd']);
    $prospect = ProspectiveStudent::create([
        'registration_number' => 'PPDB-004',
        'nama_lengkap' => 'ANDI',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'registered',
    ]);
    DB::table('ppdb_siblings')->insert([
        ['prospective_student_id' => $prospect->id, 'sort_order' => 1, 'nama_lengkap' => 'Adi Putra', 'tanggal_lahir' => null, 'jenis_kelamin' => null, 'pendidikan_pekerjaan' => null, 'created_at' => now(), 'updated_at' => now()],
        ['prospective_student_id' => $prospect->id, 'sort_order' => 2, 'nama_lengkap' => 'Ayu Lestari', 'tanggal_lahir' => null, 'jenis_kelamin' => null, 'pendidikan_pekerjaan' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $updater = app(PpdbApplicantDataUpdater::class);
    $updater->update($prospect->id, [
        'applicant_category' => null,
        'student' => ['nama_lengkap' => 'ANDI', 'nama_panggilan' => null, 'jenis_kelamin' => null, 'alamat' => null],
        'profile' => [],
        'parents' => ['father' => [], 'mother' => [], 'guardian' => []],
        'siblings' => [
            ['nama_lengkap' => 'Adi Putra', 'tanggal_lahir' => null, 'jenis_kelamin' => null, 'pendidikan_pekerjaan' => null],
            ['nama_lengkap' => 'ayu changed', 'tanggal_lahir' => null, 'jenis_kelamin' => null, 'pendidikan_pekerjaan' => null],
        ],
    ]);

    $raws = DB::table('ppdb_siblings')->where('prospective_student_id', $prospect->id)->orderBy('sort_order')->pluck('nama_lengkap')->all();
    expect($raws[0])->toBe('Adi Putra');
    expect($raws[1])->toBe('AYU CHANGED');
});

it('normalizes Unicode/accented names and preserves apostrophe/hyphen', function () {
    ensureNameNormSchema();
    $student = Student::create([
        'nama_lengkap' => 'josé müller',
        'nama_ayah' => "o'connor",
        'nama_ibu' => 'anne-marie',
    ]);
    expect($student->nama_lengkap)->toBe('JOSÉ MÜLLER');
    expect($student->nama_ayah)->toBe("O'CONNOR");
    expect($student->nama_ibu)->toBe('ANNE-MARIE');
});

it('conversion creates uppercase student names without modifying source prospect', function () {
    ensureNameNormSchema();
    $academicYear = AcademicYear::create(['year' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $class = SchoolClass::create(['name' => '1A', 'level' => 'sd']);
    $prospect = ProspectiveStudent::create([
        'registration_number' => 'PPDB-005',
        'nama_lengkap' => 'citra dewi',
        'nama_panggilan' => 'citra',
        'nama_orang_tua' => 'bambang',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'registered',
    ]);

    $student = Student::create([
        'nama_lengkap' => 'CITRA DEWI',
        'nama_panggilan' => 'CITRA',
        'nama_ayah' => 'BAMBANG',
    ]);
    $prospect->update(['status' => 'converted', 'converted_student_id' => $student->id]);

    $prospect->refresh();
    $student->refresh();
    expect($student->nama_lengkap)->toBe('CITRA DEWI');
    expect($student->nama_ayah)->toBe('BAMBANG');
    expect($prospect->nama_lengkap)->toBe('CITRA DEWI');
});
