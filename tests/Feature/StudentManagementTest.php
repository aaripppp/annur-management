<?php

use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use Livewire\Livewire;

it('membuat siswa baru', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', '20260001')
        ->set('nama_lengkap', 'Ahmad Fauzi')
        ->set('nama_panggilan', 'Ahmad')
        ->set('class_id', $class->id)
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Merdeka No. 1')
        ->call('save')
        ->assertHasNoErrors();

    $student = Student::where('nis', '20260001')->first();

    expect($student)->not->toBeNull()
        ->and($student->nama_lengkap)->toBe('Ahmad Fauzi')
        ->and($student->entry_date?->toDateString())->toBe(now()->toDateString());
});

it('membuat siswa dengan Tanggal Masuk opsional', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->assertSee('Tanggal Masuk')
        ->set('nis', '20260010')
        ->set('nama_lengkap', 'Siswa Oktober')
        ->set('nama_panggilan', 'Oktober')
        ->set('class_id', $class->id)
        ->set('entry_date', '2026-10-01')
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Oktober')
        ->call('save')
        ->assertHasNoErrors();

    expect(Student::query()->where('nis', '20260010')->sole()->entry_date->toDateString())->toBe('2026-10-01');
});

it('edit memuat siswa yang benar berdasarkan ID', function () {
    Student::factory()->create(['nis' => '20260001', 'nama_lengkap' => 'Siswa A']);
    $second = Student::factory()->create(['nis' => '20260002', 'nama_lengkap' => 'Siswa B']);

    Livewire::test(StudentManagement::class)
        ->call('edit', $second->id)
        ->assertHasNoErrors()
        ->assertSet('isEditing', true)
        ->assertSet('studentId', $second->id)
        ->assertSet('nama_lengkap', 'Siswa B')
        ->assertSet('nis', '20260002');
});

it('modal Edit Data Siswa hanya menandai Nama Lengkap dan Kelas sebagai wajib', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Marker']);

    $html = Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('isEditing', true)
        ->html();

    expect(str_contains($html, 'Nama Lengkap <span class="text-error">*</span>'))->toBeTrue()
        ->and(str_contains($html, 'Kelas <span class="text-error">*</span>'))->toBeTrue()
        ->and(str_contains($html, '>NIS</label>'))->toBeTrue()
        ->and(str_contains($html, '>Nama Panggilan</label>'))->toBeTrue()
        ->and(str_contains($html, '>Jenis Kelamin</label>'))->toBeTrue()
        ->and(str_contains($html, '>Alamat Lengkap</label>'))->toBeTrue()
        ->and(str_contains($html, 'id="nis" wire:model="nis" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: 12345678">'))->toBeTrue()
        ->and(str_contains($html, 'id="nama_panggilan" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Nama Panggilan">'))->toBeTrue()
        ->and(str_contains($html, 'id="alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Jalan, RT/RW, Desa/Kelurahan...">'))->toBeTrue()
        ->and(str_contains($html, 'id="class_id" wire:model="class_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required'))->toBeTrue()
        ->and(str_contains($html, 'id="nama_lengkap" wire:model="nama_lengkap" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Nama Lengkap Siswa" required'))->toBeTrue();
});

it('gender memakai satu native radio group dan satu scalar state', function () {
    $component = Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->assertSet('jenis_kelamin', '');

    expect(substr_count($component->html(), 'name="jenis_kelamin"'))->toBe(2)
        ->and(substr_count($component->html(), 'wire:model="jenis_kelamin"'))->toBe(2);

    $component
        ->set('jenis_kelamin', 'L')
        ->assertSet('jenis_kelamin', 'L')
        ->set('jenis_kelamin', 'P')
        ->assertSet('jenis_kelamin', 'P')
        ->set('jenis_kelamin', 'L')
        ->assertSet('jenis_kelamin', 'L');
});

it('edit memuat satu gender dan form baru tidak menyimpan state edit', function () {
    $maleStudent = Student::factory()->create(['jenis_kelamin' => 'L']);
    $femaleStudent = Student::factory()->create(['jenis_kelamin' => 'P']);

    Livewire::test(StudentManagement::class)
        ->call('edit', $maleStudent->id)
        ->assertSet('jenis_kelamin', 'L')
        ->call('edit', $femaleStudent->id)
        ->assertSet('jenis_kelamin', 'P')
        ->call('closeModal')
        ->assertSet('jenis_kelamin', '')
        ->call('openModal')
        ->assertSet('isEditing', false)
        ->assertSet('jenis_kelamin', '');
});

it('mengupdate data siswa dan list refresh tanpa reload', function () {
    $student = Student::factory()->create(['nis' => '20260003', 'nama_lengkap' => 'Siswa Lama', 'nama_panggilan' => 'Lama']);

    Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->set('nama_lengkap', 'Siswa Baru')
        ->set('nama_panggilan', 'Baru')
        ->call('save')
        ->assertHasNoErrors()
        ->set('search', 'Siswa Baru')
        ->assertDontSee('Siswa Lama')
        ->assertSee('Siswa Baru');

    expect(Student::find($student->id)->nama_lengkap)->toBe('Siswa Baru');
});

it('mengubah entry date tanpa menulis ulang tagihan atau pembayaran historis', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $type = makeBillType('SPP Aman Histori');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    $payment = Payment::create([
        'receipt_number' => 'REC-HISTORY-SAFE',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-20',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);
    $detail = $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 970000,
    ]);
    $paymentSnapshot = $payment->fresh();

    Livewire::test(StudentManagement::class)
        ->call('edit', $student->id)
        ->assertSet('entry_date', '')
        ->set('entry_date', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->entry_date->toDateString())->toBe('2026-10-01')
        ->and(StudentBill::query()->whereKey($bill->id)->sole()->only(['amount', 'period_month', 'period_year']))->toBe($bill->only(['amount', 'period_month', 'period_year']))
        ->and(Payment::query()->whereKey($payment->id)->sole()->total_amount)->toBe($paymentSnapshot->total_amount)
        ->and(Payment::query()->whereKey($payment->id)->sole()->payment_date->toDateString())->toBe($paymentSnapshot->payment_date->toDateString())
        ->and(Payment::query()->whereKey($payment->id)->sole()->status)->toBe($paymentSnapshot->status)
        ->and(PaymentDetail::query()->whereKey($detail->id)->sole()->bill_id)->toBe($bill->id)
        ->and(StudentBill::query()->where('student_id', $student->id)->count())->toBe(1)
        ->and(Payment::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('filter kelas menampilkan hanya siswa kelas tersebut', function () {
    $classA = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $classB = SchoolClass::factory()->create(['name' => 'VIII B', 'level' => 8]);

    Student::factory()->create(['class_id' => $classA->id, 'nama_lengkap' => 'Siswa Tujuh', 'nis' => '20260004']);
    Student::factory()->create(['class_id' => $classB->id, 'nama_lengkap' => 'Siswa Delapan', 'nis' => '20260005']);

    Livewire::test(StudentManagement::class)
        ->set('filterClassId', $classA->id)
        ->assertSee('Siswa Tujuh')
        ->assertDontSee('Siswa Delapan');

    Livewire::test(StudentManagement::class)
        ->set('filterClassId', $classB->id)
        ->assertDontSee('Siswa Tujuh')
        ->assertSee('Siswa Delapan');
});

it('filter jenjang TK mencakup siswa KB', function () {
    $kb = SchoolClass::query()->firstOrCreate(['name' => 'KB'], ['level' => -3]);
    $sdClass = SchoolClass::factory()->create(['level' => 1]);
    Student::factory()->create(['class_id' => $kb->id, 'nama_lengkap' => 'Siswa KB', 'nis' => 'KB-001']);
    Student::factory()->create(['class_id' => $sdClass->id, 'nama_lengkap' => 'Siswa SD', 'nis' => 'SD-001']);

    Livewire::test(StudentManagement::class)
        ->set('filterLevel', 'TK')
        ->assertSee('Siswa KB')
        ->assertDontSee('Siswa SD');
});

it('setelah filter, edit menargetkan siswa yang benar', function () {
    $classA = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $classB = SchoolClass::factory()->create(['name' => 'VIII B', 'level' => 8]);

    Student::factory()->create(['class_id' => $classA->id, 'nama_lengkap' => 'Siswa Tujuh', 'nis' => '20260006']);
    $target = Student::factory()->create(['class_id' => $classB->id, 'nama_lengkap' => 'Siswa Delapan', 'nis' => '20260007']);

    Livewire::test(StudentManagement::class)
        ->set('filterClassId', $classA->id)
        ->call('edit', $target->id)
        ->assertHasNoErrors()
        ->assertSet('studentId', $target->id)
        ->assertSet('nama_lengkap', 'Siswa Delapan')
        ->assertSet('nis', '20260007');
});

it('menghapus siswa yang punya tagihan, penyesuaian, dan pembayaran berhasil', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);

    $bill->adjustments()->create([
        'type' => 'discount',
        'amount' => 50000,
        'reason' => 'Potongan',
        'created_by' => $user->id,
    ]);

    $payment = Payment::create([
        'receipt_number' => 'REC-'.random_int(1000, 9999),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 920000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 920000,
    ]);

    makeActiveSetting($student, $type);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('delete');

    expect(Student::find($student->id))->toBeNull()
        ->and(StudentBill::where('student_id', $student->id)->count())->toBe(0)
        ->and(BillAdjustment::where('bill_id', $bill->id)->count())->toBe(0)
        ->and(Payment::where('student_id', $student->id)->count())->toBe(0)
        ->and(PaymentDetail::where('payment_id', $payment->id)->count())->toBe(0)
        ->and(StudentPaymentSetting::where('student_id', $student->id)->count())->toBe(0);
});

it('menghapus siswa tidak mengganggu siswa lain', function () {
    $target = Student::factory()->create(['nis' => '20260008', 'nama_lengkap' => 'Target']);
    $other = Student::factory()->create(['nis' => '20260009', 'nama_lengkap' => 'Lainnya']);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $target->id)
        ->call('delete');

    expect(Student::find($target->id))->toBeNull()
        ->and(Student::find($other->id))->not->toBeNull();
});

it('wire:key pada baris list menggunakan ID siswa', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa WireKey']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Siswa WireKey')
        ->assertSee('student-'.$student->id, false);
});

it('NIS yang sama dapat dipakai untuk siswa baru di jenjang berbeda', function () {
    $year = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => true,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $sdClass = SchoolClass::factory()->create(['level' => 6]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $oldStudent = Student::factory()->create(['nis' => '20269999', 'class_id' => $sdClass->id, 'status' => 'lulus']);
    StudentAcademicEnrollment::create([
        'student_id' => $oldStudent->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $sdClass->id,
        'status' => 'lulus',
    ]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', '20269999')
        ->set('nama_lengkap', 'Siswa Baru')
        ->set('nama_panggilan', 'Baru')
        ->set('class_id', $smpClass->id)
        ->set('entry_academic_year_id', (string) $year->id)
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Uji No. 1')
        ->call('save')
        ->assertHasNoErrors();

    $newStudent = Student::query()->where('nis', '20269999')->whereKeyNot($oldStudent->id)->sole();

    expect(Student::query()->where('nis', '20269999')->count())->toBe(2)
        ->and($oldStudent->fresh()->class_id)->toBe($sdClass->id)
        ->and($oldStudent->fresh()->status->value)->toBe('lulus')
        ->and($newStudent->class_id)->toBe($smpClass->id)
        ->and($newStudent->enrollments()->where('academic_year_id', $year->id)->where('status', 'active')->exists())->toBeTrue();
});

it('NIS yang sama ditolak pada jenjang dan tahun ajaran aktif yang sama', function () {
    $year = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => true,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $firstClass = SchoolClass::factory()->create(['level' => 7]);
    $otherClass = SchoolClass::factory()->create(['level' => 8]);
    $existing = Student::factory()->create(['nis' => '20268888', 'class_id' => $firstClass->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $existing->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $firstClass->id,
        'status' => 'active',
    ]);

    Livewire::test(StudentManagement::class)
        ->call('openModal')
        ->set('nis', '20268888')
        ->set('nama_lengkap', 'Duplikat SMP')
        ->set('nama_panggilan', 'Duplikat')
        ->set('class_id', $otherClass->id)
        ->set('entry_academic_year_id', (string) $year->id)
        ->set('jenis_kelamin', 'L')
        ->set('alamat', 'Jl. Uji No. 2')
        ->call('save')
        ->assertHasErrors(['nis']);

    expect(Student::query()->where('nis', '20268888')->count())->toBe(1);
});
