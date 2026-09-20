<?php

namespace App\Services;

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
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class SchoolMonthlyReportSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-school-monthly-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $options = new Options;
        $writer = new Writer($options);

        try {
            $writer->openToFile($path);
            $this->writeSummarySheet($writer, $options, $report);
            $this->writeDetailSheet($writer, $report);
            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();
            unlink($path);

            throw $exception;
        }

        return $path;
    }

    /** @param array<string, mixed> $report */
    private function writeSummarySheet(Writer $writer, Options $options, array $report): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Laporan Bulanan');
        $columnCount = count($report['categories']) + 4;
        $this->configureSummaryColumns($sheet, $report, $columnCount);

        $writer->addRow($this->stretchedRow('ANNUR MANAGEMENT', $columnCount, $this->titleStyle()));
        $writer->addRow($this->stretchedRow('REKAP PENERIMAAN UANG CASH DAN TRANSFER', $columnCount, $this->subtitleStyle()));
        $writer->addRow($this->stretchedRow('(MONTHLY REPORT)', $columnCount, $this->subtitleStyle()));
        $writer->addRow($this->stretchedRow('BULAN : '.$report['month_label_upper'], $columnCount, $this->subtitleStyle()));
        $writer->addRow($this->stretchedRow('UNIT : '.$report['unit_name'], $columnCount, $this->subtitleStyle()));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow($this->stretchedRow('PENERIMAAN BANK', $columnCount, $this->sectionStyle()));

        $header = ['Hari / Tanggal', 'Bank'];

        foreach ($report['categories'] as $category) {
            $header[] = $category['name'];
        }

        $header[] = 'Total';
        $header[] = 'Total Harian';
        $writer->addRow(Row::fromValuesWithStyles($header, $this->summaryHeaderStyles($columnCount)));

        $rowNumber = 9;

        if ($report['bank']['account_count'] === 0) {
            $writer->addRow($this->stretchedRow('Belum ada rekening bank yang dikonfigurasi', $columnCount, $this->tableBodyStyle()));
            $rowNumber++;
        } elseif ($report['bank']['dates'] === []) {
            $writer->addRow($this->stretchedRow('Tidak ada transaksi', $columnCount, $this->tableBodyStyle()));
            $rowNumber++;
        } else {
            foreach ($report['bank']['dates'] as $dateGroup) {
                $this->writeBankDateGroup($writer, $options, $report, $dateGroup, $columnCount, $rowNumber);
                $rowNumber += count($dateGroup['banks']);
            }
        }

        $bankTotalRow = ['TOTAL PENERIMAAN BANK', ''];

        foreach ($report['categories'] as $category) {
            $bankTotalRow[] = $report['bank']['category_totals'][$category['key']] ?? 0.0;
        }

        $bankTotalRow[] = $report['bank']['total'];
        $bankTotalRow[] = $report['bank']['total'];
        $writer->addRow(Row::fromValuesWithStyle($this->padCells($bankTotalRow, $columnCount), $this->grandTotalStyle()));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow($this->stretchedRow('PENERIMAAN TUNAI', $columnCount, $this->sectionStyle()));

        $cashHeader = ['Hari / Tanggal'];

        foreach ($report['categories'] as $category) {
            $cashHeader[] = $category['name'];
        }

        $cashHeader[] = 'Total';
        $cashColumnCount = count($cashHeader);
        $writer->addRow(Row::fromValuesWithStyles($cashHeader, $this->cashHeaderStyles($cashColumnCount)));

        if ($report['cash']['dates'] === []) {
            $writer->addRow($this->stretchedRow('Tidak ada transaksi', $cashColumnCount, $this->tableBodyStyle()));
        } else {
            foreach ($report['cash']['dates'] as $cashDate) {
                $cashRow = [$cashDate['date_label']];

                foreach ($report['categories'] as $category) {
                    $cashRow[] = $cashDate['amounts'][$category['key']];
                }

                $cashRow[] = $cashDate['total'];
                $writer->addRow(Row::fromValuesWithStyles($cashRow, $this->cashBodyStyles($cashColumnCount)));
            }
        }

        $cashTotalRow = ['GRAND TOTAL TUNAI'];

        foreach ($report['categories'] as $category) {
            $cashTotalRow[] = $report['cash']['category_totals'][$category['key']] ?? 0.0;
        }

        $cashTotalRow[] = $report['cash']['total'];
        $writer->addRow(Row::fromValuesWithStyle($cashTotalRow, $this->grandTotalStyle()));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow($this->stretchedRow('RINGKASAN TOTAL', $columnCount, $this->sectionStyle()));
        $writer->addRow(Row::fromValuesWithStyles(
            ['TOTAL PENERIMAAN BANK', $report['bank']['total']],
            [$this->tableBodyStyle(), $this->dailyTotalStyle()]
        ));
        $writer->addRow(Row::fromValuesWithStyles(
            ['TOTAL PENERIMAAN TUNAI', $report['cash']['total']],
            [$this->tableBodyStyle(), $this->dailyTotalStyle()]
        ));
        $writer->addRow(Row::fromValuesWithStyle(
            ['GRAND TOTAL', $report['grand_total']],
            $this->grandTotalStyle()
        ));
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array{date_label: string, banks: list<array{bank_label: string, amounts: array<string, float>, total: float}>, total: float}  $dateGroup
     * @param  int<1, max>  $columnCount
     * @param  int<1, max>  $startRow
     */
    private function writeBankDateGroup(Writer $writer, Options $options, array $report, array $dateGroup, int $columnCount, int $startRow): void
    {
        foreach ($dateGroup['banks'] as $index => $bank) {
            $cells = [
                $index === 0 ? $dateGroup['date_label'] : '',
                $bank['bank_label'],
            ];

            foreach ($report['categories'] as $category) {
                $cells[] = $bank['amounts'][$category['key']];
            }

            $cells[] = $bank['total'];
            $cells[] = $index === 0 ? $dateGroup['total'] : '';
            $writer->addRow(Row::fromValuesWithStyles(
                $this->padCells($cells, $columnCount),
                $this->summaryBodyStyles($columnCount)
            ));
        }

        $bankCount = count($dateGroup['banks']);

        if ($bankCount > 1) {
            $endRow = $startRow + $bankCount - 1;
            $options->mergeCells(0, $startRow, 0, $endRow);
            $options->mergeCells($columnCount - 1, $startRow, $columnCount - 1, $endRow);
        }
    }

    /** @param array<string, mixed> $report */
    private function writeDetailSheet(Writer $writer, array $report): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Rincian Transaksi');
        $this->configureDetailColumns($sheet);

        $writer->addRow(Row::fromValuesWithStyle(['RINCIAN TRANSAKSI SEKOLAH'], $this->titleStyle()));
        $writer->addRow(Row::fromValues([
            'Bulan',
            $report['month_label_upper'],
        ]));
        $writer->addRow(Row::fromValues([
            'Unit',
            $report['unit_name'],
        ]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle([
            'No.',
            'No. Kwitansi',
            'Tanggal',
            'Hari',
            'Nama Siswa',
            'Kelas',
            'Jenis Pembayaran',
            'Rincian',
            'Bank / Penerimaan',
            'Jenis Penerimaan',
            'Nominal',
        ], $this->tableHeaderStyle()));

        foreach ($report['detail_rows'] as $index => $detail) {
            $writer->addRow(Row::fromValuesWithStyle([
                $index + 1,
                $detail['receipt_number'],
                $detail['recorded_at']->format('d/m/Y'),
                $detail['recorded_at']->settings(['locale' => 'id'])->translatedFormat('l'),
                $detail['student_name'],
                $detail['class_name'],
                $detail['payment_kind'] === 'manual' ? 'Manual' : 'Tagihan',
                $detail['detail_label'],
                $detail['bank_label'],
                $detail['channel_label'],
                $detail['amount'],
            ], $this->tableBodyStyle()));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            ['', '', '', '', '', '', '', '', '', 'TOTAL', $report['grand_total']],
            $this->grandTotalStyle()
        ));
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  int<1, max>  $columnCount
     */
    private function configureSummaryColumns(Sheet $sheet, array $report, int $columnCount): void
    {
        $sheet->setColumnWidth(30, 1, 2);
        $lastCategoryColumn = 2 + count($report['categories']);
        $sheet->setColumnWidth(16, 3, max(3, $lastCategoryColumn));
        $sheet->setColumnWidth(16, $lastCategoryColumn + 1, $columnCount);
    }

    private function configureDetailColumns(Sheet $sheet): void
    {
        $sheet->setColumnWidth(6, 1, 1);
        $sheet->setColumnWidth(16, 2, 2);
        $sheet->setColumnWidth(12, 3, 4);
        $sheet->setColumnWidth(24, 5, 5);
        $sheet->setColumnWidth(12, 6, 6);
        $sheet->setColumnWidth(18, 7, 7);
        $sheet->setColumnWidth(28, 8, 8);
        $sheet->setColumnWidth(26, 9, 9);
        $sheet->setColumnWidth(18, 10, 10);
        $sheet->setColumnWidth(16, 11, 11);
    }

    /** @return array<int, Style> */
    private function summaryHeaderStyles(int $columnCount): array
    {
        $styles = array_fill(0, 2, $this->tableHeaderStyle());

        return array_pad($styles, $columnCount, $this->moneyHeaderStyle());
    }

    /** @return array<int, Style> */
    private function cashHeaderStyles(int $columnCount): array
    {
        return array_pad([$this->tableHeaderStyle()], $columnCount, $this->moneyHeaderStyle());
    }

    /** @return array<int, Style> */
    private function summaryBodyStyles(int $columnCount): array
    {
        $styles = [$this->dateBodyStyle(), $this->tableBodyStyle()];
        $styles = array_pad($styles, $columnCount, $this->moneyBodyStyle());
        $styles[$columnCount - 1] = $this->dailyTotalStyle();

        return $styles;
    }

    /** @return array<int, Style> */
    private function cashBodyStyles(int $columnCount): array
    {
        return array_pad([$this->dateBodyStyle()], $columnCount, $this->moneyBodyStyle());
    }

    /** @param list<mixed> $cells
     * @return list<mixed>
     */
    private function padCells(array $cells, int $count): array
    {
        return array_pad($cells, $count, '');
    }

    private function stretchedRow(string $value, int $count, Style $style): Row
    {
        $cells = [$value];
        $cells = array_pad($cells, $count, '');

        return Row::fromValuesWithStyle($cells, $style);
    }

    private function titleStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 16,
            fontColor: Color::WHITE,
            backgroundColor: '005E6A'
        );
    }

    private function subtitleStyle(): Style
    {
        return new Style(fontBold: true, fontSize: 13, fontColor: '005E6A');
    }

    private function sectionStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontSize: 11,
            fontColor: Color::WHITE,
            backgroundColor: '005E6A'
        );
    }

    private function tableHeaderStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontColor: Color::WHITE,
            fontSize: 9,
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border(),
            backgroundColor: '4472C4'
        );
    }

    private function tableBodyStyle(): Style
    {
        return new Style(shouldWrapText: true, border: $this->border(), format: '#,##0');
    }

    private function dateBodyStyle(): Style
    {
        return new Style(
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border()
        );
    }

    private function moneyBodyStyle(): Style
    {
        return new Style(
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            border: $this->border(),
            format: '#,##0;;'
        );
    }

    private function dailyTotalStyle(): Style
    {
        return new Style(
            fontBold: true,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            border: $this->border(),
            format: '#,##0;;'
        );
    }

    private function moneyHeaderStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontColor: Color::WHITE,
            fontSize: 9,
            cellAlignment: CellAlignment::RIGHT,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            border: $this->border(),
            backgroundColor: '4472C4'
        );
    }

    private function grandTotalStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontColor: Color::WHITE,
            fontSize: 12,
            border: $this->border(),
            backgroundColor: '005E6A',
            format: '#,##0;;'
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
