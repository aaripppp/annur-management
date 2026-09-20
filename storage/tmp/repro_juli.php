<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\BillGenerationService;
use Illuminate\Support\Carbon;

$class = SchoolClass::where('level', 7)->first();
echo "Kelas: ".$class->name." (level ".$class->level.")".PHP_EOL;

$student = Student::create([
    'nis' => 'RPR-'.now()->format('His'),
    'nama_lengkap' => 'Repro Juli',
    'nama_panggilan' => 'RJ',
    'class_id' => $class->id,
    'jenis_kelamin' => 'L',
    'entry_date' => '2026-07-15',
]);

$svc = app(BillGenerationService::class);
foreach (['2026-07-15', '2026-08-15', '2026-09-15'] as $d) {
    $bills = $svc->generateForStudent($student, Carbon::parse($d));
    echo str_pad($d, 12)." → dibuat ".count($bills)." bill".PHP_EOL;
    foreach ($bills as $b) {
        echo "      - ".$b->paymentType->name." | ".$b->billing_frequency." | ".$b->period_month."/".$b->period_year." | ".$b->amount.PHP_EOL;
    }
}
echo PHP_EOL."██ semua bill yg tercipta ██".PHP_EOL;
foreach ($student->bills()->with('paymentType')->orderBy('period_year')->orderBy('period_month')->get() as $b) {
    echo "   ".$b->period_month."/".$b->period_year." ".str_pad($b->paymentType->name, 20)." ".str_pad((string)$b->billing_frequency, 13)." ".$b->amount.PHP_EOL;
}
$student->bills()->delete();
$student->paymentSettings()->delete();
$student->delete();
