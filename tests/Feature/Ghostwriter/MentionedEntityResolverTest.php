<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use Domain\Account\Models\Artist;
use Domain\Account\Models\User;
use Domain\Billing\Models\Customer;
use Empire2\GazeGhostwriter\Support\MentionedEntityResolver;
use Domain\Upload\Models\Release;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);
    $this->artist = Artist::factory()->create(['user_id' => $this->user->id, 'name' => 'Claudine Gleason']);
    $this->release = Release::factory()->create(['artist_id' => $this->artist->id, 'name' => 'dolores sed']);
});

test('resolves artist by exact name', function () {
    $result = MentionedEntityResolver::resolve($this->customer, [
        ['type' => 'artist', 'query' => 'Claudine Gleason'],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['type'])->toBe('artist')
        ->and($result[0]['name'])->toBe('Claudine Gleason')
        ->and($result[0]['url'])->toContain('/nova/resources/artists/');
});

test('resolves release by exact name', function () {
    $result = MentionedEntityResolver::resolve($this->customer, [
        ['type' => 'release', 'query' => 'dolores sed'],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['type'])->toBe('release')
        ->and($result[0]['name'])->toBe('dolores sed')
        ->and($result[0]['url'])->toContain('/nova/resources/releases/');
});

test('resolves case-insensitively', function () {
    $result = MentionedEntityResolver::resolve($this->customer, [
        ['type' => 'release', 'query' => 'Dolores Sed'],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('dolores sed');
});

test('resolves partial name match', function () {
    $result = MentionedEntityResolver::resolve($this->customer, [
        ['type' => 'artist', 'query' => 'Claudine'],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('Claudine Gleason');
});

test('returns empty for unknown entity', function () {
    $result = MentionedEntityResolver::resolve($this->customer, [
        ['type' => 'release', 'query' => 'nonexistent album'],
    ]);

    expect($result)->toBe([]);
});

test('returns empty when no entities mentioned', function () {
    $result = MentionedEntityResolver::resolve($this->customer, []);

    expect($result)->toBe([]);
});

test('resolves both artist and release together', function () {
    $result = MentionedEntityResolver::resolve($this->customer, [
        ['type' => 'artist', 'query' => 'Claudine Gleason'],
        ['type' => 'release', 'query' => 'dolores sed'],
    ]);

    expect($result)->toHaveCount(2);

    $types = array_column($result, 'type');
    expect($types)->toContain('artist')
        ->and($types)->toContain('release');
});

test('skips unknown entity types gracefully', function () {
    $result = MentionedEntityResolver::resolve($this->customer, [
        ['type' => 'unknown', 'query' => 'something'],
    ]);

    expect($result)->toBe([]);
});
