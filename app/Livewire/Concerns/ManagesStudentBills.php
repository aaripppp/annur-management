<?php

namespace App\Livewire\Concerns;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\BillGenerationService;
use App\Support\AcademicYear as AcademicYearSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait ManagesStudentBills
{
    public bool $isEditOpen = false;

    public ?int $editBillId = null;

    public string $editAmount = '';

    public float $editPaidAmount = 0;

    public ?string $editPeriodLabel = null;

    public ?string $editTypeName = null;

    public bool $isAddOpen = false;

    public string $addPaymentTypeId = '';

    public string $addAmount = '';

    public string $addMonth = '';

    public string $addYear = '';

    public string $addAcademicYear = '';

    public ?string $addFrequency = null;

    public bool $addFrequencyLocked = false;

    public bool $addPeriodLocked = false;

    public ?int $addLockedMonth = null;

    public ?int $addLockedYear = null;

    public ?string $addLockedAcademicYear = null;

    public bool $isDeleteOpen = false;

    /**
     * @var array<int, string>
     */
    public const MANUAL_ADD_ORDER = ['SPP', 'Ekskul', 'OSIS', 'Jemputan', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal', 'Lain-lain'];

    public ?int $deleteBillId = null;

    public float $deleteAmount = 0;

    public float $deletePaidAmount = 0;

    public ?string $deletePeriodLabel = null;

    public ?string $deleteTypeName = null;

    abstract protected function managedBillStudent(): ?Student;

    public function editBill(int $billId): void
    {
        $bill = $this->managedBillStudent()?->bills()->with('paymentType')->find($billId);

        if (! $bill) {
            return;
        }

        $this->editBillId = $bill->id;
        $this->editAmount = (string) round($bill->effective_amount, 2);
        $this->editPaidAmount = (float) $bill->paid_amount;
        $this->editPeriodLabel = $bill->period_label;
        $this->editTypeName = $bill->paymentType->name ?? '—';
        $this->isEditOpen = true;
    }

    public function saveEditBill(): void
    {
        $this->validate([
            'editAmount' => 'required|numeric|min:0',
        ], [
            'editAmount.required' => 'Nominal tagihan wajib diisi.',
            'editAmount.numeric' => 'Nominal tagihan harus berupa angka.',
            'editAmount.min' => 'Nominal tagihan tidak boleh negatif.',
        ]);

        $bill = $this->managedBillStudent()?->bills()->with('adjustments')->findOrFail($this->editBillId);
        $newAmount = round((float) $this->editAmount, 2);

        if ((float) $bill->paid_amount > $newAmount) {
            $this->addError(
                'editAmount',
                'Tagihan tidak boleh lebih rendah dari jumlah yang sudah dibayar (Rp '.number_format((float) $bill->paid_amount, 0, ',', '.').').'
            );

            return;
        }

        if ($bill->adjustments()->exists()) {
            $bill->adjustments()->delete();
        }

        $bill->update([
            'amount' => $newAmount,
            'updated_by' => auth()->id(),
        ]);

        $this->closeEdit();
        $this->refreshManagedBills();
        $this->notifyManagedBillSuccess('Tagihan berhasil diperbarui.');
    }

    public function closeEdit(): void
    {
        $this->isEditOpen = false;
        $this->reset(['editBillId', 'editAmount', 'editPaidAmount', 'editPeriodLabel', 'editTypeName']);
    }

    public function openAddBill(): void
    {
        $this->closeAddBill();
        $student = $this->managedBillStudent();
        $historicalEnrollment = $this->manualBillUsesHistoricalContext($student)
            ? $student?->enrollments()->with('academicYear')->get()
                ->sortByDesc(fn ($enrollment): string => $enrollment->academicYear?->start_date?->toDateString() ?? '')
                ->first()
            : null;
        $defaultDate = $historicalEnrollment?->academicYear?->start_date ?? now();

        $this->addMonth = (string) $defaultDate->month;
        $this->addYear = (string) $defaultDate->year;
        $this->addAcademicYear = $historicalEnrollment?->academicYear?->year
            ?? AcademicYearSupport::fromDate(now())->label();
        $this->isAddOpen = true;
    }

    public function openAddBillForMonth(int $month, int $year): void
    {
        $this->closeAddBill();
        $this->addMonth = (string) $month;
        $this->addYear = (string) $year;
        $this->addLockedMonth = $month;
        $this->addLockedYear = $year;
        $this->addFrequency = BillFrequency::Monthly->value;
        $this->addFrequencyLocked = true;
        $this->addPeriodLocked = true;
        $this->isAddOpen = true;
    }

    public function openAddBillForAcademicYear(string $academicYear): void
    {
        $this->closeAddBill();
        $this->addAcademicYear = $academicYear;
        $this->addLockedAcademicYear = $academicYear;
        $this->addFrequency = BillFrequency::Yearly->value;
        $this->addFrequencyLocked = true;
        $this->addPeriodLocked = true;
        $this->isAddOpen = true;
    }

    public function openAddBillForOneTime(): void
    {
        $this->openAddBill();
        $this->addFrequency = BillFrequency::OneTime->value;
        $this->addFrequencyLocked = true;

        if (! $this->manualBillUsesHistoricalContext()) {
            $this->addAcademicYear = '';
        }
    }

    public function closeAddBill(): void
    {
        $this->isAddOpen = false;
        $this->reset([
            'addPaymentTypeId',
            'addAmount',
            'addMonth',
            'addYear',
            'addAcademicYear',
            'addFrequency',
            'addFrequencyLocked',
            'addPeriodLocked',
            'addLockedMonth',
            'addLockedYear',
            'addLockedAcademicYear',
        ]);
    }

    public function updatedAddPaymentTypeId(string $value): void
    {
        if ($this->addFrequencyLocked || $this->manualBillUsesHistoricalContext()) {
            return;
        }

        $this->addFrequency = $this->resolveAddFrequency((int) $value);
    }

    public function saveAddBill(): void
    {
        $student = $this->managedBillStudent();

        if (! $student || ! Student::query()->whereKey($student->getKey())->exists()) {
            $this->addError('addPaymentTypeId', 'Siswa tidak valid.');

            return;
        }

        $this->validate([
            'addPaymentTypeId' => 'required|integer',
            'addFrequency' => 'required|in:monthly,yearly,one_time',
            'addAmount' => 'required|numeric|min:1',
        ], [
            'addPaymentTypeId.required' => 'Jenis pembayaran wajib dipilih.',
            'addFrequency.required' => 'Frekuensi tagihan wajib dipilih.',
            'addFrequency.in' => 'Frekuensi tagihan tidak valid.',
            'addAmount.required' => 'Nominal tagihan wajib diisi.',
            'addAmount.numeric' => 'Nominal tagihan harus berupa angka.',
            'addAmount.min' => 'Nominal tagihan harus lebih dari 0.',
        ]);

        $paymentType = $this->allowedManualPaymentTypesQuery($student)
            ->whereKey((int) $this->addPaymentTypeId)
            ->first();

        if (! $paymentType) {
            $this->addError('addPaymentTypeId', 'Jenis pembayaran siswa tidak valid atau tidak tersedia.');

            return;
        }

        $frequency = (string) $this->addFrequency;
        $isHistorical = $this->manualBillUsesHistoricalContext($student);
        $month = null;
        $year = null;
        $academicYear = null;

        if ($frequency === BillFrequency::Monthly->value) {
            $month = $this->addLockedMonth ?? (int) $this->addMonth;
            $year = $this->addLockedYear ?? (int) $this->addYear;

            if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
                $this->addError('addMonth', 'Periode tagihan tidak valid.');

                return;
            }

            if ($isHistorical) {
                $periodDate = Carbon::createFromDate($year, $month, 1);
                $isAvailablePeriod = AcademicYear::query()
                    ->whereDate('start_date', '<=', $periodDate)
                    ->whereDate('end_date', '>=', $periodDate)
                    ->exists();

                if (! $isAvailablePeriod) {
                    $this->addError('addMonth', 'Periode tidak termasuk dalam tahun ajaran yang tersedia.');

                    return;
                }
            }
        } elseif ($frequency === BillFrequency::Yearly->value) {
            $academicYear = $this->addLockedAcademicYear ?? trim($this->addAcademicYear);

            if ($academicYear === '') {
                $this->addError('addAcademicYear', 'Tahun ajaran wajib dipilih.');

                return;
            }
        } elseif ($isHistorical) {
            $academicYear = trim($this->addAcademicYear);

            if ($academicYear === '') {
                $this->addError('addAcademicYear', 'Tahun ajaran wajib dipilih.');

                return;
            }
        }

        if ($academicYear !== null && $isHistorical && ! AcademicYear::query()->where('year', $academicYear)->exists()) {
            $this->addError('addAcademicYear', 'Tahun ajaran yang dipilih tidak valid.');

            return;
        }

        if ($this->manualBillAlreadyExists((int) $this->addPaymentTypeId, $frequency, $month, $year, $academicYear)) {
            return;
        }

        StudentBill::create([
            'student_id' => $student->id,
            'payment_type_id' => (int) $this->addPaymentTypeId,
            'amount' => round((float) $this->addAmount, 2),
            'period_month' => $month,
            'period_year' => $year,
            'academic_year' => $academicYear,
            'billing_frequency' => $frequency,
            'due_date' => null,
        ]);

        $this->closeAddBill();
        $this->refreshManagedBills();
        $this->notifyManagedBillSuccess('Tagihan manual berhasil ditambahkan.');
    }

    public function confirmDeleteBill(int $billId): void
    {
        $bill = $this->managedBillStudent()?->bills()->with('paymentType')->find($billId);

        if (! $bill) {
            return;
        }

        $this->deleteBillId = $bill->id;
        $this->deleteAmount = round((float) $bill->effective_amount, 2);
        $this->deletePaidAmount = (float) $bill->paid_amount;
        $this->deletePeriodLabel = $bill->period_label;
        $this->deleteTypeName = $bill->paymentType->name ?? '—';
        $this->isDeleteOpen = true;
    }

    public function deleteBill(): void
    {
        $bill = $this->managedBillStudent()?->bills()->with('adjustments')->findOrFail($this->deleteBillId);

        if ((float) $bill->paid_amount > 0) {
            $this->addError('deleteConfirm', 'Tagihan tidak dapat dihapus karena sudah memiliki pembayaran.');

            return;
        }

        $bill->adjustments()->delete();
        $bill->delete();

        $this->closeDelete();
        $this->refreshManagedBills();
        $this->notifyManagedBillSuccess('Tagihan berhasil dihapus.');
    }

    public function closeDelete(): void
    {
        $this->isDeleteOpen = false;
        $this->reset(['deleteBillId', 'deleteAmount', 'deletePaidAmount', 'deletePeriodLabel', 'deleteTypeName']);
    }

    protected function closeBillManagementModals(): void
    {
        $this->closeEdit();
        $this->closeAddBill();
        $this->closeDelete();
    }

    protected function notifyManagedBillSuccess(string $message): void
    {
        session()->flash('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function manualBillFormData(): array
    {
        $student = $this->managedBillStudent();
        $isHistorical = $this->manualBillUsesHistoricalContext($student);
        $manualAddPaymentTypes = $this->allowedManualPaymentTypesQuery($student)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->unique('name')
            ->sort(function (PaymentType $first, PaymentType $second): int {
                $firstIndex = array_search($first->name, self::MANUAL_ADD_ORDER, true);
                $secondIndex = array_search($second->name, self::MANUAL_ADD_ORDER, true);
                $firstOrder = $firstIndex === false ? count(self::MANUAL_ADD_ORDER) : $firstIndex;
                $secondOrder = $secondIndex === false ? count(self::MANUAL_ADD_ORDER) : $secondIndex;

                return [$firstOrder, mb_strtolower($first->name), $first->id]
                    <=> [$secondOrder, mb_strtolower($second->name), $second->id];
            })
            ->values();

        $addMonthOptions = collect(range(1, 12))->map(fn (int $month) => [
            'value' => (string) $month,
            'label' => Carbon::createFromDate(2000, $month, 1)->locale('id')->translatedFormat('F'),
        ]);
        $academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
        $addYearOptions = $academicYears
            ->flatMap(fn (AcademicYear $academicYear): array => range($academicYear->start_date->year, $academicYear->end_date->year))
            ->unique()
            ->sortDesc()
            ->map(fn (int $year): string => (string) $year)
            ->values()
            ->all();
        $addAcademicYearOptions = $academicYears->pluck('year')->unique()->values()->all();

        if ($academicYears->isEmpty()) {
            $currentYear = now()->year;
            $currentAcademicYear = AcademicYearSupport::fromDate(now());
            $addYearOptions = collect(range($currentYear - 1, $currentYear + 1))
                ->map(fn (int $year): string => (string) $year)
                ->all();
            $addAcademicYearOptions = [
                $currentAcademicYear->label(),
                (new AcademicYearSupport($currentAcademicYear->startYear() + 1))->label(),
            ];
        }

        return [
            'manualAddPaymentTypes' => $manualAddPaymentTypes,
            'manualBillIsHistorical' => $isHistorical,
            'addMonthOptions' => $addMonthOptions,
            'addYearOptions' => $addYearOptions,
            'addAcademicYearOptions' => $addAcademicYearOptions,
        ];
    }

    protected function manualBillAlreadyExists(int $typeId, string $frequency, ?int $month, ?int $year, ?string $academicYear): bool
    {
        $student = $this->managedBillStudent();

        if (! $student) {
            return false;
        }

        $typeName = PaymentType::find($typeId)?->name;

        if ($frequency === BillFrequency::Monthly->value && $month !== null && $year !== null) {
            $exists = $student->bills()
                ->where('payment_type_id', $typeId)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->exists();

            if ($exists) {
                $periodLabel = Carbon::createFromDate($year, $month, 1)->locale('id')->translatedFormat('F Y');
                $this->addError('addPeriod', 'Tagihan '.$typeName.' untuk '.$periodLabel.' sudah ada. Edit tagihan yang sudah ada jika ingin mengubah nominal.');
            }

            return $exists;
        }

        if ($frequency === BillFrequency::Yearly->value && $academicYear !== null) {
            $exists = $student->bills()
                ->where('payment_type_id', $typeId)
                ->where('academic_year', $academicYear)
                ->exists();

            if ($exists) {
                $this->addError('addPeriod', 'Tagihan '.$typeName.' untuk Tahun Ajaran '.$academicYear.' sudah ada. Edit tagihan yang sudah ada jika ingin mengubah nominal.');
            }

            return $exists;
        }

        $exists = $student->bills()
            ->where('payment_type_id', $typeId)
            ->where('billing_frequency', BillFrequency::OneTime->value)
            ->exists();

        if ($exists) {
            $this->addError('addPeriod', 'Tagihan '.$typeName.' sekali bayar sudah pernah dibuat untuk siswa ini.');
        }

        return $exists;
    }

    /** @return Builder<PaymentType> */
    protected function allowedManualPaymentTypesQuery(?Student $student): Builder
    {
        return PaymentType::query()
            ->where('audience', PaymentTypeAudience::Student)
            ->when(
                ! $this->manualBillUsesHistoricalContext($student),
                fn ($query) => $query->where('is_active', true)
            );
    }

    protected function manualBillUsesHistoricalContext(?Student $student = null): bool
    {
        $student ??= $this->managedBillStudent();

        if (! $student) {
            return false;
        }

        return StudentStatus::tryFrom((string) $student->getRawOriginal('status')) !== StudentStatus::Active;
    }

    protected function resolveAddFrequency(int $typeId): string
    {
        $type = PaymentType::find($typeId);
        $student = $this->managedBillStudent();

        if (! $type || ! $student?->schoolClass?->level) {
            return BillFrequency::OneTime->value;
        }

        $rate = app(BillGenerationService::class)->resolveRate($type, $student->schoolClass->level, now());

        return $rate?->billing_frequency?->value ?? BillFrequency::OneTime->value;
    }

    protected function refreshManagedBills(): void
    {
        $this->managedBillStudent()?->unsetRelation('bills');
    }
}
