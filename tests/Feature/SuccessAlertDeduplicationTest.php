<?php

use App\Livewire\DaycareManagement;
use App\Livewire\PaymentShow;
use App\Livewire\ProspectivePaymentShow;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

function makeSuccessAlertPayment(User $user): Payment
{
    return Payment::query()->create([
        'receipt_number' => 'KWT-ALERT-000001',
        'student_id' => Student::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-10-04',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
}

function assertOneGreenSuccessAlert(string $html, string $message): void
{
    expect(substr_count($html, e($message)))->toBe(1)
        ->and($html)->toContain('bg-green-50 border border-green-200 text-green-800')
        ->not->toContain('bg-secondary-container border border-secondary text-on-secondary-container');
}

it('renders redirected student payment success once in the global green alert', function (string $message) {
    $user = User::factory()->create();
    $payment = makeSuccessAlertPayment($user);

    $response = $this->actingAs($user)
        ->withSession(['success' => $message])
        ->get(route('pembayaran.show', $payment));

    $response->assertOk();
    assertOneGreenSuccessAlert($response->getContent(), $message);
})->with([
    'student payment create' => 'Pembayaran baru berhasil dicatat!',
    'student payment edit' => 'Pembayaran berhasil diperbarui.',
    'student payment correction' => 'Koreksi pembayaran berhasil disimpan.',
    'manual student payment edit' => 'Pembayaran manual siswa berhasil diperbarui.',
]);

it('renders redirected prospective payment success once in the global green alert', function (string $message) {
    $user = User::factory()->create();
    $payment = ProspectiveStudentPayment::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)
        ->withSession(['success' => $message])
        ->get(route('pembayaran.prospective.show', $payment));

    $response->assertOk();
    assertOneGreenSuccessAlert($response->getContent(), $message);
})->with([
    'prospective payment create' => 'Pembayaran calon siswa berhasil dicatat!',
    'prospective payment edit' => 'Pembayaran calon siswa berhasil diperbarui.',
]);

it('renders redirected daycare payment success once in the global green alert', function (string $message) {
    $user = User::factory()->create();
    $payment = DaycarePayment::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)
        ->withSession(['success' => $message])
        ->get(route('daycare.payment.show', $payment));

    $response->assertOk();
    assertOneGreenSuccessAlert($response->getContent(), $message);
})->with([
    'daycare payment create' => 'Pembayaran Daycare berhasil dicatat.',
    'daycare payment edit' => 'Pembayaran Daycare berhasil diperbarui.',
]);

it('renders conversion success once in the global green alert', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $message = 'Calon siswa Budi berhasil dijadikan siswa.';

    $response = $this->actingAs($user)
        ->withSession(['success' => $message])
        ->get(route('siswa.show', $student));

    $response->assertOk();
    assertOneGreenSuccessAlert($response->getContent(), $message);
});

it('renders daycare child creation success once in the global green alert', function () {
    $user = User::factory()->create();
    $message = 'Data anak Daycare berhasil ditambahkan.';

    $response = $this->actingAs($user)
        ->withSession(['success' => $message])
        ->get(route('daycare.index'));

    $response->assertOk();
    assertOneGreenSuccessAlert($response->getContent(), $message);
});

it('renders student payment cancellation once as a local green alert without leaking it', function () {
    $user = User::factory()->create();
    $payment = makeSuccessAlertPayment($user);
    $message = 'Pembayaran berhasil dibatalkan.';

    $component = Livewire::actingAs($user)
        ->test(PaymentShow::class, ['id' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', 'Pembayaran dicatat dua kali')
        ->call('cancelPayment')
        ->assertHasNoErrors()
        ->assertDispatched('payment-cancelled', paymentId: $payment->id);

    assertOneGreenSuccessAlert($component->html(), $message);

    $this->actingAs($user)
        ->get(route('pembayaran.index'))
        ->assertOk()
        ->assertDontSee($message);
});

it('renders prospective payment cancellation once as a local green alert', function () {
    $user = User::factory()->create();
    $payment = ProspectiveStudentPayment::factory()->create(['created_by' => $user->id]);
    $message = 'Pembayaran calon siswa berhasil dibatalkan.';

    $component = Livewire::actingAs($user)
        ->test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', 'Pembayaran dicatat dua kali')
        ->call('cancelPayment')
        ->assertHasNoErrors()
        ->assertDispatched('prospective-payment-cancelled', paymentId: $payment->id);

    assertOneGreenSuccessAlert($component->html(), $message);
});

it('preserves one local green success alert for StudentDetail actions and validation feedback', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $paymentType = makeBillType('Tagihan Alert');
    $bill = makeMonthlyBill($student, $paymentType, 100000);
    $message = 'Tagihan berhasil diperbarui.';

    $component = Livewire::actingAs($user)
        ->test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->set('editAmount', 125000)
        ->call('saveEditBill')
        ->assertHasNoErrors();

    assertOneGreenSuccessAlert($component->html(), $message);

    $component
        ->call('editBill', $bill->id)
        ->set('editAmount', '')
        ->call('saveEditBill')
        ->assertHasErrors(['editAmount' => 'required']);
});

it('preserves one local green success alert for DaycareManagement actions without leaking it', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Alert']);
    $message = 'Data Anak Alert berhasil dihapus.';

    $component = Livewire::actingAs($user)
        ->test(DaycareManagement::class)
        ->call('confirmChildDelete', $child->id)
        ->call('deleteChild');

    assertOneGreenSuccessAlert($component->html(), $message);

    $this->actingAs($user)
        ->get(route('daycare.index'))
        ->assertOk()
        ->assertDontSee($message);
});

it('keeps local informational feedback on StudentDetail', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $message = 'Tidak ada tagihan baru untuk diuji.';

    $response = $this->actingAs($user)
        ->withSession(['info' => $message])
        ->get(route('siswa.show', $student));

    $response->assertOk()->assertSee($message);
});

it('keeps local error feedback on the prospective payment workspace', function () {
    $user = User::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $message = 'Kesalahan pembayaran tetap ditampilkan.';

    $response = $this->actingAs($user)
        ->withSession(['error' => $message])
        ->get(route('pembayaran.prospective.workspace', $prospectiveStudent));

    $response->assertOk()->assertSee($message);
    expect(substr_count($response->getContent(), $message))->toBe(1);
});
