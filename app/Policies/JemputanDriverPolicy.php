<?php

namespace App\Policies;

use App\Models\JemputanDriver;
use App\Models\User;

class JemputanDriverPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JemputanDriver $jemputanDriver): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, JemputanDriver $jemputanDriver): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JemputanDriver $jemputanDriver): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, JemputanDriver $jemputanDriver): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, JemputanDriver $jemputanDriver): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->isActive() && ($user->isAdmin() || $user->isSuperAdmin());
    }
}
