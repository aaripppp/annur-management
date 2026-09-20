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

class DaycareDailyReportSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-daycare-report-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($path);
            $this->writeDailySheet($writer, $report);
            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();
            unlink($path);

            throw $exception;
        }

        return $path;
    }

    /** @param array<string, mixed> $report */
    private function writeDailySheet(Writer $writer, array $report): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Laporan Harian');
        $sheet->setColumnWidth(7, 1);
        $sheet->setColumnWidth(42, 2);
        $sheet->setColumnWidth(22, 3);

        $writer->addRow(Row::fromValuesWithStyle(['ANNUR MANAGEMENT'], $this->titleStyle()));
        $writer->addRow(Row::fromValuesWithStyle(['LAPORAN HARIAN DAYCARE'], $this->subtitleStyle()));
        $writer->addRow(Row::fromValues([
            $report['period_title'],
            $report['period_label'],
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
            $report['transaction_count'].' transaksi',
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
            $writer->addRow(Row::fromValuesWithStyle(
                ['Jenis Penerimaan', 'Rincian', 'Jumlah'],
                $this->tableHeaderStyle()
            ));

            foreach ($bank['categories'] as $category) {
                $writer->addRow(Row::fromValuesWithStyle(
                    [$category['name'], $category['count'], $category['total']],
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
