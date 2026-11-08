<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->freezeTime(); // so the "please wait" time in the message is exact
});

test('repeated logins for one address are limited, even with the right password, whatever the letter case', function () {
    User::factory()->create(['email' => 'resident@example.com']);

    foreach (['Resident@Example.com', 'RESIDENT@example.com', 'resident@example.com', ' resident@example.com', 'resident@EXAMPLE.com'] as $email) {
        $this->from(route('login'))->post(route('login'), ['email' => $email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'These credentials does not match our records']);
    }

    $this->from(route('login'))->post(route('login'), ['email' => 'resident@example.com', 'password' => 'password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please wait 60 seconds and try again.']);

    $this->assertGuest();
});

test('the login limit gives the same message whether or not the account exists', function (string $email) {
    User::factory()->create(['email' => 'resident@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->from(route('login'))->post(route('login'), ['email' => $email, 'password' => 'wrong-password']);
    }

    $this->from(route('login'))->post(route('login'), ['email' => $email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please wait 60 seconds and try again.']);
})->with(['an existing account' => 'resident@example.com', 'no account' => 'nobody@example.com']);

test('a successful login, remember me and logout still work within the limit', function () {
    $user = User::factory()->create(['email' => 'resident@example.com']);

    $this->from(route('login'))->post(route('login'), ['email' => 'resident@example.com', 'password' => 'wrong-password']);
    $this->from(route('login'))->post(route('login'), ['email' => 'resident@example.com', 'password' => 'password', 'remember' => '1'])
        ->assertRedirect(route('overview'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('registration attempts from one IP are limited with a clear message', function () {
    foreach (range(1, 3) as $attempt) {
        $this->from(route('register'))->post(route('register'), [
            'name' => 'Sample Resident', 'email' => "resident{$attempt}@example.com", 'password' => 'password1', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }

    $this->from(route('register'))->post(route('register'), [
        'name' => 'Sample Resident', 'email' => 'resident4@example.com', 'password' => 'password1', 'password_confirmation' => 'password1',
    ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['email' => 'Too many sign-up attempts. Please wait 60 seconds and try again.'])
        ->assertSessionHasInput('email', 'resident4@example.com');

    expect(User::where('email', 'resident4@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});
