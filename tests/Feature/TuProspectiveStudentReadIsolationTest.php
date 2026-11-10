<?php

use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectiveStudentManagement;
use App\Livewire\TuProspectiveStudentBiodata;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function applicantIsolationTu(SchoolLevel $schoolLevel, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => $schoolLevel,
        'is_active' => true,
    ], $attributes));
}

function applicantIsolationClass(SchoolLevel $schoolLevel, string $suffix = 'A'): SchoolClass
{
    $level = match ($schoolLevel) {
        SchoolLevel::TK => -2,
        SchoolLevel::SD => 1,
        SchoolLevel::SMP => 7,
        SchoolLevel::SMA => 10,
    };

    return SchoolClass::factory()->create([
        'name' => $schoolLevel->value.' '.$suffix,
        'level' => $level,
    ]);
}

function applicantIsolationApplicant(AcademicYear $year, ?SchoolClass $schoolClass, string $name): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create([
        'academic_year_id' => $year->id,
        'school_class_id' => $schoolClass?->id,
        'nama_lengkap' => $name,
    ]);
}

function applicantIsolationLivewireSnapshot(string $html): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);

    return html_entity_decode($matches[1] ?? '', ENT_QUOTES);
}

function applicantIsolationFinanceQueries(): Collection
{
    return collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'prospective_student_bills')
        || str_contains($query['query'], 'prospective_student_payments')
        || str_contains($query['query'], 'prospective_student_payment_details')
        || str_contains($query['query'], 'ppdb_documents')
        || str_contains($query['query'], 'bills')
        || str_contains($query['query'], 'payments'));
}

beforeEach(function () {
    AcademicYear::query()->update(['is_active' => false]);

    $this->activeYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->futureYear = AcademicYear::query()->updateOrCreate(['year' => '2027/2028'], [
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
});

it('shows TU only applicants whose target class belongs to the assigned unit', function (SchoolLevel $schoolLevel) {
    $ownClass = applicantIsolationClass($schoolLevel, 'Own');
    $otherLevel = $schoolLevel === SchoolLevel::SMA ? SchoolLevel::SMP : SchoolLevel::SMA;
    $otherClass = applicantIsolationClass($otherLevel, 'Other');
    $ownApplicant = applicantIsolationApplicant($this->activeYear, $ownClass, 'Visible Applicant '.$schoolLevel->value);
    $otherApplicant = applicantIsolationApplicant($this->activeYear, $otherClass, 'Hidden Applicant '.$otherLevel->value);

    Livewire::actingAs(applicantIsolationTu($schoolLevel))
        ->test(ProspectiveStudentManagement::class)
        ->assertSee($ownApplicant->nama_lengkap)
        ->assertDontSee($otherApplicant->nama_lengkap);
})->with([
    'TK' => SchoolLevel::TK,
    'SD' => SchoolLevel::SD,
    'SMP' => SchoolLevel::SMP,
    'SMA' => SchoolLevel::SMA,
]);

it('never derives applicant unit from academic enrollment or an invalid class level', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $invalidClass = SchoolClass::factory()->create(['name' => 'Invalid 13', 'level' => 13]);
    $nullClassApplicant = applicantIsolationApplicant($this->activeYear, null, 'Applicant Without Class');
    $invalidClassApplicant = applicantIsolationApplicant($this->activeYear, $invalidClass, 'Applicant Invalid Level');
    $ownApplicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Applicant Valid SD');
    $tu = applicantIsolationTu(SchoolLevel::SD);

    Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->assertSee($ownApplicant->nama_lengkap)
        ->assertDontSee($nullClassApplicant->nama_lengkap)
        ->assertDontSee($invalidClassApplicant->nama_lengkap);

    $this->actingAs($tu)->get(route('calon-siswa.biodata', $nullClassApplicant))->assertForbidden();
    $this->actingAs($tu)->get(route('calon-siswa.biodata', $invalidClassApplicant))->assertForbidden();
    $this->actingAs($tu)->get(route('calon-siswa.biodata', $ownApplicant))->assertOk();
});

it('prevents search and filter tampering from escaping the assigned unit', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD, 'Allowed');
    $smpClass = applicantIsolationClass(SchoolLevel::SMP, 'Forbidden');
    $ownApplicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Own Applicant Search');
    $crossApplicant = applicantIsolationApplicant($this->activeYear, $smpClass, 'Cross Applicant Secret');

    $component = Livewire::actingAs(applicantIsolationTu(SchoolLevel::SD))
        ->test(ProspectiveStudentManagement::class)
        ->assertSee($ownApplicant->nama_lengkap)
        ->assertDontSee($crossApplicant->nama_lengkap)
        ->assertSee($sdClass->name)
        ->assertDontSee($smpClass->name);

    $component
        ->set('search', 'Cross Applicant Secret')
        ->assertDontSee($crossApplicant->nama_lengkap)
        ->set('search', '')
        ->set('filterClassId', (string) $smpClass->id)
        ->assertDontSee($crossApplicant->nama_lengkap)
        ->assertDontSee($ownApplicant->nama_lengkap)
        ->set('filterClassId', '')
        ->set('filterLevel', SchoolLevel::SMP->value)
        ->assertDontSee($crossApplicant->nama_lengkap)
        ->assertDontSee($ownApplicant->nama_lengkap)
        ->set('filterLevel', '')
        ->set('filterAcademicYearId', (string) $this->futureYear->id)
        ->assertDontSee($crossApplicant->nama_lengkap)
        ->assertDontSee($ownApplicant->nama_lengkap)
        ->set('filterAcademicYearId', '')
        ->set('filterBillStatus', ProspectiveStudentBill::STATUS_PAID)
        ->assertDontSee($crossApplicant->nama_lengkap)
        ->assertSee($ownApplicant->nama_lengkap);
});

it('keeps status and category filters inside the mandatory unit scope', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $smpClass = applicantIsolationClass(SchoolLevel::SMP);
    $ownMatching = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $sdClass->id,
        'nama_lengkap' => 'Own Matching Applicant',
        'status' => ProspectiveStudentStatus::Registered,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);
    $ownDifferent = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $sdClass->id,
        'nama_lengkap' => 'Own Different Applicant',
        'status' => ProspectiveStudentStatus::Cancelled,
        'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY,
    ]);
    $foreignMatching = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $smpClass->id,
        'nama_lengkap' => 'Foreign Matching Applicant',
        'status' => ProspectiveStudentStatus::Registered,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);

    Livewire::actingAs(applicantIsolationTu(SchoolLevel::SD))
        ->test(ProspectiveStudentManagement::class)
        ->set('filterStatus', ProspectiveStudentStatus::Registered->value)
        ->set('filterCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->assertSee($ownMatching->nama_lengkap)
        ->assertDontSee($ownDifferent->nama_lengkap)
        ->assertDontSee($foreignMatching->nama_lengkap);
});

it('paginates only applicants inside the assigned unit', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $smpClass = applicantIsolationClass(SchoolLevel::SMP);
    $ownApplicants = ProspectiveStudent::factory()->count(12)->create([
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $sdClass->id,
    ]);
    $foreignApplicants = ProspectiveStudent::factory()->count(12)->create([
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $smpClass->id,
    ]);

    $component = Livewire::actingAs(applicantIsolationTu(SchoolLevel::SD))
        ->test(ProspectiveStudentManagement::class);

    expect($component->viewData('prospectiveStudents')->total())->toBe(12)
        ->and($component->viewData('prospectiveStudents')->count())->toBe(10);

    $component->set('paginators.page', 2);

    expect($component->viewData('prospectiveStudents')->count())->toBe(2);
    $component->assertDontSee($foreignApplicants->pluck('nama_lengkap')->all());
    expect($ownApplicants)->toHaveCount(12);
});

it('scopes every list counter to the assigned unit', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $smpClass = applicantIsolationClass(SchoolLevel::SMP);
    $ownApplicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Own Counter');
    applicantIsolationApplicant($this->activeYear, $smpClass, 'Foreign Counter A');
    applicantIsolationApplicant($this->activeYear, $smpClass, 'Foreign Counter B');
    applicantIsolationApplicant($this->activeYear, null, 'Null Counter');

    $component = Livewire::actingAs(applicantIsolationTu(SchoolLevel::SD))
        ->test(ProspectiveStudentManagement::class);

    expect($component->viewData('totalAll'))->toBe(1)
        ->and($component->viewData('totalRegistered'))->toBe(1)
        ->and($component->viewData('totalConverted'))->toBe(0)
        ->and($component->viewData('totalAnnurFamily'))->toBe(0)
        ->and($component->viewData('totalNonAnnurFamily'))->toBe(0)
        ->and($component->viewData('totalUncategorized'))->toBe(1);

    $component->assertSee($ownApplicant->nama_lengkap);
});

it('renders a TU applicant biodata detail with private documents but without finance', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $applicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Safe Applicant Biodata');
    ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $applicant->id,
        'amount' => 150000,
    ]);
    $this->actingAs(applicantIsolationTu(SchoolLevel::SD));

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->get(route('calon-siswa.biodata', $applicant));
    $queries = collect(DB::getQueryLog())->pluck('query');

    $response
        ->assertOk()
        ->assertSee($applicant->nama_lengkap)
        ->assertSee('Biodata Calon Siswa')
        ->assertDontSee('Tagihan')
        ->assertDontSee('Pembayaran')
        ->assertDontSee('Penyesuaian')
        ->assertDontSee('Buku Tagihan')
        ->assertSee('Dokumen PPDB')
        ->assertDontSee('Jadikan Siswa');

    expect(applicantIsolationFinanceQueries())->toHaveCount(1)
        ->and(applicantIsolationFinanceQueries()->first()['query'])->toContain('ppdb_documents')
        ->and($queries->filter(fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select'))->count())->toBe(8);
});

it('shows profile parent guardian and sibling biodata on the safe TU detail', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $applicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Complete Biodata Applicant');
    $applicant->ppdbProfile()->create([
        'tempat_lahir' => 'Bandung',
        'kelurahan' => 'Sukamaju',
        'riwayat_penyakit' => 'Tidak Ada',
    ]);
    $applicant->ppdbParents()->createMany([
        ['role' => 'father', 'nama' => 'Ayah Applicant'],
        ['role' => 'mother', 'nama' => 'Ibu Applicant'],
        ['role' => 'guardian', 'nama' => 'Wali Applicant'],
    ]);
    $applicant->ppdbSiblings()->create([
        'sort_order' => 1,
        'nama_lengkap' => 'Saudara Applicant',
        'jenis_kelamin' => 'L',
    ]);

    $this->actingAs(applicantIsolationTu(SchoolLevel::SD))
        ->get(route('calon-siswa.biodata', $applicant))
        ->assertOk()
        ->assertSee('Bandung')
        ->assertSee('Sukamaju')
        ->assertSee('Tidak Ada')
        ->assertSee('AYAH APPLICANT')
        ->assertSee('IBU APPLICANT')
        ->assertSee('WALI APPLICANT')
        ->assertSee('SAUDARA APPLICANT');
});

it('rejects a foreign or missing applicant on the TU biodata detail route', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $smpClass = applicantIsolationClass(SchoolLevel::SMP);
    $foreignApplicant = applicantIsolationApplicant($this->activeYear, $smpClass, 'Foreign Detail Applicant');
    $tu = applicantIsolationTu(SchoolLevel::SD);

    $this->actingAs($tu)
        ->get(route('calon-siswa.biodata', $foreignApplicant))
        ->assertForbidden();

    $this->actingAs($tu)
        ->get(route('calon-siswa.biodata', 999999))
        ->assertNotFound();
});

it('does not expose another unit through TU applicant component state tampering', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $smpClass = applicantIsolationClass(SchoolLevel::SMP);
    $ownApplicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Own Tamper Applicant');
    $foreignApplicant = applicantIsolationApplicant($this->activeYear, $smpClass, 'Foreign Tamper Applicant');

    $page = $this->actingAs(applicantIsolationTu(SchoolLevel::SD))
        ->get(route('calon-siswa.biodata', $ownApplicant))
        ->assertOk()
        ->assertSee($ownApplicant->nama_lengkap);

    $response = $this->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => applicantIsolationLivewireSnapshot($page->getContent()),
                'updates' => ['prospectiveStudent' => $foreignApplicant->getKey()],
                'calls' => [],
            ]],
        ]);

    $response
        ->assertOk()
        ->assertSee($ownApplicant->nama_lengkap)
        ->assertDontSee($foreignApplicant->nama_lengkap);
});

it('allows own-unit applicant deletion while financial actions remain denied for TU', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $applicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Read Only Applicant');
    $tu = applicantIsolationTu(SchoolLevel::SD);

    $component = Livewire::actingAs($tu)
        ->test(ProspectiveStudentManagement::class)
        ->assertSee('Tambah Calon Siswa')
        ->assertDontSee('Import Calon Siswa')
        ->assertSee('Update Biodata Excel')
        ->assertSee('Export Excel Biodata')
        ->assertDontSee('Semua Tagihan')
        ->assertDontSee('Belum Ada Tagihan')
        ->assertDontSee('Lunas')
        ->assertSee('title="Edit"', false)
        ->assertSee('title="Jadikan Siswa"', false)
        ->assertSee('title="Hapus"', false);

    $component->call('confirmDelete', $applicant->id)->call('delete');

    expect(ProspectiveStudent::query()->whereKey($applicant)->exists())->toBeFalse();
});

it('allows scoped applicant Livewire reads but rejects unapproved component snapshots', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $applicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Livewire Read Applicant');
    $foreignClass = applicantIsolationClass(SchoolLevel::SMP);
    $foreignApplicant = applicantIsolationApplicant($this->activeYear, $foreignClass, 'Foreign Livewire Applicant');
    $tu = applicantIsolationTu(SchoolLevel::SD);

    $indexPage = $this->actingAs($tu)->get(route('calon-siswa.index'))->assertOk();
    $indexSnapshot = applicantIsolationLivewireSnapshot($indexPage->getContent());

    $this->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $indexSnapshot,
                'updates' => ['search' => 'Livewire Read Applicant'],
                'calls' => [],
            ]],
        ])
        ->assertOk()
        ->assertSee($applicant->nama_lengkap);

    $this->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $indexSnapshot,
                'updates' => [],
                'calls' => [[
                    'path' => '',
                    'method' => 'confirmDelete',
                    'params' => [$foreignApplicant->id],
                ]],
            ]],
        ])
        ->assertForbidden();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $dashboardSnapshot = applicantIsolationLivewireSnapshot(
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent()
    );

    $this->actingAs($tu)
        ->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $dashboardSnapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ])
        ->assertForbidden();
});

it('keeps applicant import and payment workspaces denied for TU', function (string $url) {
    $this->actingAs(applicantIsolationTu(SchoolLevel::SD))
        ->get($url)
        ->assertRedirect(route('tu.pending'));
})->with([
    'import' => fn () => route('calon-siswa.import'),
    'financial workspace' => function () {
        return route('pembayaran.prospective.workspace', applicantIsolationApplicant($this->activeYear, applicantIsolationClass(SchoolLevel::SD), 'Blocked Finance Applicant'));
    },
]);

it('does not run financial queries for the TU applicant listing or counters', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $applicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'No Finance Applicant');
    ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $applicant->id,
    ]);
    ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $applicant->id,
    ]);
    $tu = applicantIsolationTu(SchoolLevel::SD);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($tu)->test(ProspectiveStudentManagement::class)->html();

    expect(applicantIsolationFinanceQueries())->toBeEmpty();
});

it('keeps applicant listing SELECT counts bounded as rows grow', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $tu = applicantIsolationTu(SchoolLevel::SD);
    $this->actingAs($tu);

    $measure = function (int $applicantCount) use ($sdClass): int {
        ProspectiveStudent::query()->delete();

        ProspectiveStudent::factory()->count($applicantCount)->create([
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $sdClass->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ProspectiveStudentManagement::class)->html();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();
    };

    $measurements = [];

    foreach ([1, 10, 20, 50] as $applicantCount) {
        $measurements[$applicantCount] = $measure($applicantCount);
    }

    expect(array_unique($measurements))->toHaveCount(1)
        ->and($measurements[1])->toBe(22);
});

it('keeps admin and superadmin applicant access unchanged', function (string $role) {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $smpClass = applicantIsolationClass(SchoolLevel::SMP);
    $applicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Administrator Applicant');
    $nullClassApplicant = applicantIsolationApplicant($this->activeYear, null, 'Administrator Null Applicant');
    $crossApplicant = applicantIsolationApplicant($this->activeYear, $smpClass, 'Administrator Cross Applicant');
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('calon-siswa.index'))
        ->assertOk()
        ->assertSee($applicant->nama_lengkap)
        ->assertSee($crossApplicant->nama_lengkap)
        ->assertSee($nullClassApplicant->nama_lengkap);

    $this->get(route('calon-siswa.show', $applicant))->assertOk();
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);

it('denies an inactive TU before scoped applicant access', function () {
    $tu = applicantIsolationTu(SchoolLevel::SD, ['is_active' => false]);

    $this->actingAs($tu)
        ->get(route('calon-siswa.index'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('mounts the TU applicant biodata component only for authorized applicants', function () {
    $sdClass = applicantIsolationClass(SchoolLevel::SD);
    $smpClass = applicantIsolationClass(SchoolLevel::SMP);
    $applicant = applicantIsolationApplicant($this->activeYear, $sdClass, 'Component Applicant');
    $foreignApplicant = applicantIsolationApplicant($this->activeYear, $smpClass, 'Component Foreign Applicant');
    $tu = applicantIsolationTu(SchoolLevel::SD);

    Livewire::actingAs($tu)
        ->test(TuProspectiveStudentBiodata::class, ['prospectiveStudent' => $applicant])
        ->assertSee($applicant->nama_lengkap);

    Livewire::actingAs($tu)
        ->test(TuProspectiveStudentBiodata::class, ['prospectiveStudent' => $foreignApplicant])
        ->assertForbidden();
});
