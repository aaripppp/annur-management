<?php

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolReportDocument;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\DB;

it('resolves active report approvers once with exact positions and lowest ids', function () {
    User::factory()->create([
        'name' => 'Direktur Nonaktif',
        'position' => User::POSITION_DIRECTOR,
        'is_active' => false,
    ]);
    $director = User::factory()->create([
        'name' => 'Direktur Utama',
        'position' => User::POSITION_DIRECTOR,
        'is_active' => true,
    ]);
    User::factory()->create([
        'name' => 'Direktur Duplikat',
        'position' => User::POSITION_DIRECTOR,
        'is_active' => true,
    ]);
    User::factory()->create([
        'name' => 'Kepala Lama',
        'position' => 'Kepala Tata Usaha',
        'is_active' => true,
    ]);
    $reviewer = User::factory()->create([
        'name' => 'Kepala TU Utama',
        'position' => User::POSITION_HEAD_TU_FOUNDATION,
        'is_active' => true,
    ]);
    User::factory()->create([
        'name' => 'Kepala TU Duplikat',
        'position' => User::POSITION_HEAD_TU_FOUNDATION,
        'is_active' => true,
    ]);
    $creator = User::factory()->create([
        'name' => 'Pembuat Laporan',
        'position' => 'Bendahara',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $approval = SchoolReportDocument::approvalFor($creator, 'Bekasi, 01 Oktober 2026');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($approval)->toMatchArray([
            'approver_title' => User::POSITION_DIRECTOR,
            'approver_name' => $director->name,
            'reviewer_title' => User::POSITION_HEAD_TU_FOUNDATION,
            'reviewer_name' => $reviewer->name,
            'report_creator_title' => 'Bendahara',
            'report_creator_name' => 'Pembuat Laporan',
            'city_and_date' => 'Bekasi, 01 Oktober 2026',
        ]);
});

it('uses centralized report approval and creator fallbacks', function () {
    $creator = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'position' => null,
    ]);

    $approval = SchoolReportDocument::approvalFor($creator, 'Bekasi, 01 Oktober 2026');
    $unnamedCreatorApproval = SchoolReportDocument::approvalFor(
        new User(['name' => '', 'position' => null]),
        'Bekasi, 01 Oktober 2026'
    );
    $missingCreatorApproval = SchoolReportDocument::approvalFor(null, 'Bekasi, 01 Oktober 2026');

    expect($approval)->toMatchArray([
        'approver_title' => 'Direktur Keuangan',
        'approver_name' => 'Nova Rabi\'ah Nurrohmah, SE, MM',
        'reviewer_title' => 'Kepala TU Yayasan',
        'reviewer_name' => 'Windiarti, SE',
        'report_creator_title' => 'Super Admin',
        'report_creator_name' => $creator->name,
    ])->and($unnamedCreatorApproval)->toMatchArray([
        'report_creator_title' => 'Admin',
        'report_creator_name' => 'Administrator',
    ])->and($missingCreatorApproval)->toMatchArray([
        'report_creator_title' => 'Administrator',
        'report_creator_name' => 'Administrator',
    ]);
});

it('passes the authenticated report creator to the right-most PDF signature', function (
    string $domain,
    string $view,
    string $routeName,
    array $parameters,
    string $creatorName,
) {
    $reportCreator = User::factory()->create([
        'name' => $creatorName,
        'position' => 'TU SD IT An-Nur',
    ]);
    $director = User::factory()->create([
        'name' => 'Direktur Dinamis',
        'position' => User::POSITION_DIRECTOR,
        'is_active' => true,
    ]);
    $reviewer = User::factory()->create([
        'name' => 'Kepala TU Dinamis',
        'position' => User::POSITION_HEAD_TU_FOUNDATION,
        'is_active' => true,
    ]);
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
        ->withArgs(function (string $actualView, array $data) use ($view, $creatorName, $transactionCreator, $director, $reviewer): bool {
            expect($actualView)->toBe($view)
                ->and($data['document']['approval']['report_creator_name'])->toBe($creatorName)
                ->and($data['document']['approval']['report_creator_title'])->toBe('TU SD IT An-Nur')
                ->and($data['document']['approval']['report_creator_name'])->not->toBe($transactionCreator->name)
                ->and($data['document']['approval']['approver_name'])->toBe($director->name)
                ->and($data['document']['approval']['reviewer_title'])->toBe('Kepala TU Yayasan')
                ->and($data['document']['approval']['reviewer_name'])->toBe($reviewer->name)
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

it('passes centralized signatures to each additional signed report', function (string $view, string $routeName, array $parameters) {
    $creator = User::factory()->create([
        'name' => 'Pembuat Dinamis',
        'position' => 'TU SD IT An-Nur',
    ]);
    $director = User::factory()->create([
        'name' => 'Direktur Dinamis',
        'position' => User::POSITION_DIRECTOR,
        'is_active' => true,
    ]);
    $reviewer = User::factory()->create([
        'name' => 'Kepala TU Dinamis',
        'position' => User::POSITION_HEAD_TU_FOUNDATION,
        'is_active' => true,
    ]);

    $pdf = Mockery::mock(DomPdf::class);
    $this->app->instance('dompdf.wrapper', $pdf);
    $pdf->shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $actualView, array $data) use ($view, $creator, $director, $reviewer): bool {
            expect($actualView)->toBe($view)
                ->and($data['document']['approval'])->toMatchArray([
                    'approver_title' => User::POSITION_DIRECTOR,
                    'approver_name' => $director->name,
                    'reviewer_title' => User::POSITION_HEAD_TU_FOUNDATION,
                    'reviewer_name' => $reviewer->name,
                    'report_creator_title' => $creator->position,
                    'report_creator_name' => $creator->name,
                ]);

            return true;
        })
        ->andReturnSelf();
    $pdf->shouldReceive('setOption')->once()->andReturnSelf();
    $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
    $pdf->shouldReceive('stream')
        ->once()
        ->andReturn(response('%PDF-mocked', 200, ['Content-Type' => 'application/pdf']));

    $this->actingAs($creator)
        ->get(route($routeName, $parameters))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
})->with([
    'monthly by level' => ['reports.school-monthly-by-level-pdf', 'laporan.jenjang.pdf', ['month' => 8, 'year' => 2026]],
    'monthly all units' => ['reports.school-monthly-all-units-pdf', 'laporan.seluruh-unit.pdf', ['month' => 8, 'year' => 2026]],
    'target and arrears' => ['reports.student-target-arrears-pdf', 'laporan.target.pdf', ['mode' => 'monthly', 'month' => 8, 'year' => 2026]],
    'bank recap' => ['reports.school-bank-recap-pdf', 'laporan.bank.pdf', ['start_date' => '2026-08-01', 'end_date' => '2026-08-31']],
]);
