<?php

namespace App\Policies;

use App\Models\ProspectiveStudent;
use App\Models\User;

class ProspectiveStudentPolicy
{
    public function updateBiodata(User $user, ProspectiveStudent $prospectiveStudent): bool
    {
        return $user->isActive() && ($user->isAdmin() || $user->isSuperAdmin());
    }

    public function viewBiodata(User $user, ProspectiveStudent $student): bool
    {
        return $user->isActive() && ($user->isAdmin() || $user->isSuperAdmin());
    }
}
