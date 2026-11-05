<?php

namespace App\Livewire;

use App\Models\PpdbPublicSubmission;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.ppdb-public')]
class PpdbPublicRegistrationSuccess extends Component
{
    public string $registrationNumber;

    public string $academicYear;

    public string $className;

    public function mount(string $reference): void
    {
        $submission = PpdbPublicSubmission::findCompletedByReference($reference);

        abort_unless($submission?->prospectiveStudent, 404);

        $this->registrationNumber = $submission->prospectiveStudent->registration_number;
        $this->academicYear = $submission->prospectiveStudent->academicYear->year;
        $this->className = $submission->prospectiveStudent->schoolClass->name;
    }

    public function render(): View
    {
        return view('livewire.ppdb-public-registration-success')
            ->title('Pendaftaran Berhasil - PPDB Online');
    }
}
