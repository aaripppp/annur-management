<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class SchoolMonthlyByLevelReportSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-monthly-level-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $options = new Options;
        $writer = new Writer($options);

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Laporan Per Jenjang');
            $columnCount = count($report['categories']) + 4;
            $sheet->setColumnWidth(18, 1, 2);
            $sheet->setColumnWidth(16, 3, $columnCount);
            $writer->addRow($this->stretchedRow('ANNUR MANAGEMENT', $columnCount, $this->titleStyle()));
            $writer->addRow($this->stretchedRow('LAPORAN PENERIMAAN PER JENJANG', $columnCount, $this->subtitleStyle()));
            $writer->addRow($this->stretchedRow('BULAN : '.$report['month_label_upper'], $columnCount, $this->subtitleStyle()));
            $writer->addRow($this->stretchedRow('UNIT : '.$report['unit_name'], $columnCount, $this->subtitleStyle()));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow($this->stretchedRow('PENERIMAAN BANK', $columnCount, $this->sectionStyle()));

            $bankHeader = ['Jenjang', 'Bank'];
            foreach ($report['categories'] as $category) {
                $bankHeader[] = $category['name'];
            }
            $bankHeader[] = 'Total';
            $bankHeader[] = 'Total Jenjang';
            $writer->addRow(Row::fromValuesWithStyle($bankHeader, $this->headerStyle()));
            $rowNumber = 8;

            if ($report['bank']['levels'] === []) {
                $writer->addRow($this->stretchedRow('Tidak ada penerimaan bank', $columnCount, $this->bodyStyle()));
                $rowNumber++;
            } else {
                foreach ($report['bank']['levels'] as $level) {
                    $startRow = $rowNumber;

                    foreach ($level['banks'] as $index => $bank) {
                        $cells = [$index === 0 ? $level['level_label'] : '', $bank['bank_label']];
                        foreach ($report['categories'] as $category) {
                            $cells[] = $bank['amounts'][$category['key']];
                        }
                        $cells[] = $bank['total'];
                        $cells[] = $index === 0 ? $level['bank_total'] : '';
                        $writer->addRow(Row::fromValuesWithStyles($cells, $this->bankBodyStyles($columnCount)));
                        $rowNumber++;
                    }

                    if ($level['bank_count'] > 1) {
                        $options->mergeCells(0, $startRow, 0, $rowNumber - 1);
                        $options->mergeCells($columnCount - 1, $startRow, $columnCount - 1, $rowNumber - 1);
                    }
                }
            }

            $bankTotal = ['TOTAL PENERIMAAN BANK', ''];
            foreach ($report['categories'] as $category) {
                $bankTotal[] = $report['bank']['category_totals'][$category['key']];
            }
            $bankTotal[] = $report['bank']['total'];
            $bankTotal[] = $report['bank']['total'];
            $writer->addRow(Row::fromValuesWithStyle($bankTotal, $this->totalStyle()));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow($this->stretchedRow('PENERIMAAN TUNAI', $columnCount, $this->sectionStyle()));

            $cashHeader = ['Jenjang'];
            foreach ($report['categories'] as $category) {
                $cashHeader[] = $category['name'];
            }
            $cashHeader[] = 'Total';
            $writer->addRow(Row::fromValuesWithStyle($cashHeader, $this->headerStyle()));

            if ($report['cash']['levels'] === []) {
                $writer->addRow($this->stretchedRow('Tidak ada penerimaan tunai', count($cashHeader), $this->bodyStyle()));
            } else {
                foreach ($report['cash']['levels'] as $level) {
                    $cells = [$level['level_label']];
                    foreach ($report['categories'] as $category) {
                        $cells[] = $level['cash_amounts'][$category['key']];
                    }
                    $cells[] = $level['cash_total'];
                    $writer->addRow(Row::fromValuesWithStyles($cells, $this->cashBodyStyles(count($cashHeader))));
                }
            }

            $cashTotal = ['TOTAL PENERIMAAN TUNAI'];
            foreach ($report['categories'] as $category) {
                $cashTotal[] = $report['cash']['category_totals'][$category['key']];
            }
            $cashTotal[] = $report['cash']['total'];
            $writer->addRow(Row::fromValuesWithStyle($cashTotal, $this->totalStyle()));
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
    private function bankBodyStyles(int $columnCount): array
    {
        return array_pad([$this->levelStyle(), $this->bodyStyle()], $columnCount, $this->moneyStyle());
    }

    /** @return list<Style> */
    private function cashBodyStyles(int $columnCount): array
    {
        return array_pad([$this->levelStyle()], $columnCount, $this->moneyStyle());
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

    private function levelStyle(): Style
    {
        return new Style(
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
        );
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
