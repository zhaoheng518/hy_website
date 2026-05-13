<?php

namespace App\Core;

/**
 * RichTextSanitizer
 *
 * Allowlist-based HTML sanitizer for Quill rich-text content.
 * Works with PHP 7.0+ and requires only ext-dom (standard in all PHP 7/8 builds).
 * No Composer, no external dependencies.
 *
 * Allowed tags and their permitted attributes:
 *   p, br, strong, em, ul, ol, li, h2, h3, h4
 *   table, thead, tbody, tr, td, th, caption, colgroup, col
 *   img  → src, alt, title
 *   a    → href, title, target  (target restricted to safe values; href strips javascript:/vbscript:/data:)
 *
 * Stripped unconditionally:
 *   - Tags: script, noscript, iframe, frame, frameset, embed, object, applet,
 *           svg, math, style, link, meta, base, form, input, button, select,
 *           textarea, option
 *   - Attributes: all on* event handlers, style, class
 *   - Protocols in href/src: javascript:, vbscript:, data: (inc. whitespace-collapsed variants)
 *
 * Unknown tags (not in allowlist and not in REMOVE_TAGS) are "unwrapped":
 * their children are preserved but the wrapper tag is dropped.
 *
 * Usage:
 *   $clean = \App\Core\RichTextSanitizer::sanitize($rawHtml);
 */
class RichTextSanitizer
{
    /**
     * Tags whose entire subtree (content included) must be removed.
     */
    private const REMOVE_TAGS = [
        'script', 'noscript',
        'iframe', 'frame', 'frameset',
        'embed', 'object', 'applet',
        'svg', 'math',
        'style', 'link', 'meta', 'base',
        'form', 'input', 'button', 'select', 'textarea', 'option', 'optgroup',
        'canvas', 'template', 'slot',
    ];

    /**
     * Allowed tags → their permitted attributes.
     * Tags absent from both REMOVE_TAGS and ALLOW are unwrapped (text kept, tag stripped).
     */
    private const ALLOW = [
        'p'        => [],
        'br'       => [],
        'strong'   => [],
        'em'       => [],
        'ul'       => [],
        'ol'       => [],
        'li'       => [],
        'h2'       => [],
        'h3'       => [],
        'h4'       => [],
        'table'    => [],
        'thead'    => [],
        'tbody'    => [],
        'tr'       => [],
        'td'       => [],
        'th'       => [],
        'caption'  => [],
        'colgroup' => [],
        'col'      => [],
        'img'      => ['src', 'alt', 'title'],
        'a'        => ['href', 'title', 'target'],
    ];

    /**
     * Sanitize an HTML fragment and return safe HTML.
     *
     * @param string $html  Raw HTML (e.g. from Quill editor via quill.root.innerHTML).
     * @return string       Sanitized HTML, safe for front-end output.
     */
    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');

        // Suppress libxml parse warnings (handles malformed HTML gracefully).
        $prevErrors = libxml_use_internal_errors(true);

        // Wrap the fragment in a full HTML document so DOMDocument parses it
        // correctly, including charset handling for UTF-8 content.
        $doc->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body><div id="__rts_root__">' . $html . '</div></body></html>'
        );

        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        // Locate our wrapper div.
        $root = self::findRootDiv($doc);
        if ($root === null) {
            return '';
        }

        self::walkNode($root, $doc);

        // Serialize cleaned children back to an HTML string.
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * Find the wrapper div by iterating body's children (more reliable than
     * getElementById in older PHP/libxml combinations).
     */
    private static function findRootDiv(\DOMDocument $doc): ?\DOMElement
    {
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return null;
        }
        foreach ($body->childNodes as $child) {
            if (
                $child->nodeType === XML_ELEMENT_NODE
                && strtolower($child->nodeName) === 'div'
                && $child instanceof \DOMElement
                && $child->getAttribute('id') === '__rts_root__'
            ) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Recursively walk a DOM node tree.
     * - Allowed tags: strip dangerous attributes, recurse.
     * - REMOVE_TAGS: remove node and all its children.
     * - Everything else: unwrap (keep text children, drop the tag shell).
     * - Non-element nodes (comments, PIs, CDATA): remove.
     *
     * We collect actions during iteration (to avoid live-list mutation problems)
     * and apply them after the loop.
     */
    private static function walkNode(\DOMNode $node, \DOMDocument $doc): void
    {
        $toRemove = [];
        $toUnwrap = [];

        foreach ($node->childNodes as $child) {
            // Text nodes are always safe.
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            // Remove all non-element nodes (comments, CDATA, PIs, etc.).
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $toRemove[] = $child;
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::REMOVE_TAGS, true)) {
                // Remove entirely — including all nested content.
                $toRemove[] = $child;
                continue;
            }

            if (!array_key_exists($tag, self::ALLOW)) {
                // Not in allowlist → schedule for unwrapping (children promoted).
                $toUnwrap[] = $child;
                continue;
            }

            // Allowed tag: clean its attributes and recurse into its children.
            self::cleanAttributes($child, $tag);
            self::walkNode($child, $doc);
        }

        // Remove dangerous nodes (whole subtree gone).
        foreach ($toRemove as $n) {
            if ($n->parentNode !== null) {
                $n->parentNode->removeChild($n);
            }
        }

        // Unwrap unknown-but-harmless wrapper tags.
        foreach ($toUnwrap as $n) {
            if ($n->parentNode === null) {
                continue;
            }
            // Clean children inside the to-be-unwrapped node first.
            self::walkNode($n, $doc);
            // Snapshot children (live NodeList changes as we move nodes).
            $children = [];
            foreach ($n->childNodes as $c) {
                $children[] = $c;
            }
            // Promote children to $n's parent, positioned before $n.
            foreach ($children as $c) {
                $n->parentNode->insertBefore($c, $n);
            }
            $n->parentNode->removeChild($n);
        }
    }

    /**
     * Remove all disallowed attributes from an element.
     * Allowed attributes come from ALLOW[$tag]; all others are removed.
     */
    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $allowed      = self::ALLOW[$tag] ?? [];
        $attrsToStrip = [];

        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);

            // Block all event handler attributes (onclick, onerror, onload, …).
            if (strncmp($name, 'on', 2) === 0) {
                $attrsToStrip[] = $attr->nodeName;
                continue;
            }

            // Always strip style and class.
            if ($name === 'style' || $name === 'class') {
                $attrsToStrip[] = $attr->nodeName;
                continue;
            }

            // Strip any attribute not in the explicit allowlist for this tag.
            if (!in_array($name, $allowed, true)) {
                $attrsToStrip[] = $attr->nodeName;
                continue;
            }

            // Validate URL attributes against dangerous protocols.
            if (in_array($name, ['href', 'src'], true)) {
                $val = $attr->nodeValue;
                // Strip null bytes and ASCII control characters.
                $stripped  = preg_replace('/[\x00-\x1F\x7F]/u', '', $val);
                // Collapse all whitespace (browsers ignore it in protocols).
                $collapsed = strtolower(preg_replace('/\s/', '', $stripped));

                if (
                    strpos($collapsed, 'javascript:') !== false
                    || strpos($collapsed, 'vbscript:')   !== false
                    || strpos($collapsed, 'data:')        === 0
                ) {
                    $attrsToStrip[] = $attr->nodeName;
                    continue;
                }
            }

            // For <a target="...">, only allow safe frame-target values.
            if ($name === 'target') {
                $val = trim($attr->nodeValue);
                if (!in_array($val, ['_blank', '_self', '_parent', '_top'], true)) {
                    $attrsToStrip[] = $attr->nodeName;
                }
            }
        }

        foreach ($attrsToStrip as $attrName) {
            $el->removeAttribute($attrName);
        }

        // Harden external links: add rel="noopener noreferrer" to <a target="_blank">.
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }
}
