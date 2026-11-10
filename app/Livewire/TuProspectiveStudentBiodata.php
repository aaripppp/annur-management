<?php

namespace App\Livewire;

use App\Models\ProspectiveStudent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TuProspectiveStudentBiodata extends Component
{
    public ProspectiveStudent $prospectiveStudent;

    public function mount(ProspectiveStudent $prospectiveStudent): void
    {
        $this->authorize('view', $prospectiveStudent);

        $this->prospectiveStudent = $prospectiveStudent->load([
            'academicYear',
            'schoolClass',
            'ppdbProfile',
            'ppdbParents',
            'ppdbSiblings',
        ]);
    }

    public function render(): View
    {
        $this->authorize('view', $this->prospectiveStudent);

        return view('livewire.tu-prospective-student-biodata');
    }
}
