<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class SchoolDailyReportSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-school-report-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($path);
            $this->writeSummarySheet($writer, $report);
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
    private function writeSummarySheet(Writer $writer, array $report): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Laporan Harian');
        $sheet->setColumnWidth(7, 1);
        $sheet->setColumnWidth(42, 2);
        $sheet->setColumnWidth(22, 3);

        $writer->addRow(Row::fromValuesWithStyle(['ANNUR MANAGEMENT'], $this->titleStyle()));
        $writer->addRow(Row::fromValuesWithStyle(['LAPORAN HARIAN SEKOLAH'], $this->subtitleStyle()));
        $writer->addRow(Row::fromValues([
            $report['period_title'],
            $report['period_label'],
        ]));
        $writer->addRow(Row::fromValues([
            'Unit',
            $report['unit_name'],
        ]));
        $writer->addRow(Row::fromValues([]));

        foreach ($report['channels'] as $channel) {
            $this->writeChannelSection($writer, $channel);
            $writer->addRow(Row::fromValues([]));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            ['', 'TOTAL PENERIMAAN', $report['grand_total']],
            $this->grandTotalStyle()
        ));
        $writer->addRow(Row::fromValues([
            '',
            $report['transaction_count'].' transaksi / '.$report['detail_count'].' rincian',
            '',
        ]));
    }

    /** @param array<string, mixed> $channel */
    private function writeChannelSection(Writer $writer, array $channel): void
    {
        $writer->addRow(Row::fromValuesWithStyle([$channel['label'], '', ''], $this->sectionStyle()));

        if ($channel['banks'] === []) {
            $writer->addRow(Row::fromValuesWithStyle(['', 'Tidak ada transaksi', 0], $this->tableBodyStyle()));
        }

        foreach ($channel['banks'] as $bank) {
            $writer->addRow(Row::fromValuesWithStyle(
                ['', $bank['bank_label'], $bank['total']],
                $this->bankStyle()
            ));
            $writer->addRow(Row::fromValuesWithStyle(['No.', 'Jenis Penerimaan', 'Nominal'], $this->tableHeaderStyle()));

            foreach ($bank['categories'] as $index => $category) {
                $writer->addRow(Row::fromValuesWithStyle(
                    [$index + 1, $category['name'], $category['total']],
                    $this->tableBodyStyle()
                ));
            }

            $writer->addRow(Row::fromValuesWithStyle(
                ['', 'Total '.$bank['bank_name'], $bank['total']],
                $this->totalStyle()
            ));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            ['', 'TOTAL '.$channel['label'], $channel['total']],
            $this->totalStyle()
        ));
    }

    /** @param array<string, mixed> $report */
    private function writeDetailSheet(Writer $writer, array $report): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Rincian Transaksi');
        $sheet->setColumnWidth(7, 1);
        $sheet->setColumnWidth(14, 2);
        $sheet->setColumnWidth(22, 3);
        $sheet->setColumnWidth(28, 4);
        $sheet->setColumnWidth(14, 5);
        $sheet->setColumnWidth(28, 6, 7);
        $sheet->setColumnWidth(20, 8);
        $sheet->setColumnWidth(34, 9);
        $sheet->setColumnWidth(18, 10);

        $writer->addRow(Row::fromValuesWithStyle(['RINCIAN TRANSAKSI SEKOLAH'], $this->titleStyle()));
        $writer->addRow(Row::fromValues([
            $report['period_title'],
            $report['period_label'],
        ]));
        $writer->addRow(Row::fromValues([
            'Unit',
            $report['unit_name'],
        ]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle([
            'No.',
            'Tanggal',
            'No. Kwitansi',
            'Siswa',
            'Kelas',
            'Detail',
            'Kategori',
            'Kanal',
            'Bank / Rekening',
            'Nominal',
        ], $this->tableHeaderStyle()));

        foreach ($report['detail_rows'] as $index => $detail) {
            $writer->addRow(Row::fromValuesWithStyle([
                $index + 1,
                $detail['recorded_at']->format('d/m/Y'),
                $detail['receipt_number'],
                $detail['student_name'],
                $detail['class_name'],
                $detail['detail_label'],
                $detail['category_name'],
                $detail['channel_label'],
                $detail['bank_label'],
                $detail['amount'],
            ], $this->tableBodyStyle()));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            ['', '', '', '', '', '', '', '', 'TOTAL', $report['grand_total']],
            $this->grandTotalStyle()
        ));
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
            fontColor: Color::WHITE,
            backgroundColor: '0060A9',
            border: $this->border()
        );
    }

    private function bankStyle(): Style
    {
        return new Style(
            fontBold: true,
            backgroundColor: 'DDEBF7',
            border: $this->border(),
            format: '#,##0'
        );
    }

    private function tableHeaderStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontColor: Color::WHITE,
            cellAlignment: CellAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border(),
            backgroundColor: '4472C4'
        );
    }

    private function tableBodyStyle(): Style
    {
        return new Style(shouldWrapText: true, border: $this->border(), format: '#,##0');
    }

    private function totalStyle(): Style
    {
        return new Style(
            fontBold: true,
            border: $this->border(),
            backgroundColor: 'E2F0D9',
            format: '#,##0'
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
            format: '#,##0'
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
