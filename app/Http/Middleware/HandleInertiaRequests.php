<?php

namespace App\Http\Middleware;

use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = null;
        try {
            $user = CurrentWorkspace::user($request);
        } catch (\Throwable) {
            $user = null;
        }

        $newsletters = [];
        $currentNewsletter = null;
        $getStarted = null;
        $accountAlert = null;

        if ($user && Schema::hasTable('newsletters')) {
            try {
                $account = $user->account()->first();
                if ($account) {
                    if (in_array($account->status, ['paused', 'blocked'], true)) {
                        $accountAlert = [
                            'type' => $account->status === 'blocked' ? 'danger' : 'warning',
                            'title' => $account->status === 'blocked' ? 'Account Sending Blocked' : 'Automated Circuit Breaker Active',
                            'message' => $account->pause_reason ?? ($account->status === 'blocked'
                                ? 'Your account dispatch privileges have been suspended. Review deliverability health.'
                                : 'Outbound campaign dispatching has been automatically paused by system health monitoring.'),
                            'pausedBy' => $account->paused_by,
                            'pausedAt' => $account->paused_at?->toIso8601String(),
                            'actionUrl' => '/deliverability',
                            'actionLabel' => 'Inspect Deliverability & Health',
                        ];
                    } elseif (Schema::hasTable('campaigns')) {
                        $pausedCampaign = \App\Models\Campaign::whereHas('newsletter', fn ($q) => $q->where('account_id', $account->id))
                            ->where('status', 'paused')
                            ->latest('updated_at')
                            ->first();

                        if ($pausedCampaign) {
                            $accountAlert = [
                                'type' => 'warning',
                                'title' => 'Campaign Dispatching Paused',
                                'message' => "Campaign \"{$pausedCampaign->subject}\" is paused: " . ($pausedCampaign->pause_reason ?? 'Awaiting operator review.'),
                                'pausedBy' => $pausedCampaign->pause_code ?? 'operator',
                                'pausedAt' => $pausedCampaign->updated_at?->toIso8601String(),
                                'actionUrl' => "/campaigns/{$pausedCampaign->id}",
                                'actionLabel' => 'Review Campaign Report',
                            ];
                        }
                    }

                    $newsletters = \App\Models\Newsletter::where('account_id', $account->id)->get()->map(fn ($n) => [
                        'id' => $n->id,
                        'name' => $n->name,
                        'slug' => $n->slug,
                        'from_name' => $n->from_name,
                        'from_email' => $n->from_email,
                        'welcome_enabled' => (bool) $n->welcome_enabled,
                        'double_opt_in' => (bool) $n->double_opt_in,
                    ])->values()->all();

                    $selectedId = $request->hasSession() ? $request->session()->get('selected_newsletter_id') : null;
                    $currentNewsletter = collect($newsletters)->firstWhere('id', $selectedId) ?? ($newsletters[0] ?? null);

                    if (Schema::hasTable('campaigns')) {
                        $hasCampaign = \App\Models\Campaign::whereHas('newsletter', fn ($q) => $q->where('account_id', $account->id))->exists();
                        $hasSentCampaign = \App\Models\Campaign::whereHas('newsletter', fn ($q) => $q->where('account_id', $account->id))->whereIn('status', ['scheduled', 'sending', 'sent', 'paused'])->exists();
                        $hasContacts = \App\Models\Contact::whereHas('newsletter', fn ($q) => $q->where('account_id', $account->id))->exists();
                        $hasDomain = \App\Models\Domain::where('account_id', $account->id)->where('status', 'verified')->exists();
                        $hasApiKey = \App\Models\ApiKey::where('account_id', $account->id)->exists();

                        $steps = [
                            [
                                'id' => 'write',
                                'title' => 'Write a draft',
                                'description' => 'Write your first issue or announcement in the composer.',
                                'href' => '/campaigns/new',
                                'buttonText' => 'New draft',
                                'isDone' => $hasCampaign,
                                'isOptional' => false,
                            ],
                            [
                                'id' => 'subscribers',
                                'title' => 'Add subscribers',
                                'description' => 'Import your audience via CSV or add a test subscriber.',
                                'href' => '/contacts',
                                'buttonText' => 'Add contacts',
                                'isDone' => $hasContacts,
                                'isOptional' => false,
                            ],
                            [
                                'id' => 'domain',
                                'title' => 'Verify sending domain',
                                'description' => 'Configure SPF and DKIM on your domain for optimal deliverability.',
                                'href' => '/domains',
                                'buttonText' => 'Check domains',
                                'isDone' => $hasDomain,
                                'isOptional' => true,
                            ],
                            [
                                'id' => 'send',
                                'title' => 'Send your first campaign',
                                'description' => 'Dispatch or schedule your newsletter to your audience.',
                                'href' => '/campaigns',
                                'buttonText' => 'View campaigns',
                                'isDone' => $hasSentCampaign,
                                'isOptional' => false,
                            ],
                            [
                                'id' => 'agent',
                                'title' => 'Connect an AI agent or API key',
                                'description' => 'Generate an API key to manage campaigns via MCP or REST API.',
                                'href' => '/api-keys',
                                'buttonText' => 'Generate key',
                                'isDone' => $hasApiKey,
                                'isOptional' => false,
                            ],
                        ];

                        $doneCount = collect($steps)->where('isDone', true)->count();
                        $isFinished = collect($steps)->every(fn ($s) => $s['isDone'] || $s['isOptional']);

                        $getStarted = [
                            'steps' => $steps,
                            'completedCount' => $doneCount,
                            'totalCount' => count($steps),
                            'isFinished' => $isFinished,
                        ];
                    }
                }
            } catch (\Throwable) {
                $newsletters = [];
                $currentNewsletter = null;
                $getStarted = null;
                $accountAlert = null;
            }
        }

        return [
            ...parent::share($request),
            'edition' => config('spiggle.edition', 'cloud'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isSuperAdmin' => $user->isSuperAdmin(),
                ] : null,
            ],
            'newsletters' => $newsletters,
            'currentNewsletter' => $currentNewsletter,
            'getStarted' => $getStarted,
            'accountAlert' => $accountAlert,
            'flash' => [
                'success' => fn () => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => fn () => $request->hasSession() ? $request->session()->get('error') : null,
            ],
        ];
    }
}
