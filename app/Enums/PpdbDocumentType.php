<?php

namespace App\Enums;

enum PpdbDocumentType: string
{
    case BuktiTransfer = 'bukti_transfer';
    case KartuKeluarga = 'kartu_keluarga';
    case AktaKelahiran = 'akta_kelahiran';
    case KtpAyah = 'ktp_ayah';
    case KtpIbu = 'ktp_ibu';
    case PasFoto = 'pas_foto';

    public function label(): string
    {
        return match ($this) {
            self::BuktiTransfer => 'Bukti Transfer',
            self::KartuKeluarga => 'Kartu Keluarga (KK)',
            self::AktaKelahiran => 'Akta Kelahiran',
            self::KtpAyah => 'KTP Ayah',
            self::KtpIbu => 'KTP Ibu',
            self::PasFoto => 'Pas Foto Siswa',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function values(): array
    {
        return [
            self::BuktiTransfer->value,
            self::KartuKeluarga->value,
            self::AktaKelahiran->value,
            self::KtpAyah->value,
            self::KtpIbu->value,
            self::PasFoto->value,
        ];
    }
}
