<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use Domain\Account\Models\User;
use Domain\Billing\Models\Customer;
use Empire2\GazeGhostwriter\Support\SmartActionCustomerResolver;

test('resolves customer from email via user', function () {
    $user = User::factory()->create(['email' => 'krischan@example.com']);
    $customer = Customer::factory()->create(['user_id' => $user->id, 'firstname' => 'Krischan', 'lastname' => 'Test']);

    $result = SmartActionCustomerResolver::resolve('krischan@example.com');

    expect($result)->toBeInstanceOf(Customer::class)
        ->and($result->id)->toBe($customer->id);
});

test('resolves customer case-insensitively', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);
    Customer::factory()->create(['user_id' => $user->id]);

    $result = SmartActionCustomerResolver::resolve('ANNA@EXAMPLE.COM');

    expect($result)->toBeInstanceOf(Customer::class);
});

test('returns null when no user matches email', function () {
    $result = SmartActionCustomerResolver::resolve('nobody@nowhere.test');

    expect($result)->toBeNull();
});

test('returns null when user has no customer', function () {
    User::factory()->create(['email' => 'noprofile@example.com']);

    $result = SmartActionCustomerResolver::resolve('noprofile@example.com');

    expect($result)->toBeNull();
});

test('returns null for invalid email placeholder', function () {
    expect(SmartActionCustomerResolver::resolve('unknown@invalid'))->toBeNull();
    expect(SmartActionCustomerResolver::resolve(''))->toBeNull();
    expect(SmartActionCustomerResolver::resolve('  '))->toBeNull();
});
