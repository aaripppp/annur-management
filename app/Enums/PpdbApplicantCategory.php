<?php

namespace App\Enums;

enum PpdbApplicantCategory: string
{
    case ANNUR_FAMILY = 'annur_family';

    case NON_ANNUR_FAMILY = 'non_annur_family';

    public function label(): string
    {
        return match ($this) {
            self::ANNUR_FAMILY => 'Keluarga Annur',
            self::NON_ANNUR_FAMILY => 'Non-Keluarga Annur',
        };
    }
}
