<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Services\PaymentCancellationService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PaymentShow extends Component
{
    public int $paymentId;

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public bool $printMode = false;

    public function mount(int $id, bool $printMode = false): void
    {
        $this->paymentId = $id;
        $this->printMode = $printMode;
    }

    public function openCancelModal(): void
    {
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->resetErrorBag();
    }

    public function cancelPayment(PaymentCancellationService $cancellationService): void
    {
        $this->validate($cancellationService->rules(), $cancellationService->messages());

        $payment = $cancellationService->cancel(
            (int) $this->paymentId,
            $this->cancelReason,
            (int) auth()->id()
        );

        $this->showCancelModal = false;
        $this->cancelReason = '';

        $this->dispatch('payment-cancelled', paymentId: $payment->id);

        session()->flash('success', 'Pembayaran berhasil dibatalkan.');
    }

    public function render(): View
    {
        $payment = Payment::with(['student.schoolClass', 'bank', 'user', 'cancelledBy', 'details.paymentType', 'details.bill'])
            ->findOrFail($this->paymentId);

        return view('livewire.payment.show', [
            'payment' => $payment,
        ]);
    }
}
