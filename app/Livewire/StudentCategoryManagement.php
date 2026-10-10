<?php

namespace App\Livewire;

use App\Models\StudentCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class StudentCategoryManagement extends Component
{
    use WithPagination;

    public bool $isModalOpen = false;

    public bool $isEditing = false;

    public bool $isDeleteModalOpen = false;

    public ?int $categoryId = null;

    public ?int $deletingId = null;

    public string $name = '';

    public string $code = '';

    public bool $is_active = true;

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

    public function resetForm(): void
    {
        $this->reset(['categoryId', 'name', 'code', 'isEditing']);
        $this->is_active = true;
        $this->resetValidation();
    }

    public function edit(StudentCategory $studentCategory): void
    {
        $this->isEditing = true;
        $this->categoryId = $studentCategory->id;
        $this->name = $studentCategory->name;
        $this->code = $studentCategory->code;
        $this->is_active = $studentCategory->is_active;
        $this->isModalOpen = true;
    }

    public function save(): void
    {
        $this->code = Str::slug($this->code, '_');

        $category = $this->isEditing
            ? StudentCategory::query()->findOrFail((int) $this->categoryId)
            : new StudentCategory;
        $wasActive = $category->exists ? $category->is_active : null;

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/',
                Rule::unique('student_categories', 'code')->ignore($category->id),
            ],
            'is_active' => ['boolean'],
        ];

        $validated = $this->validate($rules, [
            'name.required' => 'Nama kategori wajib diisi.',
            'code.required' => 'Kode kategori wajib diisi.',
            'code.regex' => 'Kode hanya boleh berisi huruf kecil, angka, dan garis bawah.',
            'code.unique' => 'Kode kategori sudah digunakan.',
        ]);

        $category->name = $validated['name'];
        $category->code = $validated['code'];
        $category->is_active = $validated['is_active'];
        $category->save();

        $message = match (true) {
            ! $this->isEditing => 'Kategori siswa berhasil ditambahkan.',
            $wasActive !== $category->is_active => 'Status kategori berhasil diperbarui.',
            default => 'Kategori siswa berhasil diperbarui.',
        };

        session()->flash('success', $message);

        $this->closeModal();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
        $this->isDeleteModalOpen = false;
    }

    public function delete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $category = StudentCategory::query()
            ->withCount('students')
            ->findOrFail($this->deletingId);

        if ($category->students_count > 0) {
            $count = number_format($category->students_count, 0, ',', '.');
            $this->cancelDelete();
            session()->flash('error', "Kategori masih digunakan oleh {$count} siswa dan tidak dapat dihapus.");

            return;
        }

        DB::transaction(fn () => $category->delete());
        $this->cancelDelete();
        session()->flash('success', 'Kategori siswa berhasil dihapus.');
    }

    public function render(): View
    {
        return view('livewire.student-category-management', [
            'categories' => StudentCategory::query()
                ->withCount('students')
                ->orderBy('name')
                ->paginate(10),
            'totalCategories' => StudentCategory::query()->count(),
        ]);
    }
}
