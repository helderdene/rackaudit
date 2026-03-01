<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// Password confirmation is disabled (confirmPasswordView not registered in FortifyServiceProvider)
test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/ConfirmPassword')
    );
})->skip('Password confirmation feature is disabled');

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});
