<?php

namespace App\Policies;

use App\Models\ProspectiveStudent;
use App\Models\User;
use App\Services\ProspectiveStudentUnitScope;

class ProspectiveStudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isInternalAdministrator($user)
            || ($user->isTuUnit() && $user->school_level !== null);
    }

    /**
     * TU may only view applicants whose target class belongs to its unit.
     */
    public function view(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        if ($this->isInternalAdministrator($user)) {
            return true;
        }

        if (! $user->isTuUnit() || $user->school_level === null) {
            return false;
        }

        if ($prospectiveStudent->relationLoaded('schoolClass')) {
            return $prospectiveStudent->schoolClass?->schoolLevel === $user->school_level;
        }

        return app(ProspectiveStudentUnitScope::class)
            ->applyForUser(ProspectiveStudent::query()->whereKey($prospectiveStudent->getKey()), $user)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->isInternalAdministrator($user)
            || ($user->isTuUnit() && $user->school_level !== null);
    }

    public function update(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        return $this->view($user, $prospectiveStudent);
    }

    public function delete(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        return $this->view($user, $prospectiveStudent);
    }

    public function convert(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        if ($this->isInternalAdministrator($user)) {
            return true;
        }

        if (! $user->isTuUnit() || $user->school_level === null) {
            return false;
        }

        return app(ProspectiveStudentUnitScope::class)
            ->applyForUser(ProspectiveStudent::query()->whereKey($prospectiveStudent->getKey()), $user)
            ->exists();
    }

    public function exportBiodataRecap(User $user): bool
    {
        return $user->isActive()
            && ($this->isInternalAdministrator($user) || ($user->isTuUnit() && $user->school_level !== null));
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->isActive()
            && ($this->isInternalAdministrator($user) || ($user->isTuUnit() && $user->school_level !== null));
    }

    public function import(User $user): bool
    {
        return $this->isInternalAdministrator($user);
    }

    public function updateBiodata(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        return $user->isActive() && $this->update($user, $prospectiveStudent);
    }

    public function viewBiodata(User $user, ProspectiveStudent $student): bool
    {
        return $user->isActive() && $this->view($user, $student);
    }

    public function replaceDocument(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        return $user->isActive() && $this->update($user, $prospectiveStudent);
    }

    public function manageFinance(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        return $user->isActive() && $this->isInternalAdministrator($user);
    }

    private function isInternalAdministrator(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
