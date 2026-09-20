<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class StudentTargetArrearsReportSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-target-arrears-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Target dan Tunggakan');
            $sheet->setColumnWidth(30, 1);
            $sheet->setColumnWidth(18, 2, 5);
            $writer->addRow(Row::fromValuesWithStyle(['ANNUR MANAGEMENT'], $this->titleStyle()));
            $writer->addRow(Row::fromValuesWithStyle(['TARGET & TUNGGAKAN SISWA'], $this->subtitleStyle()));
            $writer->addRow(Row::fromValues(['Mode', $report['mode_label']]));
            $writer->addRow(Row::fromValues(['Periode', $report['period_label']]));
            $writer->addRow(Row::fromValues(['Unit', $report['unit_name']]));
            $writer->addRow(Row::fromValues([]));
            $sections = $report['sections'] ?? null;

            if (is_array($sections)) {
                $writer->addRow(Row::fromValuesWithStyles([
                    'GRAND TOTAL',
                    $report['totals']['target'],
                    $report['totals']['paid'],
                    $report['totals']['outstanding'],
                    $report['totals']['achievement_percentage'] / 100,
                ], $this->reportTotalStyles()));
                $writer->addRow(Row::fromValues([]));

                foreach ($sections as $section) {
                    $writer->addRow(Row::fromValuesWithStyle(
                        ['TAGIHAN '.mb_strtoupper($section['mode_label']), $section['period_label']],
                        $this->subtitleStyle()
                    ));
                    $this->writeReportRows($writer, $section);
                    $writer->addRow(Row::fromValues([]));
                }
            } else {
                $this->writeReportRows($writer, $report);
            }

            $detailSheet = $writer->addNewSheetAndMakeItCurrent();
            $detailSheet->setName('Detail Tunggakan');
            $detailSheet->setColumnWidth(28, 1, 3);
            $detailSheet->setColumnWidth(18, 4, 6);
            $writer->addRow(Row::fromValuesWithStyle(['DETAIL TUNGGAKAN SISWA'], $this->titleStyle()));
            $writer->addRow(Row::fromValues(['Periode', $report['period_label']]));
            $writer->addRow(Row::fromValues(['Unit', $report['unit_name']]));
            $writer->addRow(Row::fromValues([]));
            if (is_array($sections)) {
                foreach ($sections as $section) {
                    $writer->addRow(Row::fromValuesWithStyle(
                        ['TAGIHAN '.mb_strtoupper($section['mode_label']), $section['period_label']],
                        $this->subtitleStyle()
                    ));
                    $this->writeDetailRows($writer, $section);
                    $writer->addRow(Row::fromValues([]));
                }
            } else {
                $this->writeDetailRows($writer, $report);
            }

            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();
            unlink($path);

            throw $exception;
        }

        return $path;
    }

    /** @param array<string, mixed> $report */
    private function writeReportRows(Writer $writer, array $report): void
    {
        $writer->addRow(Row::fromValuesWithStyle(
            ['Jenis Tagihan', 'Target', 'Terbayar', 'Tunggakan', 'Capaian'],
            $this->headerStyle()
        ));

        foreach ($report['rows'] as $row) {
            $writer->addRow(Row::fromValuesWithStyles([
                $row['payment_type_name'],
                $row['target'],
                $row['paid'],
                $row['outstanding'],
                $row['achievement_percentage'] / 100,
            ], $this->reportRowStyles()));
        }

        $writer->addRow(Row::fromValuesWithStyles([
            'TOTAL',
            $report['totals']['target'],
            $report['totals']['paid'],
            $report['totals']['outstanding'],
            $report['totals']['achievement_percentage'] / 100,
        ], $this->reportTotalStyles()));
    }

    /** @param array<string, mixed> $report */
    private function writeDetailRows(Writer $writer, array $report): void
    {
        $writer->addRow(Row::fromValuesWithStyle(
            ['Jenis Tagihan', 'Nama Siswa', 'Kelas', 'Tagihan', 'Terbayar', 'Sisa'],
            $this->headerStyle()
        ));

        foreach ($report['rows'] as $row) {
            foreach ($row['details'] as $detail) {
                $writer->addRow(Row::fromValuesWithStyles([
                    $row['payment_type_name'],
                    $detail['student_name'],
                    $detail['class_name'],
                    $detail['target'],
                    $detail['paid'],
                    $detail['outstanding'],
                ], $this->detailRowStyles()));
            }
        }
    }

    private function titleStyle(): Style
    {
        return new Style(fontBold: true, fontColor: Color::WHITE, fontSize: 14, backgroundColor: '005E6A');
    }

    private function subtitleStyle(): Style
    {
        return new Style(fontBold: true, fontSize: 12, fontColor: '005E6A');
    }

    private function headerStyle(): Style
    {
        return new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '4472C4', cellAlignment: CellAlignment::CENTER);
    }

    /** @return list<Style> */
    private function reportRowStyles(): array
    {
        return [$this->textStyle(), $this->moneyStyle(), $this->moneyStyle(), $this->moneyStyle(), $this->percentageStyle()];
    }

    /** @return list<Style> */
    private function reportTotalStyles(): array
    {
        return [$this->totalTextStyle(), $this->totalMoneyStyle(), $this->totalMoneyStyle(), $this->totalMoneyStyle(), $this->totalPercentageStyle()];
    }

    /** @return list<Style> */
    private function detailRowStyles(): array
    {
        return [$this->textStyle(), $this->textStyle(), $this->textStyle(), $this->moneyStyle(), $this->moneyStyle(), $this->moneyStyle()];
    }

    private function textStyle(): Style
    {
        return new Style;
    }

    private function moneyStyle(): Style
    {
        return new Style(cellAlignment: CellAlignment::RIGHT, format: '#,##0');
    }

    private function percentageStyle(): Style
    {
        return new Style(cellAlignment: CellAlignment::RIGHT, format: '0.0%');
    }

    private function totalTextStyle(): Style
    {
        return new Style(fontBold: true, backgroundColor: 'E2F0D9');
    }

    private function totalMoneyStyle(): Style
    {
        return new Style(fontBold: true, backgroundColor: 'E2F0D9', cellAlignment: CellAlignment::RIGHT, format: '#,##0');
    }

    private function totalPercentageStyle(): Style
    {
        return new Style(fontBold: true, backgroundColor: 'E2F0D9', cellAlignment: CellAlignment::RIGHT, format: '0.0%');
    }
}
