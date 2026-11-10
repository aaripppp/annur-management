<?php

use App\Enums\SchoolLevel;
use App\Livewire\AccountManagement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function identityFoundationUser(string $role, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'is_active' => true,
    ], $attributes));
}

it('adds the TU role compatible school level identity fields without changing existing roles', function () {
    $admin = identityFoundationUser(User::ROLE_ADMIN);
    $superAdmin = identityFoundationUser(User::ROLE_SUPER_ADMIN);

    expect(Schema::hasColumns('users', ['role', 'school_level', 'is_active']))->toBeTrue()
        ->and($admin->refresh()->role)->toBe(User::ROLE_ADMIN)
        ->and($admin->school_level)->toBeNull()
        ->and($superAdmin->refresh()->role)->toBe(User::ROLE_SUPER_ADMIN)
        ->and($superAdmin->school_level)->toBeNull();
});

it('casts all four TU units and exposes focused role helpers', function (SchoolLevel $schoolLevel) {
    $user = identityFoundationUser(User::ROLE_TU_UNIT, [
        'school_level' => $schoolLevel,
    ]);

    expect($user->refresh()->school_level)->toBe($schoolLevel)
        ->and($user->isTuUnit())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->hasSchoolLevel($schoolLevel))->toBeTrue()
        ->and($user->roleLabel())->toBe('TU Unit');
})->with([
    'TK' => [SchoolLevel::TK],
    'SD' => [SchoolLevel::SD],
    'SMP' => [SchoolLevel::SMP],
    'SMA' => [SchoolLevel::SMA],
]);

it('lets only a superadmin create a TU account for each valid unit', function (SchoolLevel $schoolLevel) {
    $superAdmin = identityFoundationUser(User::ROLE_SUPER_ADMIN);
    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'TU '.$schoolLevel->value)
        ->set('email', 'tu-'.strtolower($schoolLevel->value).'@annur.test')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', User::ROLE_TU_UNIT)
        ->set('school_level', $schoolLevel->value)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'tu-'.strtolower($schoolLevel->value).'@annur.test')->firstOrFail();

    expect($user->role)->toBe(User::ROLE_TU_UNIT)
        ->and($user->school_level)->toBe($schoolLevel);
})->with([
    'TK' => [SchoolLevel::TK],
    'SD' => [SchoolLevel::SD],
    'SMP' => [SchoolLevel::SMP],
    'SMA' => [SchoolLevel::SMA],
]);

it('requires a valid assigned unit for TU account creation', function (string $schoolLevel) {
    $this->actingAs(identityFoundationUser(User::ROLE_SUPER_ADMIN));

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'TU Invalid')
        ->set('email', 'tu-invalid@annur.test')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', User::ROLE_TU_UNIT)
        ->set('school_level', $schoolLevel)
        ->call('save')
        ->assertHasErrors(['school_level']);

    expect(User::query()->where('email', 'tu-invalid@annur.test')->exists())->toBeFalse();
})->with([
    'missing unit' => [''],
    'unsupported unit' => ['SMK'],
]);

it('clears school level when a TU account is changed to an unrestricted role', function (string $role) {
    $superAdmin = identityFoundationUser(User::ROLE_SUPER_ADMIN);
    $tu = identityFoundationUser(User::ROLE_TU_UNIT, ['school_level' => SchoolLevel::SMP]);
    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('edit', $tu->id)
        ->set('role', $role)
        ->call('save')
        ->assertHasNoErrors();

    expect($tu->refresh()->role)->toBe($role)
        ->and($tu->school_level)->toBeNull();
})->with([
    'admin' => [User::ROLE_ADMIN],
    'superadmin' => [User::ROLE_SUPER_ADMIN],
]);

it('keeps account management inaccessible to admin and TU users', function (string $role, ?SchoolLevel $schoolLevel) {
    $user = identityFoundationUser($role, ['school_level' => $schoolLevel]);

    $this->actingAs($user)
        ->get(route('akun.index'))
        ->assertStatus($role === User::ROLE_TU_UNIT ? 302 : 403);

    if ($role === User::ROLE_TU_UNIT) {
        $this->get(route('akun.index'))->assertRedirect(route('tu.pending'));
    }
})->with([
    'admin' => [User::ROLE_ADMIN, null],
    'TU Unit' => [User::ROLE_TU_UNIT, SchoolLevel::SD],
]);

it('authenticates an active TU to the scoped Student listing', function () {
    $user = identityFoundationUser(User::ROLE_TU_UNIT, ['school_level' => SchoolLevel::SMP]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('siswa.index'));

    $this->get(route('siswa.index'))
        ->assertOk()
        ->assertSee('Data Siswa')
        ->assertSee('SMP');
});

it('rejects inactive or unitless TU authentication', function (array $attributes, string $message) {
    $user = identityFoundationUser(User::ROLE_TU_UNIT, $attributes);

    $this->from(route('login'))->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email']);

    $this->assertGuest();
    expect(session('errors')->first('email'))->toContain($message);
})->with([
    'inactive TU' => [
        ['school_level' => SchoolLevel::TK, 'is_active' => false],
        'Akun Anda sedang dinonaktifkan',
    ],
    'unitless TU' => [
        ['school_level' => null],
        'Unit sekolah akun TU belum ditentukan',
    ],
]);

it('revokes an existing session as soon as the account becomes inactive', function () {
    $user = identityFoundationUser(User::ROLE_ADMIN);
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

it('prevents inactive accounts from continuing Livewire actions', function () {
    $user = identityFoundationUser(User::ROLE_ADMIN);
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->withHeader('X-Livewire', 'true')
        ->post('/livewire/update', ['components' => []])
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('blocks TU from unrelated modules', function (string $routeName) {
    $tu = identityFoundationUser(User::ROLE_TU_UNIT, ['school_level' => SchoolLevel::SMA]);

    $this->actingAs($tu)
        ->get(route($routeName))
        ->assertRedirect(route('tu.pending'));
})->with([
    'dashboard' => ['dashboard'],
    'payments' => ['pembayaran.index'],
    'reports' => ['laporan.index'],
    'master data' => ['kelas.index'],
    'daycare' => ['daycare.index'],
]);

it('keeps existing admin and superadmin login landing and access unchanged', function (string $role) {
    $user = identityFoundationUser($role);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))->assertOk();

    if ($role === User::ROLE_SUPER_ADMIN) {
        $this->get(route('akun.index'))->assertOk();
    }
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);

it('keeps guest public PPDB registration available', function () {
    $this->get(route('ppdb.public.register'))->assertOk();
});
