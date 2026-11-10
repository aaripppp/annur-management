<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\StudentUnitScope;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TuStudentBiodata extends Component
{
    public Student $student;

    public string $effectiveClassName = '-';

    public string $effectiveSchoolLevel = '-';

    public function mount(Student $student, StudentUnitScope $studentUnitScope): void
    {
        $this->authorize('view', $student);

        $this->student = $student->load([
            'categories',
            'schoolClass',
            'enrollments.academicYear',
            'enrollments.schoolClass',
        ]);

        $effectiveClass = $studentUnitScope->resolveEffectiveClassFromLoaded(
            $this->student,
            AcademicYear::active(),
        );

        $this->effectiveClassName = $effectiveClass?->name ?? '-';
        $this->effectiveSchoolLevel = $effectiveClass?->school_level?->value ?? '-';
    }

    public function render(): View
    {
        $this->authorize('view', $this->student);

        return view('livewire.tu-student-biodata');
    }
}
