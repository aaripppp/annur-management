<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});

test('password confirmation must match', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', ['password', 'password_confirmation'])
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});

test('short password validation is visible in Indonesian without flashing password values', function () {
    $user = User::factory()->create([
        'name' => 'Akun Tetap',
        'position' => 'Staf TU',
    ]);
    $originalHash = $user->password;

    $response = $this->followingRedirects()
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'acil',
            'password_confirmation' => 'acil',
        ]);

    $response->assertOk();

    expect(session()->getOldInput('current_password'))->toBeNull()
        ->and(session()->getOldInput('password'))->toBeNull()
        ->and(session()->getOldInput('password_confirmation'))->toBeNull();

    $response
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="error"', false)
        ->assertSee('x-init="setTimeout(() => show = false, 5000)"', false)
        ->assertSee('Password baru minimal 8 karakter.')
        ->assertSee('aria-invalid="true"', false)
        ->assertDontSee('data-password-error-summary', false)
        ->assertDontSee('data-profile-error-summary', false)
        ->assertDontSee('acil');

    expect(substr_count($response->getContent(), 'data-flash-toast'))->toBe(1);

    $user->refresh();

    expect($user->password)->toBe($originalHash)
        ->and($user->name)->toBe('Akun Tetap')
        ->and($user->position)->toBe('Staf TU');
});

test('wrong current password feedback is visible in Indonesian', function () {
    $user = User::factory()->create();

    $response = $this->followingRedirects()->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertOk()
        ->assertSee('Password saat ini tidak sesuai.')
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="error"', false)
        ->assertDontSee('data-password-error-summary', false);
});

test('password confirmation mismatch feedback is visible in Indonesian', function () {
    $user = User::factory()->create();

    $response = $this->followingRedirects()->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

    $response
        ->assertOk()
        ->assertSee('Konfirmasi password tidak cocok.')
        ->assertSee('id="password-confirmation-error"', false);
});

test('empty password feedback is visible in Indonesian', function () {
    $user = User::factory()->create();

    $response = $this->followingRedirects()->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => '',
            'password_confirmation' => '',
        ]);

    $response
        ->assertOk()
        ->assertSee('Password baru wajib diisi.')
        ->assertSee('Konfirmasi password baru wajib diisi.');
});

test('successful password update displays the correct feedback', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->get('/profile')
        ->assertOk()
        ->assertSee('data-flash-toast', false)
        ->assertSee('data-flash-type="success"', false)
        ->assertSee('Password berhasil diperbarui.')
        ->assertDontSee('data-password-success', false)
        ->assertDontSee('data-profile-success', false);
});
