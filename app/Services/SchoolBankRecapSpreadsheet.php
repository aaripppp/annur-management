<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class SchoolBankRecapSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-bank-recap-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Rekap Bank');
            $sheet->setColumnWidth(7, 1);
            $sheet->setColumnWidth(30, 2);
            $sheet->setColumnWidth(18, 3, 4);
            $writer->addRow(Row::fromValuesWithStyle(['ANNUR MANAGEMENT'], $this->titleStyle()));
            $writer->addRow(Row::fromValuesWithStyle(['REKAP BANK SEKOLAH'], $this->subtitleStyle()));
            $writer->addRow(Row::fromValues(['Periode', $report['period_label']]));

            if ($report['bank_filter_kind'] !== 'all') {
                $writer->addRow(Row::fromValues(['Bank / Channel', $report['bank_filter_label']]));
            }

            $writer->addRow(Row::fromValues([]));

            foreach ($report['sections'] as $section) {
                if ($section['transaction_count'] === 0) {
                    continue;
                }

                $writer->addRow(Row::fromValuesWithStyle([$section['bank_name'], $section['bank_label']], $this->sectionStyle()));
                $writer->addRow(Row::fromValuesWithStyle(['No.', 'Tanggal Pembayaran', 'Transaksi', 'Jumlah'], $this->headerStyle()));

                foreach ($section['rows'] as $index => $row) {
                    $writer->addRow(Row::fromValuesWithStyle([
                        $index + 1,
                        $row['date']->format('d/m/Y'),
                        $row['transaction_count'],
                        $row['total'],
                    ], $this->bodyStyle()));
                }

                $writer->addRow(Row::fromValuesWithStyle(['', 'TOTAL '.$section['bank_name'], '', $section['total']], $this->totalStyle()));
                $writer->addRow(Row::fromValues([]));
            }

            $writer->addRow(Row::fromValuesWithStyle(['', 'GRAND TOTAL', $report['transaction_count'], $report['grand_total']], $this->titleStyle()));

            $detailSheet = $writer->addNewSheetAndMakeItCurrent();
            $detailSheet->setName('Rincian Transaksi');
            $detailSheet->setColumnWidth(7, 1);
            $detailSheet->setColumnWidth(28, 2);
            $detailSheet->setColumnWidth(18, 3, 4);
            $detailSheet->setColumnWidth(22, 5, 7);
            $writer->addRow(Row::fromValuesWithStyle(['RINCIAN REKAP BANK SEKOLAH'], $this->titleStyle()));
            $writer->addRow(Row::fromValues(['Periode', $report['period_label']]));

            if ($report['bank_filter_kind'] !== 'all') {
                $writer->addRow(Row::fromValues(['Bank / Channel', $report['bank_filter_label']]));
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValuesWithStyle([
                'No.', 'Nama', 'Tanggal Pembayaran', 'Tanggal Dicatat', 'No. Kwitansi', 'Bank / Rekening', 'Nominal',
            ], $this->headerStyle()));

            foreach ($report['detail_rows'] as $index => $detail) {
                $writer->addRow(Row::fromValuesWithStyle([
                    $index + 1,
                    $detail['name'],
                    $detail['payment_date']->format('d/m/Y'),
                    $detail['recorded_at']->format('d/m/Y H:i'),
                    $detail['receipt_number'],
                    $detail['bank_label'],
                    $detail['amount'],
                ], $this->bodyStyle()));
            }

            $writer->addRow(Row::fromValuesWithStyle(['', '', '', '', '', 'TOTAL', $report['grand_total']], $this->totalStyle()));
            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();
            unlink($path);

            throw $exception;
        }

        return $path;
    }

    private function titleStyle(): Style
    {
        return new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '005E6A', format: '#,##0');
    }

    private function subtitleStyle(): Style
    {
        return new Style(fontBold: true, fontSize: 13, fontColor: '005E6A');
    }

    private function sectionStyle(): Style
    {
        return new Style(fontBold: true, backgroundColor: 'DDEBF7');
    }

    private function headerStyle(): Style
    {
        return new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '4472C4', cellAlignment: CellAlignment::CENTER);
    }

    private function bodyStyle(): Style
    {
        return new Style(format: '#,##0');
    }

    private function totalStyle(): Style
    {
        return new Style(fontBold: true, backgroundColor: 'E2F0D9', format: '#,##0');
    }
}
