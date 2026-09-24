<?php

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;

function createSinglePageStudentReceipt(int $detailCount): Payment
{
    $user = User::factory()->create();
    $payment = Payment::create([
        'receipt_number' => 'KWT-ST-PAGE-'.str_pad((string) $detailCount, 2, '0', STR_PAD_LEFT),
        'student_id' => Student::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-25',
        'total_amount' => $detailCount * 100000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);
    $paymentType = PaymentType::factory()->create(['name' => 'Pembayaran Multi Item']);

    foreach (range(1, $detailCount) as $index) {
        PaymentDetail::create([
            'payment_id' => $payment->id,
            'payment_type_id' => $paymentType->id,
            'amount' => 100000,
            'description' => 'Item Student '.$index,
        ]);
    }

    return $payment;
}

function createSinglePageDaycareReceipt(int $detailCount): DaycarePayment
{
    $payment = DaycarePayment::factory()->create([
        'receipt_number' => 'KWT-DC-PAGE-'.str_pad((string) $detailCount, 2, '0', STR_PAD_LEFT),
        'daycare_child_id' => DaycareChild::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-25',
        'total_amount' => $detailCount * 100000,
    ]);

    foreach (range(1, $detailCount) as $index) {
        DaycarePaymentDetail::factory()->create([
            'daycare_payment_id' => $payment->id,
            'description' => 'Item Daycare '.$index,
            'amount' => 100000,
        ]);
    }

    return $payment;
}

function receiptPdfPageCount(string $pdf): int
{
    return preg_match_all('/\/Type\s*\/Page\b/', $pdf);
}

/** @return array{0: float, 1: float} */
function receiptPdfPageSize(string $pdf): array
{
    preg_match('/\/MediaBox\s*\[\s*(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*]/', $pdf, $matches);

    return [(float) ($matches[3] ?? 0), (float) ($matches[4] ?? 0)];
}

it('merender PDF Student satu halaman di atas kertas F4B portrait untuk setiap jumlah detail', function (int $detailCount) {
    $payment = createSinglePageStudentReceipt($detailCount);
    $receiptNumber = $payment->receipt_number;
    $total = $payment->total_amount;
    $details = $payment->details()->orderBy('id')->get(['description', 'amount'])->toArray();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    [$widthPt, $heightPt] = receiptPdfPageSize($response->getContent());

    expect(receiptPdfPageCount($response->getContent()))->toBe(1)
        ->and(abs($widthPt - 612.28))->toBeLessThan(0.01)
        ->and(abs($heightPt - 935.43))->toBeLessThan(0.01)
        ->and($payment->refresh()->receipt_number)->toBe($receiptNumber)
        ->and($payment->total_amount)->toBe($total)
        ->and($payment->details()->orderBy('id')->get(['description', 'amount'])->toArray())->toBe($details)
        ->and($payment->details()->count())->toBe($detailCount);
})->with([1, 3, 10, 25]);

it('memakai kertas F4B portrait dan bukan tinggi halaman dinamis pada PDF kwitansi', function () {
    $payment = createSinglePageStudentReceipt(1);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    [$widthPt, $heightPt] = receiptPdfPageSize($response->getContent());

    expect(receiptPdfPageCount($response->getContent()))->toBe(1)
        ->and(abs($widthPt - 612.28))->toBeLessThan(0.01)
        ->and(abs($heightPt - 935.43))->toBeLessThan(0.01)
        ->and($heightPt)->toBeGreaterThan($widthPt);
});

it('membuat PDF Daycare satu halaman untuk setiap jumlah detail', function (int $detailCount) {
    $payment = createSinglePageDaycareReceipt($detailCount);
    $receiptNumber = $payment->receipt_number;
    $total = $payment->total_amount;
    $details = $payment->details()->orderBy('id')->get(['description', 'amount'])->toArray();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('daycare.payment.print', $payment));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect(receiptPdfPageCount($response->getContent()))->toBe(1)
        ->and($payment->refresh()->receipt_number)->toBe($receiptNumber)
        ->and($payment->total_amount)->toBe($total)
        ->and($payment->details()->orderBy('id')->get(['description', 'amount'])->toArray())->toBe($details)
        ->and($payment->details()->count())->toBe($detailCount);
})->with([1, 3, 10, 25]);

it('menyertakan stempel otorisasi yang sama dengan Student pada PDF Daycare', function () {
    $payment = createSinglePageDaycareReceipt(1);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('daycare.payment.print', $payment));

    expect($response->getContent())->toStartWith('%PDF-');
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect(substr_count($response->getContent(), '/Subtype /Image'))->toBeGreaterThanOrEqual(2);
});
