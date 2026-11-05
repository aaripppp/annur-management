<?php

namespace App\Policies;

use App\Models\PpdbDocument;
use App\Models\User;

class PpdbDocumentPolicy
{
    public function view(User $user, PpdbDocument $ppdbDocument): bool
    {
        return $this->hasDocumentAccess($user);
    }

    public function download(User $user, PpdbDocument $ppdbDocument): bool
    {
        return $this->hasDocumentAccess($user);
    }

    public function replace(User $user, ?PpdbDocument $ppdbDocument = null): bool
    {
        return $this->hasDocumentAccess($user);
    }

    public function update(User $user, ?PpdbDocument $ppdbDocument = null): bool
    {
        return $this->hasDocumentAccess($user);
    }

    private function hasDocumentAccess(User $user): bool
    {
        return $user->isActive() && ($user->isAdmin() || $user->isSuperAdmin());
    }
}
