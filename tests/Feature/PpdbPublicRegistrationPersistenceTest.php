<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\PpdbApplicantCategory;
use App\Enums\PpdbDocumentType;
use App\Enums\SchoolLevel;
use App\Livewire\PpdbPublicRegistration;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\PpdbAcademicYearSetting;
use App\Models\PpdbDefaultClassMapping;
use App\Models\PpdbProfile;
use App\Models\PpdbPublicSubmission;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Services\PpdbPublicRegistrationService;
use App\Services\ProspectiveStudentBillGenerationService;
use App\Services\ProspectiveStudentRegistrationNumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function persistentPpdbFixture(bool $isOpen = true): array
{
    $year = AcademicYear::query()->create([
        'year' => '2060/2061',
        'is_active' => false,
        'start_date' => '2060-07-01',
        'end_date' => '2061-06-30',
    ]);

    $setting = PpdbAcademicYearSetting::query()->create([
        'academic_year_id' => $year->id,
        'is_open' => $isOpen,
    ]);

    $class = SchoolClass::factory()->create([
        'name' => 'Kelas 7 PPDB',
        'level' => 7,
    ]);

    PpdbDefaultClassMapping::query()->create([
        'class_level' => 7,
        'school_class_id' => $class->id,
    ]);

    return [$year, $setting, $class];
}

function persistentPpdbData(AcademicYear $year): array
{
    return [
        'academic_year_id' => $year->id,
        'school_level' => 'SMP',
        'class_level' => 7,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
        'full_name' => 'Ahmad Pendaftar',
        'nickname' => 'Ahmad',
        'gender' => 'L',
        'birthplace' => 'Jakarta',
        'birthdate' => '2015-01-01',
        'asal_sekolah' => 'TK ANNUR',
        'nik_siswa' => '3201010101010001',
        'nisn' => '0012345678',
        'nomor_kk' => '3201010101010002',
        'nomor_akta_kelahiran' => 'AKTA-001',
        'anak_ke' => 1,
        'jumlah_saudara' => 1,
        'hobi' => 'Membaca',
        'cita_cita' => 'Dokter',
        'status_anak' => 'Kandung',
        'agama' => 'Islam',
        'bahasa_sehari_hari' => 'Indonesia',
        'address' => 'Jl. Pendidikan No. 1',
        'rt' => '01',
        'rw' => '02',
        'kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibiru',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40615',
        'jarak_tempuh_kategori' => 'more_than_1_km',
        'jarak_tempuh_detail' => '2,5',
        'moda_transportasi' => 'antar_jemput',
        'moda_transportasi_lainnya' => null,
        'tinggi_badan' => 140,
        'berat_badan' => 35,
        'lingkar_kepala' => 52,
        'golongan_darah' => 'O',
        'riwayat_penyakit' => 'Tidak Ada',
        'berkebutuhan_khusus' => 'Tidak',
        'keterangan_kebutuhan_khusus' => null,
        'father' => [
            'name' => 'Ayah Ahmad',
            'living_status' => 'hidup',
            'nik' => '3201010101010003',
            'birthplace' => 'Bandung',
            'birthdate' => '1985-01-01',
            'education_last' => 'S1',
            'email' => 'ayah@example.com',
            'phone' => '081234567890',
            'occupation' => 'Wiraswasta',
            'income_range' => '5m_to_7_499m',
        ],
        'mother' => [
            'name' => 'Ibu Ahmad',
            'living_status' => 'wafat',
            'nik' => '3201010101010004',
            'birthplace' => 'Bandung',
            'birthdate' => '1987-01-01',
            'education_last' => 'SMA',
            'email' => 'ibu@example.com',
            'phone' => '081234567891',
            'occupation' => 'Guru',
            'income_range' => '3m_to_4_999m',
        ],
        'guardian' => [
            'name' => '',
            'nik' => '',
            'birthplace' => '',
            'birthdate' => '',
            'education_last' => '',
            'email' => '',
            'phone' => '',
            'occupation' => '',
            'income_range' => '',
        ],
        'siblings' => [
            ['key' => (string) Str::uuid(), 'full_name' => 'Saudara Ahmad', 'birthdate' => '2016-01-01', 'gender' => 'L', 'education_occupation' => 'Kelas 5'],
        ],
        'consent' => true,
    ];
}

function persistentPpdbDocuments(): array
{
    $documents = [];

    foreach (PpdbDocumentType::values() as $documentType) {
        $documents[$documentType] = UploadedFile::fake()->image($documentType.'.jpg');
    }

    return $documents;
}

function persistentPpdbBillType(int $amount = 412345, string $name = 'Formulir PPDB Public'): PaymentType
{
    $paymentType = PaymentType::query()->create([
        'name' => $name,
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::query()->create([
        'payment_type_id' => $paymentType->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $paymentType->id,
        'class_level' => 7,
        'amount' => $amount,
        'effective_from' => '2059-01-01',
        'effective_until' => null,
    ]);

    return $paymentType;
}

it('persists the submitted student NIK to the PPDB profile student NIK column', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();

    $result = app(PpdbPublicRegistrationService::class)->register(
        persistentPpdbData($year),
        persistentPpdbDocuments(),
        Str::random(64),
    );

    $prospectiveStudentId = DB::table('prospective_students')
        ->where('registration_number', $result['registration_number'])
        ->value('id');

    expect(DB::table('ppdb_profiles')
        ->where('prospective_student_id', $prospectiveStudentId)
        ->value('nik_siswa'))->toBe('3201010101010001');
});

it('accepts and persists every approved transportation option', function (string $transportation) {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);
    $data['moda_transportasi'] = $transportation;
    $data['moda_transportasi_lainnya'] = $transportation === 'lainnya' ? '  Perahu  ' : 'nilai-usang';

    app(PpdbPublicRegistrationService::class)->register($data, persistentPpdbDocuments(), Str::random(64));

    expect(DB::table('ppdb_profiles')->value('moda_transportasi'))->toBe($transportation)
        ->and(DB::table('ppdb_profiles')->value('moda_transportasi_lainnya'))->toBe($transportation === 'lainnya' ? 'Perahu' : null);
})->with(['jalan_kaki', 'sepeda', 'motor', 'mobil_pribadi', 'angkutan_umum', 'antar_jemput', 'lainnya']);

it('enforces the additional required biodata at the service boundary', function (string $field, mixed $value) {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);
    data_set($data, $field, $value);

    expect(fn () => app(PpdbPublicRegistrationService::class)->register(
        $data,
        persistentPpdbDocuments(),
        Str::random(64),
    ))->toThrow(ValidationException::class);

    expect(DB::table('prospective_students')->count())->toBe(0);
})->with([
    'NISN missing' => ['nisn', null],
    'NISN not ten digits' => ['nisn', '123456789'],
    'birth certificate whitespace' => ['nomor_akta_kelahiran', '   '],
    'child order missing' => ['anak_ke', null],
    'sibling count missing' => ['jumlah_saudara', null],
    'hobby whitespace' => ['hobi', '   '],
    'aspiration whitespace' => ['cita_cita', '   '],
    'distance category missing' => ['jarak_tempuh_kategori', null],
    'distance detail missing' => ['jarak_tempuh_detail', null],
    'distance not above one' => ['jarak_tempuh_detail', '1'],
    'distance too precise' => ['jarak_tempuh_detail', '2.555'],
    'distance too large' => ['jarak_tempuh_detail', '10000'],
    'transportation invalid' => ['moda_transportasi', 'pesawat'],
    'father living status missing' => ['father.living_status', null],
    'mother living status missing' => ['mother.living_status', null],
]);

it('forces inactive conditional values to null at the service boundary', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);
    $data['jarak_tempuh_kategori'] = 'less_than_1_km';
    $data['jarak_tempuh_detail'] = '99.99';
    $data['moda_transportasi'] = 'motor';
    $data['moda_transportasi_lainnya'] = 'nilai-usang';

    app(PpdbPublicRegistrationService::class)->register($data, persistentPpdbDocuments(), Str::random(64));

    $profile = DB::table('ppdb_profiles')->sole();
    expect($profile->jarak_tempuh_detail)->toBeNull()
        ->and($profile->moda_transportasi_lainnya)->toBeNull();
});

it('requires nonblank transportation detail for lainnya at the service boundary', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);
    $data['moda_transportasi'] = 'lainnya';
    $data['moda_transportasi_lainnya'] = '   ';

    expect(fn () => app(PpdbPublicRegistrationService::class)->register(
        $data,
        persistentPpdbDocuments(),
        Str::random(64),
    ))->toThrow(ValidationException::class);
});

it('allows TK without NISN and forces a stale NISN to null', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $class = SchoolClass::factory()->create(['name' => 'TK B PPDB', 'level' => -1]);
    PpdbDefaultClassMapping::query()->create(['class_level' => -1, 'school_class_id' => $class->id]);
    $data = persistentPpdbData($year);
    $data['school_level'] = 'TK';
    $data['class_level'] = -1;
    $data['nisn'] = '0012345678';

    app(PpdbPublicRegistrationService::class)->register($data, persistentPpdbDocuments(), Str::random(64));

    expect(DB::table('ppdb_profiles')->value('nisn'))->toBeNull();
});

it('requires an approved applicant category at the service boundary', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $missingCategory = persistentPpdbData($year);
    unset($missingCategory['applicant_category']);

    expect(fn () => app(PpdbPublicRegistrationService::class)->register(
        $missingCategory,
        persistentPpdbDocuments(),
        Str::random(64),
    ))->toThrow(ValidationException::class);

    expect(fn () => app(PpdbPublicRegistrationService::class)->register(
        array_merge(persistentPpdbData($year), ['applicant_category' => 'discount-family']),
        persistentPpdbDocuments(),
        Str::random(64),
    ))->toThrow(ValidationException::class);

    expect(DB::table('prospective_students')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('persists each applicant category without changing bills or documents', function (string $category) {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    persistentPpdbBillType(412345);
    $data = persistentPpdbData($year);
    $data['applicant_category'] = $category;

    $result = app(PpdbPublicRegistrationService::class)->register(
        $data,
        persistentPpdbDocuments(),
        Str::random(64),
    );

    $prospectiveStudentId = DB::table('prospective_students')
        ->where('registration_number', $result['registration_number'])
        ->value('id');

    expect(DB::table('prospective_students')->where('id', $prospectiveStudentId)->value('applicant_category'))->toBe($category)
        ->and((float) DB::table('prospective_student_bills')->where('prospective_student_id', $prospectiveStudentId)->value('amount'))->toBe(412345.0)
        ->and(DB::table('ppdb_documents')->where('prospective_student_id', $prospectiveStudentId)->count())->toBe(6)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
})->with([
    'Keluarga Annur' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    'Non-Keluarga Annur' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
]);

it('persists a complete public registration with dynamic bills and no payment writes', function () {
    Storage::fake('local');
    [$year, , $class] = persistentPpdbFixture();
    $paymentType = persistentPpdbBillType();
    $token = Str::random(64);
    $financialTables = [
        'prospective_student_payments',
        'prospective_student_payment_details',
        'payments',
        'student_bills',
        'students',
    ];
    $before = collect($financialTables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    $result = app(PpdbPublicRegistrationService::class)->register(
        persistentPpdbData($year),
        persistentPpdbDocuments(),
        $token,
    );

    $prospect = DB::table('prospective_students')->where('registration_number', $result['registration_number'])->first();
    $profile = DB::table('ppdb_profiles')->where('prospective_student_id', $prospect->id)->first();
    $father = DB::table('ppdb_parents')->where('prospective_student_id', $prospect->id)->where('role', 'father')->first();
    $mother = DB::table('ppdb_parents')->where('prospective_student_id', $prospect->id)->where('role', 'mother')->first();
    $submission = DB::table('ppdb_public_submissions')->first();
    $bill = ProspectiveStudentBill::query()->where('prospective_student_id', $prospect->id)->first();

    expect($prospect)->not->toBeNull()
        ->and($prospect->school_class_id)->toBe($class->id)
        ->and($prospect->created_by)->toBeNull()
        ->and($prospect->nama_lengkap)->toBe('AHMAD PENDAFTAR')
        ->and($prospect->nama_panggilan)->toBe('AHMAD')
        ->and($prospect->jenis_kelamin)->toBe('L')
        ->and($prospect->alamat)->toBe('Jl. Pendidikan No. 1')
        ->and($profile->nik_siswa)->toBe('3201010101010001')
        ->and($profile->nisn)->toBe('0012345678')
        ->and($profile->nomor_kk)->toBe('3201010101010002')
        ->and($profile->nomor_akta_kelahiran)->toBe('AKTA-001')
        ->and($profile->tempat_lahir)->toBe('Jakarta')
        ->and(Str::before($profile->tanggal_lahir, ' '))->toBe('2015-01-01')
        ->and($profile->asal_sekolah)->toBe('TK ANNUR')
        ->and($profile->anak_ke)->toBe(1)
        ->and($profile->jumlah_saudara)->toBe(1)
        ->and($profile->hobi)->toBe('Membaca')
        ->and($profile->cita_cita)->toBe('Dokter')
        ->and($profile->status_anak)->toBe('Kandung')
        ->and($profile->agama)->toBe('Islam')
        ->and($profile->bahasa_sehari_hari)->toBe('Indonesia')
        ->and($profile->rt)->toBe('01')
        ->and($profile->rw)->toBe('02')
        ->and($profile->kelurahan)->toBe('Sukamaju')
        ->and($profile->kecamatan)->toBe('Cibiru')
        ->and($profile->kabupaten_kota)->toBe('Bandung')
        ->and($profile->provinsi)->toBe('Jawa Barat')
        ->and($profile->kode_pos)->toBe('40615')
        ->and($profile->jarak_tempuh_kategori)->toBe('more_than_1_km')
        ->and((float) $profile->jarak_tempuh_detail)->toBe(2.5)
        ->and(PpdbProfile::query()->find($profile->id)?->jarak_tempuh_detail)->toBe('2.50')
        ->and($profile->moda_transportasi)->toBe('antar_jemput')
        ->and($profile->moda_transportasi_lainnya)->toBeNull()
        ->and((float) $profile->tinggi_badan)->toBe(140.0)
        ->and((float) $profile->berat_badan)->toBe(35.0)
        ->and((float) $profile->lingkar_kepala)->toBe(52.0)
        ->and($profile->golongan_darah)->toBe('O')
        ->and($profile->riwayat_penyakit)->toBe('Tidak Ada')
        ->and((bool) $profile->berkebutuhan_khusus)->toBeFalse()
        ->and($profile->keterangan_kebutuhan_khusus)->toBeNull()
        ->and($father->nik)->toBe('3201010101010003')
        ->and($father->status_hidup)->toBe('hidup')
        ->and($father->tempat_lahir)->toBe('Bandung')
        ->and(Str::before($father->tanggal_lahir, ' '))->toBe('1985-01-01')
        ->and($father->pendidikan_terakhir)->toBe('S1')
        ->and($father->email)->toBe('ayah@example.com')
        ->and($father->nama)->toBe('AYAH AHMAD')
        ->and($father->no_telp)->toBe('081234567890')
        ->and($father->occupation)->toBe('Wiraswasta')
        ->and($father->income_range)->toBe('5m_to_7_499m')
        ->and($mother->nik)->toBe('3201010101010004')
        ->and($mother->status_hidup)->toBe('wafat')
        ->and($mother->tempat_lahir)->toBe('Bandung')
        ->and(Str::before($mother->tanggal_lahir, ' '))->toBe('1987-01-01')
        ->and($mother->pendidikan_terakhir)->toBe('SMA')
        ->and($mother->email)->toBe('ibu@example.com')
        ->and($mother->nama)->toBe('IBU AHMAD')
        ->and($mother->no_telp)->toBe('081234567891')
        ->and($mother->occupation)->toBe('Guru')
        ->and($mother->income_range)->toBe('3m_to_4_999m')
        ->and(DB::table('ppdb_parents')->where('prospective_student_id', $prospect->id)->count())->toBe(2)
        ->and(DB::table('ppdb_siblings')->where('prospective_student_id', $prospect->id)->count())->toBe(1)
        ->and(DB::table('ppdb_documents')->where('prospective_student_id', $prospect->id)->count())->toBe(6)
        ->and($submission->idempotency_key_hash)->toBe(hash('sha256', $token))
        ->and($submission->idempotency_key_hash)->not->toBe($token)
        ->and($submission->success_reference_hash)->not->toBe($result['success_reference'])
        ->and($submission->consent_policy_version)->toBe(PpdbPublicRegistrationService::CONSENT_POLICY_VERSION)
        ->and($submission->consented_at)->not->toBeNull()
        ->and($submission->completed_at)->not->toBeNull()
        ->and($bill)->not->toBeNull()
        ->and($bill->payment_type_id)->toBe($paymentType->id)
        ->and($bill->amount)->toBe('412345.00')
        ->and($bill->academic_year)->toBe('2060/2061')
        ->and($bill->billing_frequency->value)->toBe('one_time')
        ->and($bill->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID)
        ->and($bill->paid_amount)->toBe(0.0)
        ->and($bill->created_by)->toBeNull();

    expect(DB::table('ppdb_documents')
        ->where('prospective_student_id', $prospect->id)
        ->where('document_type', 'bukti_transfer')
        ->count())->toBe(1);

    $storedDocuments = DB::table('ppdb_documents')->get();
    foreach ($storedDocuments as $document) {
        expect($document->disk)->toBe('local')
            ->and($document->path)->toStartWith('ppdb/documents/')
            ->and($document->path)->not->toContain($document->original_name);
        Storage::disk('local')->assertExists($document->path);
    }

    foreach ($before as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
});

it('trims asal sekolah and preserves a dash placeholder', function (string $asalSekolah, string $expected) {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);
    $data['asal_sekolah'] = $asalSekolah;

    app(PpdbPublicRegistrationService::class)->register(
        $data,
        persistentPpdbDocuments(),
        Str::random(64),
    );

    expect(DB::table('ppdb_profiles')->value('asal_sekolah'))->toBe($expected);
})->with([
    'padded school name' => ['  TK ANNUR  ', 'TK ANNUR'],
    'dash placeholder' => ['-', '-'],
]);

it('rejects missing blank whitespace-only and overlong asal sekolah before writing', function (?string $asalSekolah) {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);

    if ($asalSekolah === null) {
        unset($data['asal_sekolah']);
    } else {
        $data['asal_sekolah'] = $asalSekolah;
    }

    expect(fn () => app(PpdbPublicRegistrationService::class)->register(
        $data,
        persistentPpdbDocuments(),
        Str::random(64),
    ))->toThrow(ValidationException::class);

    expect(DB::table('prospective_students')->count())->toBe(0)
        ->and(DB::table('ppdb_profiles')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'missing' => null,
    'empty' => '',
    'whitespace only' => '   ',
    'overlong' => str_repeat('a', 256),
]);

it('persists guardian data and keeps sibling count independent from sibling rows', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);
    $data['jumlah_saudara'] = 7;
    $data['tinggi_badan'] = 140.25;
    $data['berat_badan'] = 35.50;
    $data['lingkar_kepala'] = 52.75;
    $data['berkebutuhan_khusus'] = 'Ya';
    $data['keterangan_kebutuhan_khusus'] = 'Pendampingan belajar';
    $data['guardian'] = [
        'name' => 'Wali Ahmad',
        'nik' => '3201010101010005',
        'birthplace' => 'Bogor',
        'birthdate' => '1980-05-06',
        'education_last' => 'D3',
        'email' => 'wali@example.com',
        'phone' => '081234567892',
        'occupation' => 'Pedagang',
        'income_range' => '1m_to_2_999m',
    ];

    $result = app(PpdbPublicRegistrationService::class)->register(
        $data,
        persistentPpdbDocuments(),
        Str::random(64),
    );

    $prospectiveStudentId = DB::table('prospective_students')
        ->where('registration_number', $result['registration_number'])
        ->value('id');
    $guardian = DB::table('ppdb_parents')
        ->where('prospective_student_id', $prospectiveStudentId)
        ->where('role', 'guardian')
        ->sole();
    $profile = DB::table('ppdb_profiles')->where('prospective_student_id', $prospectiveStudentId)->sole();

    expect($guardian->nama)->toBe('WALI AHMAD')
        ->and($guardian->nik)->toBe('3201010101010005')
        ->and($guardian->tempat_lahir)->toBe('Bogor')
        ->and(Str::before($guardian->tanggal_lahir, ' '))->toBe('1980-05-06')
        ->and($guardian->pendidikan_terakhir)->toBe('D3')
        ->and($guardian->email)->toBe('wali@example.com')
        ->and($guardian->no_telp)->toBe('081234567892')
        ->and($guardian->occupation)->toBe('Pedagang')
        ->and($guardian->income_range)->toBe('1m_to_2_999m')
        ->and($profile->jumlah_saudara)->toBe(7)
        ->and((float) $profile->tinggi_badan)->toBe(140.25)
        ->and((float) $profile->berat_badan)->toBe(35.5)
        ->and((float) $profile->lingkar_kepala)->toBe(52.75)
        ->and((bool) $profile->berkebutuhan_khusus)->toBeTrue()
        ->and($profile->keterangan_kebutuhan_khusus)->toBe('Pendampingan belajar')
        ->and(DB::table('ppdb_siblings')->where('prospective_student_id', $prospectiveStudentId)->count())->toBe(1)
        ->and(DB::table('ppdb_siblings')->where('prospective_student_id', $prospectiveStudentId)->value('sort_order'))->toBe(1);
});

it('accepts empty optional student fields, no guardian, and zero sibling rows', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $data = persistentPpdbData($year);

    foreach (['status_anak', 'agama', 'bahasa_sehari_hari', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos'] as $field) {
        $data[$field] = null;
    }
    $data['jumlah_saudara'] = 0;
    $data['siblings'] = [];
    $data['father']['email'] = null;
    $data['mother']['email'] = null;

    $result = app(PpdbPublicRegistrationService::class)->register(
        $data,
        persistentPpdbDocuments(),
        Str::random(64),
    );

    $prospectiveStudentId = DB::table('prospective_students')
        ->where('registration_number', $result['registration_number'])
        ->value('id');
    $profile = DB::table('ppdb_profiles')->where('prospective_student_id', $prospectiveStudentId)->sole();

    expect($profile->nomor_akta_kelahiran)->toBe('AKTA-001')
        ->and($profile->anak_ke)->toBe(1)
        ->and($profile->status_anak)->toBeNull()
        ->and($profile->agama)->toBeNull()
        ->and($profile->bahasa_sehari_hari)->toBeNull()
        ->and($profile->rt)->toBeNull()
        ->and($profile->rw)->toBeNull()
        ->and($profile->kelurahan)->toBeNull()
        ->and($profile->kecamatan)->toBeNull()
        ->and($profile->kabupaten_kota)->toBeNull()
        ->and($profile->provinsi)->toBeNull()
        ->and($profile->kode_pos)->toBeNull()
        ->and($profile->jumlah_saudara)->toBe(0)
        ->and(DB::table('ppdb_parents')->where('prospective_student_id', $prospectiveStudentId)->count())->toBe(2)
        ->and(DB::table('ppdb_parents')->where('prospective_student_id', $prospectiveStudentId)->where('role', 'father')->value('email'))->toBeNull()
        ->and(DB::table('ppdb_parents')->where('prospective_student_id', $prospectiveStudentId)->where('role', 'mother')->value('email'))->toBeNull()
        ->and(DB::table('ppdb_siblings')->where('prospective_student_id', $prospectiveStudentId)->count())->toBe(0);
});

it('returns the same outcome for a completed idempotency token without duplicate writes', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    persistentPpdbBillType();
    $token = Str::random(64);
    $service = app(PpdbPublicRegistrationService::class);

    $first = $service->register(persistentPpdbData($year), persistentPpdbDocuments(), $token);
    $replayedData = persistentPpdbData($year);
    $replayedData['applicant_category'] = PpdbApplicantCategory::NON_ANNUR_FAMILY->value;
    $second = $service->register($replayedData, [], $token);

    expect($second)->toBe($first)
        ->and(DB::table('prospective_students')->value('applicant_category'))->toBe(PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->and(DB::table('prospective_students')->count())->toBe(1)
        ->and(DB::table('ppdb_public_submissions')->count())->toBe(1)
        ->and(DB::table('ppdb_documents')->count())->toBe(6)
        ->and(DB::table('prospective_student_bills')->count())->toBe(1)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});

it('allows a completed livewire submission token to recover its original result after the action limit is reached', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    persistentPpdbBillType();
    $token = Str::random(64);
    $service = app(PpdbPublicRegistrationService::class);
    $first = $service->register(persistentPpdbData($year), persistentPpdbDocuments(), $token);

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('idempotencyToken', $token)
        ->set('academicYearId', (string) $year->id)
        ->set('schoolLevel', 'SMP')
        ->set('destinationGrade', '7')
        ->set('applicantCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->set('fullName', 'Ahmad Pendaftar')
        ->set('gender', 'L')
        ->set('birthplace', 'Jakarta')
        ->set('birthdate', '2015-01-01')
        ->set('asalSekolah', 'TK ANNUR')
        ->set('nikSiswa', '3201010101010001')
        ->set('nisn', '0012345678')
        ->set('nomorKk', '3201010101010002')
        ->set('nomorAktaKelahiran', 'AKTA-001')
        ->set('anakKe', '1')
        ->set('jumlahSaudara', '1')
        ->set('hobi', 'Membaca')
        ->set('citaCita', 'Dokter')
        ->set('address', 'Jl. Pendidikan No. 1')
        ->set('jarakTempuhKategori', 'more_than_1_km')
        ->set('jarakTempuhDetail', '2,5')
        ->set('modaTransportasi', 'antar_jemput')
        ->set('tinggiBadan', '140')
        ->set('beratBadan', '35')
        ->set('lingkarKepala', '52')
        ->set('golonganDarah', 'O')
        ->set('riwayatPenyakit', 'Tidak Ada')
        ->set('berkebutuhanKhusus', 'Tidak')
        ->set('father', persistentPpdbData($year)['father'])
        ->set('mother', persistentPpdbData($year)['mother'])
        ->set('guardian', persistentPpdbData($year)['guardian'])
        ->set('siblings', persistentPpdbData($year)['siblings'])
        ->set('consent', true);

    foreach (persistentPpdbDocuments() as $type => $document) {
        $component->set('documents.'.$type, $document);
    }

    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $component->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('ppdb.public.success', $first['success_reference']));
    }

    expect(DB::table('prospective_students')->count())->toBe(1)
        ->and(DB::table('ppdb_public_submissions')->count())->toBe(1)
        ->and(DB::table('ppdb_documents')->count())->toBe(6)
        ->and(DB::table('prospective_student_bills')->count())->toBe(1)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});

it('submits the livewire form and redirects to its opaque success URL', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    persistentPpdbBillType();
    $deletedSiblingKey = (string) Str::uuid();
    $remainingSiblingKey = (string) Str::uuid();
    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('academicYearId', (string) $year->id)
        ->set('schoolLevel', 'SMP')
        ->set('destinationGrade', '7')
        ->set('applicantCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->set('fullName', 'Ahmad Pendaftar')
        ->set('nickname', 'Ahmad')
        ->set('gender', 'L')
        ->set('birthplace', 'Jakarta')
        ->set('birthdate', '2015-01-01')
        ->set('asalSekolah', 'TK ANNUR')
        ->set('nikSiswa', '3201010101010001')
        ->set('nisn', '0012345678')
        ->set('nomorKk', '3201010101010002')
        ->set('nomorAktaKelahiran', 'AKTA-001')
        ->set('anakKe', '1')
        ->set('jumlahSaudara', '1')
        ->set('hobi', 'Membaca')
        ->set('citaCita', 'Dokter')
        ->set('address', 'Jl. Pendidikan No. 1')
        ->set('jarakTempuhKategori', 'more_than_1_km')
        ->set('jarakTempuhDetail', '2,5')
        ->set('modaTransportasi', 'antar_jemput')
        ->set('tinggiBadan', '140')
        ->set('beratBadan', '35')
        ->set('lingkarKepala', '52')
        ->set('golonganDarah', 'O')
        ->set('riwayatPenyakit', 'Tidak Ada')
        ->set('berkebutuhanKhusus', 'Tidak')
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.nik', '3201010101010003')
        ->set('father.birthplace', 'Bandung')
        ->set('father.birthdate', '1985-01-01')
        ->set('father.education_last', 'S1')
        ->set('father.email', 'ayah@example.com')
        ->set('father.phone', '081234567890')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', '5m_to_7_499m')
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'wafat')
        ->set('mother.nik', '3201010101010004')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1987-01-01')
        ->set('mother.education_last', 'SMA')
        ->set('mother.email', 'ibu@example.com')
        ->set('mother.phone', '081234567891')
        ->set('mother.occupation', 'Guru')
        ->set('mother.income_range', '3m_to_4_999m')
        ->set('siblings', [
            ['key' => $deletedSiblingKey, 'full_name' => 'Saudara Dihapus', 'birthdate' => '2014-01-01', 'gender' => 'P', 'education_occupation' => 'Kelas 6'],
            ['key' => $remainingSiblingKey, 'full_name' => 'Saudara Tersisa', 'birthdate' => '2016-01-01', 'gender' => 'L', 'education_occupation' => 'Kelas 5'],
        ])
        ->set('consent', true);

    foreach (persistentPpdbDocuments() as $type => $document) {
        $component->set('documents.'.$type, $document);
    }

    $token = $component->get('idempotencyToken');
    $successReference = hash_hmac('sha256', 'ppdb-success:'.$token, (string) config('app.key'));

    $component
        ->call('removeSibling', $deletedSiblingKey)
        ->assertSet('siblings.1.full_name', 'Saudara Tersisa')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('ppdb.public.success', $successReference));

    expect(DB::table('prospective_students')->count())->toBe(1)
        ->and(DB::table('ppdb_documents')->count())->toBe(6)
        ->and(DB::table('prospective_student_bills')->count())->toBe(1)
        ->and(DB::table('ppdb_siblings')->count())->toBe(1)
        ->and(DB::table('ppdb_siblings')->value('nama_lengkap'))->toBe('SAUDARA TERSISA')
        ->and(DB::table('ppdb_siblings')->value('sort_order'))->toBe(1);
});

it('rechecks the ppdb opening state and creates nothing after closure', function () {
    Storage::fake('local');
    [$year, $setting] = persistentPpdbFixture();
    $setting->update(['is_open' => false]);

    expect(fn () => app(PpdbPublicRegistrationService::class)->register(
        persistentPpdbData($year),
        persistentPpdbDocuments(),
        Str::random(64),
    ))->toThrow(ValidationException::class);

    expect(DB::table('prospective_students')->count())->toBe(0)
        ->and(DB::table('ppdb_public_submissions')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rolls back records and removes permanent files when completion fails', function () {
    Storage::fake('local');
    $unrelatedPath = 'unrelated/keep.txt';
    Storage::disk('local')->put($unrelatedPath, 'keep');
    [$year] = persistentPpdbFixture();
    persistentPpdbBillType();
    PpdbPublicSubmission::updating(fn () => throw new RuntimeException('Forced completion failure'));

    try {
        app(PpdbPublicRegistrationService::class)->register(
            persistentPpdbData($year),
            persistentPpdbDocuments(),
            Str::random(64),
        );
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Forced completion failure');
    } finally {
        PpdbPublicSubmission::flushEventListeners();
    }

    expect(DB::table('prospective_students')->count())->toBe(0)
        ->and(DB::table('ppdb_public_submissions')->count())->toBe(0)
        ->and(DB::table('prospective_student_bills')->count())->toBe(0)
        ->and(DB::table('ppdb_documents')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([$unrelatedPath])
        ->and(Storage::disk('local')->directories('ppdb/documents/'.now()->format('Y/m')))->toBe([]);
});

it('rolls back the public registration when bill generation throws', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $billGenerationService = Mockery::mock(ProspectiveStudentBillGenerationService::class);
    $billGenerationService->shouldReceive('generateFor')
        ->once()
        ->andThrow(new RuntimeException('Forced bill generation failure'));
    $service = new PpdbPublicRegistrationService(
        app(ProspectiveStudentRegistrationNumberGenerator::class),
        $billGenerationService,
    );

    expect(fn () => $service->register(
        persistentPpdbData($year),
        persistentPpdbDocuments(),
        Str::random(64),
    ))->toThrow(RuntimeException::class, 'Forced bill generation failure');

    expect(DB::table('prospective_students')->count())->toBe(0)
        ->and(DB::table('ppdb_public_submissions')->count())->toBe(0)
        ->and(DB::table('prospective_student_bills')->count())->toBe(0)
        ->and(DB::table('ppdb_profiles')->count())->toBe(0)
        ->and(DB::table('ppdb_documents')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('keeps the existing silent skip contract when no rate applies', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();

    app(PpdbPublicRegistrationService::class)->register(
        persistentPpdbData($year),
        persistentPpdbDocuments(),
        Str::random(64),
    );

    expect(DB::table('prospective_students')->count())->toBe(1)
        ->and(DB::table('prospective_student_bills')->count())->toBe(0)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});

it('creates every applicable prospective one-time bill type', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $firstType = persistentPpdbBillType(310000, 'Formulir Public Satu');
    $secondType = persistentPpdbBillType(125000, 'Formulir Public Dua');

    app(PpdbPublicRegistrationService::class)->register(
        persistentPpdbData($year),
        persistentPpdbDocuments(),
        Str::random(64),
    );

    expect(ProspectiveStudentBill::query()->count())->toBe(2)
        ->and(ProspectiveStudentBill::query()->pluck('payment_type_id')->all())
        ->toEqualCanonicalizing([$firstType->id, $secondType->id])
        ->and((float) ProspectiveStudentBill::query()->sum('amount'))->toBe(435000.0)
        ->and(DB::table('prospective_student_payments')->count())->toBe(0);
});

it('rejects an invalid document before writing records or files', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $documents = persistentPpdbDocuments();
    $documents['kartu_keluarga'] = UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml');

    expect(fn () => app(PpdbPublicRegistrationService::class)->register(
        persistentPpdbData($year),
        $documents,
        Str::random(64),
    ))->toThrow(ValidationException::class);

    expect(DB::table('prospective_students')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('serves only a valid unexpired success reference with private response headers', function () {
    Storage::fake('local');
    [$year] = persistentPpdbFixture();
    $result = app(PpdbPublicRegistrationService::class)->register(
        persistentPpdbData($year),
        persistentPpdbDocuments(),
        Str::random(64),
    );

    $response = $this->get(route('ppdb.public.success', $result['success_reference']));

    $response->assertOk()
        ->assertSee($result['registration_number'])
        ->assertHeader('Cache-Control')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');

    $this->get(route('ppdb.public.success', str_repeat('a', 64)))->assertNotFound();

    PpdbPublicSubmission::query()->update(['expires_at' => now()->subMinute()]);

    $this->get(route('ppdb.public.success', $result['success_reference']))->assertNotFound();
});
