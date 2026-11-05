<?php

namespace App\Support;

final class PersonNameNormalizer
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtoupper(trim($value), 'UTF-8');
    }
}
