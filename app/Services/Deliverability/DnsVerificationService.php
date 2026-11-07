<?php

declare(strict_types=1);

namespace App\Services\Deliverability;

final class DnsVerificationService
{
    /**
     * Complete check for SPF, DKIM, DMARC, and Custom MAIL FROM DNS records.
     *
     * @param  list<string>  $dkimTokens
     * @return array{
     *     spf: array{valid: bool, record: ?string, message: string},
     *     dkim: array{valid: bool, record: ?string, message: string},
     *     dmarc: array{valid: bool, record: ?string, policy: ?string, message: string},
     *     mail_from: array{valid: bool, mx: ?string, spf: ?string, message: string}
     * }
     */
    public static function checkAuthentication(string $domain, array $dkimTokens = [], string $region = 'us-east-1'): array
    {
        $domain = strtolower(trim($domain));

        return [
            'spf' => self::checkSpf($domain),
            'dkim' => self::checkDkim($domain, $dkimTokens),
            'dmarc' => self::checkDmarc($domain),
            'mail_from' => self::checkMailFrom('mail.'.$domain, $region),
        ];
    }

    public static function checkSpf(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_TXT) ?: [];
        $spfRecord = null;

        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';
            if (str_starts_with(strtolower(trim($txt)), 'v=spf1')) {
                $spfRecord = $txt;
                break;
            }
        }

        if ($spfRecord === null) {
            return [
                'valid' => false,
                'record' => null,
                'message' => 'No SPF record found. Add TXT record: v=spf1 include:amazonses.com ~all',
            ];
        }

        $hasSes = str_contains(strtolower($spfRecord), 'include:amazonses.com');

        return [
            'valid' => $hasSes,
            'record' => $spfRecord,
            'message' => $hasSes ? 'SPF record valid with Amazon SES.' : 'SPF found, but missing include:amazonses.com.',
        ];
    }

    public static function checkDkim(string $domain, array $tokens = []): array
    {
        if (empty($tokens)) {
            return [
                'valid' => false,
                'record' => null,
                'message' => 'No DKIM tokens configured for this domain yet.',
            ];
        }

        $validCount = 0;
        foreach ($tokens as $token) {
            $cleanToken = strtolower(trim((string) $token));
            if ($cleanToken === '') {
                continue;
            }
            $cnameHost = "{$cleanToken}._domainkey.{$domain}";
            $expectedTarget = "{$cleanToken}.dkim.amazonses.com";
            $cnames = @dns_get_record($cnameHost, DNS_CNAME) ?: [];
            foreach ($cnames as $cname) {
                $target = rtrim(strtolower((string) ($cname['target'] ?? '')), '.');
                if ($target === $expectedTarget) {
                    $validCount++;
                    break;
                }
            }
        }

        $allValid = $validCount === count($tokens);

        return [
            'valid' => $allValid,
            'record' => "{$validCount}/".count($tokens).' CNAME records verified',
            'message' => $allValid
                ? 'All 3 AWS SES DKIM CNAME records verified.'
                : "Verified {$validCount} of ".count($tokens).' required .dkim.amazonses.com CNAME records.',
        ];
    }

    public static function checkDmarc(string $domain): array
    {
        $dmarcHost = "_dmarc.{$domain}";
        $records = @dns_get_record($dmarcHost, DNS_TXT) ?: [];
        $dmarcRecord = null;

        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';
            if (str_starts_with(strtolower(trim($txt)), 'v=dmarc1')) {
                $dmarcRecord = $txt;
                break;
            }
        }

        if ($dmarcRecord === null) {
            return [
                'valid' => false,
                'record' => null,
                'policy' => null,
                'message' => 'No DMARC record found at _dmarc.'.$domain.'. Gmail and Yahoo require DMARC.',
            ];
        }

        preg_match('/p=([a-z]+)/i', $dmarcRecord, $matches);
        $policy = strtolower($matches[1] ?? 'none');

        return [
            'valid' => true,
            'record' => $dmarcRecord,
            'policy' => $policy,
            'message' => "DMARC verified with policy p={$policy}.",
        ];
    }

    public static function checkMailFrom(string $mailFromDomain, string $region = 'us-east-1'): array
    {
        $mxRecords = @dns_get_record($mailFromDomain, DNS_MX) ?: [];
        $expectedMxHost = strtolower("feedback-smtp.{$region}.amazonses.com");
        $matchedMx = null;

        foreach ($mxRecords as $mx) {
            $target = rtrim(strtolower((string) ($mx['target'] ?? '')), '.');
            if ($target === $expectedMxHost || str_ends_with($target, '.amazonses.com')) {
                $matchedMx = $target;
                break;
            }
        }

        $spfCheck = self::checkSpf($mailFromDomain);
        $valid = ($matchedMx !== null) && $spfCheck['valid'];

        return [
            'valid' => $valid,
            'mx' => $matchedMx,
            'spf' => $spfCheck['record'],
            'message' => $valid
                ? 'Custom MAIL FROM MX and SPF records verified.'
                : "Add MX (10 {$expectedMxHost}) and TXT (v=spf1 include:amazonses.com ~all) on {$mailFromDomain}.",
        ];
    }
}
