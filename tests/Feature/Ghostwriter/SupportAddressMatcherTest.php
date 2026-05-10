<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Support\SupportAddressMatcher;

test('matches exact address among recipients', function () {
    $m = new SupportAddressMatcher;
    expect($m->matches(['support@artistfy.com'], ['support@artistfy.com']))->toBeTrue();
});

test('does not match when support list empty', function () {
    $m = new SupportAddressMatcher;
    expect($m->matches([], ['support@artistfy.com']))->toBeFalse();
});

test('normalizes list to lower case', function () {
    $m = new SupportAddressMatcher;
    expect($m->normalizeList(['  Support@Artistfy.COM ']))->toBe(['support@artistfy.com']);
});
