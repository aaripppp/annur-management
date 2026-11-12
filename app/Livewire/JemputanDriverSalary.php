<?php

namespace App\Livewire;

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\JemputanDriverAllocationService;
use App\Services\JemputanDriverDeductionRateResolver;
use App\Services\JemputanDriverSalaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class JemputanDriverSalary extends Component
{
    use WithPagination;

    public string $selectedAcademicYearId = '';

    public string $selectedDriverId = '';

    public string $selectedMonth = '';

    public int $perPage = 25;

    public bool $isAllocationModalOpen = false;

    public ?int $allocationStudentId = null;

    public ?string $allocationStudentClass = null;

    public ?string $allocationStudentNis = null;

    public string $allocationMonthKey = '';

    /** @var array<string, mixed> */
    public array $allocationPreview = [];

    /** @var array<string, string> */
    public array $allocationAmounts = [];

    /** @var list<array{key: string, label: string}> */
    public array $allocationMonthOptions = [];

    /** @var list<string> */
    public array $additionalAllocationMonthKeys = [];

    public bool $applyAllocationToOtherMonths = false;

    public float $allocationTotal = 0.0;

    public float $allocationDifference = 0.0;

    public function mount(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->selectedAcademicYearId = (string) (AcademicYear::active()?->id
            ?? AcademicYear::query()->latest('start_date')->value('id')
            ?? '');
    }

    public function updatedSelectedAcademicYearId(): void
    {
        $this->authorizeSalary();
        $this->validateOnly('selectedAcademicYearId', $this->rules());
        $this->reset('selectedDriverId', 'selectedMonth');
        $this->resetPage('salaryPage');
    }

    public function updatedSelectedDriverId(): void
    {
        $this->authorizeSalary();
        $this->validateOnly('selectedDriverId', $this->rules());
        $this->reset('selectedMonth');
        $this->resetPage('salaryPage');
    }

    public function updatedSelectedMonth(): void
    {
        $this->authorizeSalary();

        if ($this->selectedMonth !== '') {
            $this->validateOnly('selectedMonth', $this->rules());
        }

        $this->resetPage('salaryPage');
    }

    public function updatedPerPage(): void
    {
        $this->authorizeSalary();
        $this->validateOnly('perPage', $this->rules());
        $this->resetPage('salaryPage');
    }

    public function updatedPaginators(): void
    {
        $this->authorizeSalary();
    }

    public function exportExcel(JemputanDriverDeductionRateResolver $deductionRates): void
    {
        $this->authorizeSalary();
        $this->validate($this->exportRules());
        $driver = JemputanDriver::query()->findOrFail((int) $this->selectedDriverId);
        $deductionRate = $deductionRates->resolve(
            $driver,
            (int) substr($this->selectedMonth, 0, 4),
            (int) substr($this->selectedMonth, 5, 2),
        );

        if (! $deductionRate['configured']) {
            $this->addError(
                'export',
                'Persentase potongan yayasan sopir ini belum diatur. Atur persentase terlebih dahulu di Master Sopir.',
            );

            return;
        }

        $this->redirectRoute('jemputan.salary.export', [
            'academic_year_id' => $this->selectedAcademicYearId,
            'driver_id' => $this->selectedDriverId,
            'month' => $this->selectedMonth,
        ]);
    }

    public function openAllocationModal(int $studentId, JemputanDriverAllocationService $service): void
    {
        $this->authorize('create', JemputanDriver::class);
        $this->resetAllocationForm();

        if (! $this->filtersAreValid() || $this->selectedDriverId === '') {
            $this->notify('Pilih sopir dan bulan laporan terlebih dahulu.', 'error');

            return;
        }

        if (! $this->studentBelongsToSelectedReport($studentId)) {
            $this->notify('Siswa tidak termasuk dalam filter laporan ini.', 'error');

            return;
        }

        $month = $this->selectedReportMonth($service);

        if ($month === null) {
            $this->notify('Bulan laporan tidak valid.', 'error');

            return;
        }

        $preview = $service->preview(
            $studentId,
            (int) $this->selectedAcademicYearId,
            (int) $month['year'],
            (int) $month['month'],
        );

        if (! $preview['is_multi_driver']) {
            $this->notify('Siswa dengan satu sopir tidak memerlukan pembagian.', 'error');

            return;
        }

        $student = Student::query()
            ->with([
                'schoolClass',
                'enrollments' => fn ($query) => $query
                    ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                    ->with('schoolClass'),
            ])
            ->findOrFail($studentId);
        $enrollment = $student->enrollments->first();

        $this->allocationStudentId = $student->id;
        $this->allocationStudentNis = $student->nis;
        $this->allocationStudentClass = $enrollment?->schoolClass?->name ?? $student->schoolClass?->name;
        $this->allocationMonthKey = $month['key'];
        $this->allocationPreview = $preview;
        $this->allocationAmounts = [];

        foreach ($preview['drivers'] as $driver) {
            $this->allocationAmounts[(string) $driver['id']] = $driver['allocated_amount'] === null
                ? ''
                : number_format((float) $driver['allocated_amount'], 2, '.', '');
        }

        $this->allocationMonthOptions = $this->allocationMonthOptions(
            $studentId,
            (int) $this->selectedAcademicYearId,
            $service,
        );
        $this->isAllocationModalOpen = true;
        $this->recalculateAllocationTotals();
    }

    public function closeAllocationModal(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->resetAllocationForm();
    }

    public function updatedAllocationAmounts(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->recalculateAllocationTotals();
    }

    public function saveAllocation(JemputanDriverAllocationService $service): void
    {
        $this->authorize('create', JemputanDriver::class);

        if (! $this->isAllocationModalOpen || $this->allocationStudentId === null) {
            $this->notify('Buka pembagian nominal terlebih dahulu.', 'error');

            return;
        }

        try {
            $this->validate([
                'allocationAmounts' => ['required', 'array'],
                'allocationAmounts.*' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
                'additionalAllocationMonthKeys' => ['array'],
                'additionalAllocationMonthKeys.*' => ['string'],
            ], [
                'allocationAmounts.*.required' => 'Nominal alokasi setiap sopir wajib diisi.',
                'allocationAmounts.*.numeric' => 'Nominal alokasi wajib berupa angka.',
                'allocationAmounts.*.min' => 'Nominal alokasi tidak boleh negatif.',
                'allocationAmounts.*.decimal' => 'Nominal alokasi maksimal dua angka desimal.',
            ]);
        } catch (ValidationException $exception) {
            $this->notify('Periksa kembali nominal pembagian.', 'error');

            throw $exception;
        }

        if (! $this->studentBelongsToSelectedReport($this->allocationStudentId)) {
            $this->addError('allocationForm', 'Siswa tidak termasuk dalam filter laporan ini.');
            $this->notify('Siswa tidak termasuk dalam filter laporan ini.', 'error');

            return;
        }

        $month = $this->selectedReportMonth($service, $this->allocationMonthKey);

        if ($month === null) {
            $this->addError('allocationForm', 'Bulan laporan tidak valid.');
            $this->notify('Bulan laporan tidak valid.', 'error');

            return;
        }

        $preview = $service->preview(
            $this->allocationStudentId,
            (int) $this->selectedAcademicYearId,
            (int) $month['year'],
            (int) $month['month'],
        );

        if (! $preview['is_multi_driver']) {
            $this->addError('allocationForm', 'Siswa dengan satu sopir tidak memerlukan pembagian.');
            $this->notify('Siswa dengan satu sopir tidak memerlukan pembagian.', 'error');

            return;
        }

        if (! $preview['has_bill']) {
            $this->addError('allocationForm', 'Tagihan Jemputan untuk periode ini belum tersedia.');
            $this->notify('Tagihan Jemputan untuk periode ini belum tersedia.', 'error');

            return;
        }

        $rows = [];

        foreach ($preview['drivers'] as $driver) {
            $driverId = (string) $driver['id'];
            $amount = $this->allocationAmounts[$driverId] ?? null;

            if (! is_scalar($amount) || trim((string) $amount) === '') {
                $this->addError('allocationAmounts.'.$driverId, 'Nominal alokasi setiap sopir wajib diisi.');
                $this->notify('Periksa kembali nominal pembagian.', 'error');

                return;
            }

            $rows[] = [
                'jemputan_driver_id' => (int) $driver['id'],
                'allocated_amount' => trim((string) $amount),
            ];
        }

        $allocationsByPeriod = [$month['key'] => $rows];
        $availableMonths = $this->allocationMonthOptions(
            $this->allocationStudentId,
            (int) $this->selectedAcademicYearId,
            $service,
        );
        $availableMonthKeys = array_column($availableMonths, 'key');
        $additionalMonths = $this->applyAllocationToOtherMonths
            ? array_values(array_unique($this->additionalAllocationMonthKeys))
            : [];

        foreach ($additionalMonths as $additionalMonthKey) {
            if (! is_string($additionalMonthKey) || ! in_array($additionalMonthKey, $availableMonthKeys, true)) {
                $this->addError('allocationMonths', 'Bulan tambahan tidak valid atau tidak memiliki tagihan.');
                $this->notify('Bulan tambahan tidak valid atau tidak memiliki tagihan.', 'error');

                return;
            }

            $additionalMonth = $this->selectedReportMonth($service, $additionalMonthKey);

            if ($additionalMonth === null) {
                $this->addError('allocationMonths', 'Bulan tambahan tidak valid.');
                $this->notify('Bulan tambahan tidak valid.', 'error');

                return;
            }

            $additionalPreview = $service->preview(
                $this->allocationStudentId,
                (int) $this->selectedAcademicYearId,
                (int) $additionalMonth['year'],
                (int) $additionalMonth['month'],
            );

            if (! $additionalPreview['has_bill']) {
                $this->addError('allocationMonths', 'Bulan yang dipilih tidak memiliki tagihan Jemputan.');
                $this->notify('Bulan yang dipilih tidak memiliki tagihan Jemputan.', 'error');

                return;
            }

            if ($this->amountToCents((float) $additionalPreview['bill_amount']) !== $this->amountToCents((float) $preview['bill_amount'])) {
                $message = 'Nominal tagihan '.$additionalMonth['label'].' berbeda. Isi alokasi terpisah untuk bulan tersebut.';
                $this->addError('allocationMonths', $message);
                $this->notify($message, 'error');

                return;
            }

            $allocationsByPeriod[$additionalMonthKey] = $rows;
        }

        try {
            $service->save(
                $this->allocationStudentId,
                (int) $this->selectedAcademicYearId,
                $allocationsByPeriod,
                auth()->user(),
            );
        } catch (ValidationException $exception) {
            $this->addError(
                'allocationForm',
                $exception->validator->errors()->first() ?: 'Periksa kembali nominal pembagian.',
            );
            $this->notify('Periksa kembali nominal pembagian.', 'error');

            return;
        }

        $this->closeAllocationModal();
        $this->notify('Pembagian nominal berhasil disimpan.');
    }

    public function render(JemputanDriverSalaryService $service): View
    {
        $this->authorizeSalary();

        $academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'year', 'start_date', 'end_date']);
        $drivers = JemputanDriver::query()
            ->when(ctype_digit($this->selectedAcademicYearId), fn (Builder $query) => $query
                ->whereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
                    ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                    ->currentMembership()))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $report = null;

        if ($this->selectedDriverId !== '' && $this->filtersAreValid()) {
            $report = $service->generate(
                (int) $this->selectedAcademicYearId,
                (int) $this->selectedDriverId,
                $this->selectedMonth === '' ? null : $this->selectedMonth,
                $this->perPage,
                $this->getPage('salaryPage'),
            );

            if ($this->selectedMonth === '' || ! collect($report['months'])->contains('key', $this->selectedMonth)) {
                $this->selectedMonth = $report['month']['key'] ?? '';
            }
        }

        return view('livewire.jemputan-driver-salary', [
            'academicYears' => $academicYears,
            'drivers' => $drivers,
            'report' => $report,
        ]);
    }

    private function resetAllocationForm(): void
    {
        $this->reset([
            'isAllocationModalOpen',
            'allocationStudentId',
            'allocationStudentClass',
            'allocationStudentNis',
            'allocationMonthKey',
            'allocationPreview',
            'allocationAmounts',
            'allocationMonthOptions',
            'additionalAllocationMonthKeys',
            'applyAllocationToOtherMonths',
            'allocationTotal',
            'allocationDifference',
        ]);
        $this->resetValidation();
    }

    /** @return array{key: string, year: int, month: int, label: string}|null */
    private function selectedReportMonth(
        JemputanDriverAllocationService $service,
        ?string $monthKey = null,
    ): ?array {
        if (! ctype_digit($this->selectedAcademicYearId)) {
            return null;
        }

        $months = $service->periodsForAcademicYear((int) $this->selectedAcademicYearId);
        $monthKey = $monthKey ?? $this->selectedMonth;

        if ($monthKey === '' && $months !== []) {
            return $months[0];
        }

        foreach ($months as $month) {
            if ($month['key'] === $monthKey) {
                return $month;
            }
        }

        return null;
    }

    private function studentBelongsToSelectedReport(int $studentId): bool
    {
        if (! ctype_digit($this->selectedAcademicYearId) || ! ctype_digit($this->selectedDriverId)) {
            return false;
        }

        return JemputanDriverAssignment::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', (int) $this->selectedAcademicYearId)
            ->where('jemputan_driver_id', (int) $this->selectedDriverId)
            ->currentMembership()
            ->exists();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function allocationMonthOptions(
        int $studentId,
        int $academicYearId,
        JemputanDriverAllocationService $service,
    ): array {
        $months = $service->periodsForAcademicYear($academicYearId);
        $billedMonthKeys = $this->billedMonthKeys($studentId, $months);

        return collect($months)
            ->filter(fn (array $month): bool => $month['key'] !== $this->allocationMonthKey
                && in_array($month['key'], $billedMonthKeys, true))
            ->map(fn (array $month): array => [
                'key' => $month['key'],
                'label' => $month['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{key: string, year: int, month: int, label: string}>  $months
     * @return list<string>
     */
    private function billedMonthKeys(int $studentId, array $months): array
    {
        $paymentTypeIds = PaymentType::query()
            ->where('name', 'Jemputan')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($paymentTypeIds === [] || $months === []) {
            return [];
        }

        $years = array_values(array_unique(array_map(fn (array $month): int => (int) $month['year'], $months)));
        $monthKeys = array_column($months, 'key');
        $billedKeys = StudentBill::query()
            ->where('student_id', $studentId)
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->where('billing_frequency', BillFrequency::Monthly->value)
            ->whereIn('period_year', $years)
            ->get(['period_year', 'period_month'])
            ->map(fn (StudentBill $bill): string => sprintf('%04d-%02d', (int) $bill->period_year, (int) $bill->period_month))
            ->unique()
            ->values()
            ->all();

        return array_values(array_intersect($monthKeys, $billedKeys));
    }

    private function recalculateAllocationTotals(): void
    {
        $total = 0.0;

        foreach ($this->allocationPreview['drivers'] ?? [] as $driver) {
            $amount = $this->allocationAmounts[(string) ($driver['id'] ?? '')] ?? null;

            if (is_numeric($amount)) {
                $total += (float) $amount;
            }
        }

        $this->allocationTotal = round($total, 2);
        $this->allocationDifference = round(
            (float) ($this->allocationPreview['bill_amount'] ?? 0) - $this->allocationTotal,
            2,
        );
    }

    private function amountToCents(float $amount): int
    {
        return (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->dispatch('annur-toast', message: $message, type: $type);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'selectedAcademicYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'selectedDriverId' => ['nullable', 'integer', 'exists:jemputan_drivers,id'],
            'selectedMonth' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'perPage' => ['required', 'integer', Rule::in([10, 25, 50])],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function exportRules(): array
    {
        return [
            'selectedAcademicYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'selectedDriverId' => ['required', 'integer', 'exists:jemputan_drivers,id'],
            'selectedMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    private function authorizeSalary(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
    }

    private function filtersAreValid(): bool
    {
        $validator = Validator::make([
            'selectedAcademicYearId' => $this->selectedAcademicYearId,
            'selectedDriverId' => $this->selectedDriverId,
            'selectedMonth' => $this->selectedMonth === '' ? null : $this->selectedMonth,
            'perPage' => $this->perPage,
        ], $this->rules());

        return ! $validator->fails();
    }
}
