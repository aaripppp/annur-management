<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ProspectiveStudentUnitScope
{
    public function classBelongsToUser(?int $schoolClassId, User $user): bool
    {
        if (! $user->isTuUnit()) {
            return true;
        }

        if ($schoolClassId === null || $user->school_level === null) {
            return false;
        }

        return SchoolClass::query()
            ->whereKey($schoolClassId)
            ->whereIn('level', $user->school_level->classLevels())
            ->exists();
    }

    /**
     * Apply the authenticated user's mandatory PPDB applicant visibility boundary.
     *
     * Applicant unit is derived from the target class only; academic enrollment
     * precedence never applies to prospective students.
     *
     * @param  Builder<ProspectiveStudent>  $query
     * @return Builder<ProspectiveStudent>
     */
    public function applyForUser(Builder $query, ?User $user): Builder
    {
        if ($user === null || ! $user->isTuUnit()) {
            return $query;
        }

        if ($user->school_level === null) {
            return $query->whereRaw('0 = 1');
        }

        return $this->apply($query, $user->school_level);
    }

    /**
     * Scope applicants by the target class level in the given school unit.
     *
     * Applicants without a target class, or whose target class has an
     * unrecognised level, never match.
     *
     * @param  Builder<ProspectiveStudent>  $query
     * @return Builder<ProspectiveStudent>
     */
    public function apply(Builder $query, SchoolLevel $schoolLevel): Builder
    {
        return $query->whereExists(function (QueryBuilder $targetClass) use ($schoolLevel): void {
            $targetClass
                ->selectRaw('1')
                ->from('school_classes')
                ->whereColumn('school_classes.id', 'prospective_students.school_class_id')
                ->whereIn('school_classes.level', $schoolLevel->classLevels());
        });
    }
}
