<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConvertsProspectiveStudent;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Services\ProspectiveStudentPaymentDeletionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class ProspectiveStudentDetail extends Component
{
    use ConvertsProspectiveStudent;

    public ?ProspectiveStudent $prospectiveStudent = null;

    public bool $isDeletePaymentModalOpen = false;

    public ?int $deletingPaymentId = null;

    #[Locked]
    public bool $canManageFinance = false;

    public function mount(ProspectiveStudent $prospectiveStudent): void
    {
        if ($this->shouldAuthorizeSharedDetail()) {
            $this->authorize('view', $prospectiveStudent);
            $this->canManageFinance = auth()->user()->can('manageFinance', $prospectiveStudent);
        } else {
            $this->canManageFinance = true;
        }

        $relations = [
            'academicYear',
            'schoolClass',
            'convertedStudent',
            'ppdbProfile',
            'ppdbParents',
            'ppdbSiblings',
            'ppdbDocuments',
        ];

        if ($this->canManageFinance) {
            $relations = [...$relations,
                'bills.paymentType',
                'bills.paymentDetails.payment',
                'payments.bank',
                'payments.details.bill',
                'payments.details.paymentType',
            ];
        }

        $this->prospectiveStudent = $prospectiveStudent->load($relations);
    }

    public function confirmDeletePayment(int $paymentId): void
    {
        $this->authorizeFinance();
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
        $this->authorizeFinance();
        $this->isDeletePaymentModalOpen = false;
        $this->deletingPaymentId = null;
    }

    public function deletePayment(ProspectiveStudentPaymentDeletionService $deletionService): void
    {
        $this->authorizeFinance();
        if (! $this->deletingPaymentId || ! $this->prospectiveStudent) {
            return;
        }

        $deletionService->delete($this->deletingPaymentId, $this->prospectiveStudent->id);

        $this->cancelDeletePayment();
        session()->flash('success', 'Transaksi pembayaran berhasil dihapus permanen.');
    }

    #[On('ppdb-biodata-updated')]
    #[On('ppdb-documents-updated')]
    public function refreshPpdbBiodata(?int $prospectiveStudentId = null): void
    {
        if ($prospectiveStudentId !== null && $this->prospectiveStudent?->id !== $prospectiveStudentId) {
            return;
        }
        if ($this->prospectiveStudent?->id !== $prospectiveStudentId) {
            return;
        }

        if ($this->shouldAuthorizeSharedDetail()) {
            $this->authorize('view', $this->prospectiveStudent);
        }
        $relations = [
            'academicYear',
            'schoolClass',
            'convertedStudent',
            'ppdbProfile',
            'ppdbParents',
            'ppdbSiblings',
            'ppdbDocuments',
        ];

        if ($this->canManageFinance) {
            $relations = [...$relations,
                'bills.paymentType',
                'bills.paymentDetails.payment',
                'payments.bank',
                'payments.details.bill',
                'payments.details.paymentType',
            ];
        }

        $this->prospectiveStudent->refresh()->load($relations);
    }

    public function render(): mixed
    {
        if ($this->shouldAuthorizeSharedDetail()) {
            $this->authorize('view', $this->prospectiveStudent);
        }
        $deletingPayment = $this->canManageFinance && $this->isDeletePaymentModalOpen && $this->deletingPaymentId
            ? ProspectiveStudentPayment::query()->with('prospectiveStudent')->find($this->deletingPaymentId)
            : null;

        return view('livewire.prospective-student-detail', [
            'deletingPayment' => $deletingPayment,
        ]);
    }

    private function authorizeFinance(): void
    {
        if (auth()->check()) {
            $this->authorize('manageFinance', $this->prospectiveStudent);
        }
    }

    private function shouldAuthorizeSharedDetail(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isAdmin() || $user->isSuperAdmin() || $user->isTuUnit());
    }
}
