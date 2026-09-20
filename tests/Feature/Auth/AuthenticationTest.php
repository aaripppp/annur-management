<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertStatus(200)
        ->assertSeeHtml('name="password"')
        ->assertSeeHtml('autocomplete="current-password"')
        ->assertSeeHtml('x-data="{ showPassword: false }"')
        ->assertSeeHtml('id="password-visibility-toggle"')
        ->assertSeeHtml('type="button"')
        ->assertSeeHtml('right-3 top-1/2 -translate-y-1/2 z-10')
        ->assertSeeHtml('x-show="! showPassword">visibility</span>')
        ->assertSeeHtml('x-show="showPassword" x-cloak>visibility_off</span>')
        ->assertSee('Annur Management')
        ->assertSee(asset('images/annur_logo2.png'), false)
        ->assertSeeHtml('rel="icon" type="image/png"')
        ->assertSeeHtml('rel="shortcut icon" type="image/png"')
        ->assertDontSee('EduPay Admin')
        ->assertSee('window.Alpine?.start()', false);
});

test('authenticated application layout uses Annur branding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Annur Management')
        ->assertSee(asset('images/annur_logo2.png'), false)
        ->assertSeeHtml('rel="icon" type="image/png"')
        ->assertSeeHtml('rel="shortcut icon" type="image/png"')
        ->assertDontSee('EduPay Admin');
});

test('default public favicon uses the Annur icon asset', function () {
    $faviconPath = public_path('favicon.ico');
    $faviconSize = getimagesize($faviconPath);

    expect($faviconSize)->not->toBeFalse()
        ->and([$faviconSize[0], $faviconSize[1]])->toBe([32, 32])
        ->and(file_exists(public_path('favicon.svg')))->toBeFalse();
});

test('landing page uses Annur branding without Laravel branding', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Annur Management')
        ->assertSee(asset('images/annur_logo2.png'), false)
        ->assertSeeHtml('rel="icon" type="image/png"')
        ->assertSeeHtml('rel="shortcut icon" type="image/png"')
        ->assertDontSee('Laravel');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('successful login ignores intended payment URL and redirects to dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->withSession(['url.intended' => route('pembayaran.index')])
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect($response->headers->get('Location'))->not->toBe(route('pembayaran.index'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));
});

test('unauthenticated users are redirected from dashboard to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});
