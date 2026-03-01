<?php

test('registration route returns 404 when feature is disabled', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('login route renders successfully', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
});

test('password reset request route renders successfully', function () {
    $response = $this->get(route('password.request'));

    $response->assertSuccessful();
});
