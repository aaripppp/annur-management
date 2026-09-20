<?php

use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\SchoolMonthlyByLevelReportService;
use App\Services\SchoolMonthlyReportService;
use Livewire\Livewire;

it('groups canonical monthly receipts by historical jenjang bank type and dynamic payment type', function () {
    $user = User::factory()->create();
    [$sdStudent, , $academicYear] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$smaStudent] = makeEnrolledStudent(SchoolLevel::SMA);
    $currentSmaClass = SchoolClass::factory()->create(['level' => 11]);
    $sdStudent->update(['class_id' => $currentSmaClass->id]);

    $cashA = Bank::factory()->cash()->create(['name' => 'Loket A']);
    $cashB = Bank::query()->create(['name' => 'Loket B', 'type' => Bank::TYPE_CASH, 'is_active' => true]);
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);
    $bankNamedCash = Bank::factory()->create(['name' => 'Tunai', 'account_number' => '333333']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI', 'account_number' => '444444']);
    $spp = makeBillType('SPP Per Jenjang');
    $yearlyReceipt = makeBillType('Uang Buku Per Jenjang');
    $oneTimeReceipt = makeBillType('Uang Pangkal Per Jenjang');

    createSchoolMonthlyReportPayment($sdStudent, $bankA, $user, '2026-09-03', [
        ['payment_type_id' => $spp->id, 'amount' => 2_000_000],
        ['payment_type_id' => $yearlyReceipt->id, 'amount' => 300_000],
    ], paymentDate: '2026-08-31', recordedAt: '2026-09-03 08:00:00');
    createSchoolMonthlyReportPayment($smpStudent, $bankNamedCash, $user, '2026-09-04', [
        ['payment_type_id' => $oneTimeReceipt->id, 'amount' => 700_000],
    ]);
    createSchoolMonthlyReportPayment($sdStudent, $cashA, $user, '2026-09-05', [
        ['payment_type_id' => $spp->id, 'amount' => 500_000],
    ]);
    createSchoolMonthlyReportPayment($sdStudent, $cashB, $user, '2026-09-05', [
        ['payment_type_id' => $yearlyReceipt->id, 'amount' => 50_000],
    ]);
    createSchoolMonthlyReportPayment($smaStudent, $cashA, $user, '2026-09-06', [
        ['payment_type_id' => $oneTimeReceipt->id, 'amount' => 200_000],
    ]);
    createSchoolMonthlyReportPayment($smpStudent, $bankA, $user, '2026-09-07', [
        ['payment_type_id' => $spp->id, 'amount' => 900_000],
    ], status: Payment::STATUS_CANCELLED);

    $daycare = DaycarePayment::factory()->create([
        'bank_id' => $cashA->id,
        'payment_date' => '2026-09-08',
        'total_amount' => 1_000_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycare->id,
        'description' => 'Daycare September',
        'amount' => 1_000_000,
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $monthly = app(SchoolMonthlyReportService::class)->generate(2026, 9);
    $categories = collect($report['categories'])->pluck('key', 'name');
    $bankLevels = collect($report['bank']['levels'])->keyBy('level_key');
    $cashLevels = collect($report['cash']['levels'])->keyBy('level_key');
    $sdBanks = collect($bankLevels[SchoolLevel::SD->value]['banks']);
    $sppKey = $categories['SPP Per Jenjang'];
    $bookKey = $categories['Uang Buku Per Jenjang'];
    $entryKey = $categories['Uang Pangkal Per Jenjang'];

    expect($report['month_label'])->toBe('September 2026')
        ->and($report['categories'])->toHaveCount(4)
        ->and($bankLevels->keys()->all())->toBe([SchoolLevel::SD->value, SchoolLevel::SMP->value])
        ->and($cashLevels->keys()->all())->toBe([SchoolLevel::SD->value, SchoolLevel::SMA->value])
        ->and($bankLevels)->not->toHaveKey(SchoolLevel::TK->value)
        ->and($cashLevels)->not->toHaveKey(SchoolLevel::SMP->value)
        ->and($sdBanks)->toHaveCount(4)
        ->and($bankLevels[SchoolLevel::SD->value]['bank_count'])->toBe(4)
        ->and($sdBanks->pluck('bank_id')->all())->toContain($bankA->id, $bankB->id, $bankNamedCash->id, $zeroBank->id)
        ->and($sdBanks->firstWhere('bank_id', $bankA->id)['amounts'][$sppKey])->toBe(2_000_000.0)
        ->and($sdBanks->firstWhere('bank_id', $bankA->id)['amounts'][$bookKey])->toBe(300_000.0)
        ->and($sdBanks->firstWhere('bank_id', $bankB->id)['total'])->toBe(0.0)
        ->and($sdBanks->firstWhere('bank_id', $zeroBank->id)['amounts'][$sppKey])->toBe(0.0)
        ->and($bankLevels[SchoolLevel::SD->value]['bank_total'])->toBe(2_300_000.0)
        ->and($bankLevels[SchoolLevel::SMP->value]['bank_total'])->toBe(700_000.0)
        ->and(collect($bankLevels[SchoolLevel::SMP->value]['banks'])->firstWhere('bank_id', $bankNamedCash->id)['amounts'][$entryKey])->toBe(700_000.0)
        ->and($cashLevels[SchoolLevel::SD->value]['cash_amounts'][$sppKey])->toBe(500_000.0)
        ->and($cashLevels[SchoolLevel::SD->value]['cash_amounts'][$bookKey])->toBe(50_000.0)
        ->and($cashLevels[SchoolLevel::SD->value]['cash_total'])->toBe(550_000.0)
        ->and($cashLevels[SchoolLevel::SMA->value]['cash_total'])->toBe(200_000.0)
        ->and($report['bank']['total'])->toBe(3_000_000.0)
        ->and($report['cash']['total'])->toBe(750_000.0)
        ->and($report['grand_total'])->toBe(3_750_000.0)
        ->and($report['bank']['total'] + $report['cash']['total'])->toBe($report['grand_total'])
        ->and($report['grand_total'])->toBe($monthly['grand_total'])
        ->and($report['detail_count'])->toBe(6)
        ->and($academicYear->year)->toBe('2026/2027');

    foreach ($report['bank']['levels'] as $level) {
        expect(collect($level['banks'])->sum('total'))->toBe($level['bank_total']);
    }

    foreach ($report['cash']['levels'] as $level) {
        expect(array_sum($level['cash_amounts']))->toBe($level['cash_total']);
    }
});

it('renders the level tab with dynamic bank rows blank zeros and export links', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SD);
    $bank = Bank::factory()->create(['name' => 'BCA UI Jenjang', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI UI Jenjang', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket UI Jenjang']);
    $type = makeBillType('SPP UI Per Jenjang');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);

    $this->actingAs($user);

    $component = Livewire::test(SchoolDailyReport::class, [
        'activeTab' => 'monthly',
        'monthlyMode' => SchoolDailyReport::MONTHLY_MODE_BY_LEVEL,
        'reportMonth' => 9,
        'reportYear' => 2026,
    ])
        ->assertSee('Per Jenjang')
        ->assertSee('PENERIMAAN BANK')
        ->assertSee('PENERIMAAN TUNAI')
        ->assertSee('RINGKASAN TOTAL')
        ->assertSee(SchoolLevel::SD->value)
        ->assertSee($bank->optionLabel())
        ->assertSee($zeroBank->optionLabel())
        ->assertDontSee($cash->name)
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 100.000')
        ->assertSee('Rp 400.000')
        ->assertSee('laporan/per-jenjang.xlsx')
        ->assertSee('laporan/per-jenjang.pdf')
        ->assertSeeHtml('rowspan="2"');

    expect($component->html())
        ->not->toContain('Rp 0')
        ->toContain('<caption class="sr-only">Penerimaan Bank per Jenjang</caption>')
        ->toContain('<caption class="sr-only">Penerimaan Tunai per Jenjang</caption>')
        ->toContain('rowspan="2" class="px-3 py-2 text-center align-middle font-bold text-on-surface sm:px-4"')
        ->toContain('class="px-3 py-2 text-center align-middle font-bold text-on-surface sm:px-4">SD</td>');
});

it('keeps payments without a valid class level in an explicit unclassified bucket', function () {
    $user = User::factory()->create();
    $invalidClass = SchoolClass::factory()->create(['level' => 99]);
    $student = Student::factory()->create(['class_id' => $invalidClass->id]);
    $bank = Bank::factory()->create();
    $type = makeBillType('SPP Tanpa Level Valid');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-09-09', [
        ['payment_type_id' => $type->id, 'amount' => 125_000],
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $monthly = app(SchoolMonthlyReportService::class)->generate(2026, 9);
    $level = $report['bank']['levels'][0];

    expect($report['bank']['levels'])->toHaveCount(1)
        ->and($level['level_key'])->toBe('unclassified')
        ->and($level['level_label'])->toBe('Tidak Terklasifikasi')
        ->and($level['bank_total'])->toBe(125_000.0)
        ->and($report['grand_total'])->toBe(125_000.0)
        ->and($report['grand_total'])->toBe($monthly['grand_total']);
});

it('classifies candidate calon students by current class level on bank and cash rows', function () {
    $user = User::factory()->create();
    $smaClass = SchoolClass::factory()->create(['name' => 'X-A Calon', 'level' => 10]);
    $candidate = Student::factory()->create(['class_id' => $smaClass->id]);
    $bank = Bank::factory()->create(['name' => 'BSI Calon', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Calon']);
    $type = makeBillType('SPP Calon Siswa');

    createSchoolMonthlyReportPayment($candidate, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 1_200_000],
    ]);
    createSchoolMonthlyReportPayment($candidate, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 400_000],
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $bankLevels = collect($report['bank']['levels'])->keyBy('level_key');
    $cashLevels = collect($report['cash']['levels'])->keyBy('level_key');

    expect($report['grand_total'])->toBe(1_600_000.0)
        ->and($bankLevels->keys()->all())->toBe([SchoolLevel::SMA->value])
        ->and($cashLevels->keys()->all())->toBe([SchoolLevel::SMA->value])
        ->and($bankLevels)->not->toHaveKey('unclassified')
        ->and($cashLevels)->not->toHaveKey('unclassified')
        ->and($bankLevels[SchoolLevel::SMA->value]['bank_total'])->toBe(1_200_000.0)
        ->and($cashLevels[SchoolLevel::SMA->value]['cash_total'])->toBe(400_000.0);
});

it('classifies candidate SMP and SD students by their class level', function () {
    $user = User::factory()->create();
    $smpCandidate = Student::factory()->create(['class_id' => SchoolClass::factory()->create(['level' => 7])->id]);
    $sdCandidate = Student::factory()->create(['class_id' => SchoolClass::factory()->create(['level' => 4])->id]);
    $bank = Bank::factory()->create(['name' => 'BSI Jenjang Calon', 'account_number' => '111111']);
    $type = makeBillType('SPP Per Jenjang Calon');

    createSchoolMonthlyReportPayment($smpCandidate, $bank, $user, '2026-09-05', [
        ['payment_type_id' => $type->id, 'amount' => 900_000],
    ]);
    createSchoolMonthlyReportPayment($sdCandidate, $bank, $user, '2026-09-06', [
        ['payment_type_id' => $type->id, 'amount' => 600_000],
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $bankLevels = collect($report['bank']['levels'])->keyBy('level_key');

    expect(collect($report['bank']['levels'])->pluck('level_key'))->not->toContain('unclassified')
        ->and($bankLevels->keys()->all())->toBe([SchoolLevel::SD->value, SchoolLevel::SMP->value])
        ->and($bankLevels[SchoolLevel::SD->value]['bank_total'])->toBe(600_000.0)
        ->and($bankLevels[SchoolLevel::SMP->value]['bank_total'])->toBe(900_000.0);
});

it('keeps historical enrollment classification ahead of the current class fallback', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2025/2026', '2025-07-01', '2026-06-30');
    $smaClass = SchoolClass::factory()->create(['level' => 11]);
    $student->update(['class_id' => $smaClass->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => AcademicYear::firstOrCreate(
            ['year' => '2026/2027'],
            ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
        )->id,
        'school_class_id' => $smaClass->id,
        'status' => 'active',
    ]);
    $bank = Bank::factory()->create(['name' => 'BSI Promosi', 'account_number' => '111111']);
    $type = makeBillType('SPP Promosi');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-06-10', [
        ['payment_type_id' => $type->id, 'amount' => 800_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-08-10', [
        ['payment_type_id' => $type->id, 'amount' => 700_000],
    ]);

    $june = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 6);
    $aug = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 8);

    expect(collect($june['bank']['levels'])->keyBy('level_key')->keys()->all())->toBe([SchoolLevel::SMP->value])
        ->and($june['bank']['levels'][0]['bank_total'])->toBe(800_000.0)
        ->and(collect($aug['bank']['levels'])->keyBy('level_key')->keys()->all())->toBe([SchoolLevel::SMA->value])
        ->and($aug['bank']['levels'][0]['bank_total'])->toBe(700_000.0);
});

it('renders candidate calon payments under the correct level tab in HTML', function () {
    $user = User::factory()->create();
    $candidate = Student::factory()->create([
        'class_id' => SchoolClass::factory()->create(['name' => 'X-A HTML', 'level' => 10])->id,
    ]);
    $bank = Bank::factory()->create(['name' => 'BCA HTML Jenjang', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket HTML Jenjang']);
    $type = makeBillType('SPP HTML Per Jenjang');

    createSchoolMonthlyReportPayment($candidate, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 350_000],
    ]);
    createSchoolMonthlyReportPayment($candidate, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 120_000],
    ]);

    $this->actingAs($user);

    Livewire::test(SchoolDailyReport::class, [
        'activeTab' => 'monthly',
        'monthlyMode' => SchoolDailyReport::MONTHLY_MODE_BY_LEVEL,
        'reportMonth' => 9,
        'reportYear' => 2026,
    ])
        ->assertSee(SchoolLevel::SMA->value)
        ->assertSee('Rp 350.000')
        ->assertSee('Rp 120.000')
        ->assertSee('Rp 470.000')
        ->assertDontSee('Tidak Terklasifikasi')
        ->assertSeeHtml('>SMA</td>');
});
