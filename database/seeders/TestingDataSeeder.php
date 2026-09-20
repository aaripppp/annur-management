<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentCreationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TestingDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'TestingDataSeeder tidak boleh dijalankan di production.'
            );
        }

        $this->captureMasterState();

        $this->resetOperationalData();

        $this->ensureAcademicYearState();

        $this->createTestStudents();

        $this->verifyMasterIntegrity();

        $this->printSummary();
    }

    private array $ratesBefore = [];

    private array $typesBefore = [];

    private function captureMasterState(): void
    {
        $this->ratesBefore = PaymentRate::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->toArray();

        $this->typesBefore = PaymentType::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => $t->toArray())
            ->toArray();
    }

    private function resetOperationalData(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        DB::table('payment_correction_logs')->delete();
        DB::table('payment_details')->delete();
        DB::table('payments')->delete();
        DB::table('bill_adjustments')->delete();
        DB::table('student_bills')->delete();
        DB::table('student_payment_settings')->delete();
        DB::table('student_academic_enrollments')->delete();
        DB::table('students')->delete();

        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function ensureAcademicYearState(): void
    {
        AcademicYear::query()
            ->where('year', '2026/2027')
            ->update(['is_active' => true]);

        AcademicYear::query()
            ->where('year', '2027/2028')
            ->update([
                'is_active' => false,
                'promotion_processed_at' => null,
            ]);
    }

    private function createTestStudents(): void
    {
        $service = app(StudentCreationService::class);

        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        try {
            foreach (SchoolClass::query()->orderBy('id')->get() as $class) {

                $service->create([
                    'nis' => 'TEST-'.$class->id.'-01',
                    'nama_lengkap' => 'TEST '.$class->name.' 01',
                    'nama_panggilan' => 'Test01',
                    'class_id' => $class->id,
                    'jenis_kelamin' => 'L',
                    'alamat' => 'Jl. Testing No. 1',
                ]);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    private function verifyMasterIntegrity(): void
    {
        $ratesAfter = PaymentRate::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->toArray();

        if ($this->ratesBefore !== $ratesAfter) {
            throw new RuntimeException(
                'PaymentRate data berubah setelah seeder! Tariff tidak boleh diubah.'
            );
        }

        $typesAfter = PaymentType::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => $t->toArray())
            ->toArray();

        if ($this->typesBefore !== $typesAfter) {
            throw new RuntimeException(
                'PaymentType data berubah setelah seeder!'
            );
        }

        $expectedStudents = SchoolClass::count();
        $actualStudents = Student::count();

        if ($actualStudents !== $expectedStudents) {
            throw new RuntimeException(
                "Expected {$expectedStudents} students, got {$actualStudents}."
            );
        }
    }

    private function printSummary(): void
    {
        $out = $this->command;

        if (! $out) {
            return;
        }

        $classCount = SchoolClass::count();
        $typeCount = PaymentType::count();
        $rateCount = PaymentRate::count();
        $bankCount = Bank::count();

        $studentCount = Student::count();
        $enrollmentCount = DB::table('student_academic_enrollments')->count();
        $billCount = DB::table('student_bills')->count();
        $paymentCount = DB::table('payments')->count();
        $detailCount = DB::table('payment_details')->count();

        $activeYear = AcademicYear::where('is_active', true)->first();
        $targetYear = AcademicYear::where('year', '2027/2028')->first();

        $out->newLine();
        $out->line('========================================');
        $out->line('ANNUR TESTING DATA READY');
        $out->line('========================================');
        $out->newLine();
        $out->line('Master data preserved:');
        $out->line(sprintf('  School Classes     : %d', $classCount));
        $out->line(sprintf('  Payment Types      : %d', $typeCount));
        $out->line(sprintf('  Payment Rates      : %d', $rateCount));
        $out->line(sprintf('  Banks              : %d', $bankCount));
        $out->newLine();
        $out->line('Tariff verification:');
        $out->line('  UNCHANGED');
        $out->newLine();
        $out->line('Academic Years:');
        $out->line(sprintf('  2026/2027          : %s', $activeYear?->is_active ? 'ACTIVE' : 'INACTIVE'));
        $out->line(sprintf('  2027/2028          : %s', $targetYear?->is_active ? 'ACTIVE' : 'INACTIVE'));
        $out->line(sprintf('  Promotion 27/28    : %s', $targetYear?->promotion_processed_at ? 'PROCESSED' : 'NOT PROCESSED'));
        $out->newLine();
        $out->line('Operational:');
        $out->line(sprintf('  Students           : %d', $studentCount));
        $out->line(sprintf('  Enrollments        : %d', $enrollmentCount));
        $out->line(sprintf('  Student Bills      : %d', $billCount));
        $out->line(sprintf('  Payments           : %d', $paymentCount));
        $out->line(sprintf('  Payment Details    : %d', $detailCount));
        $out->newLine();
        $out->line('Per class:');

        foreach (SchoolClass::query()->orderBy('id')->get() as $class) {
            $count = Student::where('class_id', $class->id)->count();
            $out->line(sprintf('  %-20s: %d siswa', $class->name, $count));
        }

        $out->newLine();
        $out->line('Promotion Preview Expected:');

        $naikKelas = 0;
        $lulus = 0;

        foreach (SchoolClass::query()->get() as $class) {
            $level = (int) $class->level;

            $studentCount = Student::where('class_id', $class->id)->count();

            $isGraduationLevel = match ($level) {
                -1, 6, 9, 12 => true,
                default => false,
            };

            if ($isGraduationLevel) {
                $lulus += $studentCount;
            } else {
                $naikKelas += $studentCount;
            }
        }

        $out->line(sprintf('  Naik Kelas         : %d', $naikKelas));
        $out->line(sprintf('  Lulus              : %d', $lulus));
        $out->newLine();
        $out->line('========================================');
    }
}
