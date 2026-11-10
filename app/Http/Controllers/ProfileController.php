<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! $user->hasReservedPosition()) {
            $payload['position'] = ($validated['position'] ?? null) ?: null;
        }

        $user->update($payload);

        return Redirect::route('profile.edit')->with('profile_notification', [
            'type' => 'success',
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }

    /**
     * Deny self-service account deletion.
     */
    public function destroy(): never
    {
        abort(403);
    }
}
