<?php

namespace App\Livewire;

use App\Models\DaycareChild;
use App\Services\DaycarePaymentDeletionService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class DaycareDetail extends Component
{
    use WithPagination;

    public DaycareChild $child;

    public bool $isEditModalOpen = false;

    public bool $isDeleteModalOpen = false;

    public ?int $deletingPaymentId = null;

    public string $deletingReceiptNumber = '';

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

    public function mount(DaycareChild $child): void
    {
        $this->child = $child;

        if (request()->boolean('edit')) {
            $this->openEditModal();
        }
    }

    public function openEditModal(): void
    {
        $this->child->refresh();
        $this->nama_lengkap = $this->child->nama_lengkap;
        $this->nama_panggilan = $this->child->nama_panggilan ?? '';
        $this->tempat_lahir = $this->child->tempat_lahir ?? '';
        $this->tanggal_lahir = $this->child->tanggal_lahir?->format('Y-m-d') ?? '';
        $this->jenis_kelamin = $this->child->jenis_kelamin ?? '';
        $this->alamat = $this->child->alamat ?? '';
        $this->kelas = $this->child->kelas;
        $this->nama_ayah = $this->child->nama_ayah ?? '';
        $this->no_telp_ayah = $this->child->no_telp_ayah ?? '';
        $this->nama_ibu = $this->child->nama_ibu ?? '';
        $this->no_telp_ibu = $this->child->no_telp_ibu ?? '';
        $this->is_active = $this->child->is_active;
        $this->resetValidation();
        $this->isEditModalOpen = true;
    }

    public function closeEditModal(): void
    {
        $this->isEditModalOpen = false;
        $this->resetValidation();
    }

    public function confirmDelete(int $paymentId): void
    {
        $payment = $this->child->payments()->findOrFail($paymentId);
        $this->deletingPaymentId = $payment->id;
        $this->deletingReceiptNumber = $payment->receipt_number;
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete(): void
    {
        $this->reset(['isDeleteModalOpen', 'deletingPaymentId', 'deletingReceiptNumber']);
    }

    public function delete(DaycarePaymentDeletionService $deletionService): void
    {
        if ($this->deletingPaymentId === null) {
            return;
        }

        $deletionService->delete($this->deletingPaymentId);
        $this->cancelDelete();
        session()->flash('success', 'Transaksi Daycare berhasil dihapus.');
    }

    public function updateBiodata(): void
    {
        $validated = $this->validate($this->rules(), $this->messages());

        foreach (['nama_panggilan', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'nama_ayah', 'no_telp_ayah', 'nama_ibu', 'no_telp_ibu'] as $field) {
            $validated[$field] = filled($validated[$field]) ? trim((string) $validated[$field]) : null;
        }

        $this->child->update($validated);
        $this->child->refresh();
        $this->isEditModalOpen = false;
        session()->flash('success', 'Biodata anak Daycare berhasil diperbarui.');
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

    public function render(): mixed
    {
        $this->child->refresh();

        $paymentsQuery = $this->child->payments()->with(['bank', 'details'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('livewire.daycare-detail', [
            'payments' => (clone $paymentsQuery)->paginate(10),
            'totalTransactions' => $this->child->payments()->count(),
            'totalPayments' => $this->child->payments()->sum('total_amount'),
            'lastPayment' => $this->child->payments()->orderByDesc('payment_date')->orderByDesc('id')->first(),
        ]);
    }
}
