<?php

use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentCreationService;
use Illuminate\Support\Carbon;

$ay = AcademicYear::active();
echo "████ sekarang = ".now()->toDateTimeString()." | tahun ajaran ".$ay->year." (".$ay->start_date?->toDateString()." s.d. ".$ay->end_date?->toDateString().") ████".PHP_EOL;

$level = 7;
$cls = SchoolClass::where('level', $level)->first();
$svc = app(StudentCreationService::classface);
$student = $svc->create([
    'nis' => 'UI-'.now()->format('His'),
    'nama_lengkap' => 'Repro UI Juli',
    'nama_panggilan' => 'RJ',
    'class_id' => $cls->id,
    'jenis_kelamin' => 'L',
    'alamat' => 'Jl Repro UI',
    'entry_date' => '2026-07-15',
]);

echo PHP_EOL."████ bulan pertama tagihan dari UI path (per_semua bill) ████".PHP_EOL;
foreach ($student->bills()->with('paymentType')->orderBy('period_year')->orderBy('period_month')->get() as $b) {
    echo "   ".str_pad(($b->period_month ? $b->period_month.'/'.$b->period_year : $b->period_year), 22)
        ." ".str_pad($b->paymentType->name, 16)
        ." ".str_pad((string)$b->billing_frequency, 15)
        ." ".str_pad((string)$b->amount, 12)
        ." ".($b->studentPaymentSetting?->started_at?->toDateString() ?? '').PHP_EOL;
}

echo PHP_EOL."████ payment settings siswa (started_at per type) ████".PHP_EOL;
foreach ($student->paymentSettings()->with('paymentType')->get() as $s) {
    echo "   ".str_pad($s->paymentType->name, 18)." started_at=".$s->started_at?->toDateString().PHP_EOL;
}

$student->bills()->delete();
$student->paymentSettings()->delete();
$student->delete();
