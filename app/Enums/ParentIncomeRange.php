<?php

namespace App\Enums;

enum ParentIncomeRange: string
{
    case NoIncome = 'no_income';
    case BelowOneMillion = 'below_1m';
    case OneToTwoMillion = '1m_to_2_999m';
    case ThreeToFourMillion = '3m_to_4_999m';
    case FiveToSevenMillion = '5m_to_7_499m';
    case SevenToNineMillion = '7_5m_to_9_999m';
    case TenToFourteenMillion = '10m_to_14_999m';
    case FifteenMillionPlus = '15m_plus';

    public function label(): string
    {
        return match ($this) {
            self::NoIncome => 'Tidak berpenghasilan',
            self::BelowOneMillion => 'Kurang dari Rp1.000.000',
            self::OneToTwoMillion => 'Rp1.000.000 – Rp2.999.999',
            self::ThreeToFourMillion => 'Rp3.000.000 – Rp4.999.999',
            self::FiveToSevenMillion => 'Rp5.000.000 – Rp7.499.999',
            self::SevenToNineMillion => 'Rp7.500.000 – Rp9.999.999',
            self::TenToFourteenMillion => 'Rp10.000.000 – Rp14.999.999',
            self::FifteenMillionPlus => 'Rp15.000.000 atau lebih',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $range) {
            $options[$range->value] = $range->label();
        }

        return $options;
    }
}
