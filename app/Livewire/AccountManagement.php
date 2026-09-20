<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AccountManagement extends Component
{
    use WithPagination;

    public bool $isModalOpen = false;

    public bool $isEditing = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = User::ROLE_ADMIN;

    public string $position = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('manage-accounts');
    }

    public function openModal(): void
    {
        $this->authorize('manage-accounts');

        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function edit(User $user): void
    {
        $this->authorize('manage-accounts');

        $this->isEditing = true;
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->position = $user->position ?? '';
        $this->is_active = $user->is_active;
        $this->password = '';
        $this->password_confirmation = '';
        $this->isModalOpen = true;
    }

    public function resetForm(): void
    {
        $this->reset(['isEditing', 'editingUserId', 'name', 'email', 'password', 'password_confirmation', 'position']);
        $this->role = User::ROLE_ADMIN;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('manage-accounts');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->editingUserId),
            ],
            'role' => ['required', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])],
            'position' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'password' => $this->isEditing
                ? ['nullable', 'string', 'confirmed', Password::defaults()]
                : ['required', 'string', 'confirmed', Password::defaults()],
        ];

        $validated = $this->validate($rules);

        if ($this->isEditing) {
            $user = User::query()->findOrFail($this->editingUserId);

            $this->assertCanMutate($user, $validated);

            $payload = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'position' => $validated['position'] ?: null,
                'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];

            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }

            $user->update($payload);

            session()->flash('success', 'Data akun berhasil diupdate.');
        } else {
            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
                'position' => $validated['position'] ?: null,
                'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);

            session()->flash('success', 'Akun baru berhasil ditambahkan.');
        }

        $this->closeModal();
    }

    public function toggleStatus(User $user): void
    {
        $this->authorize('manage-accounts');

        if ($user->is_active) {
            if ($user->id === auth()->id()) {
                session()->flash('error', 'Akun yang sedang digunakan tidak dapat dinonaktifkan.');

                return;
            }

            if ($user->isSuperAdmin()) {
                $activeSuperAdmins = $this->activeSuperAdminCount();

                if ($activeSuperAdmins <= 1) {
                    session()->flash('error', 'Sistem harus memiliki minimal satu Super Admin aktif.');

                    return;
                }
            }

            $user->update(['is_active' => false]);

            session()->flash('success', 'Akun '.$user->name.' berhasil dinonaktifkan.');

            return;
        }

        $user->update(['is_active' => true]);

        session()->flash('success', 'Akun '.$user->name.' berhasil diaktifkan.');
    }

    public function render()
    {
        $users = User::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.account-management', [
            'users' => $users,
            'totalAccounts' => User::count(),
            'activeAccounts' => User::query()->where('is_active', true)->count(),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'role.required' => 'Role wajib dipilih.',
            'role.in' => 'Role tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'is_active.boolean' => 'Status aktif tidak valid.',
        ];
    }

    private function assertCanMutate(User $user, array $validated): void
    {
        $deactivating = ! filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($user->id === auth()->id() && $deactivating) {
            throw ValidationException::withMessages([
                'is_active' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.',
            ]);
        }

        if ($user->isSuperAdmin() && $user->is_active) {
            $downgrading = $validated['role'] !== User::ROLE_SUPER_ADMIN;

            if ($deactivating || $downgrading) {
                if ($this->activeSuperAdminCount() <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'Sistem harus memiliki minimal satu Super Admin aktif.',
                    ]);
                }
            }
        }
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->count();
    }
}
