<?php

namespace App\Policies;

use App\Models\PpdbDocument;
use App\Models\User;

class PpdbDocumentPolicy
{
    public function view(User $user, PpdbDocument $ppdbDocument): bool
    {
        return $this->hasDocumentAccess($user, $ppdbDocument);
    }

    public function download(User $user, PpdbDocument $ppdbDocument): bool
    {
        return $this->hasDocumentAccess($user, $ppdbDocument);
    }

    public function replace(User $user, ?PpdbDocument $ppdbDocument = null): bool
    {
        return $ppdbDocument !== null && $this->hasDocumentAccess($user, $ppdbDocument);
    }

    public function update(User $user, ?PpdbDocument $ppdbDocument = null): bool
    {
        return $ppdbDocument !== null && $this->hasDocumentAccess($user, $ppdbDocument);
    }

    private function hasDocumentAccess(User $user, PpdbDocument $ppdbDocument): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $prospectiveStudent = $ppdbDocument->relationLoaded('prospectiveStudent')
            ? $ppdbDocument->prospectiveStudent
            : $ppdbDocument->prospectiveStudent()->first();

        return $prospectiveStudent !== null && $user->can('viewBiodata', $prospectiveStudent);
    }
}
