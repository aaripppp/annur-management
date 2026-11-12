<?php

namespace App\Services;

use Carbon\CarbonImmutable;
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

class JemputanDriverSalarySpreadsheet
{
    public const SHEET_NAME = 'GAJI_SOPIR';

    private const COLUMN_COUNT = 8;

    private const GROUP_HEADER_ROW = 4;

    private const DETAIL_HEADER_ROW = 5;

    private const DATA_START_ROW = 6;

    private const IDENTITY_COLOR = 'D9EAF7';

    private const PERIOD_COLOR = 'FFF2CC';

    private const BORDER_COLOR = 'B7C9D6';

    public function filename(array $report): string
    {
        $driver = preg_replace('/[^0-9A-Za-z_-]+/', '-', $report['driver']['name']);
        $driver = trim(trim($driver ?? ''), '-_');
        $driver = $driver !== '' ? $driver : 'Sopir-'.$report['driver']['id'];

        return 'Gaji_Sopir_'.$driver.'_'.$report['month']['key'].'.xlsx';
    }

    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        if (! ($report['summary']['percentage_configured'] ?? false)) {
            throw new RuntimeException('Persentase potongan yayasan belum diatur pada Master Sopir.');
        }

        $path = tempnam(sys_get_temp_dir(), 'annur-jemputan-salary-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file gaji sopir sementara.');
        }

        $options = new Options(
            pageMargin: new PageMargin(top: 0.6, right: 0.4, bottom: 0.6, left: 0.4, header: 0.2, footer: 0.2),
            pageSetup: new PageSetup(PageOrientation::PORTRAIT, PaperSize::A4, 0, 1),
        );
        $writer = new Writer($options);

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName(self::SHEET_NAME);
            $this->configureSheet($options, $sheet);
            $this->writeReport($writer, $options, $sheet, $report);
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

    private function configureSheet(Options $options, Sheet $sheet): void
    {
        $sheet->setColumnWidth(5, 1);
        $sheet->setColumnWidth(30, 2);
        $sheet->setColumnWidth(12, 3);
        $sheet->setColumnWidth(16, 4);
        $sheet->setColumnWidth(22, 5);
        $sheet->setColumnWidth(14, 6);
        $sheet->setColumnWidth(11, 7);
        $sheet->setColumnWidth(20, 8);
        $sheet->setPrintTitleRows(self::GROUP_HEADER_ROW.':'.self::DETAIL_HEADER_ROW);
        $sheet->setSheetView(
            (new SheetView)
                ->withTopLeftCell('A1')
                ->withZoomScale(100)
        );

        $options->mergeCells(0, 1, self::COLUMN_COUNT - 1, 1, $sheet->getIndex());
        $options->mergeCells(0, 2, self::COLUMN_COUNT - 1, 2, $sheet->getIndex());
        $options->mergeCells(0, 3, self::COLUMN_COUNT - 1, 3, $sheet->getIndex());

        foreach (range(0, 4) as $column) {
            $options->mergeCells(
                $column,
                self::GROUP_HEADER_ROW,
                $column,
                self::DETAIL_HEADER_ROW,
                $sheet->getIndex(),
            );
        }

        $options->mergeCells(5, self::GROUP_HEADER_ROW, self::COLUMN_COUNT - 1, self::GROUP_HEADER_ROW, $sheet->getIndex());
    }

    /** @param array<string, mixed> $report */
    private function writeReport(Writer $writer, Options $options, Sheet $sheet, array $report): void
    {
        $writer->addRow($this->stretchedRow(
            'DATA JEMPUTAN SISWA TP. '.$this->academicYearLabel($report),
            $this->titleStyle(),
            24,
        ));
        $writer->addRow($this->stretchedRow('Nama Sopir: '.$report['driver']['name'], $this->metadataStyle(), 16));
        $writer->addRow($this->stretchedRow('Periode: '.$report['month']['label'], $this->metadataStyle(), 16));
        $this->writeHeaderRows($writer, $options, $sheet, $report);

        foreach ($report['rows'] as $index => $row) {
            $writer->addRow($this->dataRow($row, $index + 1));
        }

        $lastDataRow = self::DATA_START_ROW + count($report['rows']) - 1;

        if ($report['rows'] === []) {
            $lastDataRow = self::DATA_START_ROW;
            $writer->addRow($this->stretchedRow(
                'Tidak ada siswa yang sesuai dengan filter.',
                $this->emptyStyle(),
                18,
            ));
            $options->mergeCells(0, self::DATA_START_ROW, self::COLUMN_COUNT - 1, self::DATA_START_ROW, $sheet->getIndex());
        }

        $totalRow = $lastDataRow + 1;
        $this->writeTotalRow($writer, $options, $sheet, $totalRow, $lastDataRow);
        $this->writeSummary($writer, $options, $sheet, $report, $totalRow);
    }

    /** @param array<string, mixed> $report */
    private function writeHeaderRows(Writer $writer, Options $options, Sheet $sheet, array $report): void
    {
        $groupValues = ['No', 'Nama', 'Kelas', 'Tujuan', 'Ket', Str::upper((string) $report['month']['label']), '', ''];
        $groupStyles = [
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->periodHeaderStyle(),
            $this->periodHeaderStyle(),
            $this->periodHeaderStyle(),
        ];
        $writer->addRow(Row::fromValuesWithStyles($groupValues, $groupStyles, 22));

        $detailValues = ['', '', '', '', '', 'Jumlah', 'Tanggal', 'Jenis'];
        $detailStyles = [
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->identityHeaderStyle(),
            $this->periodHeaderStyle(),
            $this->periodHeaderStyle(),
            $this->periodHeaderStyle(),
        ];
        $writer->addRow(Row::fromValuesWithStyles($detailValues, $detailStyles, 18));
    }

    /** @param array<string, mixed> $row */
    private function dataRow(array $row, int $number): Row
    {
        $isMultiDriver = (bool) $row['is_multi_driver'];
        $values = [
            $number,
            $row['student_name'],
            $row['historical_class'] ?? '',
            '',
            $this->allocationNote($row),
            (float) $row['salary_amount'],
            $row['payment_date'] === null
                ? ''
                : CarbonImmutable::parse($row['payment_date'])->format('d/m/Y'),
            $row['bank_display'] ?? $row['payment_method'] ?? '',
        ];
        $styles = [
            $this->centerStyle(),
            $this->nameStyle(),
            $this->centerStyle(),
            $this->textStyle(),
            $isMultiDriver ? $this->multiDriverTextStyle() : $this->textStyle(),
            $this->currencyStyle($isMultiDriver),
            $this->centerStyle(),
            $this->textStyle(),
        ];

        return Row::fromValuesWithStyles($values, $styles, 18);
    }

    private function writeTotalRow(
        Writer $writer,
        Options $options,
        Sheet $sheet,
        int $totalRow,
        int $lastDataRow,
    ): void {
        $values = [
            'TOTAL',
            '',
            '',
            '',
            '',
            '=SUM(F'.self::DATA_START_ROW.':F'.$lastDataRow.')',
            '',
            '',
        ];
        $styles = [
            $this->totalLabelStyle(),
            $this->totalLabelStyle(),
            $this->totalLabelStyle(),
            $this->totalLabelStyle(),
            $this->totalLabelStyle(),
            $this->totalCurrencyStyle(),
            $this->totalEmptyStyle(),
            $this->totalEmptyStyle(),
        ];

        $writer->addRow(Row::fromValuesWithStyles($values, $styles, 20));
        $options->mergeCells(0, $totalRow, 4, $totalRow, $sheet->getIndex());
    }

    /** @param array<string, mixed> $report */
    private function writeSummary(
        Writer $writer,
        Options $options,
        Sheet $sheet,
        array $report,
        int $totalRow,
    ): void {
        $summaryRow = $totalRow + 2;
        $revenueRow = $summaryRow + 1;
        $deductionRow = $summaryRow + 2;
        $netRow = $summaryRow + 3;
        $percentage = (float) $report['summary']['percentage'];
        $percentageLabel = $this->percentageLabel($percentage);
        $percentageFormula = $this->percentageFormula($percentage);

        $writer->addRow(Row::fromValues([]));
        $note = $this->summaryNote($report);
        $writer->addRow($this->summaryTitleRow($note));

        if ($note !== null) {
            $options->mergeCells(0, $summaryRow, 4, $summaryRow, $sheet->getIndex());
        }

        $options->mergeCells(5, $summaryRow, self::COLUMN_COUNT - 1, $summaryRow, $sheet->getIndex());

        $writer->addRow($this->rincianRow(
            'Pendapatan '.$report['month']['label'],
            '=F'.$totalRow,
        ));
        $writer->addRow($this->rincianRow(
            'Potongan Yayasan ('.$percentageLabel.'%)',
            '=ROUND(F'.$totalRow.'*'.$percentageFormula.'/100,0)',
            $this->rincianDeductionStyle(),
            $this->rincianDeductionValueStyle(),
        ));
        $writer->addRow($this->rincianRow(
            'Total',
            '=H'.$revenueRow.'-H'.$deductionRow,
            $this->rincianTotalLabelStyle(),
            $this->rincianTotalValueStyle(),
        ));

        foreach ([$revenueRow, $deductionRow, $netRow] as $rowNumber) {
            $options->mergeCells(5, $rowNumber, 6, $rowNumber, $sheet->getIndex());
        }

        $signatureDateRow = $netRow + 2;

        $writer->addRow(Row::fromValues([]));
        $writer->addRow($this->signatureRow(
            'Cikarang, '.CarbonImmutable::now()->locale('id')->translatedFormat('j F Y'),
        ));
        $writer->addRow($this->spacerRow(8));
        $writer->addRow($this->signatureRow('Penerima,'));
        $writer->addRow($this->spacerRow(22));
        $writer->addRow($this->spacerRow(22));
        $writer->addRow($this->spacerRow(22));
        $writer->addRow($this->signatureRow('_____________________'));
        $writer->addRow($this->signatureRow($report['driver']['name'], bold: true));

        foreach ([
            $signatureDateRow,
            $signatureDateRow + 1,
            $signatureDateRow + 2,
            $signatureDateRow + 3,
            $signatureDateRow + 4,
            $signatureDateRow + 5,
            $signatureDateRow + 6,
            $signatureDateRow + 7,
        ] as $rowNumber) {
            $options->mergeCells(5, $rowNumber, self::COLUMN_COUNT - 1, $rowNumber, $sheet->getIndex());
        }
    }

    private function summaryTitleRow(?string $note): Row
    {
        $withNote = $note !== null;
        $values = $withNote
            ? [$note, '', '', '', '', 'Rincian:', '', '']
            : ['', '', '', '', '', 'Rincian:', '', ''];
        $styles = $withNote
            ? [
                $this->multiNoteStyle(),
                $this->multiNoteStyle(),
                $this->multiNoteStyle(),
                $this->multiNoteStyle(),
                $this->multiNoteStyle(),
                $this->rincianTitleStyle(),
                $this->rincianTitleStyle(),
                $this->rincianTitleStyle(),
            ]
            : [
                $this->plainStyle(),
                $this->plainStyle(),
                $this->plainStyle(),
                $this->plainStyle(),
                $this->plainStyle(),
                $this->rincianTitleStyle(),
                $this->rincianTitleStyle(),
                $this->rincianTitleStyle(),
            ];

        return Row::fromValuesWithStyles($values, $styles, $withNote ? 30 : 18);
    }

    /** @param array<string, mixed> $row */
    private function allocationNote(array $row): string
    {
        if (! ($row['is_multi_driver'] ?? false)) {
            return '';
        }

        return match ($row['allocation_status'] ?? null) {
            JemputanDriverAllocationService::STATUS_VALID => 'MULTI SOPIR - ALOKASI',
            JemputanDriverAllocationService::STATUS_MISSING => 'MULTI SOPIR - BELUM DIALOKASIKAN',
            default => 'MULTI SOPIR - PERLU DIPERBAIKI',
        };
    }

    /** @param array<string, mixed> $report */
    private function summaryNote(array $report): ?string
    {
        if ($report['summary']['has_allocation_warning'] ?? false) {
            return 'Catatan: Masih ada siswa Multi-Sopir yang belum memiliki alokasi valid. Nominal merupakan angka sementara dan harus diperiksa sebelum laporan digunakan.';
        }

        if ($report['has_multi_driver'] ?? false) {
            return 'Catatan: Nominal siswa Multi-Sopir sudah mengikuti alokasi yang tersimpan.';
        }

        return null;
    }

    private function rincianRow(
        string $label,
        string|float|int $value,
        ?Style $labelStyle = null,
        ?Style $valueStyle = null,
    ): Row {
        $labelStyle ??= $this->rincianLabelStyle();
        $valueStyle ??= $this->rincianValueStyle();

        return Row::fromValuesWithStyles(
            ['', '', '', '', '', $label, '', $value],
            [
                $this->plainStyle(),
                $this->plainStyle(),
                $this->plainStyle(),
                $this->plainStyle(),
                $this->plainStyle(),
                $labelStyle,
                $labelStyle,
                $valueStyle,
            ],
            18,
        );
    }

    private function spacerRow(float $height): Row
    {
        return Row::fromValues(['', '', '', '', '', ' ', '', ''], $height);
    }

    private function signatureRow(string $value, bool $bold = false): Row
    {
        $style = new Style(
            fontBold: $bold,
            fontSize: 9,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
        );

        return Row::fromValuesWithStyles(
            ['', '', '', '', '', $value, '', ''],
            array_fill(0, self::COLUMN_COUNT, $style),
            16,
        );
    }

    private function stretchedRow(string $value, Style $style, float $height = 0): Row
    {
        return new Row(
            Row::fromValuesWithStyle(array_pad([$value], self::COLUMN_COUNT, ''), $style)->cells,
            $height,
        );
    }

    /** @param array<string, mixed> $report */
    private function academicYearLabel(array $report): string
    {
        return str_replace('/', '-', (string) $report['academic_year']['label']);
    }

    private function percentageLabel(float $percentage): string
    {
        return rtrim(rtrim(number_format($percentage, 2, ',', '.'), '0'), ',');
    }

    private function percentageFormula(float $percentage): string
    {
        $formula = rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');

        return $formula === '' ? '0' : $formula;
    }

    private function titleStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 14,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
        );
    }

    private function metadataStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 10,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
        );
    }

    private function identityHeaderStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 9,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border(),
            backgroundColor: self::IDENTITY_COLOR,
        );
    }

    private function periodHeaderStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 9,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border(),
            backgroundColor: self::PERIOD_COLOR,
        );
    }

    private function nameStyle(): Style
    {
        return new Style(
            fontSize: 9,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            shouldShrinkToFit: true,
            border: $this->border(),
            backgroundColor: 'FFFFFF',
        );
    }

    private function textStyle(): Style
    {
        return new Style(
            fontSize: 9,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: 'FFFFFF',
        );
    }

    private function centerStyle(): Style
    {
        return new Style(
            fontSize: 9,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: 'FFFFFF',
        );
    }

    private function currencyStyle(bool $highlighted = false): Style
    {
        return new Style(
            fontSize: 9,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: $highlighted ? self::PERIOD_COLOR : 'FFFFFF',
            format: '"Rp" #,##0',
        );
    }

    private function multiDriverTextStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontItalic: true,
            fontSize: 8,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: self::PERIOD_COLOR,
        );
    }

    private function multiNoteStyle(): Style
    {
        return new Style(
            fontItalic: true,
            fontSize: 8,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::TOP,
            shouldWrapText: true,
            backgroundColor: self::PERIOD_COLOR,
        );
    }

    private function emptyStyle(): Style
    {
        return new Style(
            fontItalic: true,
            fontColor: '666666',
            fontSize: 9,
            cellAlignment: CellAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
        );
    }

    private function totalLabelStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 9,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: 'FFFFFF',
        );
    }

    private function totalCurrencyStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 9,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: 'FFFFFF',
            format: '"Rp" #,##0',
        );
    }

    private function totalEmptyStyle(): Style
    {
        return new Style(
            fontSize: 9,
            shouldWrapText: false,
            border: $this->border(),
            backgroundColor: 'FFFFFF',
        );
    }

    private function plainStyle(): Style
    {
        return new Style(
            fontSize: 9,
            shouldWrapText: false,
        );
    }

    private function rincianTitleStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 10,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
        );
    }

    private function rincianLabelStyle(): Style
    {
        return new Style(
            fontSize: 9,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
        );
    }

    private function rincianValueStyle(): Style
    {
        return new Style(
            fontSize: 9,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            format: '#,##0',
        );
    }

    private function rincianDeductionStyle(): Style
    {
        return new Style(
            fontUnderline: true,
            fontSize: 9,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->bottomBorder(),
        );
    }

    private function rincianDeductionValueStyle(): Style
    {
        return new Style(
            fontSize: 9,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            border: $this->bottomBorder(),
            format: '#,##0',
        );
    }

    private function rincianTotalLabelStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 9,
            cellAlignment: CellAlignment::LEFT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
        );
    }

    private function rincianTotalValueStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 9,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: false,
            format: '#,##0',
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

    private function bottomBorder(): Border
    {
        return new Border(
            new BorderPart(BorderName::BOTTOM, self::BORDER_COLOR, BorderWidth::THIN),
        );
    }
}
