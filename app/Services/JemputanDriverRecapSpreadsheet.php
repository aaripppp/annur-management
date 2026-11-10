<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class JemputanDriverRecapSpreadsheet
{
    public const CHUNK_SIZE = 200;

    public const MAX_WORKSHEETS = 16384;

    private const COLUMN_COUNT = 40;

    private const GROUP_HEADER_ROW = 5;

    public function __construct(private JemputanDriverRecapService $recapService) {}

    /**
     * @param  iterable<int, JemputanDriver>  $drivers
     */
    public function create(
        AcademicYear $academicYear,
        iterable $drivers,
        ?SchoolLevel $schoolLevel = null,
        ?int $schoolClassId = null,
    ): string {
        $path = tempnam(sys_get_temp_dir(), 'annur-jemputan-recap-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file rekap Jemputan sementara.');
        }

        $options = new Options;
        $writer = new Writer($options);
        $usedSheetNames = [];
        $sheetCount = 0;

        try {
            $writer->openToFile($path);

            foreach ($drivers as $driver) {
                $sheetCount++;

                if ($sheetCount > self::MAX_WORKSHEETS) {
                    throw new RuntimeException('Jumlah sopir melebihi batas worksheet Excel.');
                }

                $sheet = $sheetCount === 1
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($this->uniqueSheetName($driver, $usedSheetNames));
                $this->writeDriverSheet(
                    $writer,
                    $options,
                    $sheet,
                    $academicYear,
                    $driver,
                    $schoolLevel,
                    $schoolClassId,
                );
            }

            if ($sheetCount === 0) {
                throw new RuntimeException('Tidak ada kelompok sopir yang dapat diekspor.');
            }

            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();

            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }

        return $path;
    }

    public function filename(AcademicYear $academicYear): string
    {
        $year = preg_replace('/[^0-9A-Za-z_-]+/', '-', $academicYear->year);

        return 'Rekap_Jemputan_'.trim($year ?? '', '-').'.xlsx';
    }

    /** @param array<string, bool> $usedSheetNames */
    private function uniqueSheetName(JemputanDriver $driver, array &$usedSheetNames): string
    {
        $baseName = preg_replace('~[\x00-\x1F\x7F\\/?*:\[\]]+~u', '-', Str::upper(trim($driver->name)));
        $baseName = trim(trim($baseName ?? ''), " '");
        $baseName = $baseName !== '' ? $baseName : 'SOPIR-'.$driver->id;
        $baseName = trim(Str::substr($baseName, 0, 31));
        $candidate = $baseName;
        $suffixNumber = 2;

        while (isset($usedSheetNames[Str::lower($candidate)])) {
            $suffix = " ({$suffixNumber})";
            $candidate = Str::substr($baseName, 0, 31 - Str::length($suffix)).$suffix;
            $suffixNumber++;
        }

        $usedSheetNames[Str::lower($candidate)] = true;

        return $candidate;
    }

    private function writeDriverSheet(
        Writer $writer,
        Options $options,
        Sheet $sheet,
        AcademicYear $academicYear,
        JemputanDriver $driver,
        ?SchoolLevel $schoolLevel,
        ?int $schoolClassId,
    ): void {
        $writer->setCurrentSheet($sheet);
        $firstReport = $this->recapService->generate(
            $academicYear->id,
            $driver->id,
            $schoolLevel,
            $schoolClassId,
            self::CHUNK_SIZE,
            1,
            false,
        );
        $this->configureSheet($options, $sheet);
        $this->writeSheetHeader($writer, $options, $sheet, $firstReport);
        $rowNumber = 1;
        $page = 1;
        $report = $firstReport;

        while (true) {
            foreach ($report['rows'] as $row) {
                $writer->addRow($this->dataRow($report, $row, $rowNumber));
                $rowNumber++;
            }

            if ($page >= $report['pagination']['last_page']) {
                break;
            }

            $page++;
            unset($report);
            $report = $this->recapService->generate(
                $academicYear->id,
                $driver->id,
                $schoolLevel,
                $schoolClassId,
                self::CHUNK_SIZE,
                $page,
                false,
            );
        }

        if ($rowNumber === 1) {
            $writer->addRow($this->stretchedRow(
                'Tidak ada siswa yang sesuai dengan filter.',
                self::COLUMN_COUNT,
                $this->emptyStyle(),
            ));
            $options->mergeCells(0, 7, self::COLUMN_COUNT - 1, 7, $sheet->getIndex());
        }
    }

    private function configureSheet(Options $options, Sheet $sheet): void
    {
        $sheet->setColumnWidth(7, 1);
        $sheet->setColumnWidth(34, 2);
        $sheet->setColumnWidth(18, 3);
        $sheet->setColumnWidth(18, 4);

        for ($monthOffset = 0; $monthOffset < 12; $monthOffset++) {
            $startColumn = 5 + ($monthOffset * 3);
            $sheet->setColumnWidth(18, $startColumn);
            $sheet->setColumnWidth(14, $startColumn + 1);
            $sheet->setColumnWidth(20, $startColumn + 2);
        }

        $sheet->setSheetView(
            (new SheetView)
                ->withFreezeRow(7)
                ->withFreezeColumn('E')
                ->withTopLeftCell('E7')
                ->withZoomScale(85)
        );

        $options->mergeCells(0, 1, self::COLUMN_COUNT - 1, 1, $sheet->getIndex());
        $options->mergeCells(1, 2, self::COLUMN_COUNT - 1, 2, $sheet->getIndex());
        $options->mergeCells(1, 3, self::COLUMN_COUNT - 1, 3, $sheet->getIndex());
    }

    /** @param array<string, mixed> $report */
    private function writeSheetHeader(Writer $writer, Options $options, Sheet $sheet, array $report): void
    {
        $writer->addRow($this->stretchedRow('REKAP JEMPUTAN', self::COLUMN_COUNT, $this->titleStyle()));
        $writer->addRow($this->stretchedRow('Nama Sopir: '.$report['driver']['name'], self::COLUMN_COUNT, $this->metadataStyle()));
        $writer->addRow($this->stretchedRow('Tahun Ajaran: '.$report['academic_year']['label'], self::COLUMN_COUNT, $this->metadataStyle()));
        $writer->addRow(Row::fromValues([]));

        $groupHeader = ['NO.', 'NAMA SISWA', 'KELAS', 'TARIF JEMPUTAN'];
        $detailHeader = ['', '', '', ''];

        foreach ($report['months'] as $month) {
            array_push($groupHeader, Str::upper($month['label']), '', '');
            array_push($detailHeader, 'NOMINAL DIBAYAR', 'TANGGAL BAYAR', 'BANK');
        }

        $writer->addRow(Row::fromValuesWithStyle($groupHeader, $this->headerStyle()));
        $writer->addRow(Row::fromValuesWithStyle($detailHeader, $this->headerStyle()));

        foreach (range(0, 3) as $column) {
            $options->mergeCells(
                $column,
                self::GROUP_HEADER_ROW,
                $column,
                self::GROUP_HEADER_ROW + 1,
                $sheet->getIndex(),
            );
        }

        for ($monthOffset = 0; $monthOffset < 12; $monthOffset++) {
            $startColumn = 4 + ($monthOffset * 3);
            $options->mergeCells(
                $startColumn,
                self::GROUP_HEADER_ROW,
                $startColumn + 2,
                self::GROUP_HEADER_ROW,
                $sheet->getIndex(),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $row
     */
    private function dataRow(array $report, array $row, int $number): Row
    {
        $effectiveAmounts = collect($row['months'])
            ->filter(fn (array $month): bool => $month['bill_count'] > 0)
            ->pluck('effective_billed_amount')
            ->map(fn ($amount): float => round((float) $amount, 2))
            ->unique()
            ->values();
        $tariff = match (true) {
            $effectiveAmounts->count() === 1 => (float) $effectiveAmounts->first(),
            $effectiveAmounts->count() > 1 => 'Bervariasi',
            default => '',
        };
        $values = [$number, $row['student_name'], $row['historical_class'] ?? '', $tariff];
        $styles = [$this->centerStyle(), $this->textStyle(), $this->textStyle(), is_float($tariff) ? $this->currencyStyle() : $this->centerStyle()];

        foreach ($report['months'] as $month) {
            $monthData = $row['months'][$month['key']];
            $values[] = (float) $monthData['paid_amount'];
            $values[] = $monthData['payment_date'] === null
                ? ''
                : CarbonImmutable::parse($monthData['payment_date'])->format('d/m/Y');
            $values[] = $monthData['bank_display'] ?? '';
            array_push($styles, $this->currencyStyle(), $this->centerStyle(), $this->textStyle());
        }

        return Row::fromValuesWithStyles($values, $styles);
    }

    private function stretchedRow(string $value, int $count, Style $style): Row
    {
        return Row::fromValuesWithStyle(array_pad([$value], $count, ''), $style);
    }

    private function titleStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 16,
            fontColor: Color::WHITE,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            backgroundColor: '005E6A',
        );
    }

    private function metadataStyle(): Style
    {
        return new Style(fontBold: true, fontColor: '005E6A', shouldWrapText: false);
    }

    private function headerStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 9,
            fontColor: Color::WHITE,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: '4472C4',
        );
    }

    private function textStyle(): Style
    {
        return new Style(shouldWrapText: false, border: $this->border());
    }

    private function centerStyle(): Style
    {
        return new Style(
            cellAlignment: CellAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
        );
    }

    private function currencyStyle(): Style
    {
        return new Style(
            cellAlignment: CellAlignment::RIGHT,
            shouldWrapText: false,
            border: $this->border(),
            format: '"Rp" #,##0',
        );
    }

    private function emptyStyle(): Style
    {
        return new Style(
            fontItalic: true,
            fontColor: '666666',
            cellAlignment: CellAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
        );
    }

    private function border(): Border
    {
        return new Border(
            new BorderPart(BorderName::TOP, 'B7C9D6', BorderWidth::THIN),
            new BorderPart(BorderName::RIGHT, 'B7C9D6', BorderWidth::THIN),
            new BorderPart(BorderName::BOTTOM, 'B7C9D6', BorderWidth::THIN),
            new BorderPart(BorderName::LEFT, 'B7C9D6', BorderWidth::THIN),
        );
    }
}
