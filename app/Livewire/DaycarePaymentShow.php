<?php

namespace App\Livewire;

use App\Models\DaycarePayment;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DaycarePaymentShow extends Component
{
    public DaycarePayment $payment;

    public bool $printMode = false;

    public function mount(DaycarePayment $payment, bool $printMode = false): void
    {
        $this->payment = $payment;
        $this->printMode = $printMode;
    }

    public function render(): mixed
    {
        $this->payment->load(['child', 'bank', 'creator', 'details']);

        return view('livewire.daycare-payment-show');
    }
}
