<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use Domain\Account\Models\User;
use Domain\Billing\Models\Customer;
use Empire2\GazeGhostwriter\Models\GhostwriterUserData;
use Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer;

test('replacer uses signing_name from ghostwriter user data', function () {
    $user = User::factory()->create(['name' => 'X']);
    GhostwriterUserData::query()->create([
        'user_id' => $user->id,
        'signing_name' => 'Markus von Artistfy',
    ]);

    expect(GhostwriterPlaceholderReplacer::signingName($user))->toBe('Markus von Artistfy')
        ->and(GhostwriterPlaceholderReplacer::apply("Hallo\n[Dein Name]", $user))->toBe("Hallo\nMarkus von Artistfy");
});

test('replacer keeps placeholders when signing_name not configured', function () {
    $user = User::factory()->create(['name' => 'ShouldNotUseForVorname']);
    Customer::factory()->for($user)->create(['firstname' => 'Anna']);

    expect(GhostwriterPlaceholderReplacer::signingName($user))->toBe('[Dein Name]')
        ->and(GhostwriterPlaceholderReplacer::firstNameOnly($user))->toBe('[Dein Name]')
        ->and(GhostwriterPlaceholderReplacer::apply('[Dein Vorname] / [Dein Name]', $user))->toBe('[Dein Name] / [Dein Name]');
});

test('firstNameOnly returns full signing_name', function () {
    $user = User::factory()->create();
    GhostwriterUserData::query()->create([
        'user_id' => $user->id,
        'signing_name' => 'Markus von Artistfy',
    ]);

    expect(GhostwriterPlaceholderReplacer::firstNameOnly($user))->toBe('Markus von Artistfy');
});

test('replacer appends reply signature from ghostwriter user data', function () {
    $user = User::factory()->create();
    GhostwriterUserData::query()->create([
        'user_id' => $user->id,
        'signing_name' => null,
        'reply_signature' => "—\nSupport Team",
    ]);

    expect(GhostwriterPlaceholderReplacer::replySignature($user))->toBe("—\nSupport Team")
        ->and(GhostwriterPlaceholderReplacer::appendReplySignature('Hallo', $user))->toBe("Hallo\n\n—\nSupport Team");
});

test('replacer leaves body unchanged when reply signature empty', function () {
    $user = User::factory()->create();

    expect(GhostwriterPlaceholderReplacer::appendReplySignature("Text\n", $user))->toBe("Text\n");
});

test('replacer returns HTML signature from ghostwriter user data', function () {
    $user = User::factory()->create();
    GhostwriterUserData::query()->create([
        'user_id' => $user->id,
        'reply_signature_html' => '<table><td><strong>Artistfy</strong></td></table>',
    ]);

    expect(GhostwriterPlaceholderReplacer::replySignatureHtml($user))
        ->toBe('<table><td><strong>Artistfy</strong></td></table>');
});

test('replacer returns empty string when no HTML signature set', function () {
    $user = User::factory()->create();

    expect(GhostwriterPlaceholderReplacer::replySignatureHtml($user))->toBe('');
});

test('buildHtmlReplyBody wraps text body and appends HTML signature', function () {
    $user = User::factory()->create();
    GhostwriterUserData::query()->create([
        'user_id' => $user->id,
        'reply_signature_html' => '<div><strong>Sig</strong></div>',
    ]);

    $result = GhostwriterPlaceholderReplacer::buildHtmlReplyBody("Hallo\nWelt", $user);

    expect($result)->toContain('Hallo<br />'."\n".'Welt')
        ->and($result)->toContain('<div><strong>Sig</strong></div>')
        ->and($result)->toContain('font-family:Arial');
});

test('buildHtmlReplyBody returns empty string when no HTML signature', function () {
    $user = User::factory()->create();

    expect(GhostwriterPlaceholderReplacer::buildHtmlReplyBody('Text', $user))->toBe('');
});
