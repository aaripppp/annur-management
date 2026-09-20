<?php

namespace App\Livewire;

use App\Models\ProspectiveStudentPayment;
use App\Services\ProspectiveStudentPaymentCancellationService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProspectivePaymentShow extends Component
{
    public int $paymentId;

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public bool $printMode = false;

    public function mount(int $payment, bool $printMode = false): void
    {
        $this->paymentId = $payment;
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

    public function cancelPayment(ProspectiveStudentPaymentCancellationService $cancellationService): void
    {
        $this->validate($cancellationService->rules(), $cancellationService->messages());

        $payment = $cancellationService->cancel(
            $this->paymentId,
            $this->cancelReason,
            (int) auth()->id()
        );

        $this->showCancelModal = false;
        $this->cancelReason = '';

        $this->dispatch('prospective-payment-cancelled', paymentId: $payment->id);

        session()->flash('success', 'Pembayaran calon siswa berhasil dibatalkan.');
    }

    public function render(): View
    {
        $payment = ProspectiveStudentPayment::with([
            'prospectiveStudent.academicYear',
            'prospectiveStudent.schoolClass',
            'bank',
            'creator',
            'cancelledBy',
            'details.paymentType',
            'details.bill',
        ])->findOrFail($this->paymentId);

        return view('livewire.prospective-payment-show', [
            'payment' => $payment,
        ]);
    }
}
