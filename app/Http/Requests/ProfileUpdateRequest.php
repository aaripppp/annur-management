<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * The error bag used by the profile information form.
     *
     * @var string
     */
    protected $errorBag = 'updateProfile';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $email = $this->input('email');
        $emailChanged = is_string($email) && filled($email) && $email !== $this->user()->email;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'current_password' => $emailChanged
                ? ['required', 'current_password']
                : ['nullable'],
            'position' => $this->user()->hasReservedPosition()
                ? ['prohibited']
                : [
                    'nullable',
                    'string',
                    'max:255',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        $reservedPositions = [
                            User::POSITION_DIRECTOR,
                            User::POSITION_HEAD_TU_FOUNDATION,
                        ];

                        $normalizedReservedPositions = array_map(
                            static fn (string $position): string => Str::lower($position),
                            $reservedPositions,
                        );

                        if (is_string($value) && in_array(Str::lower(trim($value)), $normalizedReservedPositions, true)) {
                            $fail('Jabatan tidak diperbolehkan diubah melalui Profil Saya.');
                        }
                    },
                ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge([
                'email' => Str::lower($email),
            ]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Email harus berupa teks.',
            'email.lowercase' => 'Email harus menggunakan huruf kecil.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email sudah digunakan.',
            'current_password.required' => 'Password saat ini wajib diisi untuk mengubah email.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'position.string' => 'Jabatan harus berupa teks.',
            'position.max' => 'Jabatan maksimal 255 karakter.',
            'position.prohibited' => 'Jabatan tidak diperbolehkan diubah melalui Profil Saya.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        $this->session()->flash('profile_notification', [
            'type' => 'error',
            'message' => $validator->errors()->first(),
        ]);

        parent::failedValidation($validator);
    }
}
