<?php

declare(strict_types=1);

namespace App\Services\Deliverability;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class EmailHygieneService
{
    private const TRAP_REGEX = '/spam[._-]?trap|honey[._-]?pot/i';

    private const ROLE_LOCAL_PARTS = [
        'abuse', 'accounting', 'admin', 'administrator', 'billing', 'careers',
        'compliance', 'contact', 'customerservice', 'enquiries', 'feedback',
        'hello', 'help', 'helpdesk', 'hostmaster', 'hr', 'info', 'inquiries',
        'jobs', 'legal', 'mail', 'mailer-daemon', 'marketing', 'media',
        'newsletter', 'no-reply', 'noreply', 'office', 'postmaster', 'press',
        'privacy', 'root', 'sales', 'security', 'spam', 'support', 'team', 'webmaster',
    ];

    private const DISPOSABLE_DOMAINS = [
        '0-mail.com', '10minutemail.co.uk', '10minutemail.com', '10minutemail.net',
        '10minutesmail.com', '20minutemail.com', '33mail.com', 'anonbox.net',
        'anonymbox.com', 'armyspy.com', 'burnermail.io', 'byom.de', 'cuvox.de',
        'dayrep.com', 'deadaddress.com', 'despam.it', 'discard.email', 'discardmail.com',
        'discardmail.de', 'dispostable.com', 'dodgit.com', 'dropmail.me', 'e4ward.com',
        'einrot.com', 'emailondeck.com', 'emailsensei.com', 'emailtemporanea.net',
        'fakeinbox.com', 'fakemail.net', 'fakemailgenerator.com', 'fleckens.hu',
        'getairmail.com', 'getnada.com', 'gishpuppy.com', 'guerrillamail.biz',
        'guerrillamail.com', 'guerrillamail.de', 'guerrillamail.info', 'guerrillamail.net',
        'guerrillamail.org', 'guerrillamailblock.com', 'gustr.com', 'harakirimail.com',
        'incognitomail.org', 'inboxbear.com', 'inboxkitten.com', 'jetable.org',
        'jourrapide.com', 'kasmail.com', 'mail-temp.com', 'mail.tm', 'mailcatch.com',
        'maildrop.cc', 'mailexpire.com', 'mailforspam.com', 'mailinator.com',
        'mailinator.net', 'mailinator2.com', 'mailmoat.com', 'mailnesia.com',
        'mailnull.com', 'mailsac.com', 'mailtemp.info', 'meltmail.com', 'mintemail.com',
        'moakt.com', 'mohmal.com', 'mt2015.com', 'mvrht.com', 'mytemp.email',
        'mytrashmail.com', 'nada.email', 'no-spam.ws', 'nowmymail.com', 'objectmail.com',
        'oneoffemail.com', 'rhyta.com', 'sharklasers.com', 'shieldemail.com',
        'sneakemail.com', 'spam4.me', 'spambog.com', 'spambox.us', 'spamex.com',
        'spamgourmet.com', 'spamherelots.com', 'spaml.de', 'spammotel.com',
        'superrito.com', 'teleworm.us', 'temp-mail.io', 'temp-mail.org', 'tempail.com',
        'tempinbox.com', 'tempmail.com', 'tempmail.net', 'tempmail.plus',
        'tempmailaddress.com', 'tempmailo.com', 'tempr.email', 'throwam.com',
        'throwawaymail.com', 'tmail.ws', 'tmpmail.net', 'tmpmail.org', 'trash-mail.com',
        'trashmail.com', 'trashmail.de', 'trashmail.io', 'trashmail.net', 'trbvm.com',
        'wegwerfmail.de', 'wegwerfmail.net', 'yopmail.com', 'yopmail.fr',
        'yopmail.net', 'zetmail.com',
    ];

    private const TYPO_MAP = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmal.com' => 'gmail.com',
        'gmil.com' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gmaul.com' => 'gmail.com',
        'gnail.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gmali.com' => 'gmail.com',
        'gmeil.com' => 'gmail.com', 'gmsil.com' => 'gmail.com', 'gmaio.com' => 'gmail.com',
        'gmaik.com' => 'gmail.com', 'ggmail.com' => 'gmail.com', 'gmmail.com' => 'gmail.com',
        'gmaiil.com' => 'gmail.com', 'gmaail.com' => 'gmail.com', 'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com', 'gmail.om' => 'gmail.com',
        'googlemai.com' => 'googlemail.com', 'googlemial.com' => 'googlemail.com', 'googlemail.co' => 'googlemail.com',
        'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com', 'yahho.com' => 'yahoo.com',
        'yhoo.com' => 'yahoo.com', 'yhaoo.com' => 'yahoo.com', 'yaoo.com' => 'yahoo.com',
        'yahool.com' => 'yahoo.com', 'uahoo.com' => 'yahoo.com', 'yahoo.co' => 'yahoo.com',
        'yahoo.cm' => 'yahoo.com', 'yahoo.om' => 'yahoo.com',
        'hotmial.com' => 'hotmail.com', 'hotmal.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
        'hotmil.com' => 'hotmail.com', 'hotnail.com' => 'hotmail.com', 'hotmali.com' => 'hotmail.com',
        'homail.com' => 'hotmail.com', 'hotamil.com' => 'hotmail.com', 'hotmaill.com' => 'hotmail.com',
        'htomail.com' => 'hotmail.com', 'hormail.com' => 'hotmail.com', 'hoymail.com' => 'hotmail.com',
        'hotmsil.com' => 'hotmail.com', 'hotmail.co' => 'hotmail.com', 'hotmail.cm' => 'hotmail.com',
        'hotmail.om' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outllok.com' => 'outlook.com', 'outlool.com' => 'outlook.com',
        'oulook.com' => 'outlook.com', 'otlook.com' => 'outlook.com', 'outook.com' => 'outlook.com',
        'putlook.com' => 'outlook.com', 'outlook.co' => 'outlook.com', 'outlook.cm' => 'outlook.com',
        'iclod.com' => 'icloud.com', 'icoud.com' => 'icloud.com', 'icluod.com' => 'icloud.com',
        'iclould.com' => 'icloud.com', 'icload.com' => 'icloud.com', 'iclound.com' => 'icloud.com',
        'icloud.co' => 'icloud.com', 'icloud.cm' => 'icloud.com',
    ];

    private const ENDINGS_MAP = [
        'con' => 'com', 'cmo' => 'com', 'ocm' => 'com', 'vom' => 'com', 'xom' => 'com',
        'cpm' => 'com', 'comm' => 'com', 'coom' => 'com', 'ner' => 'net', 'nte' => 'net',
        'nett' => 'net', 'ogr' => 'org', 'rog' => 'org', 'orgg' => 'org',
    ];

    /**
     * Checks if address is valid and returns drop reason (trap, typo, role, disposable, invalid) or null if clean.
     */
    public static function checkEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid';
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return 'invalid';
        }

        [$localPart, $domain] = $parts;

        if (preg_match(self::TRAP_REGEX, $localPart)) {
            return 'trap';
        }

        if (self::getSuggestion($email) !== null) {
            return 'typo';
        }

        $baseLocal = explode('+', $localPart)[0];
        if (in_array($baseLocal, self::ROLE_LOCAL_PARTS, true)) {
            return 'role';
        }

        if (self::isDisposable($domain)) {
            return 'disposable';
        }

        if (config('spiggle.verify_email_mx', false) && ! self::hasValidMailRecords($domain)) {
            return 'no_mx';
        }

        return null;
    }

    public static function hasValidMailRecords(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || str_ends_with($domain, '.local') || str_ends_with($domain, '.test')) {
            return false;
        }

        return (bool) Cache::remember("spiggle_mx_valid:{$domain}", 3600, static function () use ($domain): bool {
            return @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA');
        });
    }

    public static function isDisposable(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        $labels = explode('.', $domain);
        $count = count($labels);

        // 1. Fast memory check against bundled seeds
        for ($i = 0; $i < $count; $i++) {
            $sub = implode('.', array_slice($labels, $i));
            if (in_array($sub, self::DISPOSABLE_DOMAINS, true)) {
                return true;
            }
        }

        // 2. Continuous feed check from MariaDB (cached in Redis / memory)
        $cachedSet = Cache::remember('disposable_domains_set', 86400, function () {
            return DB::table('disposable_domains')->pluck('domain')->flip()->toArray();
        });

        for ($i = 0; $i < $count; $i++) {
            $sub = implode('.', array_slice($labels, $i));
            if (isset($cachedSet[$sub])) {
                return true;
            }
        }

        return false;
    }

    public static function getSuggestion(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }

        $local = substr($email, 0, $at);
        $domain = strtolower(substr($email, $at + 1));

        $fixed = self::fixEnding($domain);
        $meant = self::TYPO_MAP[$fixed] ?? $fixed;

        if ($meant !== $domain) {
            return "{$local}@{$meant}";
        }

        return null;
    }

    private static function fixEnding(string $domain): string
    {
        $dot = strrpos($domain, '.');
        if ($dot === false) {
            return $domain;
        }

        $ending = substr($domain, $dot + 1);
        if (isset(self::ENDINGS_MAP[$ending])) {
            return substr($domain, 0, $dot).'.'.self::ENDINGS_MAP[$ending];
        }

        return $domain;
    }
}
