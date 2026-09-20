<?php

namespace App\Livewire;

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Services\DaycarePaymentDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class DaycarePaymentEntry extends Component
{
    use WithPagination;

    #[Url(as: 'child')]
    public ?int $selectedChildId = null;

    #[Url(as: 'tab')]
    public string $activeTab = 'pembayaran';

    public string $search = '';

    public string $historySearch = '';

    public string $bankId = '';

    public string $status = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $summaryPreset = 'all';

    public string $summaryStartDate = '';

    public string $summaryEndDate = '';

    public bool $isDeleteModalOpen = false;

    public ?int $deletingPaymentId = null;

    public string $deletingReceiptNumber = '';

    public function mount(): void
    {
        if (! in_array($this->activeTab, ['pembayaran', 'history'], true)) {
            $this->activeTab = 'pembayaran';
        }

        if ($this->selectedChildId === null) {
            return;
        }

        $childId = $this->selectedChildId;
        $this->selectedChildId = null;
        $this->selectChild($childId);
    }

    public function selectChild(int $childId): void
    {
        if (DaycareChild::query()->whereKey($childId)->doesntExist()) {
            return;
        }

        $this->selectedChildId = $childId;
        $this->search = '';
        $this->resetPage('historyPage');
    }

    public function changeChild(): void
    {
        $this->selectedChildId = null;
        $this->search = '';
        $this->resetPage('historyPage');
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['pembayaran', 'history'], true)) {
            return;
        }

        $this->activeTab = $tab;

        if ($tab !== 'history') {
            $this->cancelDelete();
        }
    }

    public function updatedHistorySearch(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedBankId(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedStartDate(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedEndDate(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedSummaryPreset(string $value): void
    {
        if ($value === 'today') {
            $this->summaryStartDate = now()->toDateString();
            $this->summaryEndDate = now()->toDateString();

            return;
        }

        if ($value === 'yesterday') {
            $yesterday = now()->subDay()->toDateString();
            $this->summaryStartDate = $yesterday;
            $this->summaryEndDate = $yesterday;

            return;
        }

        if ($value === 'this_month') {
            $this->summaryStartDate = now()->startOfMonth()->toDateString();
            $this->summaryEndDate = now()->toDateString();

            return;
        }

        if ($value === 'all') {
            $this->summaryStartDate = '';
            $this->summaryEndDate = '';
        }
    }

    public function updatedSummaryStartDate(): void
    {
        $this->summaryPreset = 'manual';
    }

    public function updatedSummaryEndDate(): void
    {
        $this->summaryPreset = 'manual';
    }

    public function confirmDelete(int $paymentId): void
    {
        if ($this->activeTab !== 'history') {
            return;
        }

        $payment = DaycarePayment::query()->find($paymentId);

        if ($payment === null) {
            return;
        }

        $this->deletingPaymentId = $payment->id;
        $this->deletingReceiptNumber = $payment->receipt_number;
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete(): void
    {
        $this->reset(['isDeleteModalOpen', 'deletingPaymentId', 'deletingReceiptNumber']);
    }

    public function delete(DaycarePaymentDeletionService $deletionService): void
    {
        if ($this->deletingPaymentId === null) {
            return;
        }

        if (DaycarePayment::query()->whereKey($this->deletingPaymentId)->doesntExist()) {
            $this->cancelDelete();

            return;
        }

        $deletionService->delete($this->deletingPaymentId);
        $this->cancelDelete();
        $this->resetPage('historyPage');
        session()->flash('success', 'Transaksi Daycare berhasil dihapus.');
    }

    public function render(): mixed
    {
        /** @var Collection<int, DaycareChild> $searchResults */
        $searchResults = new Collection;

        $search = trim($this->search);

        if ($this->selectedChildId === null && mb_strlen($search) >= 2) {
            $searchTerm = '%'.$search.'%';

            /** @var Collection<int, DaycareChild> $searchResults */
            $searchResults = DaycareChild::query()
                ->where(function ($query) use ($searchTerm): void {
                    $query->where('nama_lengkap', 'like', $searchTerm)
                        ->orWhere('nama_panggilan', 'like', $searchTerm);
                })
                ->orderBy('nama_lengkap')
                ->orderBy('id')
                ->limit(8)
                ->get();
        }

        $selectedChild = $this->selectedChildId === null
            ? null
            : DaycareChild::query()->find($this->selectedChildId);

        /** @var LengthAwarePaginator<int, DaycarePayment>|null $historyPayments */
        $historyPayments = $this->activeTab === 'history'
            ? $this->historyPayments()
            : null;

        $summary = $this->summaryTotals();

        return view('livewire.daycare-payment-entry', [
            'searchResults' => $searchResults,
            'selectedChild' => $selectedChild,
            'historyPayments' => $historyPayments,
            'banks' => Bank::query()->orderBy('name')->get(),
            'summaryTotal' => $summary['total_amount'],
            'summaryCount' => $summary['total_count'],
        ]);
    }

    /** @return LengthAwarePaginator<int, DaycarePayment> */
    private function historyPayments(): LengthAwarePaginator
    {
        $search = trim($this->historySearch);

        $query = DaycarePayment::query()->with(['bank', 'details', 'child', 'creator']);

        if ($search !== '') {
            $term = '%'.$search.'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('receipt_number', 'like', $term)
                    ->orWhereHas('child', function (Builder $query) use ($term): void {
                        $query->where('nama_lengkap', 'like', $term)
                            ->orWhere('nama_panggilan', 'like', $term);
                    });
            });
        }

        return $query
            ->when($this->bankId !== '', fn (Builder $query) => $query->where('bank_id', $this->bankId))
            ->when($this->status === 'cancelled', fn (Builder $query) => $query->whereKey(0))
            ->when($this->startDate !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'historyPage');
    }

    /**
     * @return array{total_amount: float, total_count: int}
     */
    private function summaryTotals(): array
    {
        $this->resetValidation(['summaryStartDate', 'summaryEndDate']);

        $rules = [
            'summaryStartDate' => ['nullable'],
            'summaryEndDate' => ['nullable'],
        ];

        if ($this->summaryStartDate !== '') {
            $rules['summaryStartDate'][] = 'date';
        }

        if ($this->summaryEndDate !== '') {
            $rules['summaryEndDate'][] = 'date';
        }

        try {
            $this->validate($rules, [
                'summaryStartDate.date' => 'Tanggal mulai tidak valid.',
                'summaryEndDate.date' => 'Tanggal akhir tidak valid.',
            ]);
        } catch (ValidationException) {
            return ['total_amount' => 0.0, 'total_count' => 0];
        }

        $start = $this->summaryStartDate;
        $end = $this->summaryEndDate;

        if ($this->summaryPreset === 'today') {
            $start = now()->toDateString();
            $end = $start;
        } elseif ($this->summaryPreset === 'yesterday') {
            $start = now()->subDay()->toDateString();
            $end = $start;
        } elseif ($this->summaryPreset === 'this_month') {
            $start = now()->startOfMonth()->toDateString();
            $end = now()->toDateString();
        }

        if ($start !== '' && $end !== '' && $end < $start) {
            $this->addError('summaryEndDate', 'Tanggal akhir tidak boleh sebelum tanggal mulai.');

            return ['total_amount' => 0.0, 'total_count' => 0];
        }

        $row = DaycarePayment::query()
            ->when($start !== '', static fn (Builder $query) => $query->whereDate('payment_date', '>=', $start))
            ->when($end !== '', static fn (Builder $query) => $query->whereDate('payment_date', '<=', $end))
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_amount')
            ->selectRaw('COUNT(*) AS total_count')
            ->first();

        return [
            'total_amount' => (float) ($row->total_amount ?? 0),
            'total_count' => (int) ($row->total_count ?? 0),
        ];
    }
}
