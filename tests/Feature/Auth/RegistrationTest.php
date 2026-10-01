<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('registration page can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page->component('auth/register')
    );
});

test('new users can register', function () {
    $response = $this->post(route('register'), [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home'));

    $this->assertDatabaseHas('users', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'is_admin' => false,
    ]);
});

test('registration requires a unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->from(route('register'))->post(route('register'), [
        'name' => 'Budi Santoso',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('registration requires a confirmed password of the minimum length', function () {
    $response = $this->from(route('register'))->post(route('register'), [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('password');
});

test('registration requires a name and a valid email', function () {
    $response = $this->from(route('register'))->post(route('register'), [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['name', 'email']);
});
