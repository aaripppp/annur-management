<?php

use App\Enums\SchoolLevel;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('lets every active role access its own profile and account dropdown', function (string $role, ?SchoolLevel $schoolLevel = null) {
    $user = User::factory()->create([
        'role' => $role,
        'school_level' => $schoolLevel,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Profil Saya')
        ->assertSee('Informasi Profil')
        ->assertSee('Informasi Akun')
        ->assertSee('Ubah Password')
        ->assertSee('aria-controls="desktop-account-menu"', false)
        ->assertSee('aria-controls="mobile-account-menu"', false)
        ->assertSee(route('profile.edit'), false)
        ->assertSee('Keluar');
})->with([
    'Superadmin' => [User::ROLE_SUPER_ADMIN],
    'Admin' => [User::ROLE_ADMIN],
    'TU Unit' => [User::ROLE_TU_UNIT, SchoolLevel::SD],
]);

it('redirects guests from the profile page to login', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

it('revokes an inactive user before profile access', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

it('updates the authenticated users name and position', function () {
    $user = User::factory()->create(['position' => null]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            'position' => 'Kepala Tata Usaha',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh())
        ->name->toBe('Test User')
        ->position->toBe('Kepala Tata Usaha');

    $successResponse = $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="success"', false)
        ->assertSee('x-init="setTimeout(() => show = false, 3000)"', false)
        ->assertSee('Profil berhasil diperbarui.')
        ->assertDontSee('data-profile-success', false)
        ->assertDontSee('data-password-success', false);

    expect(substr_count($successResponse->getContent(), 'data-flash-toast'))->toBe(1);
});

it('normalizes and updates email with the correct current password without ending the session', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $verifiedAt = $user->email_verified_at;

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'UPDATED@EXAMPLE.COM',
            'current_password' => 'password',
            'position' => $user->position,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->email)->toBe('updated@example.com')
        ->and($user->email_verified_at?->equalTo($verifiedAt))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('denies an email update with an incorrect current password', function () {
    $user = User::factory()->create(['email' => 'original@example.com']);

    $response = $this->followingRedirects()->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'updated@example.com',
            'current_password' => 'wrong-password',
            'position' => $user->position,
        ]);

    expect($user->refresh()->email)->toBe('original@example.com');

    $response
        ->assertOk()
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="error"', false)
        ->assertSee('Password saat ini tidak sesuai.')
        ->assertDontSee('data-profile-error-summary', false)
        ->assertDontSee('data-password-error-summary', false)
        ->assertDontSee('wrong-password');
});

it('requires the email to remain unique', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create(['email' => 'original@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => strtoupper($existing->email),
            'current_password' => 'password',
            'position' => $user->position,
        ])
        ->assertSessionHasErrorsIn('updateProfile', 'email');

    expect($user->refresh()->email)->toBe('original@example.com');
});

it('denies self assignment of reserved financial positions', function (string $position) {
    $user = User::factory()->create(['position' => 'Staf TU']);

    $response = $this->followingRedirects()->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => $position,
        ]);

    expect($user->refresh()->position)->toBe('Staf TU');

    $response
        ->assertOk()
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="error"', false)
        ->assertDontSee('data-profile-error-summary', false)
        ->assertSee('Jabatan tidak diperbolehkan diubah melalui Profil Saya.');
})->with([
    User::POSITION_DIRECTOR,
    User::POSITION_HEAD_TU_FOUNDATION,
    strtolower(User::POSITION_DIRECTOR),
]);

it('keeps an existing reserved position read only while allowing other profile updates', function () {
    $user = User::factory()->create(['position' => User::POSITION_DIRECTOR]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(User::POSITION_DIRECTOR)
        ->assertSee('Jabatan ini memiliki kewenangan laporan keuangan')
        ->assertDontSee('name="position"', false);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Direktur Baru',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh())
        ->name->toBe('Direktur Baru')
        ->position->toBe(User::POSITION_DIRECTOR);
});

it('rejects attempts to submit a position for an existing reserved position', function () {
    $user = User::factory()->create(['position' => User::POSITION_HEAD_TU_FOUNDATION]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => 'Jabatan Lain',
        ])
        ->assertSessionHasErrorsIn('updateProfile', 'position');

    expect($user->refresh()->position)->toBe(User::POSITION_HEAD_TU_FOUNDATION);
});

it('ignores privileged profile field tampering', function (string $field, mixed $value) {
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'school_level' => null,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position,
            $field => $value,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->role)->toBe(User::ROLE_ADMIN)
        ->and($user->school_level)->toBeNull()
        ->and($user->is_active)->toBeTrue();
})->with([
    'role' => ['role', User::ROLE_SUPER_ADMIN],
    'school level' => ['school_level', SchoolLevel::SMA->value],
    'active status' => ['is_active', false],
    'verification timestamp' => ['email_verified_at', null],
    'remember token' => ['remember_token', 'tampered-token'],
]);

it('cannot target another user through profile input', function () {
    $user = User::factory()->create(['name' => 'Current User']);
    $otherUser = User::factory()->create(['name' => 'Other User']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'id' => $otherUser->id,
            'user_id' => $otherUser->id,
            'name' => 'Updated Current User',
            'email' => $user->email,
            'position' => $user->position,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Updated Current User')
        ->and($otherUser->refresh()->name)->toBe('Other User');
});

it('shows visible Indonesian profile name validation without changing account fields', function () {
    $user = User::factory()->create([
        'name' => 'Nama Awal',
        'email' => 'awal@example.com',
        'position' => 'Staf TU',
        'role' => User::ROLE_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->followingRedirects()->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => '',
            'email' => 'awal@example.com',
            'position' => 'Jabatan Baru',
        ]);

    $response
        ->assertOk()
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="error"', false)
        ->assertSee('x-init="setTimeout(() => show = false, 5000)"', false)
        ->assertSee('Nama wajib diisi.')
        ->assertSee('id="profile-name-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertDontSee('data-profile-error-summary', false)
        ->assertDontSee('data-password-error-summary', false);

    expect(substr_count($response->getContent(), 'data-flash-toast'))->toBe(1);

    expect($user->refresh())
        ->name->toBe('Nama Awal')
        ->email->toBe('awal@example.com')
        ->position->toBe('Staf TU')
        ->role->toBe(User::ROLE_ADMIN)
        ->is_active->toBeTrue();
});

it('shows visible Indonesian invalid email feedback', function () {
    $user = User::factory()->create(['email' => 'awal@example.com']);

    $response = $this->followingRedirects()->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'bukan-email',
            'current_password' => 'password',
            'position' => $user->position,
        ]);

    $response
        ->assertOk()
        ->assertSee('Format email tidak valid.')
        ->assertSee('id="profile-email-error"', false);

    expect($user->refresh()->email)->toBe('awal@example.com');
});

it('shows visible Indonesian duplicate email feedback', function () {
    User::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create(['email' => 'awal@example.com']);

    $response = $this->followingRedirects()->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'existing@example.com',
            'current_password' => 'password',
            'position' => $user->position,
        ]);

    $response
        ->assertOk()
        ->assertSee('Email sudah digunakan.')
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="error"', false)
        ->assertDontSee('data-profile-error-summary', false);

    expect($user->refresh()->email)->toBe('awal@example.com');
});

it('denies self service account deletion without deleting the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect($user->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('keeps the TU sidebar limited to Student and Applicant operations', function () {
    $tu = User::factory()->create([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => SchoolLevel::SD,
    ]);
    $html = $this->actingAs($tu)->get(route('profile.edit'))->assertOk()->getContent();

    preg_match('/<aside[^>]*id="main-sidebar".*?<\/aside>/s', $html, $matches);
    $sidebar = $matches[0] ?? '';

    expect($sidebar)
        ->toContain(route('siswa.index'))
        ->toContain(route('calon-siswa.index'))
        ->not->toContain('Profil Saya')
        ->not->toContain('Manajemen Akun');
});

it('stores profile passwords hashed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->password)->not->toBe('new-password')
        ->and(Hash::check('new-password', $user->password))->toBeTrue();
});

it('validates password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])
        ->assertSessionHasErrorsIn('updatePassword', 'password')
        ->assertRedirect(route('profile.edit'));

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
