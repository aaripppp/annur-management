<?php

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\PDF as DomPdf;

it('passes the authenticated report creator to the right-most PDF signature', function (
    string $domain,
    string $view,
    string $routeName,
    array $parameters,
    string $creatorName,
) {
    $reportCreator = User::factory()->create(['name' => $creatorName]);
    $transactionCreator = User::factory()->create(['name' => 'Transaction Entry Operator']);
    $bank = Bank::query()->create([
        'name' => 'Tunai Signature '.uniqid(),
        'type' => Bank::TYPE_CASH,
        'account_number' => null,
        'account_name' => null,
        'is_active' => true,
    ]);

    if ($domain === 'student') {
        $payment = Payment::query()->create([
            'receipt_number' => 'KWT-SIGNATURE-'.uniqid(),
            'payment_kind' => Payment::KIND_MANUAL,
            'student_id' => Student::factory()->create()->id,
            'bank_id' => $bank->id,
            'payment_date' => '2026-08-27',
            'total_amount' => 123_000,
            'payment_method' => 'cash',
            'status' => Payment::STATUS_ACTIVE,
            'created_by' => $transactionCreator->id,
        ]);
        $payment->forceFill([
            'created_at' => '2026-08-27 09:00:00',
            'updated_at' => '2026-08-27 09:00:00',
        ])->saveQuietly();
        PaymentDetail::query()->create([
            'payment_id' => $payment->id,
            'description' => 'Penerimaan Signature',
            'amount' => 123_000,
        ]);
    } else {
        DaycarePayment::query()->create([
            'receipt_number' => 'KWT-DC-SIGNATURE-'.uniqid(),
            'daycare_child_id' => DaycareChild::factory()->create()->id,
            'bank_id' => $bank->id,
            'payment_date' => '2026-08-27',
            'total_amount' => 123_000,
            'created_by' => $transactionCreator->id,
        ]);
    }

    $pdf = Mockery::mock(DomPdf::class);
    $this->app->instance('dompdf.wrapper', $pdf);
    $pdf->shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $actualView, array $data) use ($view, $creatorName, $transactionCreator): bool {
            expect($actualView)->toBe($view)
                ->and($data['document']['approval']['report_creator_name'])->toBe($creatorName)
                ->and($data['document']['approval']['report_creator_name'])->not->toBe($transactionCreator->name)
                ->and($data['document']['approval']['reviewer_name'])->toBe('Windiarti, SE')
                ->and($data['report']['grand_total'])->toBe(123_000.0);

            if ($creatorName === 'Febrian') {
                expect($data['document']['approval'])->not->toContain('Arif Hamdani');
            }

            return true;
        })
        ->andReturnSelf();
    $pdf->shouldReceive('setOption')->once()->andReturnSelf();
    $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
    $pdf->shouldReceive('stream')
        ->once()
        ->andReturn(response('%PDF-mocked', 200, ['Content-Type' => 'application/pdf']));

    $this->actingAs($reportCreator)
        ->get(route($routeName, $parameters))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
})->with([
    'Student Daily - Arif Hamdani' => ['student', 'reports.school-daily-pdf', 'laporan.harian.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27'], 'Arif Hamdani'],
    'Student Daily - Febrian' => ['student', 'reports.school-daily-pdf', 'laporan.harian.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27'], 'Febrian'],
    'Student Monthly - Arif Hamdani' => ['student', 'reports.school-monthly-pdf', 'laporan.bulanan.pdf', ['month' => 8, 'year' => 2026], 'Arif Hamdani'],
    'Student Monthly - Febrian' => ['student', 'reports.school-monthly-pdf', 'laporan.bulanan.pdf', ['month' => 8, 'year' => 2026], 'Febrian'],
    'Daycare Daily - Arif Hamdani' => ['daycare', 'reports.daycare-daily-pdf', 'daycare.report.daily.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27'], 'Arif Hamdani'],
    'Daycare Daily - Febrian' => ['daycare', 'reports.daycare-daily-pdf', 'daycare.report.daily.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27'], 'Febrian'],
    'Daycare Monthly - Arif Hamdani' => ['daycare', 'reports.daycare-monthly-pdf', 'daycare.report.monthly.pdf', ['month' => 8, 'year' => 2026], 'Arif Hamdani'],
    'Daycare Monthly - Febrian' => ['daycare', 'reports.daycare-monthly-pdf', 'daycare.report.monthly.pdf', ['month' => 8, 'year' => 2026], 'Febrian'],
]);
