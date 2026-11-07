<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Domain;
use App\Services\Deliverability\DnsVerificationService;
use App\Services\Email\AmazonSesEmailProvider;
use App\Services\Email\EmailProviderInterface;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DomainController extends Controller
{
    public function index(Request $request, EmailProviderInterface $emailProvider): Response|RedirectResponse
    {
        $account = $this->getActiveAccount($request);
        if (! $account) {
            return redirect('/signin');
        }

        $newsletter = CurrentWorkspace::newsletter($request);
        $region = $emailProvider instanceof AmazonSesEmailProvider
            ? $emailProvider->getRegion()
            : (string) ($account->settings['aws_region'] ?? config('services.ses.region', 'us-east-1'));

        $domains = Domain::where('account_id', $account->id)->get();
        if ($domains->isEmpty()) {
            $fromDomain = 'spiggle-mail.local';
            if ($newsletter && ! empty($newsletter->from_email) && str_contains($newsletter->from_email, '@')) {
                $fromDomain = substr(strrchr($newsletter->from_email, '@'), 1);
            }

            $candidateDomain = $fromDomain;
            if (Domain::where('domain', $candidateDomain)->where('account_id', '!=', $account->id)->exists()) {
                $slug = $newsletter?->slug ? Str::slug($newsletter->slug) : 'newsletter';
                $candidateDomain = $slug.'-'.substr(strtolower($account->id), -6).'.local';
            }

            $isLocalSynthetic = str_ends_with($candidateDomain, '.local');
            $defaultDomain = Domain::firstOrCreate(
                ['domain' => $candidateDomain, 'account_id' => $account->id],
                [
                    'id' => (string) Str::ulid(),
                    'status' => (app()->environment('local', 'testing') && $isLocalSynthetic) ? 'verified' : 'pending',
                    'dkim_tokens' => [
                        'spiggle1'.substr(md5($candidateDomain), 0, 6),
                        'spiggle2'.substr(md5($candidateDomain), 6, 6),
                        'spiggle3'.substr(md5($candidateDomain), 12, 6),
                    ],
                    'mail_from_domain' => 'mail.'.$candidateDomain,
                    'verified_at' => (app()->environment('local', 'testing') && $isLocalSynthetic) ? now() : null,
                ]
            );

            $domains = collect([$defaultDomain]);
        }

        $selectedId = $request->query('selected');
        $activeDomain = ($selectedId ? $domains->firstWhere('id', $selectedId) : null) ?? $domains->first();

        $dkimTokens = array_values(array_filter((array) ($activeDomain->dkim_tokens ?? [])));
        if ($dkimTokens === []) {
            $dkimTokens = ['token1', 'token2', 'token3'];
        }

        $dnsCheck = DnsVerificationService::checkAuthentication(
            $activeDomain->domain,
            $dkimTokens,
            $region
        );

        $firstToken = $dkimTokens[0];
        $dkimCnameRecords = array_map(
            static fn (string $tok): array => [
                'host' => "{$tok}._domainkey.{$activeDomain->domain}",
                'value' => "{$tok}.dkim.amazonses.com",
            ],
            $dkimTokens
        );

        $mailFromSubdomain = $activeDomain->mail_from_domain ?: ('mail.'.$activeDomain->domain);
        $mxTarget = "10 feedback-smtp.{$region}.amazonses.com";

        $domainRecord = [
            'id' => $activeDomain->id,
            'domain' => $activeDomain->domain,
            'status' => $activeDomain->status,
            'mailFromDomain' => $mailFromSubdomain,
            'verifiedAt' => $activeDomain->verified_at?->format('M d, Y'),
            'dnsRecords' => [
                'spf' => [
                    'status' => ($dnsCheck['spf']['valid'] || $activeDomain->status === 'verified') ? 'verified' : 'pending',
                    'record' => $dnsCheck['spf']['record'] ?? 'v=spf1 include:amazonses.com ~all',
                    'expected' => 'v=spf1 include:amazonses.com ~all',
                ],
                'dkim' => [
                    'status' => ($dnsCheck['dkim']['valid'] || $activeDomain->status === 'verified') ? 'verified' : 'pending',
                    'selector' => "{$firstToken}._domainkey.{$activeDomain->domain}",
                    'cname' => "{$firstToken}.dkim.amazonses.com",
                    'records' => $dkimCnameRecords,
                ],
                'dmarc' => [
                    'status' => ($dnsCheck['dmarc']['valid'] || $activeDomain->status === 'verified') ? 'verified' : 'pending',
                    'record' => $dnsCheck['dmarc']['record'] ?? "v=DMARC1; p=quarantine; pct=100; rua=mailto:dmarc@{$activeDomain->domain}",
                    'policy' => $dnsCheck['dmarc']['policy'] ?? 'quarantine',
                ],
                'mailFrom' => [
                    'status' => (($dnsCheck['mail_from']['valid'] ?? false) || $activeDomain->status === 'verified') ? 'verified' : 'pending',
                    'mx' => $mxTarget,
                    'spf' => 'v=spf1 include:amazonses.com ~all',
                ],
            ],
        ];

        return Inertia::render('Domains/Index', [
            'currentNewsletter' => [
                'id' => $newsletter?->id ?? 'nl_01',
                'name' => $newsletter?->name ?? 'Your Publication',
                'slug' => $newsletter?->slug ?? 'publication',
                'fromEmail' => $newsletter?->from_email ?? '',
            ],
            'domain' => $domainRecord,
            'domainsList' => $domains->map(fn (Domain $d) => [
                'id' => $d->id,
                'domain' => $d->domain,
                'status' => $d->status,
                'verifiedAt' => $d->verified_at?->format('M d, Y'),
            ])->values()->all(),
        ]);
    }

    public function store(Request $request, EmailProviderInterface $emailProvider): RedirectResponse
    {
        $account = $this->getActiveAccount($request);
        if (! $account) {
            return back()->withErrors(['domain' => 'No active account found.']);
        }

        $validated = $request->validate([
            'domain' => 'required|string|max:190',
        ]);

        $normalized = strtolower(trim($validated['domain']));
        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = rtrim(explode('/', $normalized)[0], '.');

        if (! filter_var('test@'.$normalized, FILTER_VALIDATE_EMAIL) || str_ends_with($normalized, '.local')) {
            return back()->withErrors(['domain' => 'Please provide a valid public domain or subdomain (e.g., example.com or news.example.com).']);
        }

        if (Domain::where('domain', $normalized)->exists()) {
            return back()->withErrors(['domain' => 'This domain is already registered.']);
        }

        // Register real Easy DKIM + custom MAIL FROM identity in Amazon SES (or mock provider in local/testing)
        try {
            $identity = $emailProvider->getOrCreateDomainIdentity($normalized);
        } catch (Throwable $e) {
            return back()->withErrors(['domain' => 'AWS SES identity registration failed: '.$e->getMessage()]);
        }

        $tokens = ! empty($identity['dkim_tokens'])
            ? $identity['dkim_tokens']
            : [
                substr(md5($normalized.'1'), 0, 16),
                substr(md5($normalized.'2'), 0, 16),
                substr(md5($normalized.'3'), 0, 16),
            ];

        $domain = Domain::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'domain' => $normalized,
            'status' => 'pending',
            'dkim_tokens' => $tokens,
            'mail_from_domain' => $identity['mail_from'] ?? ('mail.'.$normalized),
            'verified_at' => null,
        ]);

        return redirect("/domains?selected={$domain->id}")
            ->with('success', "Domain '{$normalized}' registered with SES. Add all 3 DKIM CNAMEs, SPF, DMARC, and MAIL FROM DNS records below.");
    }

    public function verify(Request $request, string $id, EmailProviderInterface $emailProvider): RedirectResponse
    {
        $account = $this->getActiveAccount($request);
        if (! $account) {
            return back()->with('error', 'No active account found.');
        }

        $domain = Domain::where('account_id', $account->id)->where('id', $id)->first();
        if (! $domain) {
            return back()->with('error', 'Domain not found.');
        }

        $region = $emailProvider instanceof AmazonSesEmailProvider
            ? $emailProvider->getRegion()
            : (string) ($account->settings['aws_region'] ?? config('services.ses.region', 'us-east-1'));

        $sesVerified = true;
        if ($emailProvider instanceof AmazonSesEmailProvider) {
            try {
                $identity = $emailProvider->getOrCreateDomainIdentity($domain->domain);
                if (! empty($identity['dkim_tokens'])) {
                    $domain->dkim_tokens = $identity['dkim_tokens'];
                    $domain->save();
                }
                $sesVerified = ($identity['status'] ?? 'pending') === 'verified';
            } catch (Throwable $e) {
                return back()->with('error', 'AWS SES verification check failed: '.$e->getMessage());
            }
        }

        $dnsCheck = DnsVerificationService::checkAuthentication(
            $domain->domain,
            $domain->dkim_tokens ?? [],
            $region
        );

        $isValid = ($sesVerified && $dnsCheck['spf']['valid'] && $dnsCheck['dkim']['valid'] && $dnsCheck['dmarc']['valid'])
            || (app()->environment('local', 'testing') && ! ($emailProvider instanceof AmazonSesEmailProvider));

        if ($isValid) {
            $domain->update([
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            return back()->with('success', "Domain '{$domain->domain}' successfully verified for sending!");
        }

        $reasons = array_filter([
            ! $sesVerified ? 'AWS SES DKIM status is still pending propagation.' : null,
            ! $dnsCheck['spf']['valid'] ? $dnsCheck['spf']['message'] : null,
            ! $dnsCheck['dkim']['valid'] ? $dnsCheck['dkim']['message'] : null,
            ! $dnsCheck['dmarc']['valid'] ? $dnsCheck['dmarc']['message'] : null,
        ]);

        return back()->with('error', 'DNS verification incomplete: '.implode(' ', $reasons));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $account = $this->getActiveAccount($request);
        if (! $account) {
            return back()->with('error', 'No active account found.');
        }

        $domain = Domain::where('account_id', $account->id)->where('id', $id)->first();
        if (! $domain) {
            return back()->with('error', 'Domain not found.');
        }

        $domainName = $domain->domain;
        $domain->delete();

        return redirect('/domains')->with('success', "Domain '{$domainName}' removed.");
    }

    private function getActiveAccount(Request $request): ?Account
    {
        return CurrentWorkspace::account($request);
    }
}
