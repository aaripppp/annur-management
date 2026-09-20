<?php

use App\Livewire\AccountManagement;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function accountManagementUser(string $role = User::ROLE_ADMIN, array $overrides = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $overrides));
}

/*
|--------------------------------------------------------------------------
| Schema & baseline account
|--------------------------------------------------------------------------
*/

it('gives the users table a super admin / admin role enum, is_active and position columns', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeTrue();
    expect(Schema::hasColumn('users', 'is_active'))->toBeTrue();
    expect(Schema::hasColumn('users', 'position'))->toBeTrue();
});

it('defaults new users to the Admin role and active status', function () {
    $user = accountManagementUser();
    $user->refresh();

    expect($user->role)->toBe(User::ROLE_ADMIN);
    expect($user->is_active)->toBeTrue();
    expect($user->isAdmin())->toBeTrue();
    expect($user->isActive())->toBeTrue();
});

it('seeds the baseline development account as Super Admin', function () {
    $this->seed(MasterDataSeeder::class);

    $admin = User::where('email', 'admin@annur.test')->first();

    expect($admin)->not->toBeNull();
    expect($admin->role)->toBe(User::ROLE_SUPER_ADMIN);
    expect($admin->isSuperAdmin())->toBeTrue();
});

it('seeds Arif Hamdani as an active Super Admin with position Admin TU', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::where('email', 'arif@annur.test')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Arif Hamdani');
    expect($user->role)->toBe(User::ROLE_SUPER_ADMIN);
    expect($user->isSuperAdmin())->toBeTrue();
    expect($user->position)->toBe('Admin TU');
    expect($user->is_active)->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeTrue();
});

it('does not duplicate Arif Hamdani when the seeder runs again', function () {
    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', 'arif@annur.test')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Route + navigation authorization
|--------------------------------------------------------------------------
*/

it('lets a Super Admin open the Account Management page', function () {
    $this->actingAs(accountManagementUser(User::ROLE_SUPER_ADMIN))
        ->get(route('akun.index'))
        ->assertOk()
        ->assertSee('Manajemen Akun')
        ->assertSee('Kelola akun pengguna yang dapat mengakses Annur Management.');
});

it('returns 403 when an Admin opens the Account Management route', function () {
    $this->actingAs(accountManagementUser(User::ROLE_ADMIN))
        ->get(route('akun.index'))
        ->assertForbidden();
});

it('shows Account Management in the sidebar only for Super Admins', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Manajemen Akun')
        ->assertSee(route('akun.index'), false);
});

it('hides Account Management from the sidebar for Admins without empty artifacts', function () {
    $this->actingAs(accountManagementUser(User::ROLE_ADMIN))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Manajemen Akun')
        ->assertDontSee(route('akun.index'), false);
});

it('blocks an Admin from tampering with Account Management via Livewire', function () {
    $this->actingAs(accountManagementUser(User::ROLE_ADMIN));

    Livewire::test(AccountManagement::class)->assertForbidden();

    expect(User::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Create account
|--------------------------------------------------------------------------
*/

it('lets a Super Admin create an Admin account with a position', function () {
    $this->actingAs(accountManagementUser(User::ROLE_SUPER_ADMIN));

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'Windiarti')
        ->set('email', 'windi@email.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', User::ROLE_ADMIN)
        ->set('position', 'Kepala Tata Usaha')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'windi@email.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe(User::ROLE_ADMIN);
    expect($user->position)->toBe('Kepala Tata Usaha');
    expect($user->is_active)->toBeTrue();
});

it('stores an empty position as null on create', function () {
    $this->actingAs(accountManagementUser(User::ROLE_SUPER_ADMIN));

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'Tanpa Jabatan')
        ->set('email', 'tanpa-jabatan@email.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', User::ROLE_ADMIN)
        ->set('position', '')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'tanpa-jabatan@email.com')->first();

    expect($user->position)->toBeNull();
});

it('lets a Super Admin create another Super Admin account', function () {
    $this->actingAs(accountManagementUser(User::ROLE_SUPER_ADMIN));

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'Bendahara')
        ->set('email', 'bendahara@email.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', User::ROLE_SUPER_ADMIN)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', 'bendahara@email.com')->first()->role)->toBe(User::ROLE_SUPER_ADMIN);
});

it('rejects an invalid role value', function () {
    $this->actingAs(accountManagementUser(User::ROLE_SUPER_ADMIN));

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'Intrusi')
        ->set('email', 'intrusi@email.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', 'owner')
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::where('email', 'intrusi@email.com')->exists())->toBeFalse();
});

it('rejects a duplicate email on create', function () {
    accountManagementUser(User::ROLE_ADMIN, ['email' => 'dup@email.com']);

    $this->actingAs(accountManagementUser(User::ROLE_SUPER_ADMIN));

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'Duplikat')
        ->set('email', 'dup@email.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', User::ROLE_ADMIN)
        ->call('save')
        ->assertHasErrors(['email']);

    expect(User::where('email', 'dup@email.com')->count())->toBe(1);
});

it('stores passwords hashed, never plain text', function () {
    $this->actingAs(accountManagementUser(User::ROLE_SUPER_ADMIN));

    Livewire::test(AccountManagement::class)
        ->call('openModal')
        ->set('name', 'Hashed User')
        ->set('email', 'hashed@email.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('role', User::ROLE_ADMIN)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'hashed@email.com')->first();

    expect($user->password)->not->toBe('secret123');
    expect(Hash::check('secret123', $user->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Edit account
|--------------------------------------------------------------------------
*/

it('edits an account without requiring a password', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    $admin = accountManagementUser(User::ROLE_ADMIN, ['password' => Hash::make('oldpass')]);
    $originalHash = $admin->password;

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('edit', $admin->id)
        ->set('name', 'Windiarti Updated')
        ->set('email', 'windi@email.com')
        ->set('position', 'Kepala Tata Usaha')
        ->call('save')
        ->assertHasNoErrors();

    $admin->refresh();

    expect($admin->name)->toBe('Windiarti Updated');
    expect($admin->email)->toBe('windi@email.com');
    expect($admin->position)->toBe('Kepala Tata Usaha');
    expect($admin->password)->toBe($originalHash);
    expect(Hash::check('oldpass', $admin->password))->toBeTrue();
});

it('hashes a new password when provided on edit', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    $admin = accountManagementUser(User::ROLE_ADMIN);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('edit', $admin->id)
        ->set('password', 'newpass123')
        ->set('password_confirmation', 'newpass123')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('newpass123', $admin->refresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Activate / deactivate
|--------------------------------------------------------------------------
*/

it('lets a Super Admin deactivate an Admin', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    $admin = accountManagementUser(User::ROLE_ADMIN);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('toggleStatus', $admin->id);

    expect($admin->refresh()->is_active)->toBeFalse();
});

it('keeps deactivated historical users in the database', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    $admin = accountManagementUser(User::ROLE_ADMIN, ['name' => 'Riwayat User']);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)->call('toggleStatus', $admin->id);

    $historical = User::find($admin->id);

    expect($historical)->not->toBeNull();
    expect($historical->name)->toBe('Riwayat User');
    expect($historical->is_active)->toBeFalse();
});

it('lets a Super Admin re-activate a deactivated Admin', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    $admin = accountManagementUser(User::ROLE_ADMIN, ['is_active' => false]);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)->call('toggleStatus', $admin->id);

    expect($admin->refresh()->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Historical attribution safety
|--------------------------------------------------------------------------
*/

it('keeps the historical Payment/User relation valid after deactivation', function () {
    $creator = accountManagementUser(User::ROLE_ADMIN, ['name' => 'Pencatat Lama']);
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    $student = makeBillStudent(8);
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-RIWAYAT-1',
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-01',
        'total_amount' => 100000,
        'payment_method' => 'cash',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $creator->id,
    ]);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)->call('toggleStatus', $creator->id);

    expect($creator->refresh()->is_active)->toBeFalse();
    expect($payment->refresh()->user->name)->toBe('Pencatat Lama');
});

/*
|--------------------------------------------------------------------------
| Last Super Admin & self protection
|--------------------------------------------------------------------------
*/

it('does not let the last active Super Admin downgrade their role', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('edit', $superAdmin->id)
        ->set('role', User::ROLE_ADMIN)
        ->call('save')
        ->assertHasErrors(['role']);

    expect($superAdmin->refresh()->role)->toBe(User::ROLE_SUPER_ADMIN);
});

it('does not let the last active Super Admin be deactivated through edit', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('edit', $superAdmin->id)
        ->set('is_active', false)
        ->call('save')
        ->assertHasErrors(['is_active']);

    expect($superAdmin->refresh()->is_active)->toBeTrue();
});

it('does not let the current Super Admin deactivate their own account', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    accountManagementUser(User::ROLE_SUPER_ADMIN);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)->call('toggleStatus', $superAdmin->id);

    expect($superAdmin->refresh()->is_active)->toBeTrue();
});

it('lets a Super Admin deactivate another Super Admin while at least one stays', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    $otherSuper = accountManagementUser(User::ROLE_SUPER_ADMIN, ['name' => 'Super Lain']);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)->call('toggleStatus', $otherSuper->id);

    expect($otherSuper->refresh()->is_active)->toBeFalse();

    Livewire::test(AccountManagement::class)->call('toggleStatus', $superAdmin->id);

    expect($superAdmin->refresh()->is_active)->toBeTrue();
    expect(User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->count())->toBe(1);
});

it('allows a Super Admin to self-downgrade only when another active Super Admin exists', function () {
    $superAdmin = accountManagementUser(User::ROLE_SUPER_ADMIN);
    accountManagementUser(User::ROLE_SUPER_ADMIN);

    $this->actingAs($superAdmin);

    Livewire::test(AccountManagement::class)
        ->call('edit', $superAdmin->id)
        ->set('role', User::ROLE_ADMIN)
        ->call('save')
        ->assertHasNoErrors();

    expect($superAdmin->refresh()->role)->toBe(User::ROLE_ADMIN);
    expect(User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Login behavior
|--------------------------------------------------------------------------
*/

it('blocks login for an inactive account with a friendly message', function () {
    $user = accountManagementUser(User::ROLE_ADMIN, ['is_active' => false]);

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(session('errors')->first('email'))->toContain('Akun Anda sedang dinonaktifkan. Hubungi administrator.');
    $this->assertGuest();
});

it('lets an active account log in', function () {
    $user = accountManagementUser(User::ROLE_ADMIN);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

/*
|--------------------------------------------------------------------------
| Admin keeps identical access to operational features
|--------------------------------------------------------------------------
*/

it('lets an Admin log in and access normal operational pages', function () {
    $this->seed(MasterDataSeeder::class);
    $admin = accountManagementUser(User::ROLE_ADMIN);

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin);

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('siswa.index'))->assertOk();
    $this->get(route('daycare.index'))->assertOk();
    $this->get(route('pembayaran.index'))->assertOk();
    $this->get(route('laporan.index'))->assertOk();
    $this->get(route('bank.index'))->assertOk();
    $this->get(route('kelas.index'))->assertOk();
});
