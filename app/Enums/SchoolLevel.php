<?php

namespace App\Enums;

enum SchoolLevel: string
{
    case TK = 'TK';

    case SD = 'SD';

    case SMP = 'SMP';

    case SMA = 'SMA';

    /**
     * Petakan class_level numerik ke jenjang sekolah.
     *
     * -3 = KB, -2 = TKA, -1 = TKB (semuanya jenjang TK).
     * 1-6 = SD, 7-9 = SMP, 10-12 = SMA.
     */
    public static function fromClassLevel(int $level): ?self
    {
        return match (true) {
            $level === -3, $level === -2, $level === -1 => self::TK,
            $level >= 1 && $level <= 6 => self::SD,
            $level >= 7 && $level <= 9 => self::SMP,
            $level >= 10 && $level <= 12 => self::SMA,
            default => null,
        };
    }

    /** @return array<int, int> */
    public function classLevels(): array
    {
        return match ($this) {
            self::TK => [-3, -2, -1],
            self::SD => range(1, 6),
            self::SMP => range(7, 9),
            self::SMA => range(10, 12),
        };
    }
}
