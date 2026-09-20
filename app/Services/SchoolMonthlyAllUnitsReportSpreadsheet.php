<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class SchoolMonthlyAllUnitsReportSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-monthly-all-units-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $writer = new Writer(new Options);

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Seluruh Unit');
            $columnCount = count($report['categories']) + 2;
            $sheet->setColumnWidth(22, 1);
            $sheet->setColumnWidth(16, 2, $columnCount);
            $writer->addRow($this->stretchedRow('ANNUR MANAGEMENT', $columnCount, $this->titleStyle()));
            $writer->addRow($this->stretchedRow('LAPORAN PENERIMAAN SELURUH UNIT', $columnCount, $this->subtitleStyle()));
            $writer->addRow($this->stretchedRow('BULAN : '.$report['month_label_upper'], $columnCount, $this->subtitleStyle()));
            $writer->addRow($this->stretchedRow('UNIT : '.$report['unit_name'], $columnCount, $this->subtitleStyle()));
            $writer->addRow(Row::fromValues([]));

            if ($report['detail_count'] === 0) {
                $writer->addRow($this->stretchedRow('Belum ada penerimaan siswa pada '.$report['month_label'].'.', $columnCount, $this->bodyStyle()));
                $writer->close();

                return $path;
            }

            $writer->addRow($this->stretchedRow('PENERIMAAN BANK', $columnCount, $this->sectionStyle()));
            $bankHeader = ['Bank'];

            foreach ($report['categories'] as $category) {
                $bankHeader[] = $category['name'];
            }

            $bankHeader[] = 'Total';
            $writer->addRow(Row::fromValuesWithStyle($bankHeader, $this->headerStyle()));

            if ($report['bank']['rows'] === []) {
                $writer->addRow($this->stretchedRow('Tidak ada penerimaan bank', $columnCount, $this->bodyStyle()));
            } else {
                foreach ($report['bank']['rows'] as $bank) {
                    $cells = [$bank['bank_label']];

                    foreach ($report['categories'] as $category) {
                        $cells[] = $bank['amounts'][$category['key']];
                    }

                    $cells[] = $bank['total'];
                    $writer->addRow(Row::fromValuesWithStyles($cells, $this->bodyStyles($columnCount)));
                }
            }

            $bankTotal = ['TOTAL PENERIMAAN BANK'];

            foreach ($report['categories'] as $category) {
                $bankTotal[] = $report['bank']['category_totals'][$category['key']];
            }

            $bankTotal[] = $report['bank']['total'];
            $writer->addRow(Row::fromValuesWithStyle($bankTotal, $this->totalStyle()));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow($this->stretchedRow('PENERIMAAN TUNAI', $columnCount, $this->sectionStyle()));

            $cashHeader = [];
            foreach ($report['categories'] as $category) {
                $cashHeader[] = $category['name'];
            }
            $cashHeader[] = 'Total';
            $cashColumnCount = count($cashHeader);
            $writer->addRow(Row::fromValuesWithStyle($cashHeader, $this->headerStyle()));

            $cashRow = [];
            foreach ($report['categories'] as $category) {
                $cashRow[] = $report['cash']['amounts'][$category['key']];
            }
            $cashRow[] = $report['cash']['total'];
            $writer->addRow(Row::fromValuesWithStyle($cashRow, $this->moneyStyle()));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow($this->stretchedRow('RINGKASAN TOTAL', $columnCount, $this->sectionStyle()));
            $writer->addRow(Row::fromValuesWithStyles(['TOTAL PENERIMAAN BANK', $report['bank']['total']], [$this->bodyStyle(), $this->moneyStyle()]));
            $writer->addRow(Row::fromValuesWithStyles(['TOTAL PENERIMAAN TUNAI', $report['cash']['total']], [$this->bodyStyle(), $this->moneyStyle()]));
            $writer->addRow(Row::fromValuesWithStyle(['GRAND TOTAL', $report['grand_total']], $this->totalStyle()));
            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();
            unlink($path);

            throw $exception;
        }

        return $path;
    }

    private function stretchedRow(string $value, int $count, Style $style): Row
    {
        return Row::fromValuesWithStyle(array_pad([$value], $count, ''), $style);
    }

    /** @return list<Style> */
    private function bodyStyles(int $columnCount): array
    {
        return array_pad([$this->bodyStyle()], $columnCount, $this->moneyStyle());
    }

    private function titleStyle(): Style
    {
        return new Style(fontBold: true, fontSize: 15, fontColor: Color::WHITE, backgroundColor: '005E6A');
    }

    private function subtitleStyle(): Style
    {
        return new Style(fontBold: true, fontSize: 11, fontColor: '005E6A');
    }

    private function sectionStyle(): Style
    {
        return new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '005E6A');
    }

    private function headerStyle(): Style
    {
        return new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '4472C4', cellAlignment: CellAlignment::CENTER);
    }

    private function bodyStyle(): Style
    {
        return new Style;
    }

    private function moneyStyle(): Style
    {
        return new Style(cellAlignment: CellAlignment::RIGHT, format: '#,##0;;');
    }

    private function totalStyle(): Style
    {
        return new Style(fontBold: true, backgroundColor: 'E2F0D9', format: '#,##0;;');
    }
}
