<?php

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Livewire\PpdbPublicRegistration;
use App\Models\AcademicYear;
use App\Models\PpdbAcademicYearSetting;
use App\Models\PpdbDefaultClassMapping;
use App\Models\PpdbParent;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function publicPpdbYear(string $year, bool $isOpen = true, bool $isActive = false): AcademicYear
{
    $startYear = (int) substr($year, 0, 4);
    $academicYear = AcademicYear::query()->create([
        'year' => $year,
        'is_active' => $isActive,
        'start_date' => $startYear.'-07-01',
        'end_date' => ($startYear + 1).'-06-30',
    ]);

    if ($isOpen) {
        PpdbAcademicYearSetting::query()->create([
            'academic_year_id' => $academicYear->id,
            'is_open' => true,
        ]);
    }

    return $academicYear;
}

function publicPpdbMapping(int $grade): PpdbDefaultClassMapping
{
    $class = SchoolClass::factory()->create([
        'name' => 'PPDB '.$grade,
        'level' => $grade,
    ]);

    return PpdbDefaultClassMapping::query()->create([
        'class_level' => $grade,
        'school_class_id' => $class->id,
    ]);
}

function fillPublicStep1($component, AcademicYear $year, string $level = 'SMP', string $grade = '7'): mixed
{
    Storage::fake('local');
    $docs = $component->get('documents');
    if (! is_array($docs) || ! array_key_exists('bukti_transfer', $docs)) {
        $component->set('documents.bukti_transfer', UploadedFile::fake()->image('bukti.jpg'));
    }

    return $component
        ->set('academicYearId', (string) $year->id)
        ->set('schoolLevel', $level)
        ->set('destinationGrade', $grade)
        ->set('applicantCategory', PpdbApplicantCategory::ANNUR_FAMILY->value);
}
function fillPublicStudentStep($component, AcademicYear $year, string $level = 'SMP', string $grade = '7'): mixed
{
    $component
        ->set('academicYearId', (string) $year->id)
        ->set('schoolLevel', $level)
        ->set('destinationGrade', $grade)
        ->set('applicantCategory', PpdbApplicantCategory::ANNUR_FAMILY->value);

    return $component
        ->set('fullName', 'Ahmad Pendaftar')
        ->set('nickname', 'Ahmad')
        ->set('gender', 'L')
        ->set('birthplace', 'Jakarta')
        ->set('birthdate', '2015-01-01')
        ->set('asalSekolah', 'TK ANNUR')
        ->set('nikSiswa', '3201010101010001')
        ->set('nisn', $level === 'TK' ? '' : '0012345678')
        ->set('nomorKk', '3201010101010002')
        ->set('nomorAktaKelahiran', 'AKTA/001-2026')
        ->set('anakKe', '1')
        ->set('jumlahSaudara', '0')
        ->set('hobi', 'Membaca')
        ->set('citaCita', 'Dokter')
        ->set('address', 'Jl. Pendidikan No. 1')
        ->set('jarakTempuhKategori', 'less_than_1_km')
        ->set('modaTransportasi', 'jalan_kaki')
        ->set('tinggiBadan', '140')
        ->set('beratBadan', '35')
        ->set('lingkarKepala', '52')
        ->set('golonganDarah', 'O')
        ->set('riwayatPenyakit', 'Tidak Ada')
        ->set('berkebutuhanKhusus', 'Tidak');
}

it('allows guests to access the public ppdb route with public assets and no admin navigation', function () {
    $response = $this->get(route('ppdb.public.register'));

    $response->assertOk()
        ->assertHeader('Cache-Control')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertSee('<html lang="id">', false)
        ->assertSee('YPI Nurrahim An-nur')
        ->assertSee('PPDB Online')
        ->assertSee('rel="stylesheet"', false)
        ->assertDontSee('id="main-sidebar"', false)
        ->assertDontSee('Dashboard')
        ->assertDontSee('Keluar');

    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
});

it('throttles the public registration page by ip without blocking another ip', function () {
    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get(route('ppdb.public.register'))
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->get(route('ppdb.public.register'))
        ->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
        ->get(route('ppdb.public.register'))
        ->assertOk();
});

it('throttles repeated success reference lookups by ip', function () {
    $reference = str_repeat('a', 64);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->get(route('ppdb.public.success', $reference))
            ->assertNotFound();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
        ->get(route('ppdb.public.success', $reference))
        ->assertTooManyRequests();
});

it('throttles final livewire submissions by ip with friendly feedback', function () {
    publicPpdbYear('2062/2063');
    RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturnTrue();

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 6)
        ->call('submit')
        ->assertHasErrors('submission')
        ->assertSee('Terlalu banyak percobaan pengajuan. Silakan coba kembali dalam beberapa menit.');
});

it('shows only explicitly open academic years regardless of active status', function () {
    $openYear = publicPpdbYear('2048/2049', true, false);
    $closedActiveYear = publicPpdbYear('2049/2050', false, true);

    Livewire::test(PpdbPublicRegistration::class)
        ->assertSee($openYear->year)
        ->assertDontSee($closedActiveYear->year);
});

it('renders the required applicant category radios and approved education without a default', function () {
    publicPpdbYear('2049/2050');

    Livewire::test(PpdbPublicRegistration::class)
        ->assertSet('applicantCategory', '')
        ->assertSee('Kategori Pendaftar')
        ->assertSee('Keluarga Annur')
        ->assertSee('Non-Keluarga Annur')
        ->assertSee('Siapa yang termasuk Keluarga Annur?')
        ->assertSee('Calon siswa termasuk Keluarga Annur apabila memenuhi minimal salah satu kriteria berikut:')
        ->assertSee('Memiliki kakak atau adik yang sedang bersekolah di Annur atau merupakan alumni Annur.')
        ->assertSee('Calon siswa merupakan alumni Annur dari jenjang sebelumnya. Contohnya, lulusan SD Annur yang melanjutkan pendidikan ke SMP Annur.')
        ->assertSee('Berdomisili di RT 004/RW 009 atau RT 006/RW 009.')
        ->assertSee('Siapa yang termasuk Non-Keluarga Annur?')
        ->assertSee('Calon siswa yang tidak memenuhi satu pun kriteria Keluarga Annur di atas.')
        ->assertSeeHtml('type="radio"')
        ->assertSeeHtml('wire:model="applicantCategory"')
        ->assertSeeHtml('grid grid-cols-1 gap-3 sm:grid-cols-2');
});

it('requires an approved applicant category in Step 1 and final validation', function () {
    $year = publicPpdbYear('2049/2050');
    publicPpdbMapping(7);

    fillPublicStep1(Livewire::test(PpdbPublicRegistration::class), $year)
        ->set('applicantCategory', '')
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertHasErrors(['applicantCategory' => 'required']);

    fillPublicStep1(Livewire::test(PpdbPublicRegistration::class), $year)
        ->set('applicantCategory', 'discount-family')
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertHasErrors('applicantCategory');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 6)
        ->call('submit')
        ->assertHasErrors(['applicantCategory' => 'required']);
});

it('preserves and reviews the selected applicant category across navigation', function () {
    $year = publicPpdbYear('2049/2050');
    publicPpdbMapping(7);

    fillPublicStep1(Livewire::test(PpdbPublicRegistration::class), $year)
        ->set('applicantCategory', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->call('previousStep')
        ->assertSet('currentStep', 1)
        ->assertSet('applicantCategory', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)
        ->set('currentStep', 6)
        ->assertSee('Kategori pendaftar')
        ->assertSee(PpdbApplicantCategory::NON_ANNUR_FAMILY->label());
});

it('renders the required asal sekolah field with placeholder and helper', function () {
    publicPpdbYear('2049/2050');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 2)
        ->assertSee('Asal Sekolah')
        ->assertSee('Contoh: TK ANNUR atau -')
        ->assertSee('Jika belum pernah sekolah, isi tanda (-).')
        ->assertSeeHtml('wire:model="asalSekolah"')
        ->assertSeeHtml('maxlength="255"')
        ->assertDontSee('Pernah Sekolah');
});

it('requires a nonblank asal sekolah within 255 characters and accepts a dash', function () {
    $year = publicPpdbYear('2049/2050');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('asalSekolah', '')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertHasErrors(['asalSekolah' => 'required']);

    fillPublicStudentStep($component, $year)
        ->set('asalSekolah', '   ')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertHasErrors(['asalSekolah' => 'required']);

    fillPublicStudentStep($component, $year)
        ->set('asalSekolah', str_repeat('a', 256))
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertHasErrors(['asalSekolah' => 'max']);

    fillPublicStudentStep($component, $year)
        ->set('asalSekolah', '-')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 3);

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 6)
        ->set('asalSekolah', '')
        ->call('submit')
        ->assertHasErrors(['asalSekolah' => 'required']);
});

it('blocks progression and shows a closed state when no ppdb year is open', function () {
    publicPpdbYear('2050/2051', false, true);

    Livewire::test(PpdbPublicRegistration::class)
        ->assertSee('Pendaftaran PPDB sedang ditutup')
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertHasErrors('registration');
});

it('groups destination grades by the selected school level', function () {
    publicPpdbYear('2051/2052');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('schoolLevel', 'TK')
        ->assertSee(['KB', 'TK A', 'TK B'])
        ->set('schoolLevel', 'SD')
        ->assertSee(['Kelas 1', 'Kelas 6'])
        ->set('schoolLevel', 'SMP')
        ->assertSee(['Kelas 7', 'Kelas 9'])
        ->set('schoolLevel', 'SMA')
        ->assertSee(['Kelas 10', 'Kelas 12']);

    $component->assertSet('destinationGrade', '');
});

it('blocks a grade without a global default class mapping', function () {
    $year = publicPpdbYear('2052/2053');
    Storage::fake('local');

    $component = Livewire::test(PpdbPublicRegistration::class);
    $component->set('documents.bukti_transfer', UploadedFile::fake()->image('bukti.jpg'));
    $component
        ->set('academicYearId', (string) $year->id)
        ->set('schoolLevel', 'SMP')
        ->set('destinationGrade', '7')
        ->set('applicantCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertHasErrors('destinationGrade');
});

it('rejects a grade that does not belong to the selected school level', function () {
    $year = publicPpdbYear('2053/2054');
    publicPpdbMapping(7);
    Storage::fake('local');

    $component = Livewire::test(PpdbPublicRegistration::class);
    $component->set('documents.bukti_transfer', UploadedFile::fake()->image('bukti.jpg'));
    $component
        ->set('academicYearId', (string) $year->id)
        ->set('schoolLevel', 'SD')
        ->set('destinationGrade', '7')
        ->set('applicantCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertHasErrors('destinationGrade');
});

it('requires the student NIK to contain exactly 16 digits', function () {
    $year = publicPpdbYear('2054/2055');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('nikSiswa', '320101010101001')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertHasErrors(['nikSiswa' => 'digits']);
});

it('requires the family card number to contain exactly 16 digits', function () {
    $year = publicPpdbYear('2054/2055');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('nomorKk', '320101010101001')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertHasErrors(['nomorKk' => 'digits']);
});

it('requires the approved student biodata fields and rejects whitespace-only text', function () {
    $year = publicPpdbYear('2054/2055');
    $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('nomorAktaKelahiran', '   ')
        ->set('anakKe', '')
        ->set('jumlahSaudara', '')
        ->set('hobi', '   ')
        ->set('citaCita', '   ')
        ->call('nextStep')
        ->assertHasErrors([
            'nomorAktaKelahiran' => 'required',
            'anakKe' => 'required',
            'jumlahSaudara' => 'required',
            'hobi' => 'required',
            'citaCita' => 'required',
        ]);

    fillPublicStudentStep($component, $year)
        ->set('jumlahSaudara', '0')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 3);
});

it('requires an exact ten-digit NISN for non-TK levels', function (string $level) {
    $year = publicPpdbYear('2054/2055');
    $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 2);

    fillPublicStudentStep($component, $year, $level)
        ->set('nisn', '')
        ->call('nextStep')
        ->assertHasErrors(['nisn' => 'required']);

    fillPublicStudentStep($component, $year, $level)
        ->set('nisn', '123456789')
        ->call('nextStep')
        ->assertHasErrors(['nisn' => 'digits']);
})->with(['SD', 'SMP', 'SMA']);

it('hides and clears NISN when the school level changes to TK', function () {
    $year = publicPpdbYear('2054/2055');

    $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 2);
    fillPublicStudentStep($component, $year)
        ->set('nisn', '0012345678')
        ->set('schoolLevel', 'TK')
        ->assertSet('nisn', '')
        ->assertDontSee('NISN')
        ->call('nextStep')
        ->assertHasNoErrors();
});

it('normalizes decimal commas and requires a valid distance above one kilometer', function () {
    $year = publicPpdbYear('2054/2055');
    $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('jarakTempuhKategori', 'more_than_1_km')
        ->set('jarakTempuhDetail', '2,5')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('jarakTempuhDetail', '2.50')
        ->assertSet('currentStep', 3);
});

it('rejects invalid distance details', function (string $distance) {
    $year = publicPpdbYear('2054/2055');
    $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('jarakTempuhKategori', 'more_than_1_km')
        ->set('jarakTempuhDetail', $distance)
        ->call('nextStep')
        ->assertHasErrors('jarakTempuhDetail');
})->with(['missing' => '', 'one kilometer' => '1', 'too many decimals' => '2.555', 'too large' => '10000', 'invalid text' => 'dua']);

it('clears stale distance and transportation details when their controlling values change', function () {
    publicPpdbYear('2054/2055');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 2)
        ->set('jarakTempuhKategori', 'more_than_1_km')
        ->set('jarakTempuhDetail', '2.50')
        ->set('jarakTempuhKategori', 'less_than_1_km')
        ->assertSet('jarakTempuhDetail', '')
        ->set('modaTransportasi', 'lainnya')
        ->set('modaTransportasiLainnya', 'Perahu')
        ->set('modaTransportasi', 'sepeda')
        ->assertSet('modaTransportasiLainnya', '');
});

it('accepts every approved transportation option', function (string $transportation) {
    $year = publicPpdbYear('2054/2055');
    $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('modaTransportasi', $transportation)
        ->set('modaTransportasiLainnya', $transportation === 'lainnya' ? 'Perahu' : '')
        ->call('nextStep')
        ->assertHasNoErrors();
})->with(['jalan_kaki', 'sepeda', 'motor', 'mobil_pribadi', 'angkutan_umum', 'antar_jemput', 'lainnya']);

it('requires nonblank transportation detail only for lainnya', function () {
    $year = publicPpdbYear('2054/2055');
    $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('modaTransportasi', 'lainnya')
        ->set('modaTransportasiLainnya', '   ')
        ->call('nextStep')
        ->assertHasErrors(['modaTransportasiLainnya' => 'required_if']);
});

it('uses independent native radio names and requires both parent living statuses', function () {
    publicPpdbYear('2054/2055');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 3)
        ->assertSeeHtml('name="father[living_status]"')
        ->assertSeeHtml('name="mother[living_status]"')
        ->call('nextStep')
        ->assertHasErrors([
            'father.living_status' => 'required',
            'mother.living_status' => 'required',
        ])
        ->set('father.living_status', 'wafat')
        ->call('nextStep')
        ->assertHasErrors(['mother.living_status' => 'required'])
        ->assertHasErrors(['father.name' => 'required']);
});

it('requires health responses and special-needs details when applicable', function () {
    $year = publicPpdbYear('2054/2055');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 2);

    fillPublicStudentStep($component, $year)
        ->set('tinggiBadan', '')
        ->set('beratBadan', '')
        ->set('lingkarKepala', '')
        ->set('golonganDarah', '')
        ->set('riwayatPenyakit', '')
        ->set('berkebutuhanKhusus', '')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertHasErrors([
            'tinggiBadan' => 'required',
            'beratBadan' => 'required',
            'lingkarKepala' => 'required',
            'golonganDarah' => 'required',
            'riwayatPenyakit' => 'required',
            'berkebutuhanKhusus' => 'required',
        ]);

    fillPublicStudentStep($component, $year)
        ->set('berkebutuhanKhusus', 'Ya')
        ->set('keteranganKebutuhanKhusus', '')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertHasErrors(['keteranganKebutuhanKhusus' => 'required_if']);
});

it('displays every approved income range and casts stored codes through the enum', function () {
    publicPpdbYear('2054/2055');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 3)
        ->assertSee(array_values(ParentIncomeRange::options()));

    expect(ParentIncomeRange::options())->toHaveCount(8)
        ->and(ParentIncomeRange::tryFrom('unknown'))->toBeNull()
        ->and(Schema::hasColumns('ppdb_parents', ['occupation', 'income_range']))->toBeTrue();

    $prospect = ProspectiveStudent::factory()->create();
    $parent = PpdbParent::query()->create([
        'prospective_student_id' => $prospect->id,
        'role' => 'father',
        'occupation' => 'Wiraswasta',
        'income_range' => ParentIncomeRange::FiveToSevenMillion,
    ]);

    expect($parent->fresh()->income_range)->toBe(ParentIncomeRange::FiveToSevenMillion)
        ->and($parent->occupation)->toBe('Wiraswasta');
});

it('rejects invalid income codes while allowing income to remain unknown', function () {
    publicPpdbYear('2055/2056');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 3)
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.nik', '3201010101010003')
        ->set('father.birthplace', 'Jakarta')
        ->set('father.birthdate', '1980-01-01')
        ->set('father.education_last', 'S1')
        ->set('father.email', 'ayah@example.com')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.nik', '3201010101010004')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1982-02-02')
        ->set('mother.education_last', 'SMA')
        ->set('mother.email', 'ibu@example.com')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('father.income_range', 'invalid-range')
        ->call('nextStep')
        ->assertSet('currentStep', 3)
        ->assertHasErrors('father.income_range');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 3)
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.nik', '3201010101010003')
        ->set('father.birthplace', 'Jakarta')
        ->set('father.birthdate', '1980-01-01')
        ->set('father.education_last', 'S1')
        ->set('father.email', 'ayah@example.com')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.nik', '3201010101010004')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1982-02-02')
        ->set('mother.education_last', 'SMA')
        ->set('mother.email', 'ibu@example.com')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('mother.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->call('nextStep')
        ->assertSet('currentStep', 4)
        ->assertHasNoErrors();
});

it('keeps guardian optional but requires a name when guardian details are supplied', function () {
    publicPpdbYear('2056/2057');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 3)
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.birthplace', 'Jakarta')
        ->set('father.birthdate', '1980-01-01')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1982-02-02')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('mother.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('guardian.phone', '08123456787')
        ->call('nextStep')
        ->assertSet('currentStep', 3)
        ->assertHasErrors('guardian.name');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 3)
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.nik', '3201010101010003')
        ->set('father.birthplace', 'Jakarta')
        ->set('father.birthdate', '1980-01-01')
        ->set('father.education_last', 'S1')
        ->set('father.email', 'ayah@example.com')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.nik', '3201010101010004')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1982-02-02')
        ->set('mother.education_last', 'SMA')
        ->set('mother.email', 'ibu@example.com')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('mother.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('guardian.name', 'Wali Ahmad')
        ->set('guardian.nik', '3201010101010005')
        ->set('guardian.birthplace', 'Jakarta')
        ->set('guardian.birthdate', '1985-03-03')
        ->set('guardian.education_last', 'SMA')
        ->set('guardian.email', 'wali@example.com')
        ->set('guardian.phone', '08123456787')
        ->set('guardian.occupation', 'Wiraswasta')
        ->set('guardian.income_range', ParentIncomeRange::NoIncome->value)
        ->call('nextStep')
        ->assertSet('currentStep', 4)
        ->assertHasNoErrors();
});

it('requires parent birth data while keeping both parent emails optional and validated when present', function () {
    publicPpdbYear('2060/2061');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 3)
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.nik', '3201010101010003')
        ->set('father.education_last', 'S1')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.nik', '3201010101010004')
        ->set('mother.education_last', 'SMA')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('mother.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->call('nextStep')
        ->assertHasErrors([
            'father.birthplace',
            'father.birthdate',
            'mother.birthplace',
            'mother.birthdate',
        ]);

    $component
        ->set('father.birthplace', 'Jakarta')
        ->set('father.birthdate', '1980-01-01')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1982-02-02')
        ->set('father.email', 'not-an-email')
        ->call('nextStep')
        ->assertHasErrors(['father.email'])
        ->set('father.email', 'ayah@example.com')
        ->set('mother.email', 'not-an-email')
        ->call('nextStep')
        ->assertHasErrors(['mother.email'])
        ->set('mother.email', '')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 4);
});

it('adds and removes siblings while preserving their order', function () {
    publicPpdbYear('2057/2058');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 4)
        ->call('addSibling')
        ->call('addSibling');

    $siblings = $component->get('siblings');
    $firstKey = $siblings[0]['key'];
    $secondKey = $siblings[1]['key'];

    $component
        ->set('siblings.0.full_name', 'Saudara Pertama')
        ->set('siblings.1.full_name', 'Saudara Kedua');

    $component->call('removeSibling', $firstKey)
        ->assertCount('siblings', 1)
        ->assertSet('siblings.1.key', $secondKey)
        ->assertSet('siblings.1.full_name', 'Saudara Kedua');
});

it('keeps the surviving sibling model path stable when the first row is removed', function () {
    publicPpdbYear('2057/2058');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 4)
        ->call('addSibling')
        ->call('addSibling');

    $siblings = $component->get('siblings');
    $rows = array_values($siblings);
    $firstKey = $rows[0]['key'];
    $secondKey = $rows[1]['key'];
    $secondStorageKey = collect($siblings)->search(fn (array $sibling): bool => $sibling['key'] === $secondKey);

    $component
        ->set('siblings.'.$secondStorageKey.'.full_name', 'Irfan')
        ->set('siblings.'.$secondStorageKey.'.birthdate', '2026-10-09')
        ->set('siblings.'.$secondStorageKey.'.gender', 'L')
        ->set('siblings.'.$secondStorageKey.'.education_occupation', 'test')
        ->call('removeSibling', $firstKey)
        ->assertSet('siblings.'.$secondStorageKey.'.full_name', 'Irfan')
        ->assertSet('siblings.'.$secondStorageKey.'.birthdate', '2026-10-09')
        ->assertSet('siblings.'.$secondStorageKey.'.gender', 'L')
        ->assertSet('siblings.'.$secondStorageKey.'.education_occupation', 'test')
        ->assertSeeHtml('wire:model="siblings.'.$secondStorageKey.'.full_name"');
});

it('preserves sibling data when removing the second or middle row', function () {
    publicPpdbYear('2057/2058');

    $makeComponent = function (array $names) {
        $component = Livewire::test(PpdbPublicRegistration::class)->set('currentStep', 4);

        foreach ($names as $index => $name) {
            $component
                ->call('addSibling')
                ->set('siblings.'.$index.'.full_name', $name)
                ->set('siblings.'.$index.'.birthdate', '2026-10-'.str_pad((string) ($index + 7), 2, '0', STR_PAD_LEFT))
                ->set('siblings.'.$index.'.gender', $index % 2 === 0 ? 'L' : 'P')
                ->set('siblings.'.$index.'.education_occupation', 'Pendidikan '.$name);
        }

        return $component;
    };

    $removeSecond = $makeComponent(['Ridwan', 'Irfan']);
    $secondKey = $removeSecond->get('siblings')[1]['key'];

    $removeSecond
        ->call('removeSibling', $secondKey)
        ->assertCount('siblings', 1)
        ->assertSet('siblings.0.full_name', 'Ridwan')
        ->assertSet('siblings.0.birthdate', '2026-10-07')
        ->assertSet('siblings.0.gender', 'L')
        ->assertSet('siblings.0.education_occupation', 'Pendidikan Ridwan');

    $removeMiddle = $makeComponent(['Ridwan', 'Irfan', 'Fatimah']);
    $middleKey = $removeMiddle->get('siblings')[1]['key'];

    $removeMiddle
        ->call('removeSibling', $middleKey)
        ->assertCount('siblings', 2)
        ->assertSet('siblings.0.full_name', 'Ridwan')
        ->assertSet('siblings.2.full_name', 'Fatimah')
        ->assertSet('siblings.2.birthdate', '2026-10-09')
        ->assertSet('siblings.2.gender', 'L')
        ->assertSet('siblings.2.education_occupation', 'Pendidikan Fatimah');
});

it('allows removing the final sibling and adding a new blank row without resetting survivors', function () {
    publicPpdbYear('2057/2058');

    $emptyComponent = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 4)
        ->call('addSibling');
    $onlyKey = $emptyComponent->get('siblings')[0]['key'];

    $emptyComponent
        ->call('removeSibling', $onlyKey)
        ->assertCount('siblings', 0)
        ->call('nextStep')
        ->assertSet('currentStep', 5)
        ->assertHasNoErrors();

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 4)
        ->call('addSibling')
        ->call('addSibling')
        ->set('siblings.1.full_name', 'Irfan')
        ->set('siblings.1.birthdate', '2026-10-09')
        ->set('siblings.1.gender', 'L')
        ->set('siblings.1.education_occupation', 'test');
    $firstKey = $component->get('siblings')[0]['key'];
    $survivingKey = $component->get('siblings')[1]['key'];

    $component
        ->call('removeSibling', $firstKey)
        ->call('addSibling')
        ->assertCount('siblings', 2)
        ->assertSet('siblings.1.key', $survivingKey)
        ->assertSet('siblings.1.full_name', 'Irfan')
        ->assertSet('siblings.1.birthdate', '2026-10-09')
        ->assertSet('siblings.1.gender', 'L')
        ->assertSet('siblings.1.education_occupation', 'test')
        ->assertSet('siblings.2.full_name', '')
        ->assertSet('siblings.2.birthdate', '')
        ->assertSet('siblings.2.gender', '')
        ->assertSet('siblings.2.education_occupation', '');

    expect($component->get('siblings')[2]['key'])->not->toBe($survivingKey);
});

it('preserves sparse sibling paths through validation and backward-forward navigation', function () {
    publicPpdbYear('2057/2058');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 4)
        ->call('addSibling')
        ->call('addSibling')
        ->set('siblings.1.full_name', 'Irfan')
        ->set('siblings.1.birthdate', '2026-10-09')
        ->set('siblings.1.gender', 'L');
    $firstKey = $component->get('siblings')[0]['key'];

    $component
        ->call('removeSibling', $firstKey)
        ->call('nextStep')
        ->assertSet('currentStep', 4)
        ->assertHasErrors(['siblings.1.education_occupation' => 'required'])
        ->set('siblings.1.education_occupation', 'test')
        ->call('previousStep')
        ->assertSet('currentStep', 3)
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.nik', '3201010101010003')
        ->set('father.birthplace', 'Jakarta')
        ->set('father.birthdate', '1980-01-01')
        ->set('father.education_last', 'S1')
        ->set('father.email', 'ayah@example.com')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.nik', '3201010101010004')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1982-02-02')
        ->set('mother.education_last', 'SMA')
        ->set('mother.email', 'ibu@example.com')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('mother.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->call('nextStep')
        ->assertSet('currentStep', 4)
        ->assertSet('siblings.1.full_name', 'Irfan')
        ->assertSet('siblings.1.birthdate', '2026-10-09')
        ->assertSet('siblings.1.gender', 'L')
        ->assertSet('siblings.1.education_occupation', 'test');
});

it('navigates all six steps and provides the final submission action', function () {
    $year = publicPpdbYear('2058/2059');
    publicPpdbMapping(7);
    Storage::fake('local');

    $documents = [];
    foreach (['bukti_transfer', 'kartu_keluarga', 'akta_kelahiran', 'ktp_ayah', 'ktp_ibu', 'pas_foto'] as $type) {
        $documents[$type] = UploadedFile::fake()->image($type.'.jpg');
    }

    $component = Livewire::test(PpdbPublicRegistration::class);
    foreach ($documents as $type => $document) {
        $component->set('documents.'.$type, $document);
    }

    fillPublicStudentStep($component, $year)
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertDispatched('ppdb-step-changed', step: 2);

    $component
        ->call('nextStep')
        ->assertSet('currentStep', 3)
        ->assertDispatched('ppdb-step-changed', step: 3)
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.nik', '3201010101010003')
        ->set('father.birthplace', 'Jakarta')
        ->set('father.birthdate', '1980-01-01')
        ->set('father.education_last', 'S1')
        ->set('father.email', 'ayah@example.com')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.nik', '3201010101010004')
        ->set('mother.birthplace', 'Bandung')
        ->set('mother.birthdate', '1982-02-02')
        ->set('mother.education_last', 'SMA')
        ->set('mother.email', 'ibu@example.com')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('mother.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->call('nextStep')
        ->assertSet('currentStep', 4)
        ->assertDispatched('ppdb-step-changed', step: 4)
        ->call('addSibling')
        ->set('siblings.0.full_name', 'Saudara Ahmad')
        ->set('siblings.0.birthdate', '2016-01-01')
        ->set('siblings.0.gender', 'L')
        ->set('siblings.0.education_occupation', 'Kelas 5')
        ->call('nextStep')
        ->assertSet('currentStep', 5)
        ->assertDispatched('ppdb-step-changed', step: 5)
        ->call('nextStep')
        ->assertSet('currentStep', 6)
        ->assertDispatched('ppdb-step-changed', step: 6)
        ->assertSee('Kirim Pendaftaran')
        ->call('previousStep')
        ->assertSet('currentStep', 5)
        ->assertDispatched('ppdb-step-changed', step: 5);

    expect(method_exists(PpdbPublicRegistration::class, 'submit'))->toBeTrue();
});

it('does not dispatch step navigation for invalid next or sibling row actions', function () {
    publicPpdbYear('2059/2060');

    Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 2)
        ->set('fullName', 'Data Tetap Tersimpan')
        ->call('nextStep')
        ->assertSet('currentStep', 2)
        ->assertSet('fullName', 'Data Tetap Tersimpan')
        ->assertHasErrors('gender')
        ->assertDispatched('ppdb-validation-failed', step: 2)
        ->assertNotDispatched('ppdb-step-changed');

    $component = Livewire::test(PpdbPublicRegistration::class)
        ->set('currentStep', 4)
        ->call('addSibling')
        ->assertNotDispatched('ppdb-step-changed');
    $siblingKey = $component->get('siblings')[0]['key'];

    $component
        ->call('removeSibling', $siblingKey)
        ->assertNotDispatched('ppdb-step-changed');
});

it('defines accessible post-render handlers for step navigation and validation errors', function () {
    $layout = file_get_contents(resource_path('views/layouts/ppdb-public.blade.php'));

    expect($layout)
        ->toContain("window.addEventListener('ppdb-step-changed'")
        ->toContain("window.addEventListener('ppdb-validation-failed'")
        ->toContain("document.querySelector('[data-step-section]')")
        ->toContain("querySelector('[aria-invalid=\"true\"]')")
        ->toContain('requestAnimationFrame')
        ->toContain('prefers-reduced-motion: reduce')
        ->not->toContain('setTimeout');
});

it('never creates applicants documents bills payments or registration numbers', function () {
    $year = publicPpdbYear('2059/2060');
    publicPpdbMapping(7);
    Storage::fake('local');

    $documents = [];
    foreach (['bukti_transfer', 'kartu_keluarga', 'akta_kelahiran', 'ktp_ayah', 'ktp_ibu', 'pas_foto'] as $type) {
        $documents[$type] = UploadedFile::fake()->image($type.'.jpg');
    }
    $before = [
        'prospective_students' => DB::table('prospective_students')->count(),
        'ppdb_profiles' => DB::table('ppdb_profiles')->count(),
        'ppdb_parents' => DB::table('ppdb_parents')->count(),
        'ppdb_siblings' => DB::table('ppdb_siblings')->count(),
        'ppdb_documents' => DB::table('ppdb_documents')->count(),
        'prospective_student_bills' => DB::table('prospective_student_bills')->count(),
        'prospective_student_payments' => DB::table('prospective_student_payments')->count(),
        'payments' => DB::table('payments')->count(),
        'student_bills' => DB::table('student_bills')->count(),
        'prospective_student_sequences' => DB::table('prospective_student_sequences')->count(),
    ];

    $component = Livewire::test(PpdbPublicRegistration::class);
    foreach ($documents as $type => $document) {
        $component->set('documents.'.$type, $document);
    }

    fillPublicStudentStep($component, $year)
        ->call('nextStep')
        ->call('nextStep')
        ->set('father.name', 'Ayah Ahmad')
        ->set('father.living_status', 'hidup')
        ->set('father.phone', '08123456789')
        ->set('father.occupation', 'Wiraswasta')
        ->set('father.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->set('mother.name', 'Ibu Ahmad')
        ->set('mother.living_status', 'hidup')
        ->set('mother.phone', '08123456788')
        ->set('mother.occupation', 'Ibu Rumah Tangga')
        ->set('mother.income_range', ParentIncomeRange::BelowOneMillion->value)
        ->call('nextStep')
        ->call('nextStep')
        ->call('nextStep');

    foreach ($before as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
});

it('keeps existing ppdb admin routes available', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'is_active' => true,
    ]);
    $this->actingAs($admin);

    $this->get(route('ppdb.academic-year-settings.index'))->assertOk();
    $this->get(route('ppdb.default-class-mappings.index'))->assertOk();
});
