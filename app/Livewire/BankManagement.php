<?php

namespace App\Livewire;

use App\Models\Bank;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class BankManagement extends Component
{
    use WithPagination;

    // Modal state
    public $isModalOpen = false;

    public $isEditing = false;

    // Form inputs
    public $bankId = null;

    // Delete confirmation modal
    public $isDeleteModalOpen = false;

    public $deletingId = null;

    public $name = '';

    public string $type = Bank::TYPE_BANK;

    public $account_number = '';

    public $account_name = '';

    public $is_active = true;

    public function openModal()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['bankId', 'name', 'account_number', 'account_name', 'isEditing']);
        $this->type = Bank::TYPE_BANK;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function confirmDelete(int $id)
    {
        $this->deletingId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete()
    {
        $this->deletingId = null;
        $this->isDeleteModalOpen = false;
    }

    public function edit(Bank $bank)
    {
        $this->isEditing = true;
        $this->bankId = $bank->id;
        $this->name = $bank->name;
        $this->type = $bank->type;
        $this->account_number = $bank->account_number ?? '';
        $this->account_name = $bank->account_name ?? '';
        $this->is_active = $bank->is_active;

        $this->isModalOpen = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::in(Bank::types())],
            'account_number' => [Rule::requiredIf($this->type === Bank::TYPE_BANK), 'nullable', 'string', 'max:100'],
            'account_name' => [Rule::requiredIf($this->type === Bank::TYPE_BANK), 'nullable', 'string', 'max:255'],
            'is_active' => 'boolean',
        ];

        $validatedData = $this->validate($rules);

        if ($validatedData['type'] === Bank::TYPE_CASH) {
            $validatedData['account_number'] = null;
            $validatedData['account_name'] = null;
        }

        if ($this->isEditing) {
            Bank::query()->findOrFail($this->bankId)->update($validatedData);
            session()->flash('success', 'Data bank berhasil diupdate.');
        } else {
            Bank::create($validatedData);
            session()->flash('success', 'Bank baru berhasil ditambahkan.');
        }

        $this->closeModal();
    }

    public function messages()
    {
        return [
            'name.required' => 'Nama bank wajib diisi.',
            'name.max' => 'Nama bank maksimal 100 karakter.',
            'type.required' => 'Jenis penerimaan wajib dipilih.',
            'type.in' => 'Jenis penerimaan tidak valid.',
            'account_number.required' => 'Nomor rekening wajib diisi.',
            'account_number.max' => 'Nomor rekening maksimal 100 karakter.',
            'account_name.required' => 'Nama pemilik rekening wajib diisi.',
            'account_name.max' => 'Nama pemilik rekening maksimal 255 karakter.',
        ];
    }

    public function delete()
    {
        if (! $this->deletingId) {
            return;
        }

        $bank = Bank::withCount('payments')->findOrFail($this->deletingId);

        if ($bank->payments_count > 0) {
            $this->cancelDelete();
            session()->flash('error', 'Bank tidak bisa dihapus karena masih memiliki transaksi pembayaran.');

            return;
        }

        $bank->delete();
        $this->cancelDelete();
        session()->flash('success', 'Data bank berhasil dihapus.');
    }

    public function render()
    {
        $totalBanks = Bank::count();
        $banks = Bank::latest()->paginate(10);

        return view('livewire.bank.index', [
            'totalBanks' => $totalBanks,
            'banks' => $banks,
        ]);
    }
}
