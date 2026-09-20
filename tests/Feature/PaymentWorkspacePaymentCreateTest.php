<?php

use App\Livewire\PaymentCreate;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Livewire\Livewire;

function makePreselectedFutureStudent(): Student
{
    AcademicYear::query()->update(['is_active' => false]);

    AcademicYear::query()->updateOrCreate(
        ['year' => '2098/2099'],
        [
            'is_active' => true,
            'start_date' => '2098-07-01',
            'end_date' => '2099-06-30',
        ]
    );
    $futureYear = AcademicYear::query()->updateOrCreate(
        ['year' => '2099/2100'],
        [
            'is_active' => false,
            'start_date' => '2099-07-01',
            'end_date' => '2100-06-30',
        ]
    );
    $schoolClass = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $schoolClass->id]);
    $student->update(['class_id' => $schoolClass->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return $student;
}

it('link Input Pembayaran memuat student ID yang dipilih', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee(route('pembayaran.create', ['student' => $student->id]), false);
});

it('PaymentCreate dengan student ID valid langsung memilih siswa', function () {
    $student = makeBillStudent();

    Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentCreate::class)
        ->assertSet('studentId', $student->id)
        ->assertSet('selected_student_id', $student->id)
        ->assertSet('selectedStudentData.nama_lengkap', $student->nama_lengkap);
});

it('tagihan siswa terpilih langsung dimuat', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP Preselected');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);

    $component = Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentCreate::class)
        ->assertSee('SPP Preselected');

    expect(collect($component->get('outstandingBills'))->pluck('id'))->toContain($bill->id);
});

it('siswa preselected tidak perlu dicari kembali', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Muhammad Ridwan']);

    Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentCreate::class)
        ->assertSet('student_search', '')
        ->assertSee('Muhammad Ridwan')
        ->assertDontSee('Ketik nama siswa atau NIS...');
});

it('PaymentCreate tanpa student parameter tetap memakai pencarian manual', function () {
    Livewire::test(PaymentCreate::class)
        ->assertSet('studentId', null)
        ->assertSet('selected_student_id', null)
        ->assertSee('Ketik nama siswa atau NIS...');
});

it('PaymentCreate membedakan same NIS dan tetap memilih berdasarkan Student ID', function () {
    AcademicYear::query()->update(['is_active' => false]);
    $oldYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $activeYear = AcademicYear::create([
        'year' => '2028/2029',
        'is_active' => true,
        'start_date' => '2028-07-01',
        'end_date' => '2029-06-30',
    ]);
    $sdClass = SchoolClass::factory()->create(['level' => 6]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $oldStudent = Student::factory()->create([
        'nis' => '28-0001',
        'nama_lengkap' => 'Ahmad Same NIS',
        'class_id' => $sdClass->id,
        'status' => 'lulus',
    ]);
    $newStudent = Student::factory()->create([
        'nis' => '28-0001',
        'nama_lengkap' => 'Ahmad Same NIS',
        'class_id' => $smpClass->id,
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $oldStudent->id,
        'academic_year_id' => $oldYear->id,
        'school_class_id' => $sdClass->id,
        'status' => 'lulus',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $newStudent->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $smpClass->id,
        'status' => 'active',
    ]);

    Livewire::test(PaymentCreate::class)
        ->set('student_search', '28-0001')
        ->assertSee('payment-create-student-result-'.$oldStudent->id, false)
        ->assertSee('payment-create-student-result-'.$newStudent->id, false)
        ->assertSee($sdClass->name)
        ->assertSee($smpClass->name)
        ->assertSee('SD')
        ->assertSee('SMP')
        ->assertSee($oldYear->year)
        ->assertSee($activeYear->year)
        ->assertSee('Lulus')
        ->assertSee('Aktif')
        ->call('selectStudent', $newStudent->id)
        ->assertSet('selected_student_id', $newStudent->id)
        ->assertSet('selectedStudentData.school_level', 'SMP')
        ->assertSet('selectedStudentData.academic_year', $activeYear->year);

    Livewire::withQueryParams(['student' => $oldStudent->id])
        ->test(PaymentCreate::class)
        ->assertSet('selected_student_id', $oldStudent->id)
        ->assertSet('selectedStudentData.school_level', 'SD');
});

it('student ID yang tidak valid diabaikan dengan aman', function () {
    Livewire::withQueryParams(['student' => 999999999])
        ->test(PaymentCreate::class)
        ->assertSet('selected_student_id', null)
        ->assertSee('Ketik nama siswa atau NIS...');

    Livewire::withQueryParams(['student' => 'bukan-id'])
        ->test(PaymentCreate::class)
        ->assertSet('studentId', null)
        ->assertSet('selected_student_id', null);
});

it('calon siswa dapat dipilih melalui query parameter', function () {
    $student = makePreselectedFutureStudent();

    Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentCreate::class)
        ->assertSet('selected_student_id', $student->id)
        ->assertSet('selectedStudentData.nama_lengkap', $student->nama_lengkap);
});

it('tagihan existing calon siswa langsung tersedia', function () {
    $student = makePreselectedFutureStudent();
    $yearlyType = makeBillType('Uang Buku Future Preselected');
    $oneTimeType = makeBillType('Uang Pangkal Future Preselected');
    $yearlyBill = makeYearlyBill($student, $yearlyType, 750000, '2099/2100');
    $oneTimeBill = makeOneTimeBill($student, $oneTimeType, 5000000, '2099/2100');

    $component = Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentCreate::class)
        ->assertSee('Uang Buku Future Preselected')
        ->assertSee('Uang Pangkal Future Preselected');

    expect(collect($component->get('outstandingBills'))->pluck('id')->all())
        ->toContain($yearlyBill->id, $oneTimeBill->id);
});

it('pembayaran dapat disimpan melalui jalur preselected', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $type = makeBillType('SPP Save Preselected');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);

    Livewire::actingAs($user);

    $component = Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentCreate::class)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-22')
        ->call('save')
        ->assertHasNoErrors();

    $payment = Payment::query()->where('student_id', $student->id)->latest('id')->firstOrFail();

    $component->assertRedirect(route('pembayaran.show', ['id' => $payment->id]));
    expect($payment->details()->where('bill_id', $bill->id)->exists())->toBeTrue();
});

it('receipt menyediakan link kembali ke Payment Workspace siswa', function () {
    $user = User::factory()->create();
    $student = makeBillStudent();
    $payment = Payment::create([
        'receipt_number' => 'KWT-RETURN-CONTEXT',
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-22',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee(route('pembayaran.index', ['tab' => 'history']), false)
        ->assertSee('Kembali ke Pembayaran Siswa')
        ->assertSee(route('pembayaran.index', ['student' => $student->id]), false);
});

it('Payment Workspace dapat memulihkan siswa terpilih dari return context', function () {
    $student = makeBillStudent();

    Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentIndex::class)
        ->assertSet('selectedStudentId', $student->id)
        ->assertSee('Profil Siswa')
        ->assertSee($student->nama_lengkap);
});
