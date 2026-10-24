<?php

use App\Livewire\PaymentIndex;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * @param  list<array{bill: StudentBill, amount: int}>  $allocations
 */
function createBillbookHistoryPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $receiptNumber,
    string $paymentDate,
    string $createdAt,
    array $allocations,
    string $status = Payment::STATUS_ACTIVE,
    ?int $headerTotal = null,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => $receiptNumber,
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $headerTotal ?? collect($allocations)->sum('amount'),
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);

    $payment->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->saveQuietly();

    foreach ($allocations as $allocation) {
        PaymentDetail::query()->create([
            'payment_id' => $payment->id,
            'bill_id' => $allocation['bill']->id,
            'payment_type_id' => $allocation['bill']->payment_type_id,
            'period_month' => $allocation['bill']->period_month,
            'period_year' => $allocation['bill']->period_year,
            'academic_year' => $allocation['bill']->academic_year,
            'amount' => $allocation['amount'],
        ]);
    }

    return $payment->refresh();
}

function billbookHistoryRowHtml($component, StudentBill $bill): string
{
    preg_match('/<tr wire:key="bill-'.$bill->id.'".*?<\/tr>/s', $component->html(), $matches);

    expect($matches)->toHaveKey(0);

    return $matches[0];
}

function assertBillbookHistoryUi($component, StudentBill $partialBill, StudentBill $paidBill, Payment $firstPayment): void
{
    $component
        ->assertSee('Tanggal TF Terakhir')
        ->assertSee('Tanggal Input Terakhir')
        ->assertSee('Riwayat')
        ->assertSee('Lihat (2)')
        ->assertSee('Lihat (1)')
        ->assertSee('09 Agustus 2026')
        ->assertSee('10 Agustus 2026, 08:30')
        ->assertSee('12 Agustus 2026')
        ->assertSee('Rp 200.000')
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 75.000')
        ->assertDontSee('Rp 275.000')
        ->assertDontSee('KWT-HISTORY-CANCELLED')
        ->assertSeeInOrder(['KWT-HISTORY-FIRST', 'KWT-HISTORY-SECOND'])
        ->assertSee(route('pembayaran.show', $firstPayment->id), false)
        ->assertSeeHtml('@click="historyBillId = '.$partialBill->id.'"')
        ->assertSeeHtml('@click="historyBillId = '.$paidBill->id.'"')
        ->assertSeeHtml('@click.self="historyBillId = null"')
        ->assertSeeHtml('@keydown.escape.window="historyBillId = null"')
        ->assertSee('Riwayat Pembayaran')
        ->assertSee('Tutup');

    expect(billbookHistoryRowHtml($component, $partialBill))
        ->toContain('09 Agustus 2026')
        ->toContain('10 Agustus 2026, 08:30')
        ->toContain('Lihat (2)')
        ->not->toContain('12 Agustus 2026');
}

it('shows empty latest dates and history for an unpaid bill on both billbook pages', function () {
    $user = User::factory()->create();
    $student = makeBillStudent(8);
    $paymentType = makeBillType('SPP Tanpa Pembayaran');
    $bill = makeMonthlyBill($student, $paymentType, 970000, 8, 2026);
    makeActiveSetting($student, $paymentType);

    Livewire::actingAs($user);

    $studentDetail = Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tanggal TF Terakhir')
        ->assertSee('Tanggal Input Terakhir')
        ->assertSee('Riwayat')
        ->assertDontSee('Lihat (');

    expect(substr_count(billbookHistoryRowHtml($studentDetail, $bill), '—'))->toBe(3);

    $paymentIndex = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('SPP Tanpa Pembayaran')
        ->assertSee('Tanggal TF Terakhir')
        ->assertSee('Tanggal Input Terakhir')
        ->assertSee('Riwayat')
        ->assertDontSee('Lihat (');

    expect(substr_count(billbookHistoryRowHtml($paymentIndex, $bill), '—'))->toBe(3);

    expect($bill->fresh()->paid_amount)->toBe(0.0)
        ->and($bill->fresh()->remaining_amount)->toBe(970000.0)
        ->and($bill->fresh()->status)->toBe(StudentBill::STATUS_UNPAID);
});

it('shows ordered active allocations with deterministic latest dates on both billbook pages', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $spp = makeBillType('SPP Riwayat');
    $transport = makeBillType('Jemputan Riwayat');
    $unpaidType = makeBillType('Ekskul Riwayat');
    $partialBill = makeMonthlyBill($student, $spp, 970000, 8, 2026);
    $paidBill = makeMonthlyBill($student, $transport, 75000, 8, 2026);
    makeMonthlyBill($student, $unpaidType, 50000, 8, 2026);
    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $transport);
    makeActiveSetting($student, $unpaidType);

    $firstPayment = createBillbookHistoryPayment(
        $student,
        $bank,
        $user,
        'KWT-HISTORY-FIRST',
        '2026-08-05',
        '2026-08-10 08:30:00',
        [
            ['bill' => $partialBill, 'amount' => 200000],
            ['bill' => $paidBill, 'amount' => 75000],
        ],
        headerTotal: 275000,
    );

    $firstPayment->forceFill(['payment_date' => '2026-08-12'])->saveQuietly();

    $secondPayment = createBillbookHistoryPayment(
        $student,
        $bank,
        $user,
        'KWT-HISTORY-SECOND',
        '2026-08-09',
        '2026-08-10 08:30:00',
        [['bill' => $partialBill, 'amount' => 300000]],
    );

    createBillbookHistoryPayment(
        $student,
        $bank,
        $user,
        'KWT-HISTORY-CANCELLED',
        '2026-08-15',
        '2026-08-15 09:00:00',
        [['bill' => $partialBill, 'amount' => 470000]],
        Payment::STATUS_CANCELLED,
    );

    $databaseState = [
        'payments' => Payment::query()->orderBy('id')->get()->toArray(),
        'details' => PaymentDetail::query()->orderBy('id')->get()->toArray(),
        'bills' => StudentBill::query()->orderBy('id')->get()->toArray(),
    ];

    Livewire::actingAs($user);

    $studentDetail = Livewire::test(StudentDetail::class, ['student' => $student]);
    assertBillbookHistoryUi($studentDetail, $partialBill, $paidBill, $firstPayment);

    $paymentIndex = Livewire::test(PaymentIndex::class)->call('selectStudent', $student->id);
    assertBillbookHistoryUi($paymentIndex, $partialBill, $paidBill, $firstPayment);

    expect($secondPayment->id)->toBeGreaterThan($firstPayment->id)
        ->and($partialBill->fresh()->paid_amount)->toBe(500000.0)
        ->and($partialBill->fresh()->remaining_amount)->toBe(470000.0)
        ->and($partialBill->fresh()->status)->toBe(StudentBill::STATUS_PARTIAL)
        ->and($paidBill->fresh()->paid_amount)->toBe(75000.0)
        ->and($paidBill->fresh()->remaining_amount)->toBe(0.0)
        ->and($paidBill->fresh()->status)->toBe(StudentBill::STATUS_PAID)
        ->and(Payment::query()->orderBy('id')->get()->toArray())->toBe($databaseState['payments'])
        ->and(PaymentDetail::query()->orderBy('id')->get()->toArray())->toBe($databaseState['details'])
        ->and(StudentBill::query()->orderBy('id')->get()->toArray())->toBe($databaseState['bills']);
});

it('renders bill histories without per-bill payment relationship queries', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $paymentType = makeBillType('SPP Query Riwayat');
    makeActiveSetting($student, $paymentType);

    foreach (range(1, 12) as $month) {
        $bill = makeMonthlyBill($student, $paymentType, 100000, $month, 2026);

        createBillbookHistoryPayment(
            $student,
            $bank,
            $user,
            'KWT-HISTORY-QUERY-'.$month,
            '2026-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-01',
            '2026-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-01 09:00:00',
            [['bill' => $bill, 'amount' => 25000]],
        );
    }

    Livewire::actingAs($user);

    $renders = [
        fn () => Livewire::test(StudentDetail::class, ['student' => $student]),
        fn () => Livewire::test(PaymentIndex::class)->call('selectStudent', $student->id),
    ];

    foreach ($renders as $render) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $render()
            ->assertSee('Lihat (1)')
            ->assertSee('KWT-HISTORY-QUERY-12');

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        $paymentDetailLazyLoads = $queries->filter(
            fn (string $query): bool => str_contains($query, 'from "payment_details"')
                && str_contains($query, '"payment_details"."bill_id" = ?')
        );
        $paymentLazyLoads = $queries->filter(
            fn (string $query): bool => str_contains($query, 'from "payments"')
                && str_contains($query, '"payments"."id" = ?')
        );

        expect($paymentDetailLazyLoads)->toHaveCount(0)
            ->and($paymentLazyLoads)->toHaveCount(0);
    }
});
