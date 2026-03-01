<?php

use Laravel\Fortify\Features;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
})->skip(fn () => ! Features::enabled(Features::registration()), 'Registration feature is disabled');

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
})->skip(fn () => ! Features::enabled(Features::registration()), 'Registration feature is disabled');
