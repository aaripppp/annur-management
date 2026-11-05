<?php

namespace App\Livewire;

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Models\PpdbParent;
use App\Models\ProspectiveStudent;
use App\Services\PpdbApplicantDataUpdater;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class PpdbApplicantEditor extends Component
{
    #[Locked]
    public int $prospectiveStudentId;

    #[Locked]
    public ?int $studentId = null;

    public bool $isOpen = false;

    public ?string $successMessage = null;

    public string $applicantCategory = '';

    /** @var array<string, mixed> */
    public array $student = [];

    /** @var array<string, mixed> */
    public array $profile = [];

    /** @var array<string, array<string, mixed>> */
    public array $parents = [];

    /** @var list<array<string, mixed>> */
    public array $siblings = [];

    public function mount(ProspectiveStudent $prospectiveStudent, ?int $studentId = null): void
    {
        Gate::authorize('updateBiodata', $prospectiveStudent);

        $this->prospectiveStudentId = $prospectiveStudent->id;
        $this->studentId = $studentId;
        $this->ensureStudentLinkage($prospectiveStudent);
    }

    #[On('ppdb-editor:open')]
    public function openFromEvent(?int $prospectId = null, ?int $studentId = null): void
    {
        if ($prospectId) {
            $this->prospectiveStudentId = $prospectId;
        }

        if ($studentId !== null) {
            $this->studentId = $studentId;
        }

        $prospectiveStudent = $this->authorizedProspectiveStudent();
        $this->ensureStudentLinkage($prospectiveStudent);
        $this->populateForm($prospectiveStudent);
        $this->resetValidation();
        $this->successMessage = null;
        $this->isOpen = true;
    }

    public function openEditor(): void
    {
        $prospectiveStudent = $this->authorizedProspectiveStudent();
        $this->populateForm($prospectiveStudent);
        $this->resetValidation();
        $this->successMessage = null;
        $this->isOpen = true;
    }

    public function cancel(): void
    {
        $this->isOpen = false;
        $this->resetValidation();
    }

    public function addSibling(): void
    {
        $this->siblings[] = $this->emptySibling();
    }

    public function removeSibling(string $key): void
    {
        $this->siblings = array_values(array_filter(
            $this->siblings,
            fn (array $sibling): bool => ($sibling['key'] ?? null) !== $key,
        ));
    }

    public function save(PpdbApplicantDataUpdater $updater): void
    {
        $prospectiveStudent = $this->authorizedProspectiveStudent();
        $validated = $this->validate($this->rules(), $this->messages());

        $updater->update($prospectiveStudent->id, [
            'applicant_category' => $validated['applicantCategory'],
            'student' => $validated['student'],
            'profile' => $validated['profile'],
            'parents' => $validated['parents'],
            'siblings' => $validated['siblings'],
        ], $this->studentId);

        $this->isOpen = false;
        $this->successMessage = 'Biodata PPDB berhasil diperbarui.';
        $this->dispatch('ppdb-biodata-updated', prospectiveStudentId: $prospectiveStudent->id);
    }

    public function render(): View
    {
        return view('livewire.ppdb-applicant-editor', [
            'applicantCategories' => PpdbApplicantCategory::cases(),
            'incomeOptions' => ParentIncomeRange::options(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        $rules = [
            'applicantCategory' => ['nullable', Rule::enum(PpdbApplicantCategory::class)],
            'student.nama_lengkap' => ['required', 'string', 'max:255'],
            'student.nama_panggilan' => ['nullable', 'string', 'max:100'],
            'student.jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'student.alamat' => ['nullable', 'string', 'max:2000'],
            'profile.tempat_lahir' => ['nullable', 'string', 'max:255'],
            'profile.tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'profile.asal_sekolah' => ['nullable', 'string', 'max:255'],
            'profile.nik_siswa' => ['nullable', 'digits:16'],
            'profile.nomor_kk' => ['nullable', 'digits:16'],
            'profile.nomor_akta_kelahiran' => ['nullable', 'string', 'max:100'],
            'profile.anak_ke' => ['nullable', 'integer', 'min:1'],
            'profile.jumlah_saudara' => ['nullable', 'integer', 'min:0'],
            'profile.status_anak' => ['nullable', 'string', 'max:30'],
            'profile.agama' => ['nullable', 'string', 'max:50'],
            'profile.bahasa_sehari_hari' => ['nullable', 'string', 'max:100'],
            'profile.rt' => ['nullable', 'digits_between:1,3'],
            'profile.rw' => ['nullable', 'digits_between:1,3'],
            'profile.kelurahan' => ['nullable', 'string', 'max:150'],
            'profile.kecamatan' => ['nullable', 'string', 'max:150'],
            'profile.kabupaten_kota' => ['nullable', 'string', 'max:150'],
            'profile.provinsi' => ['nullable', 'string', 'max:150'],
            'profile.kode_pos' => ['nullable', 'digits:5'],
            'profile.tinggi_badan' => ['nullable', 'numeric', 'min:0', 'max:250'],
            'profile.berat_badan' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'profile.lingkar_kepala' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profile.golongan_darah' => ['nullable', 'string', 'max:5'],
            'profile.riwayat_penyakit' => ['nullable', 'string', 'max:2000'],
            'profile.berkebutuhan_khusus' => ['nullable', Rule::in(['0', '1'])],
            'profile.keterangan_kebutuhan_khusus' => ['nullable', 'string', 'max:2000'],
            'siblings' => ['array', 'max:20'],
            'siblings.*.key' => ['required', 'uuid'],
            'siblings.*.nama_lengkap' => ['required', 'string', 'max:255'],
            'siblings.*.tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'siblings.*.jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'siblings.*.pendidikan_pekerjaan' => ['required', 'string', 'max:255'],
        ];

        foreach (['father', 'mother', 'guardian'] as $role) {
            $rules["parents.$role.nama"] = ['nullable', 'string', 'max:255'];
            $rules["parents.$role.nik"] = ['nullable', 'digits:16'];
            $rules["parents.$role.tempat_lahir"] = ['nullable', 'string', 'max:150'];
            $rules["parents.$role.tanggal_lahir"] = ['nullable', 'date'];
            $rules["parents.$role.pendidikan_terakhir"] = ['nullable', 'string', 'max:100'];
            $rules["parents.$role.email"] = ['nullable', 'email', 'max:254'];
            $rules["parents.$role.no_telp"] = ['nullable', 'string', 'max:20', 'regex:/\A[0-9+().\-\s]{8,20}\z/'];
            $rules["parents.$role.occupation"] = ['nullable', 'string', 'max:255'];
            $rules["parents.$role.income_range"] = ['nullable', Rule::enum(ParentIncomeRange::class)];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'student.nama_lengkap.required' => 'Nama lengkap siswa wajib diisi.',
            'applicantCategory.enum' => 'Kategori pendaftar tidak valid.',
            'digits' => ':attribute harus terdiri dari :digits digit.',
            'digits_between' => ':attribute harus terdiri dari :min sampai :max digit.',
            'siblings.max' => 'Maksimal 20 data saudara kandung.',
            'siblings.*.key.uuid' => 'Identitas baris saudara tidak valid.',
            'siblings.*.nama_lengkap.required' => 'Nama saudara wajib diisi.',
            'siblings.*.tanggal_lahir.required' => 'Tanggal lahir saudara wajib diisi.',
            'siblings.*.jenis_kelamin.required' => 'Jenis kelamin saudara wajib dipilih.',
            'siblings.*.pendidikan_pekerjaan.required' => 'Pendidikan atau pekerjaan saudara wajib diisi.',
            'regex' => ':attribute memiliki format yang tidak valid.',
        ];
    }

    private function authorizedProspectiveStudent(): ProspectiveStudent
    {
        $prospectiveStudent = ProspectiveStudent::query()->findOrFail($this->prospectiveStudentId);
        Gate::authorize('updateBiodata', $prospectiveStudent);
        $this->ensureStudentLinkage($prospectiveStudent);

        return $prospectiveStudent;
    }

    private function ensureStudentLinkage(ProspectiveStudent $prospectiveStudent): void
    {
        if ($this->studentId !== null
            && ($prospectiveStudent->converted_student_id !== $this->studentId
                || ! $prospectiveStudent->convertedStudent()->whereKey($this->studentId)->exists())) {
            throw new AuthorizationException('Relasi siswa dan data PPDB tidak valid.');
        }
    }

    private function populateForm(ProspectiveStudent $prospectiveStudent): void
    {
        $prospectiveStudent->load(['convertedStudent', 'ppdbProfile', 'ppdbParents', 'ppdbSiblings']);
        $profile = $prospectiveStudent->ppdbProfile;
        $student = $prospectiveStudent->convertedStudent;
        $father = $prospectiveStudent->ppdbParents->firstWhere('role', 'father');
        $mother = $prospectiveStudent->ppdbParents->firstWhere('role', 'mother');
        $guardian = $prospectiveStudent->ppdbParents->firstWhere('role', 'guardian');

        $this->applicantCategory = $prospectiveStudent->applicant_category?->value ?? '';
        $this->student = [
            'nama_lengkap' => $prospectiveStudent->nama_lengkap,
            'nama_panggilan' => $prospectiveStudent->nama_panggilan ?? '',
            'jenis_kelamin' => $prospectiveStudent->jenis_kelamin ?? '',
            'alamat' => $prospectiveStudent->alamat ?? '',
        ];
        $this->profile = [
            'tempat_lahir' => $profile?->tempat_lahir ?? $student?->tempat_lahir ?? '',
            'tanggal_lahir' => $profile?->tanggal_lahir?->format('Y-m-d') ?? $student?->tanggal_lahir?->format('Y-m-d') ?? '',
            'asal_sekolah' => $profile?->asal_sekolah ?? '',
            'nik_siswa' => $profile?->nik_siswa ?? '',
            'nomor_kk' => $profile?->nomor_kk ?? '',
            'nomor_akta_kelahiran' => $profile?->nomor_akta_kelahiran ?? '',
            'anak_ke' => $profile?->anak_ke !== null ? (string) $profile->anak_ke : '',
            'jumlah_saudara' => $profile?->jumlah_saudara !== null ? (string) $profile->jumlah_saudara : '',
            'status_anak' => $profile?->status_anak ?? '',
            'agama' => $profile?->agama ?? '',
            'bahasa_sehari_hari' => $profile?->bahasa_sehari_hari ?? '',
            'rt' => $profile?->rt ?? '',
            'rw' => $profile?->rw ?? '',
            'kelurahan' => $profile?->kelurahan ?? '',
            'kecamatan' => $profile?->kecamatan ?? '',
            'kabupaten_kota' => $profile?->kabupaten_kota ?? '',
            'provinsi' => $profile?->provinsi ?? '',
            'kode_pos' => $profile?->kode_pos ?? '',
            'tinggi_badan' => $profile?->tinggi_badan ?? '',
            'berat_badan' => $profile?->berat_badan ?? '',
            'lingkar_kepala' => $profile?->lingkar_kepala ?? '',
            'golongan_darah' => $profile?->golongan_darah ?? '',
            'riwayat_penyakit' => $profile?->riwayat_penyakit ?? '',
            'berkebutuhan_khusus' => $profile?->berkebutuhan_khusus === null ? '' : (string) (int) $profile->berkebutuhan_khusus,
            'keterangan_kebutuhan_khusus' => $profile?->keterangan_kebutuhan_khusus ?? '',
        ];
        $this->parents = [
            'father' => $this->parentState($father, $prospectiveStudent->nama_orang_tua, $prospectiveStudent->no_telp_orang_tua),
            'mother' => $this->parentState($mother, $student?->nama_ibu, $student?->no_telp_ibu),
            'guardian' => $this->parentState($guardian),
        ];
        $this->siblings = $prospectiveStudent->ppdbSiblings
            ->map(fn ($sibling): array => [
                'key' => (string) Str::uuid(),
                'nama_lengkap' => $sibling->nama_lengkap,
                'tanggal_lahir' => $sibling->tanggal_lahir?->format('Y-m-d') ?? '',
                'jenis_kelamin' => $sibling->jenis_kelamin ?? '',
                'pendidikan_pekerjaan' => $sibling->pendidikan_pekerjaan ?? '',
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function parentState(?PpdbParent $parent = null, ?string $fallbackName = null, ?string $fallbackPhone = null): array
    {
        return [
            'nama' => $parent?->nama ?? $fallbackName ?? '',
            'nik' => $parent?->nik ?? '',
            'tempat_lahir' => $parent?->tempat_lahir ?? '',
            'tanggal_lahir' => $parent?->tanggal_lahir?->format('Y-m-d') ?? '',
            'pendidikan_terakhir' => $parent?->pendidikan_terakhir ?? '',
            'email' => $parent?->email ?? '',
            'no_telp' => $parent?->no_telp ?? $fallbackPhone ?? '',
            'occupation' => $parent?->occupation ?? '',
            'income_range' => $parent?->income_range?->value ?? '',
        ];
    }

    /** @return array<string, string> */
    private function emptySibling(): array
    {
        return [
            'key' => (string) Str::uuid(),
            'nama_lengkap' => '',
            'tanggal_lahir' => '',
            'jenis_kelamin' => '',
            'pendidikan_pekerjaan' => '',
        ];
    }
}
