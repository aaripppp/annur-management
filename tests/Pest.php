<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\ClassPromotionRule;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function makeBillType(string $name, bool $auto = false, bool $required = false): PaymentType
{
    return PaymentType::factory()->create([
        'name' => $name,
        'is_active' => true,
        'is_auto_enrolled' => $auto,
        'is_required' => $required,
    ]);
}

function makeBillRate(PaymentType $type, int $level, int $amount, array $overrides = []): PaymentRate
{
    $rate = PaymentRate::factory()->create(array_merge([
        'payment_type_id' => $type->id,
        'class_level' => $level,
        'amount' => $amount,
        'is_monthly' => true,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ], $overrides));

    if (in_array($type->name, ['Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'], true)) {
        $schoolLevel = SchoolLevel::fromClassLevel($level);

        if ($schoolLevel) {
            PaymentTypeSchoolLevel::firstOrCreate(
                ['payment_type_id' => $type->id, 'school_level' => $schoolLevel],
                ['is_required' => false, 'is_active' => true]
            );
        }
    }

    return $rate;
}

function makeBillStudent(int $level = 8): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);

    return Student::factory()->create(['class_id' => $class->id]);
}

/**
 * Buat siswa dengan riwayat enrollment pada sebuah jenjang.
 *
 * Tahun ajaran dibuat ulang bila belum ada (unique year); siswa dibuat dengan
 * kelas saat ini sesuai jenjang serta satu baris StudentAcademicEnrollment.
 *
 * @return array{0: Student, 1: SchoolClass, 2: AcademicYear}
 */
function makeEnrolledStudent(
    SchoolLevel $level,
    string $year = '2026/2027',
    string $startDate = '2026-07-01',
    string $endDate = '2027-06-30',
): array {
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => $year],
        ['is_active' => true, 'start_date' => $startDate, 'end_date' => $endDate]
    );

    $classLevel = match ($level) {
        SchoolLevel::TK => -2,
        SchoolLevel::SD => 5,
        SchoolLevel::SMP => 8,
        SchoolLevel::SMA => 11,
    };

    $schoolClass = SchoolClass::factory()->create(['level' => $classLevel]);
    $student = Student::factory()->create(['class_id' => $schoolClass->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return [$student, $schoolClass, $academicYear];
}

/**
 * Buat satu aturan kenaikan kelas secara eksplisit.
 *
 * Tabel class_promotion_rules adalah satu-satunya sumber kebenaran saat
 * runtime. Test tidak boleh bergantung pada resolver default; aturan selalu
 * dinyatakan eksplisit di fixture.
 */
function createPromotionRule(SchoolClass $source, string $action, ?SchoolClass $target = null, bool $isActive = true): ClassPromotionRule
{
    return ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => $action,
        'target_class_id' => $action === 'promote' ? $target?->id : null,
        'is_active' => $isActive,
    ]);
}

/**
 * Mirror the real production school_class roster. The live database currently
 * holds exactly 74 rows: 15 TK rombels, 5 KB + 5 TK A + 5 TK B, 16 SD subgroup
 * rombels (1-2), 20 SD numeric rombels (3-6), 15 SMP rombels (7-9), and 8 SMA
 * rombels (10-12). With an explicit rule per class this resolves to 55
 * promotions and 19 graduations, with nothing blocked.
 */
function promotionRuleRosterDefinitions(): array
{
    $definitions = [];

    foreach (range(1, 5) as $i) {
        $definitions[] = ['name' => "KB-$i", 'level' => -3];
        $definitions[] = ['name' => "A-$i", 'level' => -2];
        $definitions[] = ['name' => "B-$i", 'level' => -1];
    }

    foreach (['A', 'B', 'C', 'D'] as $rombel) {
        foreach ([1, 2] as $subgroup) {
            $definitions[] = ['name' => "1$rombel-$subgroup", 'level' => 1];
            $definitions[] = ['name' => "2$rombel-$subgroup", 'level' => 2];
        }
    }

    foreach ([3, 4, 5, 6, 7, 8, 9] as $level) {
        foreach (['A', 'B', 'C', 'D', 'E'] as $rombel) {
            $definitions[] = ['name' => "$level$rombel", 'level' => $level];
        }
    }

    foreach (['A', 'B'] as $rombel) {
        $definitions[] = ['name' => "10$rombel", 'level' => 10];
        $definitions[] = ['name' => "11$rombel", 'level' => 11];
    }

    foreach (['12A-IPA', '12A-IPS', '12B-IPA', '12B-IPS'] as $name) {
        $definitions[] = ['name' => $name, 'level' => 12];
    }

    return $definitions;
}

/**
 * Mapping eksplisit nama kelas sumber → [aksi, nama kelas tujuan|null].
 *
 * Graduation levels (TKB/-1, 6, 9, 12) menerima aturan 'graduate'. Semua kelas
 * lain menerima aturan 'promote' ke kelas level+1 yang sesuai. Kelas subgroup
 * (1/2A-D-1/2) naik ke subgroup yang sama; subgroup level 2 lalu naik ke kelas
 * numerik berhuruf sama (mis. 2A-1 → 3A). Konfigurasi meniru data produksi.
 */
function promotionRuleRosterCouplings(): array
{
    $couplings = [];

    foreach (range(1, 5) as $i) {
        $couplings["KB-$i"] = ['promote', "A-$i"];
        $couplings["A-$i"] = ['promote', "B-$i"];
        $couplings["B-$i"] = ['graduate', null];
    }

    foreach (['A', 'B', 'C', 'D'] as $rombel) {
        foreach ([1, 2] as $subgroup) {
            $couplings["1$rombel-$subgroup"] = ['promote', "2$rombel-$subgroup"];
            $couplings["2$rombel-$subgroup"] = ['promote', "3$rombel"];
        }
    }

    foreach (['A', 'B', 'C', 'D', 'E'] as $rombel) {
        $couplings["3$rombel"] = ['promote', "4$rombel"];
        $couplings["4$rombel"] = ['promote', "5$rombel"];
        $couplings["5$rombel"] = ['promote', "6$rombel"];
        $couplings["6$rombel"] = ['graduate', null];
        $couplings["7$rombel"] = ['promote', "8$rombel"];
        $couplings["8$rombel"] = ['promote', "9$rombel"];
        $couplings["9$rombel"] = ['graduate', null];
    }

    foreach (['A', 'B'] as $rombel) {
        $couplings["10$rombel"] = ['promote', "11$rombel"];
        $couplings["11$rombel"] = ['promote', "12$rombel-IPA"];
    }

    foreach (['12A-IPA', '12A-IPS', '12B-IPA', '12B-IPS'] as $name) {
        $couplings[$name] = ['graduate', null];
    }

    return $couplings;
}

function createPromotionRuleRoster(): Collection
{
    return collect(promotionRuleRosterDefinitions())
        ->map(fn (array $definition): SchoolClass => SchoolClass::create($definition))
        ->keyBy('name');
}

/**
 * Materialisasi aturan eksplisit untuk seluruh kelas roster sesuai coupling map.
 */
function createPromotionRosterRules(Collection $roster): void
{
    foreach (promotionRuleRosterCouplings() as $sourceName => [$action, $targetName]) {
        createPromotionRule($roster[$sourceName], $action, $targetName === null ? null : $roster[$targetName]);
    }
}

function makeMonthlyBill(Student $student, PaymentType $type, int $amount, ?int $month = 8, ?int $year = 2026): StudentBill
{
    return StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'period_month' => $month,
        'period_year' => $year,
        'billing_frequency' => 'monthly',
        'due_date' => null,
    ]);
}

/** @param list<array<string, mixed>> $details */
function createSchoolMonthlyReportPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $date,
    array $details,
    string $kind = Payment::KIND_BILL,
    string $status = Payment::STATUS_ACTIVE,
    ?float $headerTotal = null,
    ?string $paymentDate = null,
    ?string $recordedAt = null,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-BLN-'.uniqid(),
        'payment_kind' => $kind,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate ?? $date,
        'total_amount' => $headerTotal ?? array_sum(array_column($details, 'amount')),
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'description' => 'Fixture laporan bulanan',
        'status' => $status,
        'created_by' => $user->id,
    ]);
    $recordedTimestamp = $recordedAt ?? $date.' 09:00:00';
    $payment->forceFill(['created_at' => $recordedTimestamp, 'updated_at' => $recordedTimestamp])->saveQuietly();

    foreach ($details as $detail) {
        PaymentDetail::query()->create(array_merge($detail, ['payment_id' => $payment->id]));
    }

    return $payment;
}

/**
 * @param  list<array<string, mixed>>  $details
 */
function createSchoolDailyReportPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $date,
    array $details,
    string $kind = Payment::KIND_BILL,
    string $status = Payment::STATUS_ACTIVE,
    ?float $headerTotal = null,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-RPT-'.uniqid(),
        'payment_kind' => $kind,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $headerTotal ?? array_sum(array_column($details, 'amount')),
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'description' => 'Fixture laporan harian',
        'status' => $status,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $date.' 09:00:00', 'updated_at' => $date.' 09:00:00'])->saveQuietly();

    foreach ($details as $detail) {
        PaymentDetail::query()->create(array_merge($detail, ['payment_id' => $payment->id]));
    }

    return $payment;
}

function createBankRecapPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $paymentDate,
    string $recordedAt,
    int $amount,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-BANK-'.uniqid(),
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $payment->details()->create([
        'description' => 'Pembayaran pengujian rekap bank',
        'amount' => $amount,
    ]);
    $payment->forceFill(['created_at' => $recordedAt, 'updated_at' => $recordedAt])->saveQuietly();

    return $payment->refresh();
}

function makeYearlyBill(Student $student, PaymentType $type, int $amount, string $academicYear = '2026/2027'): StudentBill
{
    return StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'academic_year' => $academicYear,
        'billing_frequency' => 'yearly',
        'due_date' => null,
    ]);
}

function makeOneTimeBill(Student $student, PaymentType $type, int $amount, string $academicYear = '2026/2027'): StudentBill
{
    return StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'academic_year' => $academicYear,
        'billing_frequency' => 'one_time',
        'due_date' => null,
    ]);
}

function makeActiveSetting(Student $student, PaymentType $type, bool $active = true): StudentPaymentSetting
{
    return StudentPaymentSetting::updateOrCreate(
        [
            'student_id' => $student->id,
            'payment_type_id' => $type->id,
        ],
        [
            'is_active' => $active,
            'started_at' => '2026-01-01',
        ]
    );
}

function makeLevelDefault(
    PaymentType $type,
    SchoolLevel $level,
    bool $required = true,
    bool $active = true
): PaymentTypeSchoolLevel {
    return PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => $level,
        'is_required' => $required,
        'is_active' => $active,
    ]);
}

/**
 * Memastikan nilai 3 summary card. String 'tracking-wider">Rp X</p>' hanya
 * dimiliki oleh nilai card (bukan sel tabel), dan varian text-error unik untuk
 * card Total Tunggakan.
 */
function assertSummaryCards($component, int $tagihan, int $dibayar, int $tunggakan): void
{
    $component
        ->assertSeeHtml('tracking-wider">Rp '.number_format($tagihan, 0, ',', '.').'</p>')
        ->assertSeeHtml('tracking-wider">Rp '.number_format($dibayar, 0, ',', '.').'</p>')
        ->assertSeeHtml('class="text-headline-md font-headline-md text-error mt-1 font-numeric-data tracking-wider">Rp '.number_format($tunggakan, 0, ',', '.').'</p>');
}

function payActiveBill(StudentBill $bill, int $amount): void
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-'.uniqid(),
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'amount' => $amount,
    ]);
}

/**
 * Katalog lengkap jenis pembayaran manual (tanpa pengaturan pembayaran
 * siswa). Semua jenis dibuat aktif di master payment_types.
 *
 * @return Collection<string, PaymentType>
 */
function manualAddCatalog(int $level = 8): Collection
{
    $definitions = [
        ['name' => 'SPP', 'amount' => 970000],
        ['name' => 'Ekskul', 'amount' => 52000],
        ['name' => 'OSIS', 'amount' => 5000],
        ['name' => 'Jemputan', 'amount' => 550000],
        ['name' => 'Uang Buku', 'amount' => 500000, 'frequency' => BillFrequency::Yearly],
        ['name' => 'Uang Kegiatan', 'amount' => 100000, 'frequency' => BillFrequency::Yearly],
        ['name' => 'Uang Pangkal', 'amount' => 5000000, 'frequency' => BillFrequency::OneTime],
        ['name' => 'Lain-lain', 'amount' => 100000, 'frequency' => BillFrequency::OneTime],
    ];

    $types = collect();

    foreach ($definitions as $definition) {
        $type = PaymentType::create([
            'name' => $definition['name'],
            'is_active' => true,
            'is_auto_enrolled' => false,
            'is_required' => false,
        ]);

        makeBillRate(
            $type,
            $level,
            $definition['amount'],
            isset($definition['frequency']) ? ['billing_frequency' => $definition['frequency']] : []
        );

        $types->put($definition['name'], $type);
    }

    return $types;
}
