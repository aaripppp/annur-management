<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validateWithBag('updatePassword', [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', Password::defaults(), 'confirmed'],
                'password_confirmation' => ['required', 'same:password'],
            ], [
                'current_password.required' => 'Password saat ini wajib diisi.',
                'current_password.current_password' => 'Password saat ini tidak sesuai.',
                'password.required' => 'Password baru wajib diisi.',
                'password.min' => 'Password baru minimal :min karakter.',
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
                'password_confirmation.required' => 'Konfirmasi password baru wajib diisi.',
                'password_confirmation.same' => 'Konfirmasi password tidak cocok.',
            ]);
        } catch (ValidationException $exception) {
            $request->session()->flash('profile_notification', [
                'type' => 'error',
                'message' => collect($exception->errors())->flatten()->first()
                    ?? 'Password belum dapat diperbarui.',
            ]);

            throw $exception;
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('profile_notification', [
            'type' => 'success',
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}
