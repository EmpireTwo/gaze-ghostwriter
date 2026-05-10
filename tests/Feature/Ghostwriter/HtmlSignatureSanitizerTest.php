<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Support\HtmlSignatureSanitizer;

test('sanitizer preserves safe email signature HTML', function () {
    $html = '<table><tr><td><img src="https://example.com/logo.png" width="80"></td><td><strong>Artistfy</strong></td></tr></table>';

    expect(HtmlSignatureSanitizer::sanitize($html))->toBe($html);
});

test('sanitizer removes script tags with content', function () {
    $html = '<div>Hello</div><script>alert("xss")</script><p>World</p>';

    expect(HtmlSignatureSanitizer::sanitize($html))->toBe('<div>Hello</div><p>World</p>');
});

test('sanitizer removes self-closing dangerous tags', function () {
    $html = '<div>Content</div><iframe src="https://evil.test"/><meta charset="utf-8">';

    $result = HtmlSignatureSanitizer::sanitize($html);

    expect($result)->not->toContain('<iframe')
        ->and($result)->not->toContain('<meta')
        ->and($result)->toContain('<div>Content</div>');
});

test('sanitizer removes event handler attributes', function () {
    $html = '<img src="logo.png" onerror="alert(1)" style="width:80px">';

    $result = HtmlSignatureSanitizer::sanitize($html);

    expect($result)->not->toContain('onerror')
        ->and($result)->toContain('src="logo.png"')
        ->and($result)->toContain('style="width:80px"');
});

test('sanitizer removes form elements', function () {
    $html = '<div>Sig</div><form action="/hack"><input type="text"><button>Submit</button></form>';

    $result = HtmlSignatureSanitizer::sanitize($html);

    expect($result)->not->toContain('<form')
        ->and($result)->not->toContain('<input')
        ->and($result)->not->toContain('<button')
        ->and($result)->toContain('<div>Sig</div>');
});

test('sanitizer returns empty string for empty input', function () {
    expect(HtmlSignatureSanitizer::sanitize(''))->toBe('')
        ->and(HtmlSignatureSanitizer::sanitize('   '))->toBe('');
});

test('sanitizer preserves links and inline styles', function () {
    $html = '<a href="https://facebook.com/artistfy" style="color:rgb(17,85,204)"><img alt="Facebook" src="https://example.com/fb.png" width="20"></a>';

    expect(HtmlSignatureSanitizer::sanitize($html))->toBe($html);
});
