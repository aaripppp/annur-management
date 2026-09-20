<?php

namespace App\Livewire;

use App\Models\SchoolClass;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SchoolClassManagement extends Component
{
    use WithPagination;

    public $filterLevel = '';

    public $isModalOpen = false;

    public $isEditing = false;

    public $isDeleteModalOpen = false;

    public ?int $deletingId = null;

    public ?int $classId = null;

    public $name = '';

    public string $level = '';

    public $deleteClassName = '';

    public $deleteStudentCount = 0;

    public function updatedFilterLevel()
    {
        $this->resetPage();
    }

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
        $this->reset(['classId', 'name', 'level', 'isEditing']);
        $this->resetValidation();
    }

    public function confirmDelete(int $id)
    {
        $class = SchoolClass::findOrFail($id);

        $this->deletingId = $class->id;
        $this->deleteClassName = $class->name;
        $this->deleteStudentCount = $class->students()->count();
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete()
    {
        $this->deletingId = null;
        $this->deleteClassName = '';
        $this->deleteStudentCount = 0;
        $this->isDeleteModalOpen = false;
    }

    public function edit(int $id)
    {
        $schoolClass = SchoolClass::findOrFail($id);

        $this->isEditing = true;
        $this->classId = $schoolClass->id;
        $this->name = $schoolClass->name;
        $this->level = (string) $schoolClass->level;

        $this->isModalOpen = true;
    }

    public function save()
    {
        $validLevels = array_map('strval', array_keys(SchoolClass::levelOptions()));
        $validatedData = $this->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('school_classes', 'name')->ignore($this->classId),
            ],
            'level' => ['required', Rule::in($validLevels)],
        ]);

        $data = ['name' => $validatedData['name'], 'level' => (int) $validatedData['level']];

        if ($this->isEditing) {
            SchoolClass::findOrFail($this->classId)->update($data);
            session()->flash('success', 'Data kelas berhasil diupdate.');
        } else {
            SchoolClass::create($data);
            session()->flash('success', 'Kelas baru berhasil ditambahkan.');
        }

        $this->closeModal();
    }

    public function messages()
    {
        return [
            'name.required' => 'Nama kelas wajib diisi.',
            'name.max' => 'Nama kelas maksimal 50 karakter.',
            'name.unique' => 'Nama kelas sudah digunakan. Pilih nama lain.',
            'level.required' => 'Tingkat kelas wajib dipilih.',
            'level.in' => 'Tingkat kelas yang dipilih tidak valid.',
        ];
    }

    public function delete()
    {
        if (! $this->deletingId) {
            return;
        }

        $class = SchoolClass::findOrFail($this->deletingId);

        if ($class->students()->count() > 0) {
            session()->flash('error', 'Kelas tidak bisa dihapus karena masih memiliki siswa terdaftar.');

            return;
        }

        $class->delete();
        $this->cancelDelete();
        $this->resetPage();
        session()->flash('success', "Data kelas {$class->name} berhasil dihapus.");
    }

    public function render()
    {
        $query = SchoolClass::withCount('students')->orderBy('level')->orderBy('name');

        if ($this->filterLevel !== '') {
            $query->where('level', $this->filterLevel);
        }

        return view('livewire.school-class.index', [
            'classes' => $query->paginate(10),
            'totalClasses' => SchoolClass::count(),
        ]);
    }
}
