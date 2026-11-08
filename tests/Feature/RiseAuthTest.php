<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

test('guests are sent to the login page', function () {
    $this->get(route('overview'))->assertRedirect(route('login'));
});

test('the login page uses RISE wording', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Sign in to see your photos and follow-ups.')
        ->assertSeeTextInOrder(['Track cracks over time, one photo at a time.', 'Welcome back'])
        ->assertDontSee('How RISE works')
        ->assertDontSee('My Notes');
});

test('registering signs the resident in and opens the overview', function () {
    $this->post(route('register'), [
        'name' => 'Sample Resident',
        'email' => 'resident@example.test',
        'password' => 'sample-password',
        'password_confirmation' => 'sample-password',
    ])->assertRedirect(route('overview'));

    $this->assertAuthenticated();
    $this->get(route('overview'))->assertOk()->assertSee('Welcome, Sample');
});

test('logging in opens the overview and logging out returns to login', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('overview'));
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('wrong passwords are still rejected', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'not-the-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the account menu no longer links to the old Notes area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('overview'))
        ->assertOk()
        ->assertSee('Log out')
        ->assertDontSee(route('notes.index'));
});

test('existing notes keep working for their owner', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('notes.store'), ['title' => 'Kept note', 'body' => 'Still here'])
        ->assertRedirect(route('notes.index'));

    $this->get(route('notes.index'))->assertOk()->assertSee('Kept note');
});
