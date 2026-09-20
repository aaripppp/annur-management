<?php

namespace App\Livewire\Concerns;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\BillGenerationService;
use App\Support\AcademicYear as AcademicYearSupport;
use Carbon\Carbon;

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
        session()->flash('success', 'Tagihan berhasil diperbarui.');
    }

    public function closeEdit(): void
    {
        $this->isEditOpen = false;
        $this->reset(['editBillId', 'editAmount', 'editPaidAmount', 'editPeriodLabel', 'editTypeName']);
    }

    public function openAddBill(): void
    {
        $this->closeAddBill();
        $this->addMonth = (string) now()->month;
        $this->addYear = (string) now()->year;
        $this->addAcademicYear = AcademicYearSupport::fromDate(now())->label();
        $this->isAddOpen = true;
    }

    public function openAddBillForMonth(int $month, int $year): void
    {
        $this->closeAddBill();
        $this->addMonth = (string) $month;
        $this->addYear = (string) $year;
        $this->addLockedMonth = $month;
        $this->addLockedYear = $year;
        $this->addPeriodLocked = true;
        $this->isAddOpen = true;
    }

    public function openAddBillForAcademicYear(string $academicYear): void
    {
        $this->closeAddBill();
        $this->addAcademicYear = $academicYear;
        $this->addLockedAcademicYear = $academicYear;
        $this->addPeriodLocked = true;
        $this->isAddOpen = true;
    }

    public function openAddBillForOneTime(): void
    {
        $this->closeAddBill();
        $this->isAddOpen = true;
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
            'addPeriodLocked',
            'addLockedMonth',
            'addLockedYear',
            'addLockedAcademicYear',
        ]);
    }

    public function updatedAddPaymentTypeId(string $value): void
    {
        $this->addFrequency = $this->resolveAddFrequency((int) $value);
    }

    public function saveAddBill(): void
    {
        $this->validate([
            'addPaymentTypeId' => 'required|exists:payment_types,id',
            'addAmount' => 'required|numeric|min:1',
        ], [
            'addPaymentTypeId.required' => 'Jenis pembayaran wajib dipilih.',
            'addAmount.required' => 'Nominal tagihan wajib diisi.',
            'addAmount.numeric' => 'Nominal tagihan harus berupa angka.',
            'addAmount.min' => 'Nominal tagihan harus lebih dari 0.',
        ]);

        $student = $this->managedBillStudent();

        if (! $student) {
            return;
        }

        $frequency = $this->addFrequency ?? $this->resolveAddFrequency((int) $this->addPaymentTypeId);
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
        } elseif ($frequency === BillFrequency::Yearly->value) {
            $academicYear = $this->addLockedAcademicYear ?? trim($this->addAcademicYear);

            if ($academicYear === '') {
                $this->addError('addAcademicYear', 'Tahun ajaran wajib dipilih.');

                return;
            }
        }

        if ($this->manualBillAlreadyExists((int) $this->addPaymentTypeId, $month, $year, $academicYear)) {
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
        session()->flash('success', 'Tagihan manual berhasil ditambahkan.');
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
        session()->flash('success', 'Tagihan berhasil dihapus.');
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

    /**
     * @return array<string, mixed>
     */
    protected function manualBillFormData(): array
    {
        $manualAddPaymentTypes = PaymentType::query()
            ->where('is_active', true)
            ->where('audience', PaymentTypeAudience::Student)
            ->get()
            ->unique('name')
            ->sortBy(function (PaymentType $type): int {
                $index = array_search($type->name, self::MANUAL_ADD_ORDER, true);

                return $index === false ? count(self::MANUAL_ADD_ORDER) : $index;
            })
            ->values();

        $addMonthOptions = collect(range(1, 12))->map(fn (int $month) => [
            'value' => (string) $month,
            'label' => Carbon::createFromDate(2000, $month, 1)->locale('id')->translatedFormat('F'),
        ]);
        $currentYear = now()->year;
        $currentAcademicYear = AcademicYearSupport::fromDate(now());

        return [
            'manualAddPaymentTypes' => $manualAddPaymentTypes,
            'addMonthOptions' => $addMonthOptions,
            'addYearOptions' => collect(range($currentYear - 1, $currentYear + 1))->map(fn (int $year) => (string) $year)->all(),
            'addAcademicYearOptions' => [
                $currentAcademicYear->label(),
                (new AcademicYearSupport($currentAcademicYear->startYear() + 1))->label(),
            ],
        ];
    }

    protected function manualBillAlreadyExists(int $typeId, ?int $month, ?int $year, ?string $academicYear): bool
    {
        $student = $this->managedBillStudent();

        if (! $student) {
            return false;
        }

        $typeName = PaymentType::find($typeId)?->name;

        if ($month !== null && $year !== null) {
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

        if ($academicYear !== null) {
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
