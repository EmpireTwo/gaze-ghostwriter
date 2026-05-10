<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Sanitizes email HTML for safe rendering inside a sandboxed iframe.
 *
 * Defense-in-depth on top of `sandbox="allow-scripts"` (without
 * `allow-same-origin`): strips scripts, event handlers, and `javascript:`
 * URLs server-side before the HTML reaches the browser.
 */
final class MailHtmlSanitizer
{
    /** @var list<string> */
    private const STRIP_TAGS = [
        'script',
        'applet',
        'object',
        'embed',
    ];

    private const RESIZE_SCRIPT = <<<'JS'
(function(){
    document.documentElement.style.overflow='hidden';
    document.body.style.overflow='hidden';
    function p(){parent.postMessage({type:'gw-iframe-resize',h:document.documentElement.scrollHeight},'*')}
    p();
    new ResizeObserver(p).observe(document.documentElement);
    addEventListener('load',p);
    document.querySelectorAll('img').forEach(function(i){i.addEventListener('load',p);i.addEventListener('error',p)});
})();
JS;

    public static function sanitizeForPreview(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        @$dom->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOERROR,
        );

        foreach ($dom->childNodes as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $dom->removeChild($node);

                break;
            }
        }

        self::removeDangerousElements($dom);
        self::sanitizeAttributes($dom);
        self::ensureBaseTarget($dom);
        self::injectResizeScript($dom);

        return trim((string) $dom->saveHTML());
    }

    private static function removeDangerousElements(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        foreach (self::STRIP_TAGS as $tag) {
            $nodes = $xpath->query('//'.$tag);
            if ($nodes === false) {
                continue;
            }
            $batch = [];
            foreach ($nodes as $node) {
                $batch[] = $node;
            }
            foreach ($batch as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $metas = $xpath->query('//meta[@http-equiv]');
        if ($metas !== false) {
            $batch = [];
            foreach ($metas as $meta) {
                if ($meta instanceof DOMElement && strcasecmp($meta->getAttribute('http-equiv'), 'refresh') === 0) {
                    $batch[] = $meta;
                }
            }
            foreach ($batch as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private static function sanitizeAttributes(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $elements = $xpath->query('//*');
        if ($elements === false) {
            return;
        }

        foreach ($elements as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $remove = [];
            foreach ($element->attributes ?? [] as $attr) {
                if (str_starts_with(strtolower($attr->name), 'on')) {
                    $remove[] = $attr->name;

                    continue;
                }

                if (preg_match('/^\s*javascript\s*:/i', $attr->value) === 1) {
                    $remove[] = $attr->name;
                }
            }

            foreach ($remove as $name) {
                $element->removeAttribute($name);
            }
        }
    }

    private static function ensureBaseTarget(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        $existing = $xpath->query('//base');
        if ($existing !== false) {
            $batch = [];
            foreach ($existing as $node) {
                $batch[] = $node;
            }
            foreach ($batch as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head === null) {
            $html = $dom->getElementsByTagName('html')->item(0);
            if ($html !== null) {
                $head = $dom->createElement('head');
                $html->insertBefore($head, $html->firstChild);
            }
        }

        if ($head !== null) {
            $base = $dom->createElement('base');
            $base->setAttribute('target', '_blank');
            $head->insertBefore($base, $head->firstChild);
        }
    }

    private static function injectResizeScript(DOMDocument $dom): void
    {
        $script = $dom->createElement('script');
        $script->textContent = self::RESIZE_SCRIPT;

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body !== null) {
            $body->appendChild($script);

            return;
        }

        $html = $dom->getElementsByTagName('html')->item(0);
        ($html ?? $dom)->appendChild($script);
    }
}
