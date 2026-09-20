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

/**
 * Create disposable testing students — one per SchoolClass.
 *
 * This seeder does NOT create/modify any master data (classes, types,
 * rates, banks, academic years). It ONLY creates Student records and
 * their associated operational data (enrollments, payment settings,
 * bills) via the production StudentCreationService.
 *
 * Safe to re-run: deletes only TEST-* students and their data first.
 *
 * Usage:
 *   php artisan db:seed --class=TestingStudentSeeder
 */
class TestingStudentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'TestingStudentSeeder tidak boleh dijalankan di production.'
            );
        }

        $this->captureMasterState();
        $this->deleteExistingTestStudents();
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

    /**
     * Delete only TEST-* students and their operational data.
     * Does NOT touch real students, master classes, rates, or banks.
     */
    private function deleteExistingTestStudents(): void
    {
        $testStudentIds = Student::where('nis', 'like', 'TEST-%')
            ->pluck('id')
            ->toArray();

        if (empty($testStudentIds)) {
            return;
        }

        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        DB::table('payment_correction_logs')
            ->whereIn('payment_id', function ($q) use ($testStudentIds) {
                $q->select('id')->from('payments')->whereIn('student_id', $testStudentIds);
            })->delete();

        DB::table('payment_details')
            ->whereIn('payment_id', function ($q) use ($testStudentIds) {
                $q->select('id')->from('payments')->whereIn('student_id', $testStudentIds);
            })->delete();

        DB::table('payments')->whereIn('student_id', $testStudentIds)->delete();

        DB::table('bill_adjustments')
            ->whereIn('bill_id', function ($q) use ($testStudentIds) {
                $q->select('id')->from('student_bills')->whereIn('student_id', $testStudentIds);
            })->delete();

        DB::table('student_bills')->whereIn('student_id', $testStudentIds)->delete();
        DB::table('student_payment_settings')->whereIn('student_id', $testStudentIds)->delete();
        DB::table('student_academic_enrollments')->whereIn('student_id', $testStudentIds)->delete();
        DB::table('students')->whereIn('id', $testStudentIds)->delete();

        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Create one test student per SchoolClass, enrolled in 2026/2027.
     */
    private function createTestStudents(): void
    {
        $service = app(StudentCreationService::class);
        $activeYear = AcademicYear::where('year', '2026/2027')->first();

        if (! $activeYear) {
            throw new RuntimeException(
                'AcademicYear 2026/2027 belum ada. Jalankan MasterDataSeeder terlebih dahulu.'
            );
        }

        $counter = 1;

        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        try {
            foreach (SchoolClass::query()->orderBy('level')->orderBy('name')->get() as $class) {
                $service->create([
                    'nis' => 'TEST-'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT),
                    'nama_lengkap' => 'TEST '.$class->name,
                    'nama_panggilan' => 'Test',
                    'class_id' => $class->id,
                    'jenis_kelamin' => 'L',
                    'alamat' => 'Jl. Testing No. '.$counter,
                ]);

                $counter++;
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
                'PaymentRate data berubah setelah TestingStudentSeeder! Tariff tidak boleh diubah.'
            );
        }

        $typesAfter = PaymentType::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => $t->toArray())
            ->toArray();

        if ($this->typesBefore !== $typesAfter) {
            throw new RuntimeException(
                'PaymentType data berubah setelah TestingStudentSeeder!'
            );
        }

        $bankCount = Bank::count();
        if ($bankCount !== 5) {
            throw new RuntimeException(
                "Expected 5 banks, got {$bankCount}. TestingStudentSeeder must not alter banks."
            );
        }
    }

    private function printSummary(): void
    {
        $out = $this->command;

        if (! $out) {
            return;
        }

        $studentCount = Student::where('nis', 'like', 'TEST-%')->count();
        $classCount = SchoolClass::count();
        $billCount = DB::table('student_bills')->count();

        $out->newLine();
        $out->line('========================================');
        $out->line('TESTING STUDENTS READY');
        $out->line('========================================');
        $out->newLine();
        $out->line(sprintf('  Classes            : %d', $classCount));
        $out->line(sprintf('  TEST students      : %d', $studentCount));
        $out->line(sprintf('  Bills generated    : %d', $billCount));
        $out->newLine();
        $out->line('Master data preserved:');
        $out->line(sprintf('  Payment Types      : %d', PaymentType::count()));
        $out->line(sprintf('  Payment Rates      : %d', PaymentRate::count()));
        $out->line(sprintf('  Banks              : %d', Bank::count()));
        $out->newLine();
        $out->line('Per class:');

        foreach (SchoolClass::query()->orderBy('level')->orderBy('name')->get() as $class) {
            $count = Student::where('class_id', $class->id)
                ->where('nis', 'like', 'TEST-%')
                ->count();
            $out->line(sprintf('  %-20s: %d test student(s)', $class->name, $count));
        }

        $out->newLine();
        $out->line('========================================');
    }
}
