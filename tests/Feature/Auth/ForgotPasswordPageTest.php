<?php

test('forgot password page renders with email field', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/ForgotPassword')
    );
});

test('success status message displays after form submission', function () {
    $user = \App\Models\User::factory()->create();

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertSessionHas('status');
});
