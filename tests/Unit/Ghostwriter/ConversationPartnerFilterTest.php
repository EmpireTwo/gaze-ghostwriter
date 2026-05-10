<?php

use Empire2\GazeGhostwriter\Support\ConversationPartnerFilter;

test('touches partner when from matches', function () {
    expect(ConversationPartnerFilter::touchesPartner(
        'Kunde@Example.com',
        ['support@test.de'],
        [],
        'kunde@example.com',
    ))->toBeTrue();
});

test('touches partner when in to or cc', function () {
    expect(ConversationPartnerFilter::touchesPartner(
        'support@test.de',
        ['Kunde@Example.com'],
        [],
        'kunde@example.com',
    ))->toBeTrue();

    expect(ConversationPartnerFilter::touchesPartner(
        'support@test.de',
        [],
        ['kunde@example.com'],
        'kunde@example.com',
    ))->toBeTrue();
});

test('does not touch unrelated mail', function () {
    expect(ConversationPartnerFilter::touchesPartner(
        'other@test.de',
        ['support@test.de'],
        [],
        'kunde@example.com',
    ))->toBeFalse();
});

test('detects outbound from support to partner', function () {
    expect(ConversationPartnerFilter::isOutboundFromSupportToPartner(
        'support@test.de',
        ['kunde@example.com'],
        [],
        ['support@test.de'],
        'kunde@example.com',
    ))->toBeTrue();
});

test('outbound requires support from address', function () {
    expect(ConversationPartnerFilter::isOutboundFromSupportToPartner(
        'other@test.de',
        ['kunde@example.com'],
        [],
        ['support@test.de'],
        'kunde@example.com',
    ))->toBeFalse();
});
