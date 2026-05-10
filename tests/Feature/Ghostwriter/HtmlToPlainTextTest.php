<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Support\HtmlToPlainText;

it('strips style tags and their content', function (): void {
    $html = '<html><head><style>body { color: red; } @font-face { font-family: Colfax; }</style></head><body><p>Hello World</p></body></html>';

    expect(HtmlToPlainText::convert($html))->toBe('Hello World');
});

it('strips multiple style blocks including media queries', function (): void {
    $html = <<<'HTML'
    <style type="text/css">
        @media screen {
            @font-face { font-family: Colfax; src: url(https://example.com/font.woff2); }
        }
    </style>
    <style>
        /** Avoid browser level font resizing */
        * { -ms-text-size-adjust: 100%; }
    </style>
    <p>Your OpenAI API account has been funded</p>
    HTML;

    $result = HtmlToPlainText::convert($html);

    expect($result)
        ->toContain('Your OpenAI API account has been funded')
        ->not->toContain('@media')
        ->not->toContain('@font-face')
        ->not->toContain('Colfax')
        ->not->toContain('text-size-adjust');
});

it('strips script tags and their content', function (): void {
    $html = '<p>Text</p><script>alert("xss")</script><p>More</p>';

    expect(HtmlToPlainText::convert($html))
        ->toContain('Text')
        ->toContain('More')
        ->not->toContain('alert');
});

it('strips HTML comments', function (): void {
    $html = '<p>Visible</p><!-- hidden comment --><p>Also visible</p>';

    expect(HtmlToPlainText::convert($html))
        ->toContain('Visible')
        ->toContain('Also visible')
        ->not->toContain('hidden comment');
});

it('converts br tags to newlines', function (): void {
    $html = 'Line 1<br>Line 2<br/>Line 3<br />Line 4';

    expect(HtmlToPlainText::convert($html))->toBe("Line 1\nLine 2\nLine 3\nLine 4");
});

it('converts block-level closing tags to newlines', function (): void {
    $html = '<p>Paragraph 1</p><p>Paragraph 2</p><div>Div content</div>';

    $result = HtmlToPlainText::convert($html);

    expect($result)
        ->toContain('Paragraph 1')
        ->toContain('Paragraph 2')
        ->toContain('Div content');
});

it('decodes HTML entities', function (): void {
    $html = '<p>Price: 10&euro; &amp; 20&dollar;</p>';

    expect(HtmlToPlainText::convert($html))->toContain('Price: 10€ & 20$');
});

it('collapses excessive whitespace', function (): void {
    $html = "<p>Word1</p>\n\n\n\n\n<p>Word2</p>";

    $result = HtmlToPlainText::convert($html);

    expect($result)->not->toMatch('/\n{3,}/');
});

it('collapses lines containing only spaces or non-breaking spaces', function (): void {
    $html = '<table>'
        .'<tr><td>&nbsp;</td></tr>'
        .'<tr><td>Top</td></tr>'
        .'<tr><td>&nbsp;</td></tr>'
        .'<tr><td>&nbsp;</td></tr>'
        .'<tr><td>&nbsp;</td></tr>'
        .'<tr><td>Bottom</td></tr>'
        .'</table>';

    $result = HtmlToPlainText::convert($html);

    expect($result)
        ->toContain('Top')
        ->toContain('Bottom')
        ->not->toMatch('/\n{3,}/');
});

it('handles table-heavy layout emails without excessive blank lines', function (): void {
    $html = <<<'HTML'
    <table>
        <tr><td>&nbsp;</td></tr>
        <tr><td>&nbsp;</td></tr>
        <tr><td>Content here</td></tr>
        <tr><td>&nbsp;</td></tr>
        <tr><td>&nbsp;</td></tr>
        <tr><td>More content</td></tr>
        <tr><td>&nbsp;</td></tr>
    </table>
    HTML;

    $result = HtmlToPlainText::convert($html);

    expect($result)
        ->toContain('Content here')
        ->toContain('More content')
        ->not->toMatch('/\n{3,}/');
});

it('returns empty string for empty input', function (): void {
    expect(HtmlToPlainText::convert(''))->toBe('');
    expect(HtmlToPlainText::convert('   '))->toBe('');
});

it('handles a realistic OpenAI transactional email', function (): void {
    $html = <<<'HTML'
    <html>
    <head>
        <style type="text/css">
            /** Google webfonts */
            @media screen {
                @font-face { font-family: Colfax; src: url(https://openai-public.s3-us-west-2.amazonaws.com/beta/fonts/ColfaxAIRegular.woff2); font-weight: normal; }
                @font-face { font-family: Colfax; src: url(https://openai-public.s3-us-west-2.amazonaws.com/beta/fonts/ColfaxAIMedium.woff2); font-weight: bold; }
            }
            /** Avoid browser level font resizing */
            * { -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        </style>
    </head>
    <body>
        <table><tr><td>
            <p>Your OpenAI API account has been funded</p>
            <p>You added $50.00 to your account.</p>
            <p>If you have any questions, contact us at support@openai.com</p>
        </td></tr></table>
    </body>
    </html>
    HTML;

    $result = HtmlToPlainText::convert($html);

    expect($result)
        ->toContain('Your OpenAI API account has been funded')
        ->toContain('You added $50.00 to your account.')
        ->toContain('support@openai.com')
        ->not->toContain('@media')
        ->not->toContain('@font-face')
        ->not->toContain('Colfax')
        ->not->toContain('text-size-adjust')
        ->not->toContain('amazonaws');
});
