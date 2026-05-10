<?php

use Empire2\GazeGhostwriter\Support\MailHtmlSanitizer;

test('returns empty string for empty input', function () {
    expect(MailHtmlSanitizer::sanitizeForPreview(''))->toBe('')
        ->and(MailHtmlSanitizer::sanitizeForPreview('   '))->toBe('');
});

test('removes script tags and their content', function () {
    $html = '<html><body><p>Hello</p><script>alert("xss")</script></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->not->toContain('alert("xss")')
        ->and($result)->toContain('<p>Hello</p>')
        ->and($result)->toContain('gw-iframe-resize');
});

test('removes event handler attributes', function () {
    $html = '<html><body><img src="logo.png" onerror="alert(1)"><div onmouseover="hack()">Hi</div></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->not->toContain('onerror="')
        ->and($result)->not->toContain('onmouseover="')
        ->and($result)->toContain('src="logo.png"')
        ->and($result)->toContain('Hi');
});

test('removes javascript: URLs', function () {
    $html = '<html><body><a href="javascript:alert(1)">Click</a><a href="https://example.com">Safe</a></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->not->toContain('javascript:')
        ->and($result)->toContain('https://example.com');
});

test('removes meta refresh tags but keeps other meta tags', function () {
    $html = '<html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=https://evil.test"></head><body>OK</body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->not->toContain('http-equiv="refresh"')
        ->and($result)->toContain('charset="utf-8"')
        ->and($result)->toContain('OK');
});

test('removes applet, object, and embed tags', function () {
    $html = '<html><body><p>Content</p><applet code="x.class"></applet><object data="x.swf"></object><embed src="x.swf"></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->not->toContain('<applet')
        ->and($result)->not->toContain('<object')
        ->and($result)->not->toContain('<embed')
        ->and($result)->toContain('<p>Content</p>');
});

test('preserves normal email HTML with inline styles', function () {
    $html = '<html><body><table><tr><td style="color:red;font-size:14px"><strong>Hello</strong></td></tr></table></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->toContain('style="color:red;font-size:14px"')
        ->and($result)->toContain('<strong>Hello</strong>');
});

test('preserves images with external sources', function () {
    $html = '<html><body><img src="https://example.com/logo.png" alt="Logo" width="200"></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->toContain('src="https://example.com/logo.png"')
        ->and($result)->toContain('alt="Logo"');
});

test('injects resize script into body', function () {
    $html = '<html><body><p>Hello</p></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->toContain('<script>')
        ->and($result)->toContain('gw-iframe-resize')
        ->and($result)->toContain('postMessage');
});

test('adds base target=_blank for links', function () {
    $html = '<html><head></head><body><a href="https://example.com">Link</a></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->toContain('<base target="_blank">');
});

test('replaces existing base tags', function () {
    $html = '<html><head><base href="https://evil.test" target="_self"></head><body>OK</body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->toContain('<base target="_blank">')
        ->and($result)->not->toContain('https://evil.test')
        ->and($result)->not->toContain('_self');
});

test('handles HTML fragments without html/body wrapper', function () {
    $html = '<p>Just a paragraph</p>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    expect($result)->toContain('<p>Just a paragraph</p>')
        ->and($result)->toContain('gw-iframe-resize');
});

test('handles UTF-8 content correctly', function () {
    $html = '<html><body><p>Ünïcödé — Ëmäïl „Hallo Welt"</p></body></html>';

    $result = MailHtmlSanitizer::sanitizeForPreview($html);

    $decoded = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($decoded)->toContain('Ünïcödé')
        ->and($decoded)->toContain('—')
        ->and($decoded)->toContain('„Hallo Welt"');
});
