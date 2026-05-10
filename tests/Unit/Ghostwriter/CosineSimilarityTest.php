<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Support\CosineSimilarity;

test('identical vectors score one', function () {
    $v = [1.0, 2.0, 3.0];
    expect(CosineSimilarity::score($v, $v))->toBeFloat()->toBeGreaterThan(0.999);
});

test('orthogonal vectors score zero', function () {
    expect(CosineSimilarity::score([1.0, 0.0], [0.0, 1.0]))->toBe(0.0);
});

test('mismatched lengths score zero', function () {
    expect(CosineSimilarity::score([1.0], [1.0, 0.0]))->toBe(0.0);
});
