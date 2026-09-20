<?php

namespace App\Livewire;

use App\Models\Bank;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Services\ProspectiveStudentReceiptNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ProspectivePaymentCreate extends Component
{
    use WithFileUploads;

    public ProspectiveStudent $prospectiveStudent;

    // Tagihan outstanding calon siswa yang belum lunas.
    public array $outstandingBills = [];

    // Tagihan terpilih untuk dibayar: daftar ID tagihan.
    public array $selectedBillIds = [];

    // Nominal yang dibayar per tagihan terpilih: [bill_id => nominal].
    public array $selectedBillAmounts = [];

    // Informasi Transaksi
    public string $bank_id = '';

    public string $payment_date = '';

    public $receipt_file = null;

    public string $description = '';

    public function mount(ProspectiveStudent $prospectiveStudent): void
    {
        $this->prospectiveStudent = $prospectiveStudent->load(['academicYear', 'schoolClass']);

        $this->payment_date = now()->format('Y-m-d');

        $firstBank = Bank::query()->orderBy('name')->first();
        if ($firstBank) {
            $this->bank_id = (string) $firstBank->id;
        }

        $this->loadOutstandingBills();
    }

    /**
     * Load tagihan pendaftaran calon siswa yang belum lunas (remaining > 0).
     */
    protected function loadOutstandingBills(): void
    {
        $this->outstandingBills = ProspectiveStudentBill::query()
            ->where('prospective_student_id', $this->prospectiveStudent->id)
            ->with(['paymentType', 'paymentDetails.payment'])
            ->get()
            ->filter(fn (ProspectiveStudentBill $bill) => $bill->remaining_amount > 0)
            ->map(fn (ProspectiveStudentBill $bill): array => [
                'id' => $bill->id,
                'payment_type_id' => $bill->payment_type_id,
                'payment_type_name' => $bill->paymentType?->name ?? 'Formulir Pendaftaran',
                'academic_year' => $bill->academic_year,
                'amount' => (float) $bill->amount,
                'paid_amount' => $bill->paid_amount,
                'remaining_amount' => $bill->remaining_amount,
            ])
            ->values()
            ->all();
    }

    /**
     * Jaga agar nominal per tagihan selalu sinkron dengan tagihan terpilih.
     * Tagihan yang baru dicentang otomatis bernominal = sisa tagihan,
     * tagihan yang tidak lagi dipilih dihapus dari daftar nominal.
     */
    public function updatedSelectedBillIds($value): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', (array) $this->selectedBillIds)));
        $billById = collect($this->outstandingBills)->keyBy('id');

        foreach (array_keys($this->selectedBillAmounts) as $id) {
            if (! in_array((int) $id, $selectedIds, true)) {
                unset($this->selectedBillAmounts[$id]);
            }
        }

        foreach ($selectedIds as $id) {
            if (! array_key_exists($id, $this->selectedBillAmounts)) {
                $bill = $billById->get($id);
                if ($bill) {
                    $this->selectedBillAmounts[$id] = (int) round((float) $bill['remaining_amount']);
                }
            }
        }

        $this->selectedBillIds = $selectedIds;
    }

    public function totalSelectedAmount(): int
    {
        $selectedIds = array_map('intval', (array) $this->selectedBillIds);

        $total = 0;
        foreach ($selectedIds as $id) {
            $total += (int) round((float) ($this->selectedBillAmounts[$id] ?? 0));
        }

        return $total;
    }

    public function save(ProspectiveStudentReceiptNumberGenerator $receiptNumberGenerator): void
    {
        if (! $this->prospectiveStudent->canReceiveRegistrationPayment()) {
            throw ValidationException::withMessages([
                'selectedBillIds' => 'Calon siswa dengan status ini tidak dapat melakukan pembayaran pendaftaran.',
            ]);
        }

        $this->validate([
            'bank_id' => 'required|exists:banks,id',
            'payment_date' => 'required|date',
            'selectedBillIds' => 'required|array|min:1',
            'selectedBillIds.*' => 'required|integer|distinct',
        ], [
            'bank_id.required' => 'Silakan pilih bank penerima.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'selectedBillIds.required' => 'Pilih minimal satu tagihan yang akan dibayar.',
            'selectedBillIds.min' => 'Pilih minimal satu tagihan yang akan dibayar.',
        ]);

        $selectedIds = array_values(array_unique(array_map('intval', $this->selectedBillIds)));

        DB::transaction(function () use ($selectedIds, $receiptNumberGenerator): void {
            $billById = ProspectiveStudentBill::query()
                ->with('paymentType')
                ->where('prospective_student_id', $this->prospectiveStudent->id)
                ->whereIn('id', $selectedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($selectedIds as $id) {
                $bill = $billById->get($id);

                if (! $bill) {
                    throw ValidationException::withMessages([
                        'selectedBillIds' => 'Ada tagihan yang tidak valid atau bukan milik calon siswa terpilih.',
                    ]);
                }

                $amount = (float) ($this->selectedBillAmounts[$id] ?? 0);
                $remaining = (float) $bill->remaining_amount;

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'selectedBillAmounts.'.$id => 'Nominal pembayaran harus lebih dari 0.',
                    ]);
                }

                if ($amount > $remaining) {
                    throw ValidationException::withMessages([
                        'selectedBillAmounts.'.$id => 'Nominal tidak boleh melebihi sisa tagihan (Rp '.number_format($remaining, 0, ',', '.').').',
                    ]);
                }
            }

            $receiptNumber = $receiptNumberGenerator->next((int) now()->format('Y'));

            $receiptPath = null;
            if ($this->receipt_file) {
                $receiptPath = $this->receipt_file->store('receipts', 'public');
            }

            $totalAmount = 0;
            foreach ($selectedIds as $id) {
                $totalAmount += (float) ($this->selectedBillAmounts[$id] ?? 0);
            }

            $typeNames = collect($selectedIds)
                ->map(fn (int $id) => $billById->get($id)?->paymentType?->name ?? 'Pembayaran')
                ->unique()
                ->values()
                ->implode(' + ');
            $finalDescription = ! empty(trim($this->description)) ? trim($this->description) : $typeNames;

            $payment = ProspectiveStudentPayment::create([
                'receipt_number' => $receiptNumber,
                'prospective_student_id' => $this->prospectiveStudent->id,
                'bank_id' => $this->bank_id,
                'payment_date' => $this->payment_date,
                'total_amount' => $totalAmount,
                'receipt' => $receiptPath,
                'description' => $finalDescription,
                'created_by' => auth()->id(),
            ]);

            foreach ($selectedIds as $id) {
                $bill = $billById->get($id);

                ProspectiveStudentPaymentDetail::create([
                    'prospective_student_payment_id' => $payment->id,
                    'prospective_student_bill_id' => $bill->id,
                    'payment_type_id' => $bill->payment_type_id,
                    'amount' => (float) ($this->selectedBillAmounts[$id] ?? 0),
                    'description' => $bill->academic_year !== null ? 'Tahun Ajaran '.$bill->academic_year : null,
                ]);
            }

            $this->dispatch('prospective-payment-saved', prospectiveStudentId: (int) $this->prospectiveStudent->id);

            session()->flash('success', 'Pembayaran calon siswa berhasil dicatat!');
            $this->redirectRoute('pembayaran.prospective.show', ['payment' => $payment->id]);
        });
    }

    public function render()
    {
        return view('livewire.prospective-payment-create', [
            'banks' => Bank::query()->orderBy('name')->get(),
            'selectedIds' => array_map('intval', (array) $this->selectedBillIds),
            'totalPembayaran' => $this->totalSelectedAmount(),
        ]);
    }
}
