<?php

namespace App\Livewire;

use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentCorrectionLog;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PaymentEdit extends Component
{
    public $paymentId;

    public $bank_id;

    public $payment_date;

    public $description = '';

    public $selectedBillIds = [];

    public $selectedBillAmounts = [];

    public $showAddBill = false;

    public function mount($id): void
    {
        $payment = Payment::with('details')->findOrFail($id);

        if ($payment->isCancelled()) {
            abort(403, 'Pembayaran yang sudah dibatalkan tidak dapat diedit.');
        }

        if ($payment->isManualPayment()) {
            abort(403, 'Pembayaran manual belum dapat diedit. Batalkan transaksi jika diperlukan.');
        }

        $this->paymentId = $payment->id;
        $this->bank_id = $payment->bank_id;
        $this->payment_date = $payment->payment_date->format('Y-m-d');
        $this->description = $payment->description ?? '';

        foreach ($payment->details as $detail) {
            if ($detail->bill_id !== null) {
                $this->selectedBillIds[] = $detail->bill_id;
                $this->selectedBillAmounts[$detail->bill_id] = (int) round((float) $detail->amount);
            }
        }

        $this->selectedBillIds = array_values(array_unique(array_map('intval', $this->selectedBillIds)));
    }

    public function updatedSelectedBillIds($value): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', (array) $this->selectedBillIds)));

        $payment = Payment::with('details')->findOrFail($this->paymentId);
        $currentByBill = $payment->details->keyBy('bill_id');

        $bills = StudentBill::with('paymentType')
            ->where('student_id', $payment->student_id)
            ->whereIn('id', $selectedIds)
            ->get()
            ->keyBy('id');

        foreach (array_keys($this->selectedBillAmounts) as $id) {
            if (! in_array((int) $id, $selectedIds, true)) {
                unset($this->selectedBillAmounts[$id]);
            }
        }

        foreach ($selectedIds as $id) {
            if (! array_key_exists($id, $this->selectedBillAmounts)) {
                $bill = $bills->get($id);
                if ($bill) {
                    $current = $currentByBill->get($id);
                    $maxAmount = round((float) $bill->remaining_amount + (float) ($current->amount ?? 0), 2);
                    $this->selectedBillAmounts[$id] = (int) round($maxAmount);
                }
            }
        }

        $this->selectedBillIds = $selectedIds;
    }

    public function removeBill(int $billId): void
    {
        $this->selectedBillIds = array_values(array_filter(
            array_map('intval', $this->selectedBillIds),
            fn (int $id) => $id !== $billId,
        ));
        unset($this->selectedBillAmounts[$billId]);
    }

    public function addBill(int $billId): void
    {
        if (in_array($billId, $this->selectedBillIds, true)) {
            return;
        }

        $payment = Payment::with('details')->findOrFail($this->paymentId);
        $bill = StudentBill::with('paymentType')->find($billId);

        if (! $bill || $bill->student_id !== $payment->student_id) {
            return;
        }

        $this->selectedBillIds[] = $billId;
        $this->selectedBillAmounts[$billId] = (int) round((float) $bill->remaining_amount);

        $this->selectedBillIds = array_values(array_unique(array_map('intval', $this->selectedBillIds)));
    }

    public function toggleAddBill(): void
    {
        $this->showAddBill = ! $this->showAddBill;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildCandidateBills(Payment $payment): array
    {
        $currentByBill = $payment->details->keyBy('bill_id');

        $bills = StudentBill::with('paymentType')
            ->where('student_id', $payment->student_id)
            ->get()
            ->filter(fn (StudentBill $bill) => $bill->remaining_amount > 0 || $currentByBill->has($bill->id));

        return $bills
            ->map(function (StudentBill $bill) use ($currentByBill) {
                $current = $currentByBill->get($bill->id);
                $currentAmount = $current ? (float) $current->amount : 0.0;

                return [
                    'id' => $bill->id,
                    'payment_type_name' => $bill->paymentType->name ?? '—',
                    'period' => $bill->period_label,
                    'billing_frequency' => $bill->billing_frequency,
                    'amount' => $bill->effective_amount,
                    'remaining_amount' => $bill->remaining_amount,
                    'current_amount' => $currentAmount,
                    'max_amount' => round($bill->remaining_amount + $currentAmount, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Bills that are not yet selected but can be added.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getAddableBills(Payment $payment): array
    {
        return array_values(array_filter(
            $this->buildCandidateBills($payment),
            fn (array $bill) => ! in_array($bill['id'], $this->selectedBillIds, true),
        ));
    }

    public function totalAmount(): int
    {
        $total = 0;

        foreach (array_map('intval', (array) $this->selectedBillIds) as $id) {
            $total += (int) round((float) ($this->selectedBillAmounts[$id] ?? 0));
        }

        return $total;
    }

    public function save(): void
    {
        $this->validate([
            'bank_id' => 'required|exists:banks,id',
            'payment_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ], [
            'bank_id.required' => 'Silakan pilih bank penerima.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
        ]);

        DB::transaction(function () {
            /** @var Payment|null $payment */
            $payment = Payment::with('details')->lockForUpdate()->find($this->paymentId);

            if (! $payment || $payment->isCancelled()) {
                throw ValidationException::withMessages([
                    'bank_id' => 'Pembayaran sudah dibatalkan dan tidak dapat diedit.',
                ]);
            }

            if ($payment->isManualPayment()) {
                throw ValidationException::withMessages([
                    'bank_id' => 'Pembayaran manual tidak dapat diproses melalui editor tagihan.',
                ]);
            }

            $selectedIds = array_values(array_unique(array_map('intval', (array) $this->selectedBillIds)));

            if (empty($selectedIds)) {
                throw ValidationException::withMessages([
                    'selectedBillIds' => 'Pilih minimal satu tagihan.',
                ]);
            }

            $currentDetails = $payment->details()->get()->keyBy('bill_id');
            $bills = StudentBill::with('paymentType')
                ->where('student_id', $payment->student_id)
                ->get()
                ->keyBy('id');

            $after = [];
            foreach ($selectedIds as $billId) {
                $bill = $bills->get($billId);

                if (! $bill) {
                    throw ValidationException::withMessages([
                        'selectedBillIds' => 'Ada tagihan yang tidak valid atau bukan milik siswa terpilih.',
                    ]);
                }

                $amount = (float) ($this->selectedBillAmounts[$billId] ?? 0);
                $currentAmount = (float) ($currentDetails->get($billId)->amount ?? 0);
                $maxAmount = round((float) $bill->remaining_amount + $currentAmount, 2);

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'selectedBillAmounts.'.$billId => 'Nominal pembayaran harus lebih dari 0.',
                    ]);
                }

                if ($amount > $maxAmount) {
                    throw ValidationException::withMessages([
                        'selectedBillAmounts.'.$billId => 'Nominal tidak boleh melebihi sisa tagihan (Rp '.number_format($maxAmount, 0, ',', '.').').',
                    ]);
                }

                $after[$billId] = $amount;
            }

            $before = PaymentCorrectionLog::snapshot($payment, $currentDetails->values());

            foreach ($currentDetails as $billId => $detail) {
                if (! array_key_exists($billId, $after)) {
                    $detail->delete();
                }
            }

            foreach ($after as $billId => $amount) {
                $detail = $currentDetails->get($billId);

                if ($detail) {
                    if ((float) $detail->amount !== $amount) {
                        $detail->update(['amount' => $amount]);
                    }

                    continue;
                }

                $bill = $bills->get($billId);

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

            $newDetails = $payment->details()->get();
            $total = round((float) $newDetails->sum('amount'), 2);

            $payment->update([
                'bank_id' => $this->bank_id,
                'payment_date' => $this->payment_date,
                'total_amount' => $total,
                'description' => trim($this->description) !== '' ? trim($this->description) : null,
            ]);

            PaymentCorrectionLog::create([
                'payment_id' => $payment->id,
                'action' => PaymentCorrectionLog::ACTION_CORRECT,
                'reason' => 'Edit pembayaran',
                'before_data' => $before,
                'after_data' => PaymentCorrectionLog::snapshot($payment, $newDetails),
                'corrected_by' => auth()->id(),
            ]);

            $this->dispatch('payment-saved', studentId: $payment->student_id);

            session()->flash('success', 'Pembayaran berhasil diperbarui.');
            $this->redirectRoute('pembayaran.show', ['id' => $payment->id]);
        });
    }

    public function render()
    {
        $payment = Payment::with(['student.schoolClass', 'bank', 'user', 'details.paymentType', 'details.bill'])
            ->findOrFail($this->paymentId);

        return view('livewire.payment.edit', [
            'payment' => $payment,
            'candidateBills' => $this->buildCandidateBills($payment),
            'addableBills' => $this->getAddableBills($payment),
            'banks' => Bank::orderBy('name')->get(),
            'selectedIds' => array_map('intval', (array) $this->selectedBillIds),
            'totalAmount' => $this->totalAmount(),
        ]);
    }
}
