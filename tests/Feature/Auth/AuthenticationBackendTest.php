<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('successful login with valid credentials redirects to dashboard', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('logout invalidates session and redirects to login', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);
    $this->assertAuthenticated();

    $response = $this->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('remember me checkbox sets remember token on user', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'remember_token' => null,
    ]);

    $this->assertNull($user->remember_token);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    $this->assertNotNull($user->remember_token);
});

test('rate limiting blocks after 5 attempts per minute', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $throttleKey = strtolower($user->email).'|127.0.0.1';
    RateLimiter::clear($throttleKey);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
