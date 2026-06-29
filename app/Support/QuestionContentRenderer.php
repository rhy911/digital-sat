<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

class QuestionContentRenderer
{
    private const BLOCKED_ELEMENTS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'button',
        'select',
        'textarea',
        'link',
        'meta',
    ];

    private const ALLOWED_ELEMENTS = [
        'a',
        'blockquote',
        'br',
        'caption',
        'code',
        'col',
        'colgroup',
        'del',
        'div',
        'em',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'img',
        'li',
        'ol',
        'p',
        'pre',
        's',
        'span',
        'strong',
        'sub',
        'sup',
        'table',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'tr',
        'u',
        'ul',
    ];

    private const GLOBAL_ATTRIBUTES = [
        'aria-hidden',
        'aria-label',
        'class',
        'title',
    ];

    private const ATTRIBUTES_BY_ELEMENT = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'table' => ['summary'],
        'th' => ['abbr', 'align', 'colspan', 'headers', 'rowspan', 'scope'],
        'td' => ['align', 'colspan', 'headers', 'rowspan'],
        'col' => ['span'],
        'colgroup' => ['span'],
    ];

    private const URL_ATTRIBUTES = ['href', 'src'];

    public static function markdown(?string $content): string
    {
        $normalized = QuestionMediaUrl::normalizeMarkdown(
            str_replace(['\(', '\)'], ['$$', '$$'], $content ?? '')
        );

        $html = Str::markdown($normalized, [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        return self::sanitize($html);
    }

    private static function sanitize(string $html): string
    {
        if (! class_exists(DOMDocument::class)) {
            return Str::markdown(strip_tags($html), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div data-question-content-root="1">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $root = $document->documentElement;
        if (! $root) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return '';
        }

        self::sanitizeChildren($root);

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $clean;
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tagName = strtolower($child->tagName);

            if (in_array($tagName, self::BLOCKED_ELEMENTS, true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if (! in_array($tagName, self::ALLOWED_ELEMENTS, true)) {
                self::sanitizeChildren($child);
                self::unwrapElement($child);
                continue;
            }

            self::sanitizeAttributes($child);
            self::sanitizeChildren($child);
        }
    }

    private static function unwrapElement(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function sanitizeAttributes(DOMElement $element): void
    {
        $tagName = strtolower($element->tagName);
        $allowed = array_merge(
            self::GLOBAL_ATTRIBUTES,
            self::ATTRIBUTES_BY_ELEMENT[$tagName] ?? []
        );

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (
                str_starts_with($name, 'on')
                || ! in_array($name, $allowed, true)
                || (in_array($name, self::URL_ATTRIBUTES, true) && ! self::isSafeUrl($value))
            ) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tagName === 'a' && $element->hasAttribute('target')) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme === null || in_array(strtolower($scheme), ['http', 'https', 'mailto'], true);
    }
}
