<?php

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;

function tuBoundaryUser(SchoolLevel $schoolLevel = SchoolLevel::SD, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => $schoolLevel,
        'is_active' => true,
    ], $attributes));
}

function renderBoundarySidebar(): string
{
    return view('layouts._sidebar-nav', [
        'isStudentActive' => false,
        'isStudentDataActive' => false,
        'isStudentExamActive' => false,
        'isCalonSiswaActive' => false,
        'isStudentPaymentActive' => false,
        'isStudentReportActive' => false,
        'isJemputanActive' => false,
        'isDaycareActive' => false,
        'isDaycareDataActive' => false,
        'isDaycarePaymentActive' => false,
        'isDaycareReportActive' => false,
        'isMasterDataActive' => false,
        'isAccountActive' => false,
    ])->render();
}

function sidebarBoundaryLinkClass(string $html, string $url): string
{
    preg_match('/<a class="([^"]*)" href="'.preg_quote($url, '/').'">/', $html, $matches);

    return $matches[1] ?? '';
}

function expectBoundarySidebarActive(string $html, string $activeUrl, string $inactiveUrl): void
{
    $activeClasses = 'bg-secondary-container text-on-secondary-container font-bold';

    expect(sidebarBoundaryLinkClass($html, $activeUrl))->toContain($activeClasses)
        ->and(sidebarBoundaryLinkClass($html, $inactiveUrl))->not->toContain($activeClasses)
        ->and($html)->toContain("sidebarMenu('siswa', true)");
}

it('shows each TU unit only student links class recap and logout', function (SchoolLevel $schoolLevel) {
    $this->actingAs(tuBoundaryUser($schoolLevel));

    $sidebar = renderBoundarySidebar();

    expect($sidebar)
        ->toContain('TU Unit '.$schoolLevel->value)
        ->toContain('Siswa')
        ->toContain('href="'.route('siswa.index').'"')
        ->toContain('Data Siswa')
        ->toContain('href="'.route('calon-siswa.index').'"')
        ->toContain('Calon Siswa')
        ->toContain('href="'.route('laporan.index', ['tab' => 'class']).'"')
        ->toContain('Laporan')
        ->toContain('action="'.route('logout').'"')
        ->toContain('Keluar')
        ->not->toContain('Dashboard')
        ->not->toContain('Kelayakan Ujian')
        ->not->toContain('Pembayaran')
        ->not->toContain('Daycare')
        ->not->toContain('Master Data')
        ->not->toContain('Pengaturan')
        ->not->toContain('Manajemen Akun');

    expect(substr_count($sidebar, '<a '))->toBe(3);
})->with([
    'TK' => SchoolLevel::TK,
    'SD' => SchoolLevel::SD,
    'SMP' => SchoolLevel::SMP,
    'SMA' => SchoolLevel::SMA,
]);

it('highlights only the matching TU Student or Applicant submenu on index and detail routes', function () {
    AcademicYear::query()->update(['is_active' => false]);
    $academicYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $schoolClass = SchoolClass::factory()->create(['level' => 4]);
    $student = Student::factory()->create(['class_id' => $schoolClass->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);
    $applicant = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
    ]);
    $tu = tuBoundaryUser();
    $studentUrl = route('siswa.index');
    $applicantUrl = route('calon-siswa.index');

    foreach ([route('siswa.index'), route('siswa.show', $student)] as $url) {
        $html = $this->actingAs($tu)->get($url)->assertOk()->getContent();
        expectBoundarySidebarActive($html, $studentUrl, $applicantUrl);
    }

    foreach ([route('calon-siswa.index'), route('calon-siswa.show', $applicant)] as $url) {
        $html = $this->actingAs($tu)->get($url)->assertOk()->getContent();
        expectBoundarySidebarActive($html, $applicantUrl, $studentUrl);
    }
});

it('renders one active TU report link inside the expanded Student submenu', function () {
    $tu = tuBoundaryUser(SchoolLevel::SMP);
    $reportUrl = route('laporan.index', ['tab' => 'class']);
    $html = $this->actingAs($tu)
        ->get($reportUrl)
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain("sidebarMenu('siswa', true)")
        ->and(substr_count($html, 'href="'.$reportUrl.'"'))->toBe(1)
        ->and(sidebarBoundaryLinkClass($html, $reportUrl))
        ->toContain('bg-secondary-container text-on-secondary-container font-bold');

    $studentPanelStart = strpos($html, 'id="nav-student-panel"');
    $studentPanelEnd = strpos($html, '</div>', strpos($html, $reportUrl));
    $reportPosition = strpos($html, $reportUrl);

    expect($studentPanelStart)->not->toBeFalse()
        ->and($reportPosition)->toBeGreaterThan($studentPanelStart)
        ->and($studentPanelEnd)->toBeGreaterThan($reportPosition);
});

it('keeps Admin and Superadmin Student submenu active styling unchanged', function (string $role, string $routeName, string $activeItem) {
    $user = User::factory()->create(['role' => $role]);
    $studentUrl = route('siswa.index');
    $applicantUrl = route('calon-siswa.index');
    $html = $this->actingAs($user)->get(route($routeName))->assertOk()->getContent();

    expectBoundarySidebarActive(
        $html,
        $activeItem === 'student' ? $studentUrl : $applicantUrl,
        $activeItem === 'student' ? $applicantUrl : $studentUrl,
    );
})->with([
    'admin Student' => [User::ROLE_ADMIN, 'siswa.index', 'student'],
    'admin Applicant' => [User::ROLE_ADMIN, 'calon-siswa.index', 'applicant'],
    'superadmin Student' => [User::ROLE_SUPER_ADMIN, 'siswa.index', 'student'],
    'superadmin Applicant' => [User::ROLE_SUPER_ADMIN, 'calon-siswa.index', 'applicant'],
]);

it('keeps the existing admin and superadmin navigation capabilities', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $sidebar = renderBoundarySidebar();

    expect($sidebar)
        ->toContain('href="'.route('dashboard').'"')
        ->toContain('href="'.route('siswa.index').'"')
        ->toContain('href="'.route('calon-siswa.index').'"')
        ->toContain('Kelayakan Ujian')
        ->toContain('Pembayaran')
        ->toContain('Laporan')
        ->toContain('Daycare')
        ->toContain('Master Data')
        ->toContain('Pengaturan')
        ->toContain('action="'.route('logout').'"')
        ->when(
            $role === User::ROLE_SUPER_ADMIN,
            fn ($expectation) => $expectation->toContain('Manajemen Akun'),
            fn ($expectation) => $expectation->not->toContain('Manajemen Akun'),
        );
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);

it('renders the TU pending landing with the secured TU sidebar', function () {
    $tu = tuBoundaryUser(SchoolLevel::SMP);

    $this->actingAs($tu)
        ->get(route('tu.pending'))
        ->assertOk()
        ->assertSee('id="main-sidebar"', false)
        ->assertSee('href="'.route('siswa.index').'"', false)
        ->assertSee('href="'.route('calon-siswa.index').'"', false)
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('Akses TU Unit sedang disiapkan')
        ->assertSee('SMP');
});

it('redirects TU direct URL tampering to the safe pending boundary', function (string $url) {
    $this->actingAs(tuBoundaryUser())
        ->get($url)
        ->assertRedirect(route('tu.pending'));
})->with([
    'dashboard' => fn () => route('dashboard'),
    'student import' => fn () => route('siswa.import'),
    'exam eligibility' => fn () => route('siswa.exam-eligibility'),
    'payment' => fn () => route('pembayaran.index'),
    'report export' => fn () => route('laporan.harian.export'),
    'master data' => fn () => route('kelas.index'),
    'PPDB settings' => fn () => route('ppdb.default-class-mappings.index'),
    'daycare' => fn () => route('daycare.index'),
    'account management' => fn () => route('akun.index'),
    'authenticated public PPDB' => fn () => route('ppdb.public.register'),
]);

it('denies valid TU billing and receipt URLs', function () {
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $daycareChild = DaycareChild::factory()->create();
    $tu = tuBoundaryUser();
    $payment = Payment::create([
        'receipt_number' => 'TU-BOUNDARY-001',
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-11-07',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'created_by' => $tu->id,
    ]);
    $daycarePayment = DaycarePayment::factory()->create([
        'daycare_child_id' => $daycareChild->id,
        'created_by' => $tu->id,
    ]);
    $ppdbDocument = PpdbDocument::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => 'kartu_keluarga',
        'disk' => 'local',
        'path' => 'ppdb/test/kartu-keluarga.pdf',
        'original_name' => 'kartu-keluarga.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'uploaded_by' => $tu->id,
    ]);

    $this->actingAs($tu);

    $restrictedUrls = [
        route('siswa.payment-settings', $student),
        route('pembayaran.pdf', $payment),
        route('daycare.payment.create', $daycareChild),
        route('daycare.payment.pdf', $daycarePayment),
    ];

    foreach ($restrictedUrls as $restrictedUrl) {
        $this->get($restrictedUrl)->assertRedirect(route('tu.pending'));
    }
});

it('returns not found safely for TU requests that do not match any route', function (string $method) {
    $this->actingAs(tuBoundaryUser());

    $this->call($method, '/tu/unapproved-capability')->assertNotFound();
})->with(['GET', 'POST']);

it('rejects direct TU Livewire and state-changing requests server-side', function (string $method, string $url) {
    $this->actingAs(tuBoundaryUser());

    $this->call($method, $url, [], [], [], ['HTTP_X_LIVEWIRE' => 'true'])
        ->assertForbidden();
})->with([
    'Livewire update' => ['POST', '/livewire/update'],
    'profile deletion' => ['DELETE', '/profile'],
]);

it('allows TU users to manage only their own profile and password', function () {
    $tu = tuBoundaryUser();

    $this->actingAs($tu)
        ->get(route('profile.edit'))
        ->assertOk();

    $this->patch(route('profile.update'), [
        'name' => 'TU Updated',
        'email' => $tu->email,
        'position' => 'Staf TU',
    ])->assertRedirect(route('profile.edit'));

    $this->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect();

    expect($tu->refresh())
        ->name->toBe('TU Updated')
        ->role->toBe(User::ROLE_TU_UNIT)
        ->school_level->toBe(SchoolLevel::SD)
        ->is_active->toBeTrue();
});

it('allows TU logout and invalidates its authenticated session', function () {
    $tu = tuBoundaryUser();

    $this->actingAs($tu)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps guest public PPDB registration available', function () {
    $this->get(route('ppdb.public.register'))->assertOk();
});

it('revokes an inactive TU before it can use the pending route', function () {
    $tu = tuBoundaryUser(attributes: ['is_active' => false]);

    $this->actingAs($tu)
        ->get(route('tu.pending'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});
