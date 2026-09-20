<?php

namespace App\Livewire;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Livewire\Concerns\ConvertsProspectiveStudent;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Services\BillGenerationService;
use App\Services\ProspectiveStudentBillGenerationService;
use App\Services\ProspectiveStudentPaymentDeletionService;
use App\Support\AcademicYear as AcademicYearSupport;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProspectivePaymentWorkspace extends Component
{
    use ConvertsProspectiveStudent;

    public ?ProspectiveStudent $prospectiveStudent = null;

    public bool $isEditProfileOpen = false;

    public string $nama_lengkap = '';

    public string $nama_panggilan = '';

    public string $jenis_kelamin = '';

    public string $nama_orang_tua = '';

    public string $no_telp_orang_tua = '';

    public string $alamat = '';

    public string $academic_year_id = '';

    public string $school_class_id = '';

    public string $notes = '';

    public bool $isEditOpen = false;

    public ?int $editBillId = null;

    public string $editAmount = '';

    public float $editPaidAmount = 0;

    public ?string $editPeriodLabel = null;

    public ?string $editTypeName = null;

    public bool $isDeleteOpen = false;

    public ?int $deleteBillId = null;

    public float $deleteAmount = 0;

    public float $deletePaidAmount = 0;

    public ?string $deletePeriodLabel = null;

    public ?string $deleteTypeName = null;

    public bool $isDeletePaymentModalOpen = false;

    public ?int $deletingPaymentId = null;

    public bool $isAddOpen = false;

    public string $addPaymentTypeId = '';

    public string $addAmount = '';

    public string $addAcademicYear = '';

    public function mount(ProspectiveStudent $prospectiveStudent): void
    {
        $this->prospectiveStudent = $prospectiveStudent->load([
            'academicYear',
            'schoolClass',
            'convertedStudent',
            'bills.paymentType',
            'bills.paymentDetails.payment',
        ]);
    }

    public function editBill(int $billId): void
    {
        $bill = $this->prospectiveStudent->bills()
            ->with(['paymentType', 'paymentDetails.payment'])
            ->find($billId);

        if (! $bill) {
            return;
        }

        $this->editBillId = $bill->id;
        $this->editAmount = (string) round((float) $bill->amount, 2);
        $this->editPaidAmount = $bill->paid_amount;
        $this->editPeriodLabel = $bill->academic_year;
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

        $bill = $this->prospectiveStudent->bills()
            ->with('paymentDetails.payment')
            ->findOrFail($this->editBillId);
        $newAmount = round((float) $this->editAmount, 2);

        if ((float) $bill->paid_amount > $newAmount) {
            $this->addError(
                'editAmount',
                'Tagihan tidak boleh lebih rendah dari jumlah yang sudah dibayar (Rp '.number_format((float) $bill->paid_amount, 0, ',', '.').').'
            );

            return;
        }

        $bill->update([
            'amount' => $newAmount,
            'is_manual_override' => true,
        ]);

        $this->closeEdit();
        session()->flash('success', 'Tagihan berhasil diperbarui.');
    }

    public function closeEdit(): void
    {
        $this->isEditOpen = false;
        $this->reset(['editBillId', 'editAmount', 'editPaidAmount', 'editPeriodLabel', 'editTypeName']);
    }

    public function confirmDeleteBill(int $billId): void
    {
        $bill = $this->prospectiveStudent->bills()
            ->with(['paymentType', 'paymentDetails.payment'])
            ->find($billId);

        if (! $bill) {
            return;
        }

        $this->deleteBillId = $bill->id;
        $this->deleteAmount = round((float) $bill->amount, 2);
        $this->deletePaidAmount = $bill->paid_amount;
        $this->deletePeriodLabel = $bill->academic_year;
        $this->deleteTypeName = $bill->paymentType->name ?? '—';
        $this->isDeleteOpen = true;
    }

    public function deleteBill(): void
    {
        $bill = $this->prospectiveStudent->bills()
            ->with('paymentDetails.payment')
            ->findOrFail($this->deleteBillId);

        if ((float) $bill->paid_amount > 0) {
            $this->addError('deleteConfirm', 'Tagihan tidak dapat dihapus karena sudah memiliki pembayaran.');

            return;
        }

        $bill->delete();

        $this->closeDelete();
        session()->flash('success', 'Tagihan berhasil dihapus.');
    }

    public function closeDelete(): void
    {
        $this->isDeleteOpen = false;
        $this->reset(['deleteBillId', 'deleteAmount', 'deletePaidAmount', 'deletePeriodLabel', 'deleteTypeName']);
    }

    public function confirmDeletePayment(int $paymentId): void
    {
        $payment = $this->prospectiveStudent->payments()
            ->with('prospectiveStudent')
            ->find($paymentId);

        if (! $payment) {
            return;
        }

        $this->deletingPaymentId = $payment->id;
        $this->isDeletePaymentModalOpen = true;
    }

    public function cancelDeletePayment(): void
    {
        $this->isDeletePaymentModalOpen = false;
        $this->deletingPaymentId = null;
    }

    public function deletePayment(ProspectiveStudentPaymentDeletionService $deletionService): void
    {
        if (! $this->deletingPaymentId) {
            return;
        }

        $deletionService->delete($this->deletingPaymentId);

        $this->cancelDeletePayment();
        session()->flash('success', 'Transaksi pembayaran berhasil dihapus permanen.');
    }

    public function openAddBill(): void
    {
        $this->isAddOpen = true;
        $this->addAcademicYear = $this->prospectiveStudent->academicYear?->year ?? '';
    }

    public function closeAddBill(): void
    {
        $this->isAddOpen = false;
        $this->reset(['addPaymentTypeId', 'addAmount', 'addAcademicYear']);
        $this->resetValidation();
    }

    public function updatedAddPaymentTypeId(string $value): void
    {
        if ($value === '') {
            $this->addAmount = '';

            return;
        }

        $rate = $this->resolvedAddRate((int) $value);

        $this->addAmount = $rate !== null ? (string) round((float) $rate->amount, 0) : '';
    }

    public function saveAddBill(): void
    {
        $this->validate([
            'addPaymentTypeId' => 'required|exists:payment_types,id',
            'addAmount' => 'required|numeric|min:1',
            'addAcademicYear' => 'required|string',
        ], [
            'addPaymentTypeId.required' => 'Jenis pembayaran wajib dipilih.',
            'addAmount.required' => 'Nominal tagihan wajib diisi.',
            'addAmount.numeric' => 'Nominal tagihan harus berupa angka.',
            'addAmount.min' => 'Nominal tagihan harus lebih dari 0.',
            'addAcademicYear.required' => 'Tahun ajaran wajib diisi.',
        ]);

        $level = $this->prospectiveStudent->target_level;

        if ($level === null) {
            $this->addError('addPaymentTypeId', 'Tentukan kelas tujuan calon siswa terlebih dahulu.');

            return;
        }

        $typeId = (int) $this->addPaymentTypeId;
        $type = PaymentType::query()->find($typeId);
        $academicYear = trim($this->addAcademicYear);
        $studentId = $this->prospectiveStudent->id;
        $resolvedRate = $this->resolvedAddRate($typeId);

        if ($type === null) {
            $this->addError('addPaymentTypeId', 'Jenis pembayaran tidak valid.');

            return;
        }

        if (! $this->addTypeAvailableForLevel($typeId)) {
            $this->addError('addPaymentTypeId', 'Jenis pembayaran tidak tersedia untuk jenjang tujuan calon siswa.');

            return;
        }

        if (ProspectiveStudentBill::query()
            ->where('prospective_student_id', $studentId)
            ->where('payment_type_id', $typeId)
            ->where('academic_year', $academicYear)
            ->exists()) {
            $this->addError('addPeriod', 'Tagihan '.$type->name.' untuk Tahun Ajaran '.$academicYear.' sudah ada. Edit tagihan yang sudah ada jika ingin mengubah nominal.');

            return;
        }

        $amount = round((float) $this->addAmount, 2);
        $isManualOverride = $resolvedRate === null || $amount !== round((float) $resolvedRate->amount, 2);

        ProspectiveStudentBill::create([
            'prospective_student_id' => $studentId,
            'payment_type_id' => $typeId,
            'amount' => $amount,
            'academic_year' => $academicYear,
            'billing_frequency' => BillFrequency::OneTime->value,
            'due_date' => null,
            'created_by' => auth()->id(),
            'is_manual_override' => $isManualOverride,
        ]);

        $this->closeAddBill();
        session()->flash('success', 'Tagihan manual berhasil ditambahkan.');
    }

    protected function resolvedAddRate(int $typeId): ?PaymentRate
    {
        $classLevel = $this->prospectiveStudent->schoolClass?->level;
        $academicYear = $this->prospectiveStudent->academicYear;

        if ($classLevel === null || $academicYear === null) {
            return null;
        }

        $type = PaymentType::query()->find($typeId);

        if ($type === null) {
            return null;
        }

        $targetDate = AcademicYearSupport::fromDate($academicYear->start_date)->startDate();

        return app(BillGenerationService::class)->resolveRate($type, $classLevel, $targetDate);
    }

    protected function addTypeAvailableForLevel(int $typeId): bool
    {
        $level = $this->prospectiveStudent->target_level;

        if ($level === null) {
            return false;
        }

        return PaymentTypeSchoolLevel::query()
            ->where('payment_type_id', $typeId)
            ->where('school_level', $level)
            ->where('is_active', true)
            ->exists();
    }

    protected function prospectiveAddPaymentTypes()
    {
        $level = $this->prospectiveStudent->target_level;

        return PaymentType::query()
            ->where('is_active', true)
            ->where('audience', PaymentTypeAudience::ProspectiveStudent)
            ->when($level !== null, fn ($query) => $query->whereHas(
                'paymentTypeSchoolLevels',
                fn ($query) => $query->where('school_level', $level)->where('is_active', true)
            ))
            ->orderBy('name')
            ->get();
    }

    public function openEditProfile(): void
    {
        if ($this->prospectiveStudent->isConverted()) {
            session()->flash('error', 'Calon siswa yang sudah dikonversi tidak dapat diubah.');

            return;
        }

        $this->nama_lengkap = $this->prospectiveStudent->nama_lengkap;
        $this->nama_panggilan = $this->prospectiveStudent->nama_panggilan ?? '';
        $this->jenis_kelamin = $this->prospectiveStudent->jenis_kelamin ?? '';
        $this->nama_orang_tua = $this->prospectiveStudent->nama_orang_tua ?? '';
        $this->no_telp_orang_tua = $this->prospectiveStudent->no_telp_orang_tua ?? '';
        $this->alamat = $this->prospectiveStudent->alamat ?? '';
        $this->academic_year_id = (string) $this->prospectiveStudent->academic_year_id;
        $this->school_class_id = $this->prospectiveStudent->school_class_id !== null ? (string) $this->prospectiveStudent->school_class_id : '';
        $this->notes = $this->prospectiveStudent->notes ?? '';
        $this->resetValidation();
        $this->isEditProfileOpen = true;
    }

    public function closeEditProfile(): void
    {
        $this->isEditProfileOpen = false;
        $this->reset([
            'nama_lengkap',
            'nama_panggilan',
            'jenis_kelamin',
            'nama_orang_tua',
            'no_telp_orang_tua',
            'alamat',
            'academic_year_id',
            'school_class_id',
            'notes',
        ]);
        $this->resetValidation();
    }

    public function saveProfile(ProspectiveStudentBillGenerationService $billGenerationService): void
    {
        if ($this->prospectiveStudent->isConverted()) {
            session()->flash('error', 'Calon siswa yang sudah dikonversi tidak dapat diubah.');
            $this->closeEditProfile();

            return;
        }

        $validated = $this->validate(
            ProspectiveStudent::profileRules(),
            ProspectiveStudent::profileMessages()
        );
        $validated = $this->normalizeNullableFields($validated);

        $this->prospectiveStudent->update($validated);
        $billGenerationService->sync($this->prospectiveStudent);

        $this->closeEditProfile();
        session()->flash('success', 'Data calon siswa berhasil diperbarui.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeNullableFields(array $data): array
    {
        foreach (['nama_panggilan', 'jenis_kelamin', 'nama_orang_tua', 'no_telp_orang_tua', 'alamat', 'notes'] as $field) {
            $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
        }

        $data['school_class_id'] = filled($data['school_class_id']) ? (int) $data['school_class_id'] : null;

        return $data;
    }

    public function render()
    {
        $this->prospectiveStudent->unsetRelation('bills');
        $this->prospectiveStudent->unsetRelation('payments');

        $deletingPayment = $this->isDeletePaymentModalOpen && $this->deletingPaymentId
            ? ProspectiveStudentPayment::query()->with('prospectiveStudent')->find($this->deletingPaymentId)
            : null;

        return view('livewire.prospective-payment-workspace', [
            'prospectiveStudent' => $this->prospectiveStudent->load([
                'bills.paymentType',
                'bills.paymentDetails.payment',
                'payments.bank',
                'payments.details.bill',
                'payments.details.paymentType',
            ]),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'schoolClasses' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(),
            'deletingPayment' => $deletingPayment,
            'prospectivePaymentTypes' => $this->prospectiveAddPaymentTypes(),
            'addAcademicYearOptions' => AcademicYear::query()
                ->orderByDesc('start_date')
                ->pluck('year')
                ->all(),
        ]);
    }
}
