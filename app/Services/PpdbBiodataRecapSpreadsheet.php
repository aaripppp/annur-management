<?php

namespace App\Services;

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PpdbParent;
use App\Models\ProspectiveStudent;
use DateTimeInterface;
use Illuminate\Support\Collection;
use LogicException;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class PpdbBiodataRecapSpreadsheet
{
    private const DETAIL_HEADER_ROW = 7;

    private const DETAIL_COLUMN_COUNT = 78;

    public function __construct(private readonly PpdbBiodataRecapQuery $query) {}

    /** @return array{path: string, filename: string} */
    public function create(int $academicYearId, SchoolLevel $schoolLevel): array
    {
        $path = tempnam(sys_get_temp_dir(), 'annur-ppdb-biodata-recap-');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file rekap biodata PPDB sementara.');
        }

        $options = new Options;
        $writer = new Writer($options);
        $styles = $this->styles();
        $opened = false;
        $detailSheets = [];
        $writtenCounts = array_fill_keys(array_keys(PpdbBiodataRecapQuery::GROUPS), 0);
        $rowNumbers = array_fill_keys(array_keys(PpdbBiodataRecapQuery::GROUPS), 0);
        $filename = '';
        $academicYearLabel = '';

        try {
            $writer->openToFile($path);
            $opened = true;
            $recapSheet = $writer->getCurrentSheet();

            $snapshot = $this->query->processSnapshot(
                $academicYearId,
                $schoolLevel,
                function (string $group, Collection $chunk) use ($writer, &$detailSheets, &$writtenCounts, &$rowNumbers, &$academicYearLabel, $schoolLevel, $styles): void {
                    $writer->setCurrentSheet($detailSheets[$group]);

                    foreach ($chunk as $applicant) {
                        $rowNumbers[$group]++;
                        $writer->addRow($this->detailRow($applicant, $academicYearLabel, $schoolLevel, $rowNumbers[$group], $styles['body']));
                        $writtenCounts[$group]++;
                    }
                },
                function (AcademicYear $academicYear, array $counts) use ($writer, $options, $recapSheet, $schoolLevel, $styles, &$detailSheets, &$filename, &$academicYearLabel): void {
                    $filename = $this->filename($academicYear, $schoolLevel);
                    $academicYearLabel = $academicYear->year;
                    $this->writeRecapSheet($writer, $options, $recapSheet, $academicYear, $schoolLevel, $counts, $styles);

                    foreach (PpdbBiodataRecapQuery::GROUPS as $group => $definition) {
                        $sheet = $writer->addNewSheetAndMakeItCurrent();
                        $sheet->setName($definition['sheet']);
                        $this->writeDetailHeader(
                            $writer,
                            $options,
                            $sheet,
                            $academicYear,
                            $schoolLevel,
                            $definition['sheet'],
                            $counts[$group],
                            $styles,
                        );
                        $detailSheets[$group] = $sheet;
                    }
                },
            );

            foreach (array_keys(PpdbBiodataRecapQuery::GROUPS) as $group) {
                if ($writtenCounts[$group] !== $snapshot['aggregate_counts'][$group]
                    || $writtenCounts[$group] !== $snapshot['retrieved_counts'][$group]) {
                    throw new LogicException("Jumlah baris detail grup {$group} tidak sesuai dengan rekap.");
                }
            }

            if (array_sum($writtenCounts) !== $snapshot['aggregate_counts']['grand_total']) {
                throw new LogicException('Jumlah seluruh baris detail tidak sesuai dengan grand total rekap.');
            }

            $writer->close();
            $opened = false;
        } catch (Throwable $exception) {
            if ($opened) {
                try {
                    $writer->close();
                } catch (Throwable) {
                }
            }

            if (is_file($path)) {
                @unlink($path);
            }

            throw new RuntimeException('Gagal membuat rekap biodata PPDB.', previous: $exception);
        }

        return ['path' => $path, 'filename' => $filename];
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, Style>  $styles
     */
    private function writeRecapSheet(
        Writer $writer,
        Options $options,
        Sheet $sheet,
        AcademicYear $academicYear,
        SchoolLevel $schoolLevel,
        array $counts,
        array $styles,
    ): void {
        $sheet->setName(PpdbBiodataRecapQuery::SHEET_NAMES[0]);
        $sheet->setColumnWidth(34, 1);
        $sheet->setColumnWidth(16, 2);
        $options->mergeCells(0, 1, 1, 1, $sheet->getIndex());
        $options->mergeCells(0, 2, 1, 2, $sheet->getIndex());

        $writer->setCurrentSheet($sheet);
        $writer->addRow($this->stringRow(['ANNUR MANAGEMENT'], $styles['title']));
        $writer->addRow($this->stringRow(['REKAP BIODATA CALON SISWA PPDB'], $styles['subtitle']));
        $writer->addRow($this->stringRow(['Tahun Ajaran', $academicYear->year], $styles['metadata']));
        $writer->addRow($this->stringRow(['Jenjang', $schoolLevel->value], $styles['metadata']));
        $writer->addRow($this->stringRow([]));
        $writer->addRow($this->stringRow(['Kelompok', 'Jumlah'], $styles['header']));

        foreach (PpdbBiodataRecapQuery::GROUPS as $group => $definition) {
            $writer->addRow(new Row([
                new StringCell($this->recapLabel($definition['sheet']), $styles['body']),
                new NumericCell($counts[$group], $styles['count']),
            ]));
        }

        $writer->addRow(new Row([
            new StringCell('GRAND TOTAL', $styles['total']),
            new NumericCell($counts['grand_total'], $styles['total']),
        ]));
    }

    /** @param array<string, Style> $styles */
    private function writeDetailHeader(
        Writer $writer,
        Options $options,
        Sheet $sheet,
        AcademicYear $academicYear,
        SchoolLevel $schoolLevel,
        string $groupName,
        int $total,
        array $styles,
    ): void {
        $this->setDetailColumnWidths($sheet);
        $options->mergeCells(0, 1, self::DETAIL_COLUMN_COUNT - 1, 1, $sheet->getIndex());
        $options->mergeCells(0, 2, self::DETAIL_COLUMN_COUNT - 1, 2, $sheet->getIndex());
        $sheet->setSheetView((new SheetView)->withFreezeRow(self::DETAIL_HEADER_ROW + 1));
        $sheet->setAutoFilter(new AutoFilter(
            0,
            self::DETAIL_HEADER_ROW,
            self::DETAIL_COLUMN_COUNT - 1,
            self::DETAIL_HEADER_ROW + $total,
        ));

        $writer->addRow($this->stringRow(['ANNUR MANAGEMENT'], $styles['title']));
        $writer->addRow($this->stringRow(['REKAP BIODATA CALON SISWA PPDB'], $styles['subtitle']));
        $writer->addRow($this->stringRow(['Tahun Ajaran', $academicYear->year], $styles['metadata']));
        $writer->addRow($this->stringRow(['Jenjang', $schoolLevel->value], $styles['metadata']));
        $writer->addRow($this->stringRow(['Kelompok', $groupName], $styles['metadata']));
        $writer->addRow(new Row([
            new StringCell('Total Applicant', $styles['metadata']),
            new NumericCell($total, $styles['count']),
        ]));
        $writer->addRow($this->stringRow(PpdbBiodataRecapQuery::detailHeaders(), $styles['header']));
    }

    private function setDetailColumnWidths(Sheet $sheet): void
    {
        $sheet->setColumnWidthForRange(18, 1, self::DETAIL_COLUMN_COUNT);
        $sheet->setColumnWidth(7, 1);

        foreach (PpdbBiodataRecapQuery::DETAIL_COLUMNS as $index => $column) {
            $width = match (true) {
                str_contains($column['key'], 'name'),
                str_contains($column['key'], 'address') => 28,
                str_contains($column['key'], 'notes'),
                str_contains($column['key'], 'history'),
                str_starts_with($column['key'], 'sibling_') => 34,
                str_contains($column['key'], 'date'),
                $column['key'] === 'registered_at' => 14,
                in_array($column['key'], [
                    'registration_number', 'student_nik', 'nisn', 'family_card_number',
                    'birth_certificate_number', 'postal_code', 'father_nik', 'mother_nik',
                    'guardian_nik', 'father_phone', 'mother_phone', 'guardian_phone',
                ], true) => 20,
                default => 18,
            };

            $sheet->setColumnWidth($width, $index + 1);
        }
    }

    private function detailRow(
        ProspectiveStudent $applicant,
        string $academicYear,
        SchoolLevel $schoolLevel,
        int $number,
        Style $style,
    ): Row {
        $profile = $applicant->ppdbProfile;
        $parents = $applicant->ppdbParents->keyBy('role');
        $father = $parents->get('father');
        $mother = $parents->get('mother');
        $guardian = $parents->get('guardian');
        $siblings = $this->query->siblingTextColumns($applicant);
        $values = [
            'row_number' => $number,
            'registration_number' => $applicant->registration_number,
            'registered_at' => $this->date($applicant->created_at),
            'academic_year' => $academicYear,
            'school_level' => $schoolLevel->value,
            'school_class' => $applicant->schoolClass?->name,
            'registration_status' => $this->statusLabel((string) $applicant->getRawOriginal('status')),
            'applicant_category' => $this->categoryLabel($this->query->applicantCategoryForExport($applicant)),
            'full_name' => $applicant->nama_lengkap,
            'nickname' => $applicant->nama_panggilan,
            'gender' => $this->genderLabel($this->query->genderForExport($applicant)),
            'birthplace' => $profile?->tempat_lahir,
            'birthdate' => $this->date($profile?->tanggal_lahir),
            'previous_school' => $profile?->asal_sekolah,
            'student_nik' => $profile?->nik_siswa,
            'nisn' => $this->query->nisnForExport($applicant, $schoolLevel),
            'family_card_number' => $profile?->nomor_kk,
            'birth_certificate_number' => $profile?->nomor_akta_kelahiran,
            'child_order' => $profile?->anak_ke,
            'sibling_count' => $profile?->jumlah_saudara,
            'hobby' => $profile?->hobi,
            'aspiration' => $profile?->cita_cita,
            'child_status' => $profile?->status_anak,
            'religion' => $profile?->agama,
            'daily_language' => $profile?->bahasa_sehari_hari,
            'address' => $applicant->alamat,
            'rt' => $profile?->rt,
            'rw' => $profile?->rw,
            'village' => $profile?->kelurahan,
            'district' => $profile?->kecamatan,
            'city' => $profile?->kabupaten_kota,
            'province' => $profile?->provinsi,
            'postal_code' => $profile?->kode_pos,
            'distance_category' => $this->distanceLabel($profile?->jarak_tempuh_kategori),
            'distance_detail' => $profile?->jarak_tempuh_detail,
            'transportation' => $this->transportationLabel($profile?->moda_transportasi),
            'other_transportation' => $profile?->moda_transportasi_lainnya,
            'height' => $profile?->tinggi_badan,
            'weight' => $profile?->berat_badan,
            'head_circumference' => $profile?->lingkar_kepala,
            'blood_type' => $profile?->golongan_darah,
            'medical_history' => $profile?->riwayat_penyakit,
            'has_special_needs' => $this->booleanLabel($profile?->berkebutuhan_khusus),
            'special_needs_notes' => $profile?->keterangan_kebutuhan_khusus,
            ...$this->parentValues('father', $father, $applicant),
            ...$this->parentValues('mother', $mother, $applicant),
            ...$this->parentValues('guardian', $guardian, $applicant),
            'detailed_sibling_count' => $siblings['count'],
            'sibling_names' => $siblings['names'],
            'sibling_birthdates' => $siblings['birthdates'],
            'sibling_genders' => $siblings['genders'],
            'sibling_education_occupations' => $siblings['education_occupations'],
        ];

        $cells = [];
        foreach (PpdbBiodataRecapQuery::DETAIL_COLUMNS as $column) {
            $value = $values[$column['key']] ?? '';
            $cells[] = $column['key'] === 'row_number'
                ? new NumericCell((int) $value, $style)
                : new StringCell($this->string($value), $style);
        }

        if (count($cells) !== self::DETAIL_COLUMN_COUNT) {
            throw new LogicException('Jumlah kolom biodata PPDB tidak sesuai kontrak.');
        }

        return new Row($cells);
    }

    /** @return array<string, mixed> */
    private function parentValues(string $prefix, ?PpdbParent $parent, ProspectiveStudent $applicant): array
    {
        $values = [];

        if ($prefix !== 'guardian') {
            $values[$prefix.'_living_status'] = $this->livingStatusLabel($parent?->status_hidup);
        }

        $values[$prefix.'_name'] = $parent?->nama;
        $values[$prefix.'_nik'] = $parent?->nik;
        $values[$prefix.'_birthplace'] = $parent?->tempat_lahir;
        $values[$prefix.'_birthdate'] = $this->date($parent?->tanggal_lahir);
        $values[$prefix.'_education'] = $parent?->pendidikan_terakhir;
        $values[$prefix.'_email'] = $parent?->email;
        $values[$prefix.'_phone'] = $parent?->no_telp;
        $values[$prefix.'_occupation'] = $parent?->occupation;
        $values[$prefix.'_income'] = $this->incomeLabel($parent);

        if ($prefix === 'father') {
            $values['father_name'] = $values['father_name'] ?: $applicant->nama_orang_tua;
            $values['father_phone'] = $values['father_phone'] ?: $applicant->no_telp_orang_tua;
        }

        return $values;
    }

    private function filename(AcademicYear $academicYear, SchoolLevel $schoolLevel): string
    {
        $year = preg_replace('~[^0-9A-Za-z_-]+~', '-', str_replace('/', '-', $academicYear->year));

        return 'Rekap_Biodata_PPDB_'.trim((string) $year, '-_').'_'.$schoolLevel->value.'.xlsx';
    }

    private function recapLabel(string $sheetName): string
    {
        return mb_convert_case($sheetName, MB_CASE_TITLE, 'UTF-8');
    }

    private function categoryLabel(string $value): string
    {
        return PpdbApplicantCategory::tryFrom($value)?->label() ?? $value;
    }

    private function statusLabel(string $value): string
    {
        return ProspectiveStudentStatus::tryFrom($value)?->label() ?? $value;
    }

    private function genderLabel(string $value): string
    {
        return match ($value) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => $value,
        };
    }

    private function livingStatusLabel(mixed $value): string
    {
        return match ($value) {
            'hidup' => 'Hidup',
            'wafat' => 'Wafat',
            default => $this->string($value),
        };
    }

    private function incomeLabel(?PpdbParent $parent): string
    {
        $raw = (string) ($parent?->getRawOriginal('income_range') ?? '');

        return ParentIncomeRange::tryFrom($raw)?->label() ?? $raw;
    }

    private function distanceLabel(mixed $value): string
    {
        return match ($value) {
            'less_than_1_km' => 'Kurang dari 1 km',
            'more_than_1_km' => 'Lebih dari 1 km',
            default => $this->string($value),
        };
    }

    private function transportationLabel(mixed $value): string
    {
        return match ($value) {
            'jalan_kaki' => 'Jalan kaki',
            'sepeda' => 'Sepeda',
            'motor' => 'Motor',
            'mobil_pribadi' => 'Mobil pribadi',
            'angkutan_umum' => 'Angkutan umum',
            'antar_jemput' => 'Antar-jemput',
            'lainnya' => 'Lainnya',
            default => $this->string($value),
        };
    }

    private function booleanLabel(?bool $value): string
    {
        return match ($value) {
            true => 'Ya',
            false => 'Tidak',
            null => '',
        };
    }

    private function date(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $this->string($value);
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function stringRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            fn (mixed $value): StringCell => new StringCell($this->string($value), $style),
            array_values($values),
        ));
    }

    /** @return array<string, Style> */
    private function styles(): array
    {
        $border = new Border(
            new BorderPart(BorderName::TOP, 'B7C9D6', BorderWidth::THIN),
            new BorderPart(BorderName::RIGHT, 'B7C9D6', BorderWidth::THIN),
            new BorderPart(BorderName::BOTTOM, 'B7C9D6', BorderWidth::THIN),
            new BorderPart(BorderName::LEFT, 'B7C9D6', BorderWidth::THIN),
        );

        return [
            'title' => new Style(fontBold: true, fontSize: 16, fontColor: Color::WHITE, backgroundColor: '005E6A'),
            'subtitle' => new Style(fontBold: true, fontSize: 13, fontColor: '005E6A'),
            'metadata' => new Style(fontBold: true),
            'header' => new Style(
                fontBold: true,
                fontColor: Color::WHITE,
                fontSize: 9,
                cellAlignment: CellAlignment::CENTER,
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
                shouldWrapText: true,
                border: $border,
                backgroundColor: '4472C4',
            ),
            'body' => new Style(
                cellVerticalAlignment: CellVerticalAlignment::TOP,
                shouldWrapText: true,
                border: $border,
            ),
            'count' => new Style(cellAlignment: CellAlignment::RIGHT, border: $border, format: '#,##0'),
            'total' => new Style(fontBold: true, border: $border, backgroundColor: 'DDEBF7', format: '#,##0'),
        ];
    }
}
