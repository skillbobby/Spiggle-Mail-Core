<?php

declare(strict_types=1);

namespace App\Support;

final class HtmlSanitizer
{
    /**
     * Sanitizes campaign HTML before rendering in the public web archive to prevent Stored XSS.
     */
    public static function sanitizeForArchive(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '<p>No content.</p>';
        }

        $clean = $html;

        // Strip dangerous executable/embedded containers and their inner contents
        $clean = preg_replace('#<(script|iframe|object|embed|applet|meta|base|form)\b[^>]*>.*?</\1>#is', '', $clean) ?? $clean;
        $clean = preg_replace('#<(script|iframe|object|embed|applet|meta|base|form|input|button)\b[^>]*/?>#is', '', $clean) ?? $clean;

        // Strip inline event handlers (onclick=, onerror=, onload=, etc.)
        $clean = preg_replace('/\s+on[a-z0-9_\-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;

        // Neutralize javascript:, vbscript:, and executable data: URIs in href/src/action/xlink:href
        $clean = preg_replace_callback(
            '/\b(href|src|action|xlink:href)\s*=\s*(["\'])(.*?)\2/is',
            static function (array $matches): string {
                $attr = $matches[1];
                $quote = $matches[2];
                $val = trim(html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $normalized = preg_replace('/[\x00-\x20]+/', '', strtolower($val)) ?? '';

                if (
                    str_starts_with($normalized, 'javascript:')
                    || str_starts_with($normalized, 'vbscript:')
                    || (str_starts_with($normalized, 'data:') && ! str_starts_with($normalized, 'data:image/'))
                ) {
                    return "{$attr}={$quote}#{$quote}";
                }

                return $matches[0];
            },
            $clean
        ) ?? $clean;

        return $clean;
    }

    /**
     * Generates a clean RFC-friendly text/plain body from HTML so SES emails
     * always contain multipart/alternative (preventing MIME_HTML_ONLY spam flags).
     */
    public static function toPlainText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Strip head/style/script blocks completely
        $text = preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        // Preserve anchor links in readable form: "Link Text (https://...)"
        $text = preg_replace_callback(
            '#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $url = trim($m[1]);
                $label = trim(strip_tags($m[2]));
                if ($url === '' || str_starts_with($url, '#')) {
                    return $label;
                }
                if ($label === '' || $label === $url) {
                    return $url;
                }

                return "{$label} ({$url})";
            },
            $text
        ) ?? $text;

        // Replace block/break tags with newlines
        $text = preg_replace('#<(br|hr)\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|li|tr|blockquote|section|article)>#i', "\n\n", $text) ?? $text;

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
