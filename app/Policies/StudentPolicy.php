<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentUnitScope;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isInternalAdministrator($user)
            || ($user->isTuUnit() && $user->school_level !== null);
    }

    public function view(User $user, Student $student): bool
    {
        if ($this->isInternalAdministrator($user)) {
            return true;
        }

        if (! $user->isTuUnit() || $user->school_level === null) {
            return false;
        }

        return app(StudentUnitScope::class)
            ->applyForUser(Student::query()->whereKey($student->getKey()), $user, AcademicYear::active())
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->isInternalAdministrator($user)
            || ($user->isTuUnit() && $user->school_level !== null);
    }

    public function update(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function restore(User $user, Student $student): bool
    {
        return $this->isInternalAdministrator($user);
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return $this->isInternalAdministrator($user);
    }

    public function import(User $user): bool
    {
        return $this->isInternalAdministrator($user);
    }

    public function export(User $user): bool
    {
        return $this->isInternalAdministrator($user);
    }

    public function bulkUpdate(User $user): bool
    {
        return $this->isInternalAdministrator($user);
    }

    public function viewStudentBilling(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function downloadStudentBillsPdf(User $user, Student $student): bool
    {
        return $this->viewStudentBilling($user, $student);
    }

    public function viewBillingPdf(User $user, Student $student): bool
    {
        return $this->downloadStudentBillsPdf($user, $student);
    }

    public function manageFinance(User $user, Student $student): bool
    {
        return $this->isInternalAdministrator($user);
    }

    private function isInternalAdministrator(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
