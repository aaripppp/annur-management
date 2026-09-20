<?php

namespace App\Enums;

enum PaymentTypeAudience: string
{
    case Student = 'student';

    case ProspectiveStudent = 'prospective_student';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Siswa',
            self::ProspectiveStudent => 'Calon Siswa',
        };
    }
}
