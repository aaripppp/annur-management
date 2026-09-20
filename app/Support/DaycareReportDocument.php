<?php

namespace App\Support;

/**
 * Nilai identitas institusi Daycare yang dipakai dokumen Laporan Harian Daycare.
 *
 * Sengaja terpisah dari SchoolReportDocument (unit mengikuti jenjang) agar
 * domain Daycare tidak bercampur dengan identitas laporan sekolah.
 */
final class DaycareReportDocument
{
    public const UNIT_NAME = 'DAYCARE ANNUR';

    public const CITY = 'Bekasi';
}
