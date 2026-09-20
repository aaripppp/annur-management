<?php

namespace App\Livewire;

use App\Models\DaycareChild;
use App\Services\DaycareChildDeletionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class DaycareManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $isChildDeleteModalOpen = false;

    public ?int $deletingChildId = null;

    public string $deletingChildName = '';

    public int $deletingChildPaymentCount = 0;

    public float $deletingChildPaymentTotal = 0;

    #[Url(as: 'kelas')]
    public string $filterClass = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    public bool $isModalOpen = false;

    public string $nama_lengkap = '';

    public string $nama_panggilan = '';

    public string $tempat_lahir = '';

    public string $tanggal_lahir = '';

    public string $jenis_kelamin = '';

    public string $alamat = '';

    public string $kelas = '';

    public string $nama_ayah = '';

    public string $no_telp_ayah = '';

    public string $nama_ibu = '';

    public string $no_telp_ibu = '';

    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage('childrenPage');
    }

    public function updatedFilterClass(): void
    {
        $this->resetPage('childrenPage');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage('childrenPage');
    }

    public function confirmChildDelete(int $childId): void
    {
        $child = DaycareChild::query()->findOrFail($childId);
        $this->deletingChildId = $child->id;
        $this->deletingChildName = $child->nama_lengkap;
        $this->deletingChildPaymentCount = $child->payments()->count();
        $this->deletingChildPaymentTotal = (float) $child->payments()->sum('total_amount');
        $this->isChildDeleteModalOpen = true;
    }

    public function cancelChildDelete(): void
    {
        $this->reset([
            'isChildDeleteModalOpen',
            'deletingChildId',
            'deletingChildName',
            'deletingChildPaymentCount',
            'deletingChildPaymentTotal',
        ]);
    }

    public function deleteChild(DaycareChildDeletionService $deletionService): void
    {
        if ($this->deletingChildId === null) {
            return;
        }

        $childName = $this->deletingChildName;
        $deletedPaymentCount = $deletionService->delete($this->deletingChildId);

        $this->cancelChildDelete();
        session()->flash(
            'success',
            $deletedPaymentCount > 0
                ? "Data {$childName} beserta {$deletedPaymentCount} riwayat transaksinya berhasil dihapus."
                : "Data {$childName} berhasil dihapus."
        );
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules(), $this->messages());
        $validated = $this->normalizeNullableFields($validated);

        $child = DaycareChild::query()->create($validated);

        session()->flash('success', 'Data anak Daycare berhasil ditambahkan.');
        $this->redirectRoute('daycare.index');
    }

    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'nama_lengkap' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'tempat_lahir' => 'nullable|string|max:255',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'alamat' => 'nullable|string',
            'kelas' => 'required|in:A,B,C,D,E',
            'nama_ayah' => 'nullable|string|max:255',
            'no_telp_ayah' => 'nullable|string|max:50',
            'nama_ibu' => 'nullable|string|max:255',
            'no_telp_ibu' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'tanggal_lahir.date' => 'Tanggal lahir tidak valid.',
            'jenis_kelamin.in' => 'Jenis kelamin tidak valid.',
            'kelas.required' => 'Kelas wajib dipilih.',
            'kelas.in' => 'Kelas Daycare hanya boleh A, B, C, D, atau E.',
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function normalizeNullableFields(array $data): array
    {
        foreach (['nama_panggilan', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'nama_ayah', 'no_telp_ayah', 'nama_ibu', 'no_telp_ibu'] as $field) {
            $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
        }

        return $data;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'nama_lengkap', 'nama_panggilan', 'tempat_lahir', 'tanggal_lahir',
            'jenis_kelamin', 'alamat', 'kelas', 'nama_ayah', 'no_telp_ayah',
            'nama_ibu', 'no_telp_ibu',
        ]);
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render(): mixed
    {
        $children = DaycareChild::query()
            ->when($this->filterClass !== '', fn ($query) => $query->where('kelas', $this->filterClass))
            ->when($this->filterStatus !== '', fn ($query) => $query->where('is_active', $this->filterStatus === 'aktif'))
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('nama_lengkap', 'like', $term)
                        ->orWhere('nama_panggilan', 'like', $term)
                        ->orWhere('no_telp_ayah', 'like', $term)
                        ->orWhere('no_telp_ibu', 'like', $term);
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'childrenPage');

        return view('livewire.daycare-management', [
            'children' => $children,
            'totalChildren' => DaycareChild::query()->count(),
            'totalActive' => DaycareChild::query()->where('is_active', true)->count(),
            'totalInactive' => DaycareChild::query()->where('is_active', false)->count(),
        ]);
    }
}
