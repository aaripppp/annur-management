<?php

namespace App\Services;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageMargin;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;
use ZipArchive;

class JemputanDriverSemesterRecapSpreadsheet
{
    public const SHEET_NAME = 'REKAP_6_BULAN';

    private const COLUMN_COUNT = 10;

    private const HEADER_ROW = 7;

    private const DATA_START_ROW = 8;

    private const HEADER_COLOR = 'D9EAF7';

    private const DRIVER_COLOR = 'EAF4E2';

    private const FEE_COLOR = 'FFF2CC';

    private const TOTAL_COLOR = 'E8E2F4';

    private const RECAP_COLOR = 'DCE6F1';

    private const WARNING_COLOR = 'FCE8E6';

    private const BORDER_COLOR = '7F8C8D';

    /** @param array<string, mixed> $report */
    public function filename(array $report): string
    {
        $level = $report['filters']['school_level'] ?? 'Semua-Unit';
        $year = preg_replace('/[^0-9A-Za-z_-]+/', '-', (string) $report['academic_year']['label']);

        return 'Rekap_Jemputan_6_Bulan_'.Str::upper((string) $level)
            .'_Semester_'.$report['filters']['semester'].'_'.trim($year ?? '', '-').'.xlsx';
    }

    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-jemputan-semester-recap-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file rekap Jemputan 6 bulan sementara.');
        }

        $options = new Options(
            pageMargin: new PageMargin(top: 0.45, right: 0.25, bottom: 0.45, left: 0.25, header: 0.15, footer: 0.15),
            pageSetup: new PageSetup(PageOrientation::PORTRAIT, PaperSize::Folio, 0, 1),
        );
        $writer = new Writer($options);

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName(self::SHEET_NAME);
            $this->configureSheet($options, $sheet, $report);
            $lastRow = $this->writeReport($writer, $report);
            $writer->close();
            $this->setPrintArea($path, $lastRow);
        } catch (Throwable $exception) {
            $writer->close();

            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }

        return $path;
    }

    /** @param array<string, mixed> $report */
    private function configureSheet(Options $options, Sheet $sheet, array $report): void
    {
        $sheet->setColumnWidth(4.5, 1);
        $sheet->setColumnWidth(21, 2);
        $sheet->setColumnWidth(13, 3);
        $sheet->setColumnWidthForRange(11.5, 4, 9);
        $sheet->setColumnWidth(13.5, 10);
        $sheet->setPrintTitleRows(self::HEADER_ROW.':'.self::HEADER_ROW);
        $sheet->setSheetView(
            (new SheetView)
                ->withTopLeftCell('A1')
                ->withZoomScale(90)
        );

        foreach (range(1, 5) as $row) {
            $options->mergeCells(0, $row, self::COLUMN_COUNT - 1, $row, $sheet->getIndex());
        }

        foreach ($report['rows'] as $index => $row) {
            $startRow = self::DATA_START_ROW + ($index * 3);
            $options->mergeCells(0, $startRow, 0, $startRow + 2, $sheet->getIndex());
            $options->mergeCells(1, $startRow, 1, $startRow + 2, $sheet->getIndex());
        }

        $recapStartRow = self::DATA_START_ROW + (count($report['rows']) * 3);
        $options->mergeCells(0, $recapStartRow, 1, $recapStartRow + 2, $sheet->getIndex());
    }

    /**
     * @param  array<string, mixed>  $report
     * @return positive-int
     */
    private function writeReport(Writer $writer, array $report): int
    {
        $writer->addRow($this->fullWidthRow('REKAP PEMBAYARAN JEMPUTAN', $this->titleStyle(), 24));
        $writer->addRow($this->fullWidthRow($this->subtitle($report), $this->subtitleStyle(), 22));
        $writer->addRow($this->fullWidthRow(
            $report['is_final']
                ? 'STATUS: FINAL'
                : 'BELUM FINAL — Terdapat alokasi atau persentase yang perlu diperiksa.',
            $report['is_final'] ? $this->finalStatusStyle() : $this->warningStyle(),
            22,
        ));
        $writer->addRow($this->fullWidthRow($this->warningDetails($report), $this->warningDetailStyle(), 28));
        $writer->addRow($this->fullWidthRow(
            'Catatan membership historis: pembagian sopir menggunakan membership aktif saat laporan dibuat; kondisi per bulan belum direkonstruksi.',
            $this->membershipNoteStyle(),
            28,
        ));
        $writer->addRow(Row::fromValues([' '], 8));
        $writer->addRow(Row::fromValuesWithStyle($this->headerValues($report), $this->headerStyle(), 28));

        foreach ($report['rows'] as $index => $row) {
            $this->writeDriverRows($writer, $report, $row, $index + 1);
        }

        $this->writeRecapRows($writer, $report);

        return self::DATA_START_ROW + (count($report['rows']) * 3) + 2;
    }

    /** @param array<string, mixed> $report */
    private function subtitle(array $report): string
    {
        $level = Str::upper((string) ($report['filters']['school_level'] ?? 'SEMUA UNIT'));

        return $level.' ANNUR SEMESTER '.$report['filters']['semester'].' TP. '.$report['academic_year']['label'];
    }

    /** @param array<string, mixed> $report */
    private function warningDetails(array $report): string
    {
        if ($report['is_final']) {
            return 'Seluruh alokasi dan riwayat persentase pada periode laporan telah tersedia.';
        }

        $allocation = $report['warnings']['allocation'];

        return 'Alokasi: '.$allocation['missing_count'].' missing, '
            .$allocation['stale_count'].' stale, '.$allocation['orphan_count'].' orphan. '
            .'Persentase belum diatur: '.$report['warnings']['missing_rate_count'].' periode. '
            .'Persentase legacy: '.$report['warnings']['legacy_rate_count'].' periode. '
            .'Nominal fallback bukan nilai final.';
    }

    /** @param array<string, mixed> $report
     * @return list<string>
     */
    private function headerValues(array $report): array
    {
        $values = ['No', 'Nama Sopir', 'Pembayaran'];

        foreach ($report['months'] as $month) {
            $values[] = Str::title((string) preg_replace('/\s+\d{4}$/', '', $month['label']));
        }

        $values[] = 'Total';

        return $values;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $row
     */
    private function writeDriverRows(Writer $writer, array $report, array $row, int $number): void
    {
        $definitions = [
            ['Sopir', 'driver_income', $this->driverLabelStyle(), $this->driverAmountStyle()],
            ['Fee Yayasan', 'foundation_fee', $this->feeLabelStyle(), $this->feeAmountStyle()],
            ['Total', 'total', $this->totalLabelStyle(), $this->totalAmountStyle()],
        ];

        foreach ($definitions as $rowOffset => [$label, $key, $labelStyle, $amountStyle]) {
            $values = [
                $rowOffset === 0 ? $number : '',
                $rowOffset === 0 ? $row['driver_name'] : '',
                $label,
            ];

            foreach ($report['months'] as $month) {
                $values[] = $this->amountValue($row['months'][$month['key']][$key]);
            }

            $values[] = $this->amountValue($row['totals'][$key]);
            $styles = [
                $this->numberStyle(),
                $this->nameStyle(),
                $labelStyle,
                ...array_fill(0, 7, $amountStyle),
            ];
            $writer->addRow(Row::fromValuesWithStyles($values, $styles, 19));
        }
    }

    /** @param array<string, mixed> $report */
    private function writeRecapRows(Writer $writer, array $report): void
    {
        $definitions = [
            ['Sopir', 'driver_income', $this->recapDriverLabelStyle(), $this->recapDriverAmountStyle()],
            ['Fee Yayasan', 'foundation_fee', $this->recapFeeLabelStyle(), $this->recapFeeAmountStyle()],
            ['Total', 'total', $this->recapTotalLabelStyle(), $this->recapTotalAmountStyle()],
        ];

        foreach ($definitions as $rowOffset => [$label, $key, $labelStyle, $amountStyle]) {
            $values = [$rowOffset === 0 ? 'REKAP' : '', '', $label];

            foreach ($report['months'] as $month) {
                $values[] = $this->amountValue($report['recap']['months'][$month['key']][$key]);
            }

            $values[] = $this->amountValue($report['recap']['totals'][$key]);
            $styles = [
                $this->recapHeadingStyle(),
                $this->recapHeadingStyle(),
                $labelStyle,
                ...array_fill(0, 6, $amountStyle),
                $key === 'foundation_fee' ? $this->recapFeeSemesterStyle() : $amountStyle,
            ];
            $writer->addRow(Row::fromValuesWithStyles($values, $styles, 20));
        }
    }

    private function amountValue(float|int|null $amount): float|string
    {
        return $amount === null ? '—' : (float) $amount;
    }

    private function setPrintArea(string $path, int $lastRow): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('Tidak dapat membuka workbook untuk mengatur print area.');
        }

        try {
            $workbookXml = $archive->getFromName('xl/workbook.xml');

            if (! is_string($workbookXml)) {
                throw new RuntimeException('Struktur workbook tidak ditemukan.');
            }

            $printArea = '<definedName name="_xlnm.Print_Area" localSheetId="0">'
                .self::SHEET_NAME.'!$A$1:$J$'.$lastRow.'</definedName>';

            if (str_contains($workbookXml, '</definedNames>')) {
                $workbookXml = str_replace('</definedNames>', $printArea.'</definedNames>', $workbookXml);
            } else {
                $workbookXml = str_replace('</workbook>', '<definedNames>'.$printArea.'</definedNames></workbook>', $workbookXml);
            }

            if (! $archive->addFromString('xl/workbook.xml', $workbookXml)) {
                throw new RuntimeException('Print area workbook tidak dapat disimpan.');
            }
        } finally {
            $archive->close();
        }
    }

    private function fullWidthRow(string $value, Style $style, float $height): Row
    {
        return Row::fromValuesWithStyle(array_pad([$value], self::COLUMN_COUNT, ''), $style, $height);
    }

    private function titleStyle(): Style
    {
        return $this->centeredStyle(fontSize: 14, bold: true);
    }

    private function subtitleStyle(): Style
    {
        return $this->centeredStyle(fontSize: 11, bold: true);
    }

    private function finalStatusStyle(): Style
    {
        return $this->centeredStyle(fontSize: 9, bold: true, backgroundColor: self::DRIVER_COLOR);
    }

    private function warningStyle(): Style
    {
        return $this->centeredStyle(fontSize: 9, bold: true, backgroundColor: self::WARNING_COLOR);
    }

    private function warningDetailStyle(): Style
    {
        return $this->centeredStyle(fontSize: 8, backgroundColor: self::WARNING_COLOR, wrap: true);
    }

    private function membershipNoteStyle(): Style
    {
        return $this->centeredStyle(fontSize: 8, italic: true, backgroundColor: 'F2F2F2', wrap: true);
    }

    private function headerStyle(): Style
    {
        return $this->centeredStyle(
            fontSize: 8,
            bold: true,
            backgroundColor: self::HEADER_COLOR,
            border: $this->border(),
            wrap: true,
        );
    }

    private function numberStyle(): Style
    {
        return $this->centeredStyle(fontSize: 8, backgroundColor: 'FFFFFF', border: $this->border());
    }

    private function nameStyle(): Style
    {
        return new Style(
            fontSize: 8,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border(),
            backgroundColor: 'FFFFFF',
        );
    }

    private function driverLabelStyle(): Style
    {
        return $this->labelStyle(self::DRIVER_COLOR);
    }

    private function feeLabelStyle(): Style
    {
        return $this->labelStyle(self::FEE_COLOR);
    }

    private function totalLabelStyle(): Style
    {
        return $this->labelStyle(self::TOTAL_COLOR, true);
    }

    private function driverAmountStyle(): Style
    {
        return $this->amountStyle(self::DRIVER_COLOR);
    }

    private function feeAmountStyle(): Style
    {
        return $this->amountStyle(self::FEE_COLOR);
    }

    private function totalAmountStyle(): Style
    {
        return $this->amountStyle(self::TOTAL_COLOR, true);
    }

    private function recapHeadingStyle(): Style
    {
        return $this->centeredStyle(
            fontSize: 9,
            bold: true,
            backgroundColor: self::RECAP_COLOR,
            border: $this->border(),
        );
    }

    private function recapDriverLabelStyle(): Style
    {
        return $this->labelStyle(self::RECAP_COLOR, true);
    }

    private function recapFeeLabelStyle(): Style
    {
        return $this->labelStyle(self::FEE_COLOR, true);
    }

    private function recapTotalLabelStyle(): Style
    {
        return $this->labelStyle(self::TOTAL_COLOR, true);
    }

    private function recapDriverAmountStyle(): Style
    {
        return $this->amountStyle(self::RECAP_COLOR, true);
    }

    private function recapFeeAmountStyle(): Style
    {
        return $this->amountStyle(self::FEE_COLOR, true);
    }

    private function recapTotalAmountStyle(): Style
    {
        return $this->amountStyle(self::TOTAL_COLOR, true);
    }

    private function recapFeeSemesterStyle(): Style
    {
        return $this->amountStyle('FFD966', true);
    }

    private function labelStyle(string $backgroundColor, bool $bold = false): Style
    {
        return new Style(
            fontBold: $bold,
            fontSize: 8,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border(),
            backgroundColor: $backgroundColor,
        );
    }

    private function amountStyle(string $backgroundColor, bool $bold = false): Style
    {
        return new Style(
            fontBold: $bold,
            fontSize: 8,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            shouldShrinkToFit: true,
            border: $this->border(),
            backgroundColor: $backgroundColor,
            format: '"Rp" #,##0',
        );
    }

    private function centeredStyle(
        float $fontSize,
        bool $bold = false,
        bool $italic = false,
        ?string $backgroundColor = null,
        ?Border $border = null,
        bool $wrap = false,
    ): Style {
        return new Style(
            fontBold: $bold,
            fontItalic: $italic,
            fontSize: $fontSize,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: $wrap,
            border: $border,
            backgroundColor: $backgroundColor,
        );
    }

    private function border(): Border
    {
        return new Border(
            new BorderPart(BorderName::TOP, self::BORDER_COLOR, BorderWidth::THIN),
            new BorderPart(BorderName::RIGHT, self::BORDER_COLOR, BorderWidth::THIN),
            new BorderPart(BorderName::BOTTOM, self::BORDER_COLOR, BorderWidth::THIN),
            new BorderPart(BorderName::LEFT, self::BORDER_COLOR, BorderWidth::THIN),
        );
    }
}
