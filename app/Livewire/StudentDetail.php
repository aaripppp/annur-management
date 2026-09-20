<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesStudentBills;
use App\Models\AcademicYear;
use App\Models\BillAdjustment;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\BillGenerationService;
use App\Support\BillbookPeriod;
use App\Support\StudentBillbook;
use App\Support\StudentRegistrationHistory;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class StudentDetail extends Component
{
    use ManagesStudentBills;

    public Student $student;

    public string $selectedAcademicYear = '';

    // Filter kategori untuk summary card. Hanya memengaruhi 3 kartu ringkasan;
    // buku tagihan di bawahnya selalu menampilkan semua tagihan. Nilai:
    // 'all' = semua tagihan, 'monthly' = tagihan bulanan, 'yearly' = tagihan
    // tahunan, 'one_time' = tagihan sekali bayar.
    public string $summaryCategory = 'all';

    // Generate Tagihan Sampai
    public bool $isGenerateUntilOpen = false;

    public string $generateUntilMonth = '';

    // Generate Buku Tagihan
    public bool $isBillbookOpen = false;

    public string $billbookStartMonth = '';

    // Penyesuaian Tagihan (Diskon)
    public bool $isAdjustmentOpen = false;

    public ?int $adjustmentBillId = null;

    public string $adjustmentType = 'discount';

    public string $adjustmentAmount = '';

    public string $adjustmentReason = '';

    public ?string $adjustmentCreatedByName = null;

    public ?string $adjustmentCreatedAt = null;

    public ?string $adjustmentPeriodLabel = null;

    public ?string $adjustmentTypeName = null;

    public function mount(Student $student): void
    {
        $this->student = $student;

        $latestEnrollment = $student->enrollments()
            ->with('academicYear')
            ->orderByDesc('academic_year_id')
            ->first();

        $this->selectedAcademicYear = $latestEnrollment?->academicYear?->year
            ?? AcademicYear::active()?->year
            ?? '';
    }

    public function updatedSelectedAcademicYear(): void
    {
        $this->summaryCategory = 'all';
    }

    public function generateBills(): void
    {
        $created = app(BillGenerationService::class)->generateForStudent($this->student);

        if (count($created) > 0) {
            session()->flash('success', count($created).' tagihan berhasil dibuat untuk periode '.now()->translatedFormat('F Y').'.');
        } else {
            session()->flash('info', 'Tidak ada tagihan baru — semua tagihan untuk periode '.now()->translatedFormat('F Y').' sudah tersedia.');
        }
    }

    public function openGenerateUntil(): void
    {
        $this->generateUntilMonth = now()->format('Y-m');
        $this->isGenerateUntilOpen = true;
    }

    public function closeGenerateUntil(): void
    {
        $this->isGenerateUntilOpen = false;
        $this->reset('generateUntilMonth');
    }

    public function generateUntilBills(): void
    {
        $this->validate([
            'generateUntilMonth' => 'required|date_format:Y-m',
        ], [
            'generateUntilMonth.required' => 'Pilih bulan tujuan.',
            'generateUntilMonth.date_format' => 'Format bulan tujuan tidak valid.',
        ]);

        $target = Carbon::parse($this->generateUntilMonth.'-01');

        $created = app(BillGenerationService::class)->generateUntil($this->student, $target);

        $this->closeGenerateUntil();
        $this->refreshBills();

        $monthLabel = $target->locale('id')->translatedFormat('F Y');

        if (count($created) > 0) {
            session()->flash('success', 'Tagihan berhasil dibuat sampai '.$monthLabel.'. ('.count($created).' tagihan baru)');
        } else {
            session()->flash('info', 'Tidak ada tagihan baru — semua tagihan sampai '.$monthLabel.' sudah tersedia.');
        }
    }

    public function openBillbook(): void
    {
        $this->billbookStartMonth = BillbookPeriod::startDate()->format('Y-m');
        $this->isBillbookOpen = true;
    }

    public function closeBillbook(): void
    {
        $this->isBillbookOpen = false;
        $this->reset('billbookStartMonth');
    }

    public function generateBillbook(): void
    {
        $this->validate([
            'billbookStartMonth' => 'required|date_format:Y-m',
        ], [
            'billbookStartMonth.required' => 'Pilih bulan awal buku tagihan.',
            'billbookStartMonth.date_format' => 'Format bulan awal tidak valid.',
        ]);

        $start = Carbon::parse($this->billbookStartMonth.'-01');

        BillbookPeriod::setStartMonth($start);

        $created = app(BillGenerationService::class)->generateBillbook($this->student, $start);

        $this->closeBillbook();
        $this->refreshBills();

        $end = BillbookPeriod::endDateFor($start);
        $startLabel = $start->locale('id')->translatedFormat('F Y');
        $endLabel = $end->locale('id')->translatedFormat('F Y');

        if (count($created) > 0) {
            session()->flash('success', 'Buku tagihan dibuat untuk '.$startLabel.' sampai '.$endLabel.'. ('.count($created).' tagihan baru)');
        } else {
            session()->flash('info', 'Buku tagihan sudah lengkap untuk '.$startLabel.' sampai '.$endLabel.'. Tidak ada tagihan baru.');
        }
    }

    public function editAdjustment(int $billId): void
    {
        $bill = StudentBill::with(['adjustments.creator', 'paymentType'])->find($billId);

        if (! $bill) {
            return;
        }

        $discount = $bill->adjustments->firstWhere('type', BillAdjustment::TYPE_DISCOUNT);

        $this->adjustmentBillId = $bill->id;
        $this->adjustmentType = BillAdjustment::TYPE_DISCOUNT;
        $this->adjustmentAmount = $discount ? (string) abs((float) $discount->amount) : '';
        $this->adjustmentReason = $discount?->reason ?? '';
        $this->adjustmentCreatedByName = $discount?->creator?->name ?? null;
        $this->adjustmentCreatedAt = $discount?->created_at?->translatedFormat('d M Y H:i') ?? null;
        $this->adjustmentPeriodLabel = $bill->period_label;
        $this->adjustmentTypeName = $bill->paymentType->name ?? '—';

        $this->isAdjustmentOpen = true;
    }

    public function saveAdjustment(): void
    {
        $this->validate([
            'adjustmentType' => 'required|in:discount',
            'adjustmentAmount' => 'required|numeric|min:0',
            'adjustmentReason' => 'nullable|string|max:255',
        ], [
            'adjustmentType.required' => 'Jenis penyesuaian wajib dipilih.',
            'adjustmentType.in' => 'Jenis penyesuaian tidak valid.',
            'adjustmentAmount.required' => 'Nominal penyesuaian wajib diisi.',
            'adjustmentAmount.numeric' => 'Nominal penyesuaian harus berupa angka.',
            'adjustmentAmount.min' => 'Nominal penyesuaian tidak boleh negatif.',
            'adjustmentReason.max' => 'Alasan tidak boleh lebih dari 255 karakter.',
        ]);

        $bill = StudentBill::with('adjustments')->findOrFail($this->adjustmentBillId);

        $proposed = -abs((float) $this->adjustmentAmount);

        $otherAdjustments = round((float) $bill->adjustments
            ->where('type', '!=', BillAdjustment::TYPE_DISCOUNT)
            ->sum('amount'), 2);

        $effective = round((float) $bill->amount + $otherAdjustments + $proposed, 2);

        if ($effective < 0) {
            $this->addError('adjustmentAmount', 'Penyesuaian tidak boleh membuat tagihan efektif menjadi negatif.');

            return;
        }

        if ((float) $bill->paid_amount > $effective) {
            $this->addError(
                'adjustmentAmount',
                'Penyesuaian tidak boleh membuat tagihan efektif lebih rendah dari jumlah yang sudah dibayar (Rp '.number_format((float) $bill->paid_amount, 0, ',', '.').').'
            );

            return;
        }

        $discount = $bill->adjustments->firstWhere('type', BillAdjustment::TYPE_DISCOUNT);

        if ($discount) {
            $discount->update([
                'amount' => $proposed,
                'reason' => $this->adjustmentReason !== '' ? $this->adjustmentReason : null,
            ]);
        } else {
            $bill->adjustments()->create([
                'type' => BillAdjustment::TYPE_DISCOUNT,
                'amount' => $proposed,
                'reason' => $this->adjustmentReason !== '' ? $this->adjustmentReason : null,
                'created_by' => auth()->id(),
            ]);
        }

        $this->closeAdjustment();
        $this->refreshBills();

        session()->flash('success', 'Penyesuaian tagihan berhasil disimpan.');
    }

    public function removeAdjustment(): void
    {
        $bill = StudentBill::with('adjustments')->findOrFail($this->adjustmentBillId);

        $discount = $bill->adjustments->firstWhere('type', BillAdjustment::TYPE_DISCOUNT);

        if ($discount) {
            $discount->delete();
        }

        $this->closeAdjustment();
        $this->refreshBills();

        session()->flash('success', 'Penyesuaian tagihan berhasil dihapus.');
    }

    public function closeAdjustment(): void
    {
        $this->isAdjustmentOpen = false;
        $this->reset([
            'adjustmentBillId',
            'adjustmentType',
            'adjustmentAmount',
            'adjustmentReason',
            'adjustmentCreatedByName',
            'adjustmentCreatedAt',
            'adjustmentPeriodLabel',
            'adjustmentTypeName',
        ]);
    }

    /**
     * Merespons perubahan pengaturan pembayaran (dari StudentPaymentSettings)
     * dan pembayaran baru (dari PaymentCreate). Data selalu dibaca ulang dari
     * database di render(), sehingga komponen otomatis tampil segar.
     */
    #[On('student-payment-settings-updated')]
    #[On('payment-saved')]
    public function refreshBills(): void
    {
        $this->student->unsetRelation('bills');
    }

    protected function managedBillStudent(): ?Student
    {
        return $this->student;
    }

    public function render(): View
    {
        $billbook = StudentBillbook::build(
            $this->student,
            $this->selectedAcademicYear,
            'all'
        );

        $totals = $this->resolvedSummaryTotals($billbook);

        $academicYearSelectorOptions = AcademicYear::orderByDesc('year')->pluck('year', 'year')->toArray();

        $registrationHistory = StudentRegistrationHistory::forStudent($this->student);

        return view('livewire.student.detail', [
            'groupedMonthlyBills' => $billbook['groupedMonthlyBills'],
            'groupedYearlyBills' => $billbook['groupedYearlyBills'],
            'oneTimeBills' => $billbook['oneTimeBills'],
            'oneTimeStatus' => $billbook['oneTimeStatus'],
            'totalTagihan' => $totals['totalTagihan'],
            'totalDibayar' => $totals['totalDibayar'],
            'totalTunggakan' => $totals['totalTunggakan'],
            'academicYearSelectorOptions' => $academicYearSelectorOptions,
            'registeredProspect' => $registrationHistory['prospect'],
            'registrationSummary' => $registrationHistory['summary'],
            ...$this->manualBillFormData(),
        ]);
    }

    /**
     * Hitung nilai 3 kartu ringkasan sesuai kategori filter. Buku tagihan
     * (group) selalu menampilkan semua tagihan; hanya total kartu yang
     * bereaksi terhadap summaryCategory.
     *
     * @param  array<string, mixed>  $billbook
     * @return array{totalTagihan: float, totalDibayar: float, totalTunggakan: float}
     */
    private function resolvedSummaryTotals(array $billbook): array
    {
        $bills = match ($this->summaryCategory) {
            'monthly' => $billbook['groupedMonthlyBills']
                ->flatMap(fn (array $group): Collection => $group['bills']),
            'yearly' => $billbook['groupedYearlyBills']
                ->flatMap(fn (array $group): Collection => $group['bills']),
            'one_time' => $billbook['oneTimeBills'],
            default => null,
        };

        if ($bills !== null) {
            return [
                'totalTagihan' => $bills->sum(fn (StudentBill $bill): float => (float) $bill->effective_amount),
                'totalDibayar' => $bills->sum(fn (StudentBill $bill): float => (float) $bill->paid_amount),
                'totalTunggakan' => $bills->sum(fn (StudentBill $bill): float => (float) $bill->remaining_amount),
            ];
        }

        return [
            'totalTagihan' => $billbook['totalTagihan'],
            'totalDibayar' => $billbook['totalDibayar'],
            'totalTunggakan' => $billbook['totalTunggakan'],
        ];
    }
}
