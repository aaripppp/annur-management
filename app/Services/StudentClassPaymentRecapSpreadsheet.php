<?php

namespace App\Services;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class StudentClassPaymentRecapSpreadsheet
{
    /** @param iterable<array<string, mixed>> $reports */
    public function create(iterable $reports): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-class-recap-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file laporan sementara.');
        }

        $options = new Options;
        $writer = new Writer($options);
        $usedSheetNames = [];
        $reportCount = 0;

        try {
            $writer->openToFile($path);

            foreach ($reports as $report) {
                $sheet = $reportCount === 0
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($this->uniqueSheetName(
                    (string) $report['school_class'],
                    (int) $report['school_class_id'],
                    $usedSheetNames,
                ));
                $this->writeClassSheet($writer, $options, $sheet, $report);
                $reportCount++;
                unset($report);
            }

            if ($reportCount === 0) {
                throw new RuntimeException('Tidak ada kelas yang dapat diekspor.');
            }

            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();

            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }

        return $path;
    }

    public function filenameSegment(string $value): string
    {
        $sanitized = preg_replace('~[\x00-\x1F\x7F\\/:*?"<>|]+~u', '_', trim($value));
        $sanitized = preg_replace('/\s+/u', '_', $sanitized ?? '');
        $sanitized = trim($sanitized ?? '', '._');

        return $sanitized !== '' ? $sanitized : 'Laporan';
    }

    /**
     * @param  array<string, bool>  $usedSheetNames
     */
    private function uniqueSheetName(string $className, int $classId, array &$usedSheetNames): string
    {
        $baseName = preg_replace('~[\\/?*:\[\]]+~u', '-', trim($className));
        $baseName = trim(trim($baseName ?? ''), "'");
        $baseName = $baseName !== '' ? $baseName : "Kelas-{$classId}";
        $baseName = Str::substr($baseName, 0, 31);
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

    /** @param array<string, mixed> $report */
    private function writeClassSheet(Writer $writer, Options $options, Sheet $sheet, array $report): void
    {
        $mainHeader = $this->mainHeaderRows($report);
        $jemputanHeader = $this->jemputanHeaderRows($report);
        $columnCount = max(count($mainHeader['group']), count($jemputanHeader['group']), 2);

        $sheet->setColumnWidth(7, 1);
        $sheet->setColumnWidth(34, 2);

        if ($columnCount > 2) {
            $sheet->setColumnWidthForRange(16, 3, $columnCount);
        }

        $writer->addRow($this->stretchedRow('ANNUR MANAGEMENT', $columnCount, $this->titleStyle()));
        $writer->addRow($this->stretchedRow('REKAP PEMBAYARAN PER KELAS', $columnCount, $this->subtitleStyle()));
        $writer->addRow(Row::fromValues(['Tahun Ajaran', $report['academic_year']]));
        $writer->addRow(Row::fromValues(['Jenjang', $report['school_level']]));
        $writer->addRow(Row::fromValues(['Kelas', $report['school_class']]));
        $writer->addRow(Row::fromValues(['Jumlah Siswa', $report['student_count']]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow($this->stretchedRow('REKAP PEMBAYARAN KELAS', $columnCount, $this->sectionStyle()));
        $this->writeGroupedHeader($writer, $options, $sheet, $mainHeader, 9);

        foreach ($report['rows'] as $index => $row) {
            $writer->addRow(Row::fromValuesWithStyle(
                $this->mainRow($report, $row, $index + 1),
                $this->tableBodyStyle(),
            ));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            $this->mainTotalsRow($report),
            $this->totalStyle(),
        ));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow($this->stretchedRow('REKAP JEMPUTAN', $columnCount, $this->sectionStyle()));
        $this->writeGroupedHeader(
            $writer,
            $options,
            $sheet,
            $jemputanHeader,
            14 + count($report['rows']),
        );

        foreach ($report['segregated']['rows'] as $index => $row) {
            $writer->addRow(Row::fromValuesWithStyle(
                $this->jemputanRow($report, $row, $index + 1),
                $this->tableBodyStyle(),
            ));
        }

        $writer->addRow(Row::fromValuesWithStyle(
            $this->jemputanTotalsRow($report),
            $this->totalStyle(),
        ));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{
     *     group: list<string>,
     *     detail: list<string>,
     *     horizontal_merges: list<array{start: int<0, max>, end: int<0, max>}>,
     *     vertical_columns: list<int<0, max>>
     * }
     */
    private function mainHeaderRows(array $report): array
    {
        $group = ['NO.', 'NAMA SISWA'];
        $detail = ['', ''];
        $horizontalMerges = [];
        $verticalColumns = [0, 1];
        $monthlyTypes = $report['payment_types']['monthly'];

        if ($monthlyTypes !== []) {
            $monthlyTypeNames = [(string) $monthlyTypes[0]['name']];

            foreach (array_slice($monthlyTypes, 1) as $type) {
                $monthlyTypeNames[] = (string) $type['name'];
            }

            $this->appendHeaderGroup(
                $group,
                $detail,
                $horizontalMerges,
                'RINGKASAN TAGIHAN',
                $monthlyTypeNames,
            );

            $verticalColumns[] = count($group);
            $group[] = 'TOTAL';
            $detail[] = '';

            foreach ($report['months'] as $month) {
                $this->appendHeaderGroup(
                    $group,
                    $detail,
                    $horizontalMerges,
                    Str::upper($month['label'].' '.$month['year']),
                    $monthlyTypeNames,
                );
            }
        }

        foreach (['yearly', 'one_time'] as $frequency) {
            foreach ($report['payment_types'][$frequency] as $type) {
                $this->appendHeaderGroup(
                    $group,
                    $detail,
                    $horizontalMerges,
                    Str::upper($type['name']),
                    ['Tagihan', 'Terbayar', 'Sisa'],
                );
            }
        }

        return [
            'group' => $group,
            'detail' => $detail,
            'horizontal_merges' => $horizontalMerges,
            'vertical_columns' => $verticalColumns,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $row
     * @return list<int|float|string>
     */
    private function mainRow(array $report, array $row, int $number): array
    {
        $values = [$number, $row['student_name']];

        foreach ($report['payment_types']['monthly'] as $type) {
            $values[] = $row['monthly_summary'][$type['id']];
        }

        if ($report['payment_types']['monthly'] !== []) {
            $values[] = $row['monthly_summary']['total'];

            foreach ($report['months'] as $month) {
                foreach ($report['payment_types']['monthly'] as $type) {
                    $values[] = $row['monthly_paid'][$month['key']][$type['id']];
                }
            }
        }

        foreach (['yearly', 'one_time'] as $frequency) {
            foreach ($report['payment_types'][$frequency] as $type) {
                foreach (['target', 'paid', 'remaining'] as $metric) {
                    $values[] = $row[$frequency][$type['id']][$metric];
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<float|string>
     */
    private function mainTotalsRow(array $report): array
    {
        $values = ['', 'JUMLAH'];

        foreach ($report['payment_types']['monthly'] as $type) {
            $values[] = $report['totals']['monthly_summary'][$type['id']];
        }

        if ($report['payment_types']['monthly'] !== []) {
            $values[] = $report['totals']['monthly_summary']['total'];

            foreach ($report['months'] as $month) {
                foreach ($report['payment_types']['monthly'] as $type) {
                    $values[] = $report['totals']['monthly_paid'][$month['key']][$type['id']];
                }
            }
        }

        foreach (['yearly', 'one_time'] as $frequency) {
            foreach ($report['payment_types'][$frequency] as $type) {
                foreach (['target', 'paid', 'remaining'] as $metric) {
                    $values[] = $report['totals'][$frequency][$type['id']][$metric];
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{
     *     group: list<string>,
     *     detail: list<string>,
     *     horizontal_merges: list<array{start: int<0, max>, end: int<0, max>}>,
     *     vertical_columns: list<int<0, max>>
     * }
     */
    private function jemputanHeaderRows(array $report): array
    {
        $group = ['NO.', 'NAMA SISWA'];
        $detail = ['', ''];
        $horizontalMerges = [];
        $verticalColumns = [0, 1];

        $this->appendHeaderGroup(
            $group,
            $detail,
            $horizontalMerges,
            'RINGKASAN TAGIHAN',
            ['Jemputan', 'Adm Jemputan', 'Total'],
        );

        foreach ($report['months'] as $month) {
            $this->appendHeaderGroup(
                $group,
                $detail,
                $horizontalMerges,
                Str::upper($month['label'].' '.$month['year']),
                ['Jemputan'],
            );
        }

        $this->appendHeaderGroup(
            $group,
            $detail,
            $horizontalMerges,
            'ADM JEMPUTAN',
            ['Terbayar', 'Status'],
        );

        return [
            'group' => $group,
            'detail' => $detail,
            'horizontal_merges' => $horizontalMerges,
            'vertical_columns' => $verticalColumns,
        ];
    }

    /**
     * @param  list<string>  $group
     * @param  list<string>  $detail
     * @param  list<array{start: int<0, max>, end: int<0, max>}>  $horizontalMerges
     * @param  non-empty-list<string>  $detailLabels
     */
    private function appendHeaderGroup(
        array &$group,
        array &$detail,
        array &$horizontalMerges,
        string $groupLabel,
        array $detailLabels,
    ): void {
        $startColumn = count($group);
        $group[] = $groupLabel;

        for ($index = 1; $index < count($detailLabels); $index++) {
            $group[] = '';
        }

        array_push($detail, ...$detailLabels);
        $endColumn = count($group) - 1;

        if ($endColumn > $startColumn) {
            $horizontalMerges[] = ['start' => $startColumn, 'end' => $endColumn];
        }
    }

    /**
     * @param  array{
     *     group: list<string>,
     *     detail: list<string>,
     *     horizontal_merges: list<array{start: int<0, max>, end: int<0, max>}>,
     *     vertical_columns: list<int<0, max>>
     * }  $header
     * @param  positive-int  $topRow
     */
    private function writeGroupedHeader(
        Writer $writer,
        Options $options,
        Sheet $sheet,
        array $header,
        int $topRow,
    ): void {
        $writer->addRow(Row::fromValuesWithStyle($header['group'], $this->tableHeaderStyle()));
        $writer->addRow(Row::fromValuesWithStyle($header['detail'], $this->tableHeaderStyle()));

        foreach ($header['horizontal_merges'] as $merge) {
            $options->mergeCells(
                $merge['start'],
                $topRow,
                $merge['end'],
                $topRow,
                $sheet->getIndex(),
            );
        }

        foreach ($header['vertical_columns'] as $column) {
            $options->mergeCells($column, $topRow, $column, $topRow + 1, $sheet->getIndex());
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $row
     * @return list<int|float|string>
     */
    private function jemputanRow(array $report, array $row, int $number): array
    {
        $values = [
            $number,
            $row['student_name'],
            $row['jemputan']['target'] ?? 0.0,
            $row['adm_jemputan']['target'] ?? 0.0,
            $row['total_tagihan'],
        ];

        foreach ($report['months'] as $month) {
            $values[] = $row['months'][$month['key']]['paid'] ?? 0.0;
        }

        $values[] = $row['adm_jemputan']['paid'] ?? 0.0;
        $values[] = match ($row['adm_jemputan']['status'] ?? null) {
            'paid' => 'Lunas',
            'unpaid' => 'Belum Lunas',
            default => '',
        };

        return $values;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<float|string>
     */
    private function jemputanTotalsRow(array $report): array
    {
        $segregated = $report['segregated'];
        $values = [
            '',
            'JUMLAH',
            $segregated['jemputan_totals']['target'],
            $segregated['adm_jemputan_totals']['target'],
            $segregated['total_tagihan'],
        ];

        foreach ($report['months'] as $month) {
            $values[] = $segregated['month_totals'][$month['key']]['paid'];
        }

        $values[] = $segregated['adm_jemputan_totals']['paid'];
        $values[] = '';

        return $values;
    }

    private function stretchedRow(string $value, int $count, Style $style): Row
    {
        return Row::fromValuesWithStyle(array_pad([$value], $count, ''), $style);
    }

    private function titleStyle(): Style
    {
        return new Style(fontBold: true, fontSize: 16, fontColor: Color::WHITE, backgroundColor: '005E6A');
    }

    private function subtitleStyle(): Style
    {
        return new Style(fontBold: true, fontSize: 13, fontColor: '005E6A');
    }

    private function sectionStyle(): Style
    {
        return new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '005E6A');
    }

    private function tableHeaderStyle(): Style
    {
        return new Style(
            fontBold: true,
            fontColor: Color::WHITE,
            fontSize: 9,
            cellAlignment: CellAlignment::CENTER,
            shouldWrapText: true,
            border: $this->border(),
            backgroundColor: '4472C4',
        );
    }

    private function tableBodyStyle(): Style
    {
        return new Style(shouldWrapText: true, border: $this->border(), format: '#,##0;;');
    }

    private function totalStyle(): Style
    {
        return new Style(fontBold: true, border: $this->border(), backgroundColor: 'DDEBF7', format: '#,##0;;');
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
