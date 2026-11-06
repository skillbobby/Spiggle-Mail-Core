<?php

declare(strict_types=1);

namespace App\Services\Campaign;

final class ShortcodeParserService
{
    /**
     * Replaces shortcodes in email HTML and Subject lines.
     * Supported tags:
     * - {{ contact.name }}
     * - {{ contact.email }}
     * - {{ newsletter.name }}
     * - {{ unsubscribe_url }}
     * - {{ current_year }}
     */
    public static function parse(
        string $content,
        mixed $contact = null,
        mixed $newsletter = null,
        string $unsubscribeUrl = '#'
    ): string {
        $contactName = is_array($contact) ? ($contact['name'] ?? 'Reader') : ($contact?->name ?? 'Reader');
        $contactEmail = is_array($contact) ? ($contact['email'] ?? 'subscriber@example.com') : ($contact?->email ?? 'subscriber@example.com');
        $newsletterName = is_array($newsletter) ? ($newsletter['name'] ?? 'Our Newsletter') : ($newsletter?->name ?? 'Our Newsletter');
        $currentYear = (string) date('Y');

        $replacements = [
            '{{ contact.name }}'       => htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'),
            '{{ contact.email }}'      => htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'),
            '{{ newsletter.name }}'    => htmlspecialchars($newsletterName, ENT_QUOTES, 'UTF-8'),
            '{{ unsubscribe_url }}'    => htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8'),
            '{{ current_year }}'       => $currentYear,
            // Fallback shortcodes
            '{{first_name}}'           => htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'),
            '{{email}}'                => htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'),
            '{{unsubscribe}}'          => htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * List of available shortcodes for the UI insertion dropdown.
     *
     * @return list<array{tag: string, label: string, example: string}>
     */
    public static function availableShortcodes(): array
    {
        return [
            ['tag' => '{{ contact.name }}', 'label' => 'Recipient Name', 'example' => 'Alex'],
            ['tag' => '{{ contact.email }}', 'label' => 'Recipient Email', 'example' => 'alex@example.com'],
            ['tag' => '{{ newsletter.name }}', 'label' => 'Publication Name', 'example' => 'The Engineering Dispatch'],
            ['tag' => '{{ unsubscribe_url }}', 'label' => 'Direct Unsubscribe Link', 'example' => 'https://.../unsubscribe'],
            ['tag' => '{{ current_year }}', 'label' => 'Current Year', 'example' => date('Y')],
        ];
    }
}
