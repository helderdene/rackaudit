<?php

test('login page renders with email and password fields', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->has('canResetPassword')
        ->has('canRegister')
    );
});

test('forgot password link appears when canResetPassword is true', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->where('canResetPassword', true)
    );
});

test('registration link is hidden when canRegister is false', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->where('canRegister', false)
    );
});

test('form displays validation errors for invalid credentials', function () {
    $response = $this->post(route('login.store'), [
        'email' => 'invalid@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors('email');
});
