<?php

namespace App\Livewire;

use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\StudentReceiptNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class PaymentCreate extends Component
{
    use WithFileUploads;

    // Data Siswa
    #[Url(as: 'student')]
    public $studentId = null;

    public $student_search = '';

    public $selected_student_id = null;

    public $selectedStudentData = null;

    // Tagihan outstanding siswa yang dipilih (belum lunas)
    public $outstandingBills = [];

    // Tagihan yang dipilih untuk dibayar: daftar ID tagihan.
    public $selectedBillIds = [];

    // Nominal yang dibayar per tagihan terpilih: [bill_id => nominal].
    public $selectedBillAmounts = [];

    // Informasi Transaksi
    public $bank_id = '';

    public $payment_date = '';

    public $receipt_file = null;

    public $description = '';

    public function mount()
    {
        $this->payment_date = now()->format('Y-m-d');

        // Set default bank
        $firstBank = Bank::first();
        if ($firstBank) {
            $this->bank_id = $firstBank->id;
        }

        $studentId = filter_var($this->studentId, FILTER_VALIDATE_INT);

        if ($studentId !== false && $studentId > 0) {
            $this->selectStudent($studentId);
        } else {
            $this->studentId = null;
        }
    }

    public function selectStudent(int $id)
    {
        $student = Student::with(['schoolClass', 'enrollments.academicYear'])->find($id);
        if ($student) {
            $this->studentId = $student->id;
            $this->selected_student_id = $student->id;
            $this->selectedStudentData = [
                'id' => $student->id,
                'nama_lengkap' => $student->nama_lengkap,
                'nis' => $student->nis,
                'class_name' => $student->schoolClass->name ?? '—',
                'class_level' => $student->schoolClass->level ?? null,
                'school_level' => $student->schoolLevel?->value ?? '—',
                'academic_status' => $student->academic_status_label,
                'academic_year' => $student->academicYearContextLabel() ?? '—',
            ];
            $this->student_search = '';

            $this->resetSelection();

            $this->loadOutstandingBills();
        }
    }

    public function clearStudent()
    {
        $this->studentId = null;
        $this->selected_student_id = null;
        $this->selectedStudentData = null;
        $this->student_search = '';
        $this->outstandingBills = [];
        $this->resetSelection();
    }

    protected function resetSelection(): void
    {
        $this->selectedBillIds = [];
        $this->selectedBillAmounts = [];
    }

    /**
     * Load tagihan siswa yang belum lunas (outstanding).
     *
     * student_bills adalah sumber kebenaran kewajiban siswa. SEMUA tagihan
     * dengan sisa > 0 ditampilkan — termasuk tagihan manual (mis. Jemputan/
     * Lain-lain) yang jenisnya tidak punya pengaturan pembayaran aktif.
     * StudentPaymentSettings TIDAK ikut memfilter daftar ini.
     */
    protected function loadOutstandingBills(): void
    {
        $this->outstandingBills = [];

        if (! $this->selected_student_id) {
            return;
        }

        $this->outstandingBills = StudentBill::query()
            ->where('student_id', $this->selected_student_id)
            ->with(['paymentType', 'paymentDetails.payment'])
            ->get()
            ->filter(fn (StudentBill $bill) => $bill->remaining_amount > 0)
            ->map(function (StudentBill $bill) {
                return [
                    'id' => $bill->id,
                    'payment_type_id' => $bill->payment_type_id,
                    'payment_type_name' => $bill->paymentType->name ?? '—',
                    'period' => $bill->period_label,
                    'period_month' => $bill->period_month,
                    'period_year' => $bill->period_year,
                    'academic_year' => $bill->academic_year,
                    'billing_frequency' => $bill->billing_frequency,
                    'amount' => $bill->effective_amount,
                    'paid_amount' => $bill->paid_amount,
                    'remaining_amount' => $bill->remaining_amount,
                ];
            })
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

    /**
     * Kelompokkan tagihan outstanding menurut periode asalnya:
     * bulanan (per bulan), tahunan (per tahun ajaran), sekali bayar.
     *
     * @return array<int, array{key:string,label:string,type:string,bills:array}>
     */
    protected function buildBillGroups(): array
    {
        $groups = [];

        foreach ($this->outstandingBills as $bill) {
            // One-time bills: always in one-time group regardless of academic_year.
            if ($bill['billing_frequency'] === 'one_time') {
                $key = 'one-time';
                $label = 'Tagihan Sekali Bayar';
                $type = 'one-time';
            } elseif ($bill['academic_year'] !== null) {
                $key = 'yearly:'.$bill['academic_year'];
                $label = 'Tahun Ajaran '.$bill['academic_year'];
                $type = 'yearly';
            } elseif ($bill['period_month'] !== null && $bill['period_year'] !== null) {
                $key = 'monthly:'.sprintf('%d-%02d', $bill['period_year'], $bill['period_month']);
                $label = 'Tagihan '.Carbon::createFromDate($bill['period_year'], $bill['period_month'], 1)
                    ->locale('id')
                    ->translatedFormat('F Y');
                $type = 'monthly';
            } else {
                $key = 'one-time';
                $label = 'Tagihan Sekali Bayar';
                $type = 'one-time';
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => $type,
                    'bills' => [],
                ];
            }

            $groups[$key]['bills'][] = $bill;
        }

        $grouped = collect($groups);

        $monthly = $grouped->where('type', 'monthly')->sortBy('key');
        $yearly = $grouped->where('type', 'yearly')->sortBy('key');
        $oneTime = $grouped->where('type', 'one-time');

        return $monthly->merge($yearly)->merge($oneTime)->values()->all();
    }

    /**
     * Total pembayaran dari semua tagihan terpilih.
     */
    public function totalSelectedAmount(): int
    {
        $selectedIds = array_map('intval', (array) $this->selectedBillIds);

        $total = 0;
        foreach ($selectedIds as $id) {
            $total += (int) round((float) ($this->selectedBillAmounts[$id] ?? 0));
        }

        return $total;
    }

    public function save(StudentReceiptNumberGenerator $receiptNumberGenerator)
    {
        $this->validate([
            'selected_student_id' => 'required|exists:students,id',
            'bank_id' => 'required|exists:banks,id',
            'payment_date' => 'required|date',
            'selectedBillIds' => 'required|array|min:1',
            'selectedBillIds.*' => 'required|integer|distinct',
        ], [
            'selected_student_id.required' => 'Silakan cari dan pilih data siswa terlebih dahulu.',
            'bank_id.required' => 'Silakan pilih bank penerima.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'selectedBillIds.required' => 'Pilih minimal satu tagihan yang akan dibayar.',
            'selectedBillIds.min' => 'Pilih minimal satu tagihan yang akan dibayar.',
        ]);

        $selectedIds = array_values(array_unique(array_map('intval', $this->selectedBillIds)));

        /** @var Collection<int, StudentBill> $billById */
        $billById = StudentBill::with('paymentType')
            ->where('student_id', $this->selected_student_id)
            ->whereIn('id', $selectedIds)
            ->get()
            ->keyBy('id');

        foreach ($selectedIds as $id) {
            $bill = $billById->get($id);
            if (! $bill) {
                throw ValidationException::withMessages([
                    'selectedBillIds' => 'Ada tagihan yang tidak valid atau bukan milik siswa terpilih.',
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

        DB::transaction(function () use ($selectedIds, $billById, $receiptNumberGenerator) {
            $receiptNumber = $receiptNumberGenerator->next((int) now()->format('Y'));

            // Handle receipt file upload if any
            $receiptPath = null;
            if ($this->receipt_file) {
                $receiptPath = $this->receipt_file->store('receipts', 'public');
            }

            $totalAmount = 0;
            foreach ($selectedIds as $id) {
                $totalAmount += (float) ($this->selectedBillAmounts[$id] ?? 0);
            }

            // Summary description or custom user input
            $typeNames = collect($selectedIds)
                ->map(fn (int $id) => $billById->get($id)?->paymentType->name ?? 'Pembayaran')
                ->unique()
                ->values()
                ->implode(' + ');
            $finalDescription = ! empty(trim($this->description)) ? trim($this->description) : $typeNames;

            // Create Payment
            $payment = Payment::create([
                'receipt_number' => $receiptNumber,
                'payment_kind' => Payment::KIND_BILL,
                'student_id' => $this->selected_student_id,
                'bank_id' => $this->bank_id,
                'payment_date' => $this->payment_date,
                'total_amount' => $totalAmount,
                'payment_method' => 'transfer',
                'receipt' => $receiptPath,
                'description' => $finalDescription,
                'created_by' => auth()->id(),
            ]);

            // Create Payment Details — satu detail per tagihan terpilih,
            // menyalin periode asli dari tagihan.
            foreach ($selectedIds as $id) {
                $bill = $billById->get($id);
                $amount = (float) ($this->selectedBillAmounts[$id] ?? 0);

                PaymentDetail::create([
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'payment_type_id' => $bill->payment_type_id,
                    'period_month' => $bill->period_month,
                    'period_year' => $bill->period_year,
                    'academic_year' => $bill->academic_year,
                    'amount' => $amount,
                    'description' => ($bill->academic_year !== null || $bill->period_month !== null)
                        ? $bill->period_label
                        : null,
                ]);
            }

            $this->dispatch('payment-saved', studentId: (int) $this->selected_student_id);

            session()->flash('success', 'Pembayaran baru berhasil dicatat!');
            $this->redirectRoute('pembayaran.show', ['id' => $payment->id]);
        });
    }

    public function render()
    {
        $searchResults = [];
        if (mb_strlen(trim($this->student_search)) >= 2) {
            $searchTerm = '%'.trim($this->student_search).'%';
            $searchResults = Student::with(['schoolClass', 'enrollments.academicYear'])
                ->where(function ($query) use ($searchTerm): void {
                    $query->where('nama_lengkap', 'like', $searchTerm)
                        ->orWhere('nama_panggilan', 'like', $searchTerm)
                        ->orWhere('nis', 'like', $searchTerm);
                })
                ->orderBy('nama_lengkap')
                ->orderBy('id')
                ->limit(5)
                ->get();
        }

        return view('livewire.payment.create', [
            'searchResults' => $searchResults,
            'banks' => Bank::orderBy('name')->get(),
            'billGroups' => $this->buildBillGroups(),
            'selectedIds' => array_map('intval', (array) $this->selectedBillIds),
            'totalPembayaran' => $this->totalSelectedAmount(),
        ]);
    }
}
