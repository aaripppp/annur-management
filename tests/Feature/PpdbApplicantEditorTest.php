<?php

use App\Enums\PpdbApplicantCategory;
use App\Enums\PpdbDocumentType;
use App\Livewire\PpdbApplicantEditor;
use App\Livewire\ProspectiveStudentDetail;
use App\Livewire\StudentDetail;
use App\Models\PpdbDocument;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\Student;
use App\Models\User;
use App\Services\PpdbApplicantDataUpdater;
use App\Services\ProspectiveStudentBillGenerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

function ppdbEditorUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => $active,
    ]);
}

/** @return array<string, mixed> */
function ppdbEditorPayload(ProspectiveStudent $prospectiveStudent, array $overrides = []): array
{
    return array_replace_recursive([
        'applicant_category' => $prospectiveStudent->applicant_category?->value,
        'student' => [
            'nama_lengkap' => $prospectiveStudent->nama_lengkap,
            'nama_panggilan' => $prospectiveStudent->nama_panggilan,
            'jenis_kelamin' => $prospectiveStudent->jenis_kelamin,
            'alamat' => $prospectiveStudent->alamat,
        ],
        'profile' => [],
        'parents' => [
            'father' => [
                'nama' => $prospectiveStudent->nama_orang_tua,
                'no_telp' => $prospectiveStudent->no_telp_orang_tua,
            ],
            'mother' => [],
            'guardian' => [],
        ],
        'siblings' => [],
    ], $overrides);
}

it('allows active admins and super admins to open the shared editor', function (string $role) {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $this->actingAs(ppdbEditorUser($role));

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->assertOk()
        ->call('openEditor')
        ->assertSet('isOpen', true)
        ->assertSet('student.nama_lengkap', $prospectiveStudent->nama_lengkap);
})->with([
    'admin' => User::ROLE_ADMIN,
    'super admin' => User::ROLE_SUPER_ADMIN,
]);

it('denies guests unsupported roles and inactive admins from mounting the editor', function (?string $role, bool $active) {
    $prospectiveStudent = ProspectiveStudent::factory()->create();

    if ($role !== null) {
        $user = ppdbEditorUser(User::ROLE_ADMIN, $active);
        $user->setAttribute('role', $role);
        $this->actingAs($user);
    }

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->assertForbidden();
})->with([
    'guest' => [null, true],
    'unsupported role' => ['auditor', true],
    'inactive admin' => [User::ROLE_ADMIN, false],
]);

it('rechecks authorization when saving after an administrator is deactivated', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $user = ppdbEditorUser();
    $this->actingAs($user);

    $component = Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->assertSet('isOpen', true);

    $user->update(['is_active' => false]);

    $component->call('save')->assertForbidden();
    expect($prospectiveStudent->fresh()->nama_lengkap)->toBe($prospectiveStudent->nama_lengkap);
});

it('exposes the same editor on applicant and linked student detail pages', function () {
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $this->actingAs(ppdbEditorUser());

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $prospectiveStudent])
        ->assertSeeLivewire(PpdbApplicantEditor::class);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeLivewire(PpdbApplicantEditor::class);

    Livewire::test(StudentDetail::class, ['student' => Student::factory()->create()])
        ->assertDontSeeLivewire(PpdbApplicantEditor::class);
});

it('rejects a tampered student and prospective student relationship', function () {
    $student = Student::factory()->create();
    $otherStudent = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, [
        'prospectiveStudent' => $prospectiveStudent,
        'studentId' => $otherStudent->id,
    ])->assertForbidden();

    expect(fn () => app(PpdbApplicantDataUpdater::class)->update(
        $prospectiveStudent->id,
        ppdbEditorPayload($prospectiveStudent),
        $otherStudent->id,
    ))->toThrow(AuthorizationException::class);
});

it('handles historical null sidecars without requiring public registration fields', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $this->actingAs(ppdbEditorUser());

    expect($prospectiveStudent->ppdbProfile)->toBeNull();

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->set('student.nama_lengkap', 'Nama Historis Dikoreksi')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isOpen', false);

    expect($prospectiveStudent->fresh()->nama_lengkap)->toBe('NAMA HISTORIS DIKOREKSI')
        ->and($prospectiveStudent->fresh()->ppdbProfile)->toBeNull();
});

it('loads trims updates and clears nullable asal sekolah in the admin editor', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'asal_sekolah' => 'TK Lama',
    ]);
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->assertSet('profile.asal_sekolah', 'TK Lama')
        ->set('profile.asal_sekolah', '  TK Baru  ')
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->ppdbProfile->asal_sekolah)->toBe('TK Baru');

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent->fresh()])
        ->call('openEditor')
        ->set('profile.asal_sekolah', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->ppdbProfile->asal_sekolah)->toBeNull();
});

it('creates a missing ppdb profile when only asal sekolah is filled', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->set('profile.asal_sekolah', 'TK ANNUR')
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->ppdbProfile)->not->toBeNull()
        ->and($prospectiveStudent->fresh()->ppdbProfile->asal_sekolah)->toBe('TK ANNUR');
});

it('validates provided student and parent identity numbers as exactly sixteen digits', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->set('profile.nik_siswa', '123')
        ->set('profile.nomor_kk', '12345678901234567')
        ->set('parents.father.nik', 'abcdefghijklmnoq')
        ->call('save')
        ->assertHasErrors([
            'profile.nik_siswa' => 'digits',
            'profile.nomor_kk' => 'digits',
            'parents.father.nik' => 'digits',
        ]);
});

it('updates category identity address health parents guardian and siblings', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'applicant_category' => null,
    ]);
    $this->actingAs(ppdbEditorUser());
    $siblingKey = (string) Str::uuid();

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->set('applicantCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->set('student.nama_lengkap', 'Ahmad Diperbarui')
        ->set('student.nama_panggilan', 'Ahmad')
        ->set('student.jenis_kelamin', 'L')
        ->set('student.alamat', 'Jalan Baru Nomor 1')
        ->set('profile.tempat_lahir', 'Bandung')
        ->set('profile.tanggal_lahir', '2015-05-10')
        ->set('profile.nik_siswa', '1234567890123456')
        ->set('profile.nomor_kk', '6543210987654321')
        ->set('profile.jumlah_saudara', '4')
        ->set('profile.rt', '007')
        ->set('profile.kecamatan', 'Cibiru')
        ->set('profile.tinggi_badan', '140.50')
        ->set('profile.berat_badan', '35.25')
        ->set('profile.berkebutuhan_khusus', '0')
        ->set('parents.father.nama', 'Ayah Ahmad')
        ->set('parents.father.nik', '1111111111111111')
        ->set('parents.father.no_telp', '081234567890')
        ->set('parents.mother.nama', 'Ibu Ahmad')
        ->set('parents.mother.nik', '2222222222222222')
        ->set('parents.mother.no_telp', '081234567891')
        ->set('parents.guardian.nama', 'Wali Ahmad')
        ->set('parents.guardian.nik', '3333333333333333')
        ->set('siblings', [[
            'key' => $siblingKey,
            'nama_lengkap' => 'Kakak Ahmad',
            'tanggal_lahir' => '2010-01-01',
            'jenis_kelamin' => 'P',
            'pendidikan_pekerjaan' => 'SMA',
        ]])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('ppdb-biodata-updated');

    $prospectiveStudent->refresh();
    expect($prospectiveStudent->applicant_category)->toBe(PpdbApplicantCategory::ANNUR_FAMILY)
        ->and($prospectiveStudent->nama_lengkap)->toBe('AHMAD DIPERBARUI')
        ->and($prospectiveStudent->nama_orang_tua)->toBe('AYAH AHMAD')
        ->and($prospectiveStudent->no_telp_orang_tua)->toBe('081234567890')
        ->and($prospectiveStudent->ppdbProfile->jumlah_saudara)->toBe(4)
        ->and($prospectiveStudent->ppdbProfile->kecamatan)->toBe('Cibiru')
        ->and($prospectiveStudent->ppdbProfile->berkebutuhan_khusus)->toBeFalse()
        ->and($prospectiveStudent->ppdbParents()->where('role', 'father')->value('nama'))->toBe('AYAH AHMAD')
        ->and($prospectiveStudent->ppdbParents()->where('role', 'mother')->value('nama'))->toBe('IBU AHMAD')
        ->and($prospectiveStudent->ppdbParents()->where('role', 'guardian')->value('nama'))->toBe('WALI AHMAD')
        ->and($prospectiveStudent->ppdbSiblings()->value('nama_lengkap'))->toBe('KAKAK AHMAD');
});

it('corrects an existing category without changing student categories', function () {
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ]);
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, [
        'prospectiveStudent' => $prospectiveStudent,
        'studentId' => $student->id,
    ])
        ->call('openEditor')
        ->set('applicantCategory', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->applicant_category)->toBe(PpdbApplicantCategory::NON_ANNUR_FAMILY)
        ->and($student->categories()->count())->toBe(0);
});

it('adds updates and explicitly removes an optional guardian', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    PpdbParent::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'role' => 'guardian',
        'nama' => 'Wali Lama',
        'no_telp' => '081234567890',
    ]);
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->set('parents.guardian.nama', 'Wali Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->ppdbParents()->where('role', 'guardian')->value('nama'))->toBe('WALI BARU');

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent->fresh()])
        ->call('openEditor')
        ->set('parents.guardian', [
            'nama' => '', 'nik' => '', 'tempat_lahir' => '', 'tanggal_lahir' => '',
            'pendidikan_terakhir' => '', 'email' => '', 'no_telp' => '',
            'occupation' => '', 'income_range' => '',
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->ppdbParents()->where('role', 'guardian')->exists())->toBeFalse();
});

it('preserves the remaining sibling row state when another row is removed', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    foreach (['Pertama', 'Kedua'] as $index => $name) {
        PpdbSibling::query()->create([
            'prospective_student_id' => $prospectiveStudent->id,
            'sort_order' => $index + 1,
            'nama_lengkap' => $name,
            'tanggal_lahir' => '2010-01-0'.($index + 1),
            'jenis_kelamin' => 'L',
            'pendidikan_pekerjaan' => 'Pelajar',
        ]);
    }
    $this->actingAs(ppdbEditorUser());

    $component = Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor');
    $rows = $component->get('siblings');

    $component
        ->set('siblings.1.nama_lengkap', 'Kedua Tetap Terisi')
        ->call('removeSibling', $rows[0]['key'])
        ->assertCount('siblings', 1)
        ->assertSet('siblings.0.key', $rows[1]['key'])
        ->assertSet('siblings.0.nama_lengkap', 'Kedua Tetap Terisi')
        ->call('save')
        ->assertHasNoErrors();

    $savedSibling = $prospectiveStudent->ppdbSiblings()->first();
    expect($prospectiveStudent->ppdbSiblings()->count())->toBe(1)
        ->and($savedSibling->nama_lengkap)->toBe('KEDUA TETAP TERISI')
        ->and($savedSibling->sort_order)->toBe(1);

    $component = Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent->fresh()])
        ->call('openEditor');
    $remainingRow = $component->get('siblings');

    $component
        ->call('removeSibling', $remainingRow[0]['key'])
        ->assertCount('siblings', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->ppdbSiblings()->count())->toBe(0);
});

it('synchronizes only mapped shared fields to a converted student', function () {
    $student = Student::factory()->create([
        'nis' => '9876543',
        'nama_lengkap' => 'Nama Lama',
        'status' => 'aktif',
        'entry_date' => '2026-07-01',
        'foto' => 'students/original.jpg',
    ]);
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $unrelatedBefore = collect($student->getRawOriginal())->only(['nis', 'class_id', 'status', 'entry_date', 'foto'])->all();
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, [
        'prospectiveStudent' => $prospectiveStudent,
        'studentId' => $student->id,
    ])
        ->call('openEditor')
        ->set('student.nama_lengkap', 'Nama Sinkron')
        ->set('student.nama_panggilan', 'Sinkron')
        ->set('student.jenis_kelamin', 'P')
        ->set('student.alamat', 'Alamat Sinkron')
        ->set('profile.tempat_lahir', 'Jakarta')
        ->set('profile.tanggal_lahir', '2014-02-03')
        ->set('parents.father.nama', 'Ayah Sinkron')
        ->set('parents.father.no_telp', '081234567890')
        ->set('parents.mother.nama', 'Ibu Sinkron')
        ->set('parents.mother.no_telp', '081234567891')
        ->call('save')
        ->assertHasNoErrors();

    $student->refresh();
    expect($student->nama_lengkap)->toBe('NAMA SINKRON')
        ->and($student->nama_panggilan)->toBe('SINKRON')
        ->and($student->jenis_kelamin)->toBe('P')
        ->and($student->alamat)->toBe('Alamat Sinkron')
        ->and($student->tempat_lahir)->toBe('Jakarta')
        ->and($student->tanggal_lahir->format('Y-m-d'))->toBe('2014-02-03')
        ->and($student->nama_ayah)->toBe('AYAH SINKRON')
        ->and($student->nama_ibu)->toBe('IBU SINKRON')
        ->and(collect($student->getRawOriginal())->only(['nis', 'class_id', 'status', 'entry_date', 'foto'])->all())->toEqual($unrelatedBefore);
});

it('leaves bills payments receipts and documents unchanged and never calls bill synchronization', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $bill = ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'amount' => 375000,
    ]);
    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'receipt_number' => 'KWT-EDITOR-001',
        'total_amount' => 175000,
    ]);
    $paymentDetail = ProspectiveStudentPaymentDetail::query()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'amount' => 175000,
        'description' => 'Pembayaran awal',
    ]);
    $document = PpdbDocument::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => PpdbDocumentType::KartuKeluarga,
        'disk' => 'local',
        'path' => 'ppdb/documents/original.pdf',
        'original_name' => 'original.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 123,
    ]);
    $billBefore = $bill->fresh()->getRawOriginal();
    $paymentBefore = $payment->fresh()->getRawOriginal();
    $paymentDetailBefore = $paymentDetail->fresh()->getRawOriginal();
    $documentBefore = $document->fresh()->getRawOriginal();
    $billService = Mockery::mock(ProspectiveStudentBillGenerationService::class);
    $billService->shouldNotReceive('sync');
    $this->app->instance(ProspectiveStudentBillGenerationService::class, $billService);
    $this->actingAs(ppdbEditorUser());

    Livewire::test(PpdbApplicantEditor::class, ['prospectiveStudent' => $prospectiveStudent])
        ->call('openEditor')
        ->set('student.nama_lengkap', 'Nama pada Kwitansi Dikoreksi')
        ->set('applicantCategory', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($bill->fresh()->getRawOriginal())->toBe($billBefore)
        ->and($payment->fresh()->getRawOriginal())->toBe($paymentBefore)
        ->and($paymentDetail->fresh()->getRawOriginal())->toBe($paymentDetailBefore)
        ->and($document->fresh()->getRawOriginal())->toBe($documentBefore);
});

it('rolls back every biodata change when a related write fails', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Nama Sebelum Transaksi',
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'tempat_lahir' => 'Sebelum',
    ]);
    PpdbSibling::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'sort_order' => 1,
        'nama_lengkap' => 'Saudara Lama',
        'tanggal_lahir' => '2010-01-01',
        'jenis_kelamin' => 'L',
        'pendidikan_pekerjaan' => 'Pelajar',
    ]);
    DB::unprepared("CREATE TRIGGER fail_ppdb_sibling_insert BEFORE INSERT ON ppdb_siblings WHEN NEW.nama_lengkap = 'FAIL' BEGIN SELECT RAISE(ABORT, 'forced sibling failure'); END");

    $payload = ppdbEditorPayload($prospectiveStudent, [
        'student' => ['nama_lengkap' => 'Nama Seharusnya Rollback'],
        'profile' => ['tempat_lahir' => 'Sesudah'],
        'siblings' => [[
            'nama_lengkap' => 'FAIL',
            'tanggal_lahir' => '2011-01-01',
            'jenis_kelamin' => 'P',
            'pendidikan_pekerjaan' => 'Pelajar',
        ]],
    ]);

    expect(fn () => app(PpdbApplicantDataUpdater::class)->update($prospectiveStudent->id, $payload))
        ->toThrow(QueryException::class);

    expect($prospectiveStudent->fresh()->nama_lengkap)->toBe('NAMA SEBELUM TRANSAKSI')
        ->and($prospectiveStudent->fresh()->ppdbProfile->tempat_lahir)->toBe('Sebelum')
        ->and($prospectiveStudent->fresh()->ppdbSiblings()->count())->toBe(1)
        ->and($prospectiveStudent->fresh()->ppdbSiblings()->value('nama_lengkap'))->toBe('SAUDARA LAMA');
});
