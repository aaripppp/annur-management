<?php

namespace App\Support;

use App\Enums\SchoolLevel;
use App\Models\User;

/**
 * Nilai identitas institusi yang dipakai bersama dokumen laporan resmi
 * (Laporan Harian & Laporan Bulanan).
 */
final class SchoolReportDocument
{
    public const UNIT_NAME = 'An-Nur';

    public const CITY = 'Bekasi';

    public const DIRECTOR_NAME_FALLBACK = 'Nova Rabi\'ah Nurrohmah, SE';

    public static function unitName(?SchoolLevel $schoolLevel): string
    {
        return match ($schoolLevel) {
            null => self::UNIT_NAME,
            default => $schoolLevel->value.' '.self::UNIT_NAME,
        };
    }

    /**
     * @return array{
     *     approver_title: string,
     *     approver_name: string,
     *     city_and_date: string,
     *     report_creator_title: string,
     *     report_creator_name: string
     * }
     */
    public static function approvalFor(User $creator, string $cityAndDate): array
    {
        $director = User::query()
            ->where('position', User::POSITION_DIRECTOR)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return [
            'approver_title' => User::POSITION_DIRECTOR,
            'approver_name' => $director?->name ?? self::DIRECTOR_NAME_FALLBACK,
            'city_and_date' => $cityAndDate,
            'report_creator_title' => $creator->position ?: $creator->roleLabel(),
            'report_creator_name' => $creator->name,
        ];
    }
}
