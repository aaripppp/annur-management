<?php

namespace App\Enums;

enum ProspectiveStudentStatus: string
{
    case Registered = 'registered';

    case Converted = 'converted';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Terdaftar',
            self::Converted => 'Dikonversi',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
