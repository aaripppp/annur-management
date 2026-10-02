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

    public const DIRECTOR_NAME_FALLBACK = 'Nova Rabi\'ah Nurrohmah, SE, MM';

    public const HEAD_TU_FOUNDATION_NAME_FALLBACK = 'Windiarti, SE';

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
     *     reviewer_title: string,
     *     reviewer_name: string,
     *     city_and_date: string,
     *     report_creator_title: string,
     *     report_creator_name: string
     * }
     */
    public static function approvalFor(?User $creator, string $cityAndDate): array
    {
        $positionUsers = User::query()
            ->where('is_active', true)
            ->whereIn('position', [
                User::POSITION_DIRECTOR,
                User::POSITION_HEAD_TU_FOUNDATION,
            ])
            ->orderBy('id')
            ->get()
            ->groupBy('position');

        $director = $positionUsers->get(User::POSITION_DIRECTOR)?->first();
        $headTuFoundation = $positionUsers->get(User::POSITION_HEAD_TU_FOUNDATION)?->first();
        $creatorTitle = $creator?->roleLabel();

        if ($creator !== null && filled($creator->position)) {
            $creatorTitle = $creator->position;
        }

        return [
            'approver_title' => User::POSITION_DIRECTOR,
            'approver_name' => $director?->name ?? self::DIRECTOR_NAME_FALLBACK,
            'reviewer_title' => User::POSITION_HEAD_TU_FOUNDATION,
            'reviewer_name' => $headTuFoundation?->name ?? self::HEAD_TU_FOUNDATION_NAME_FALLBACK,
            'city_and_date' => $cityAndDate,
            'report_creator_title' => filled($creatorTitle) ? $creatorTitle : 'Administrator',
            'report_creator_name' => filled($creator?->name) ? $creator->name : 'Administrator',
        ];
    }
}
