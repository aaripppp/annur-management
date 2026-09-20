<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Simulate Livewire mount + render for StudentDetail
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel;

$student = Student::find(348);
if (! $student) {
    echo "Student 348 not found!\n";
    exit(1);
}

echo "Student: {$student->nama_lengkap} (ID: {$student->id})\n";
echo 'Status: '.$student->status->value."\n";

$component = new StudentDetail;

// Set the student property
$reflection = new ReflectionClass($component);

$studentProp = $reflection->getProperty('student');
$studentProp->setValue($component, $student);

// Simulate mount logic manually
$enrollments = $student->enrollments()->with('academicYear')->orderByDesc('academic_year_id')->get();
echo 'Enrollments: '.$enrollments->count()."\n";
foreach ($enrollments as $e) {
    echo '  Year: '.($e->academicYear?->year ?? 'null')." Status: {$e->status}\n";
}

$latestEnrollment = $enrollments->first();
if ($latestEnrollment && $latestEnrollment->academicYear) {
    $selectedYear = $latestEnrollment->academicYear->year;
} else {
    $active = AcademicYear::active();
    $selectedYear = $active?->year ?? '';
}
echo "Selected academic year: {$selectedYear}\n";

$selectedProp = $reflection->getProperty('selectedAcademicYear');
$selectedProp->setValue($component, $selectedYear);

// Simulate render() data computation without Blade compilation
echo "\n--- Simulating render() data ---\n";
try {
    $bills = $student->bills()
        ->with(['paymentType', 'paymentDetails.payment', 'adjustments.creator'])
        ->orderByDesc('period_year')
        ->orderByDesc('period_month')
        ->orderByDesc('id')
        ->get();
    echo 'Bills loaded: '.$bills->count()."\n";

    // Test filterByAcademicYear
    $ay = AcademicYear::where('year', $selectedYear)->first();
    echo 'AcademicYear record: '.($ay ? $ay->year.' start='.$ay->start_date : 'null')."\n";

    $start = $ay->start_date->copy()->startOfMonth();
    $end = $ay->end_date->copy()->endOfMonth();
    echo "Range: $start to $end\n";

    $filtered = $bills->filter(function ($bill) use ($start, $end) {
        if ($bill->billing_frequency === 'one_time') {
            if ($bill->academic_year === null) {
                return true;
            }

            return $bill->academic_year <= '2027/2028';
        }
        if ($bill->period_month !== null && $bill->period_year !== null) {
            $billDate = Carbon::createFromDate($bill->period_year, $bill->period_month, 1)->startOfMonth();

            return $billDate->gte($start) && $billDate->lte($end);
        }
        if ($bill->academic_year !== null) {
            return $bill->academic_year === '2027/2028';
        }

        return false;
    })->values();
    echo 'Filtered bills: '.$filtered->count()."\n";

    echo "\n--- Attempting Blade compile (view object) ---\n";
    $view = view('livewire.student.detail', [
        'groupedMonthlyBills' => collect(),
        'groupedYearlyBills' => collect(),
        'oneTimeBills' => collect(),
        'oneTimeStatus' => 'unpaid',
        'periodOptions' => collect(),
        'academicYearOptions' => collect(),
        'showOtherOption' => false,
        'summaryPeriodEmpty' => true,
        'totalTagihan' => 0,
        'totalDibayar' => 0,
        'totalTunggakan' => 0,
        'manualAddPaymentTypes' => collect(),
        'addMonthOptions' => collect(),
        'addYearOptions' => [],
        'addAcademicYearOptions' => [],
        'academicYearSelectorOptions' => [],
    ]);

    // Set component for Livewire view
    $view->slot('student', $student);

    echo 'View name: '.$view->name()."\n";
    $content = $view->render();
    echo 'Blade render SUCCESS, length: '.strlen($content)."\n";
} catch (Throwable $e) {
    echo 'EXCEPTION: '.get_class($e)."\n";
    echo 'MESSAGE: '.$e->getMessage()."\n";
    echo 'FILE: '.$e->getFile().':'.$e->getLine()."\n";
    echo "\nStack trace:\n".$e->getTraceAsString()."\n";
}
