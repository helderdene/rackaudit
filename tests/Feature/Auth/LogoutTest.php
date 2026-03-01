<?php

use App\Models\User;

test('logout is accessible from authenticated pages', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
});

test('clicking logout redirects to login page as guest', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user);
    $this->assertAuthenticated();

    $response = $this->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});
