<?php

namespace App\Http\Controllers;

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\SchoolLevel;
use App\Models\PpdbParent;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;

class PpdbBiodataPdfController extends Controller
{
    public function __invoke(ProspectiveStudent $prospectiveStudent): Response
    {
        Gate::authorize('viewBiodata', $prospectiveStudent);

        $prospectiveStudent->load([
            'academicYear',
            'schoolClass',
            'ppdbProfile',
            'ppdbParents',
            'ppdbSiblings',
        ]);

        $profile = $prospectiveStudent->ppdbProfile;
        $parents = $prospectiveStudent->ppdbParents->keyBy('role');
        $guardian = $parents->get('guardian');
        $distanceCategory = $profile?->jarak_tempuh_kategori;
        $transportation = $profile?->moda_transportasi;

        $data = [
            'registration' => [
                'number' => $this->display($prospectiveStudent->registration_number),
                'academic_year' => $this->display($prospectiveStudent->academicYear?->year),
                'target_level' => $this->display($prospectiveStudent->schoolClass?->schoolLevel?->value),
                'target_class' => $this->display($prospectiveStudent->schoolClass?->name),
                'category' => $this->applicantCategoryLabel($prospectiveStudent),
            ],
            'identity' => [
                'nama_lengkap' => $this->display($prospectiveStudent->nama_lengkap),
                'nama_panggilan' => $this->display($prospectiveStudent->nama_panggilan),
                'jenis_kelamin' => $this->genderLabel($prospectiveStudent->jenis_kelamin),
                'nik_siswa' => $this->display($profile?->nik_siswa),
                'nisn' => $this->display($profile?->nisn),
                'nomor_kk' => $this->display($profile?->nomor_kk),
                'nomor_akta_kelahiran' => $this->display($profile?->nomor_akta_kelahiran),
                'tempat_lahir' => $this->display($profile?->tempat_lahir),
                'tanggal_lahir' => $this->formatDate($profile?->tanggal_lahir),
                'asal_sekolah' => $this->display($profile?->asal_sekolah),
                'anak_ke' => $this->formatDecimal($profile?->anak_ke),
                'jumlah_saudara' => $this->formatDecimal($profile?->jumlah_saudara),
                'hobi' => $this->display($profile?->hobi),
                'cita_cita' => $this->display($profile?->cita_cita),
                'status_anak' => $this->display($profile?->status_anak),
                'agama' => $this->display($profile?->agama),
                'bahasa_sehari_hari' => $this->display($profile?->bahasa_sehari_hari),
            ],
            'address' => [
                'alamat' => $this->display($prospectiveStudent->alamat),
                'rt' => $this->display($profile?->rt),
                'rw' => $this->display($profile?->rw),
                'kelurahan' => $this->display($profile?->kelurahan),
                'kecamatan' => $this->display($profile?->kecamatan),
                'kabupaten_kota' => $this->display($profile?->kabupaten_kota),
                'provinsi' => $this->display($profile?->provinsi),
                'kode_pos' => $this->display($profile?->kode_pos),
                'jarak_tempuh' => $this->distanceLabel($distanceCategory),
                'show_jarak_tempuh_detail' => $distanceCategory === 'more_than_1_km',
                'jarak_tempuh_detail' => $this->formatDistance($profile?->jarak_tempuh_detail),
                'moda_transportasi' => $this->transportationLabel($transportation),
                'show_moda_transportasi_lainnya' => $transportation === 'lainnya',
                'moda_transportasi_lainnya' => $this->display($profile?->moda_transportasi_lainnya),
            ],
            'health' => [
                'tinggi_badan' => $this->formatDecimal($profile?->tinggi_badan),
                'berat_badan' => $this->formatDecimal($profile?->berat_badan),
                'lingkar_kepala' => $this->formatDecimal($profile?->lingkar_kepala),
                'golongan_darah' => $this->display($profile?->golongan_darah),
                'riwayat_penyakit' => $this->display($profile?->riwayat_penyakit),
                'berkebutuhan_khusus' => $this->specialNeedsLabel($profile?->berkebutuhan_khusus),
                'keterangan_kebutuhan_khusus' => $this->display($profile?->keterangan_kebutuhan_khusus),
            ],
            'father' => $this->parentData($parents->get('father'), true),
            'mother' => $this->parentData($parents->get('mother'), true),
            'guardian' => $this->hasParentData($guardian) ? $this->parentData($guardian) : null,
            'siblings' => $prospectiveStudent->ppdbSiblings
                ->map(fn (PpdbSibling $sibling): array => [
                    'nama_lengkap' => $this->display($sibling->nama_lengkap),
                    'tanggal_lahir' => $this->formatDate($sibling->tanggal_lahir),
                    'jenis_kelamin' => $this->genderLabel($sibling->jenis_kelamin),
                    'pendidikan_pekerjaan' => $this->display($sibling->pendidikan_pekerjaan),
                ])
                ->all(),
            'printed_at' => now()->settings(['locale' => 'id'])->translatedFormat('d F Y'),
            'registration_number' => $this->display($prospectiveStudent->registration_number),
            'show_nisn' => $prospectiveStudent->schoolClass?->schoolLevel !== SchoolLevel::TK,
        ];

        $filename = 'biodata-ppdb-'.$this->safeFilenamePart($prospectiveStudent->registration_number).'.pdf';

        return Pdf::loadView('reports.ppdb-biodata-pdf', $data)
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    /**
     * @return array<string, string>
     */
    private function parentData(?PpdbParent $parent, bool $includeLivingStatus = false): array
    {
        $data = [
            'nama' => $this->display($parent?->nama),
            'nik' => $this->display($parent?->nik),
            'tempat_lahir' => $this->display($parent?->tempat_lahir),
            'tanggal_lahir' => $this->formatDate($parent?->tanggal_lahir),
            'pendidikan_terakhir' => $this->display($parent?->pendidikan_terakhir),
            'pekerjaan' => $this->display($parent?->occupation),
            'penghasilan' => $this->incomeRangeLabel($parent),
            'no_telp' => $this->display($parent?->no_telp),
            'email' => $this->display($parent?->email),
        ];

        if ($includeLivingStatus) {
            $data['status_hidup'] = $this->livingStatusLabel($parent?->status_hidup);
        }

        return $data;
    }

    private function hasParentData(?PpdbParent $parent): bool
    {
        if ($parent === null) {
            return false;
        }

        return collect([
            $parent->nama,
            $parent->nik,
            $parent->tempat_lahir,
            $parent->tanggal_lahir,
            $parent->pendidikan_terakhir,
            $parent->email,
            $parent->no_telp,
            $parent->occupation,
            $parent->getAttributes()['income_range'] ?? null,
        ])->contains(fn (mixed $value): bool => filled($value));
    }

    private function incomeRangeLabel(?PpdbParent $parent): string
    {
        $raw = $parent?->getAttributes()['income_range'] ?? null;

        if (! filled($raw)) {
            return '-';
        }

        $range = ParentIncomeRange::tryFrom((string) $raw);

        return $range?->label() ?? (string) $raw;
    }

    private function applicantCategoryLabel(ProspectiveStudent $prospectiveStudent): string
    {
        $raw = $prospectiveStudent->getAttributes()['applicant_category'] ?? null;

        if (! filled($raw)) {
            return '-';
        }

        $category = PpdbApplicantCategory::tryFrom((string) $raw);

        return $category?->label() ?? (string) $raw;
    }

    private function genderLabel(mixed $value): string
    {
        return match ($value) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };
    }

    private function distanceLabel(mixed $value): string
    {
        return match ($value) {
            'less_than_1_km' => 'Kurang dari 1 km',
            'more_than_1_km' => 'Lebih dari 1 km',
            default => '-',
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
            default => '-',
        };
    }

    private function livingStatusLabel(mixed $value): string
    {
        return match ($value) {
            'hidup' => 'Hidup',
            'wafat' => 'Wafat',
            default => '-',
        };
    }

    private function specialNeedsLabel(mixed $value): string
    {
        if (is_null($value)) {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'ya', 'y'], true)) {
                return 'Ya';
            }

            if (in_array($normalized, ['0', 'false', 'tidak', 't'], true)) {
                return 'Tidak';
            }

            return $this->display($value);
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 'Ya' : 'Tidak';
        }

        return '-';
    }

    private function formatDate(mixed $value): string
    {
        if (! filled($value)) {
            return '-';
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->settings(['locale' => 'id'])->translatedFormat('d F Y');
        }

        try {
            return Carbon::parse((string) $value)->settings(['locale' => 'id'])->translatedFormat('d F Y');
        } catch (Throwable) {
            return $this->display($value);
        }
    }

    private function formatDecimal(mixed $value): string
    {
        if (! filled($value)) {
            return '-';
        }

        if (! is_numeric($value)) {
            return $this->display($value);
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }

    private function formatDistance(mixed $value): string
    {
        if (! filled($value) || ! is_numeric($value)) {
            return '-';
        }

        return number_format((float) $value, 2, ',', '.').' km';
    }

    private function display(mixed $value): string
    {
        if (is_null($value)) {
            return '-';
        }

        if (is_string($value)) {
            return trim($value) === '' ? '-' : $value;
        }

        return (string) $value;
    }

    private function safeFilenamePart(?string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $value) ?? '';
        $sanitized = trim($sanitized, '-');

        return $sanitized !== '' ? $sanitized : 'prospective-student';
    }
}
