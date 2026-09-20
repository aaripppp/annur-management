<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConvertsProspectiveStudent;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Services\ProspectiveStudentPaymentDeletionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProspectiveStudentDetail extends Component
{
    use ConvertsProspectiveStudent;

    public ?ProspectiveStudent $prospectiveStudent = null;

    public bool $isDeletePaymentModalOpen = false;

    public ?int $deletingPaymentId = null;

    public function mount(ProspectiveStudent $prospectiveStudent): void
    {
        $this->prospectiveStudent = $prospectiveStudent->load([
            'academicYear',
            'schoolClass',
            'convertedStudent',
            'bills.paymentType',
            'bills.paymentDetails.payment',
            'payments.bank',
            'payments.details.bill',
            'payments.details.paymentType',
        ]);
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

    public function render(): mixed
    {
        $deletingPayment = $this->isDeletePaymentModalOpen && $this->deletingPaymentId
            ? ProspectiveStudentPayment::query()->with('prospectiveStudent')->find($this->deletingPaymentId)
            : null;

        return view('livewire.prospective-student-detail', [
            'deletingPayment' => $deletingPayment,
        ]);
    }
}
