<?php

use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\SnsWebhookController;
use App\Http\Controllers\Api\UploadController;
use App\Jobs\SendConfirmationEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Services\Deliverability\EmailHygieneService;
use App\Services\Deliverability\TrackingTokenService;
use App\Services\Mcp\McpServerService;
use App\Support\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

$authenticateApiKey = function (Request $request, array $allowedRoles = ['full_access', 'send_only', 'read_only']) {
    $bearerToken = $request->bearerToken();
    if (! $bearerToken) {
        return ['error' => response()->json([
            'error' => [
                'code' => 'unauthorized',
                'message' => 'Missing Authorization Bearer token.',
            ],
        ], 401)];
    }

    $keyHash = hash('sha256', $bearerToken);
    $apiKey = ApiKey::where('key_hash', $keyHash)->first();
    if (! $apiKey || ($apiKey->expires_at && $apiKey->expires_at->isPast())) {
        return ['error' => response()->json([
            'error' => [
                'code' => 'unauthorized',
                'message' => 'Invalid or expired API key.',
            ],
        ], 401)];
    }

    if (! in_array($apiKey->role, $allowedRoles, true)) {
        return ['error' => response()->json([
            'error' => [
                'code' => 'forbidden',
                'message' => "This API key has {$apiKey->role} access and cannot perform this operation.",
            ],
        ], 403)];
    }

    $apiKey->update(['last_used_at' => now()]);

    return ['key' => $apiKey];
};

$serviceInfo = function () {
    return response()->json([
        'name' => 'SpiggleMail API',
        'version' => '1.0.0',
        'status' => 'operational',
        'documentation' => url('/docs'),
        'openapi' => url('/api/v1/openapi.json'),
        'mcp_server' => url('/api/mcp'),
        'endpoints' => [
            'v1_root' => url('/api/v1'),
            'contacts' => url('/api/v1/contacts'),
            'contacts_export' => url('/api/v1/contacts/export.csv'),
            'campaigns' => url('/api/v1/campaigns'),
            'deliverability' => url('/api/v1/deliverability'),
            'openapi' => url('/api/v1/openapi.json'),
            'mcp' => url('/api/mcp'),
        ],
    ]);
};

Route::middleware(['throttle:api'])->group(function () use ($authenticateApiKey, $serviceInfo) {
    Route::get('/', $serviceInfo);
    Route::get('/openapi.json', [OpenApiController::class, 'show']);
    Route::post('/uploads', [UploadController::class, 'store']);

    Route::prefix('v1')->group(function () use ($authenticateApiKey, $serviceInfo) {
        Route::get('/', $serviceInfo);
        Route::get('/openapi.json', [OpenApiController::class, 'show']);
        Route::post('/uploads', [UploadController::class, 'store']);

        Route::get('/check-email', function (Request $request) {
            $email = strtolower(trim((string) $request->query('email', '')));
            if ($email === '') {
                return response()->json([
                    'error' => [
                        'code' => 'missing_email',
                        'message' => 'Email parameter is required.',
                    ],
                ], 400);
            }

            $suggestion = EmailHygieneService::getSuggestion($email);
            $hygieneReason = EmailHygieneService::checkEmail($email);
            $parts = explode('@', $email, 2);
            $domain = $parts[1] ?? '';
            $isDisposable = $domain !== '' && EmailHygieneService::isDisposable($domain);

            return response()->json([
                'email' => $email,
                'isValid' => $hygieneReason === null,
                'suggestion' => $suggestion,
                'has_typo' => $suggestion !== null,
                'isDisposable' => $isDisposable,
                'reason' => $hygieneReason,
            ]);
        });

        Route::get('/contacts/export.csv', function (Request $request) {
            $authenticated = false;
            $accountId = null;

            $bearerToken = $request->bearerToken();
            if ($bearerToken) {
                $keyHash = hash('sha256', $bearerToken);
                $apiKey = ApiKey::where('key_hash', $keyHash)->first();
                if ($apiKey && (! $apiKey->expires_at || $apiKey->expires_at->isFuture())) {
                    $apiKey->update(['last_used_at' => now()]);
                    $authenticated = true;
                    $accountId = $apiKey->account_id;
                }
            }

            $token = $request->query('token');
            if (! $authenticated && is_string($token) && $token !== '') {
                $payload = TrackingTokenService::decodeToken($token);
                if ($payload && ! empty($payload['account_id'])) {
                    $authenticated = true;
                    $accountId = (string) $payload['account_id'];
                }
            }

            if (! $authenticated || ! $accountId) {
                return response()->json([
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'Valid API key (Bearer token) or export token required.',
                    ],
                ], 401);
            }

            $query = Contact::query()->whereHas('newsletter', fn ($q) => $q->where('account_id', $accountId));

            if ($request->filled('status')) {
                $query->where('status', (string) $request->query('status'));
            }

            if ($request->filled('search')) {
                $query->where('email', 'like', $request->query('search').'%');
            }

            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="contacts-'.now()->toDateString().'.csv"',
                'Cache-Control' => 'no-store, no-cache',
            ];

            return response()->stream(function () use ($query) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['email', 'status', 'source', 'open_count', 'click_count', 'subscribed_at']);

                $query->chunk(500, function ($contacts) use ($output) {
                    foreach ($contacts as $contact) {
                        fputcsv($output, CsvSanitizer::row([
                            $contact->email,
                            $contact->status,
                            $contact->source ?? 'website',
                            $contact->open_count,
                            $contact->click_count,
                            $contact->subscribed_at?->toIso8601String() ?? '',
                        ]));
                    }
                });

                fclose($output);
            }, 200, $headers);
        });

        Route::get('/contacts', function (Request $request) use ($authenticateApiKey) {
            $auth = $authenticateApiKey($request);
            if (isset($auth['error'])) {
                return $auth['error'];
            }
            $apiKey = $auth['key'];

            $query = Contact::query()
                ->whereHas('newsletter', fn ($q) => $q->where('account_id', $apiKey->account_id))
                ->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                $query->where('status', (string) $request->query('status'));
            }

            if ($request->filled('search')) {
                $query->where('email', 'like', '%'.$request->query('search').'%');
            }

            $perPage = min(100, max(5, (int) $request->query('per_page', 25)));
            $paginated = $query->paginate($perPage);

            return response()->json([
                'data' => collect($paginated->items())->map(fn ($c) => [
                    'id' => $c->id,
                    'newsletterId' => $c->newsletter_id,
                    'email' => $c->email,
                    'status' => $c->status,
                    'source' => $c->source,
                    'openCount' => $c->open_count,
                    'clickCount' => $c->click_count,
                    'lastSentAt' => $c->last_sent_at?->toIso8601String(),
                    'lastOpenedAt' => $c->last_opened_at?->toIso8601String(),
                    'subscribedAt' => $c->subscribed_at?->toIso8601String(),
                    'createdAt' => $c->created_at?->toIso8601String(),
                ]),
                'meta' => [
                    'total' => $paginated->total(),
                    'perPage' => $paginated->perPage(),
                    'currentPage' => $paginated->currentPage(),
                    'lastPage' => $paginated->lastPage(),
                ],
            ]);
        });

        Route::post('/contacts', function (Request $request) use ($authenticateApiKey) {
            $auth = $authenticateApiKey($request, ['full_access', 'send_only']);
            if (isset($auth['error'])) {
                return $auth['error'];
            }
            $apiKey = $auth['key'];

            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'newsletter_id' => 'nullable|string|max:64',
                'newsletter_slug' => 'nullable|string|max:100',
                'status' => 'nullable|in:subscribed,pending',
                'source' => 'nullable|string|max:50',
            ]);

            $email = strtolower(trim($validated['email']));

            $hygieneReason = EmailHygieneService::checkEmail($email);
            if ($hygieneReason !== null) {
                return response()->json([
                    'error' => [
                        'code' => 'validation_failed',
                        'message' => "Email address rejected: {$hygieneReason}",
                    ],
                ], 422);
            }

            $hash = hash_hmac('sha256', $email, (string) config('app.key'));
            if (Suppression::where('email_hash', $hash)->exists()) {
                return response()->json([
                    'error' => [
                        'code' => 'suppressed',
                        'message' => 'This email address is on the deliverability suppression list.',
                    ],
                ], 422);
            }

            // Strictly scope to API key's owning account (never fall back to Newsletter::first())
            $newsletterQuery = Newsletter::where('account_id', $apiKey->account_id);
            if (! empty($validated['newsletter_id'])) {
                $newsletter = (clone $newsletterQuery)->where('id', $validated['newsletter_id'])->first();
            } elseif (! empty($validated['newsletter_slug'])) {
                $newsletter = (clone $newsletterQuery)->where('slug', $validated['newsletter_slug'])->first();
            } else {
                $newsletter = $newsletterQuery->first();
            }

            if (! $newsletter) {
                return response()->json([
                    'error' => [
                        'code' => 'forbidden_or_not_found',
                        'message' => 'Newsletter not found or not owned by this account.',
                    ],
                ], 403);
            }

            $existing = Contact::where('newsletter_id', $newsletter->id)->where('email', $email)->first();
            if ($existing && in_array($existing->status, ['bounced', 'complained', 'unsubscribed'], true)) {
                return response()->json([
                    'error' => [
                        'code' => 'suppressed_status',
                        'message' => "Cannot re-subscribe contact with status '{$existing->status}'.",
                    ],
                ], 422);
            }

            $requiresConfirmation = (bool) $newsletter->double_opt_in;
            $status = $validated['status'] ?? ($requiresConfirmation ? 'pending' : 'subscribed');

            $contact = Contact::updateOrCreate(
                [
                    'newsletter_id' => $newsletter->id,
                    'email' => $email,
                ],
                [
                    'status' => $status,
                    'source' => $validated['source'] ?? 'api',
                    'confirmed_at' => $status === 'subscribed' ? now() : null,
                    'subscribed_at' => now(),
                ]
            );

            if ($status === 'pending') {
                SendConfirmationEmailJob::dispatch($contact->id);
            } elseif ($status === 'subscribed' && $newsletter->welcome_enabled) {
                SendWelcomeEmailJob::dispatch($contact->id);
            }

            return response()->json([
                'data' => [
                    'id' => $contact->id,
                    'newsletterId' => $contact->newsletter_id,
                    'email' => $contact->email,
                    'status' => $contact->status,
                    'source' => $contact->source,
                    'subscribedAt' => $contact->subscribed_at?->toIso8601String(),
                    'createdAt' => $contact->created_at?->toIso8601String(),
                ],
            ], 201);
        });

        Route::get('/campaigns', function (Request $request) use ($authenticateApiKey) {
            $auth = $authenticateApiKey($request);
            if (isset($auth['error'])) {
                return $auth['error'];
            }
            $apiKey = $auth['key'];

            $query = Campaign::query()
                ->whereHas('newsletter', fn ($q) => $q->where('account_id', $apiKey->account_id))
                ->orderByDesc('created_at');

            if ($request->filled('status')) {
                $query->where('status', (string) $request->query('status'));
            }

            $limit = min(100, max(1, (int) $request->query('limit', 20)));
            $campaigns = $query->limit($limit)->get();

            return response()->json([
                'data' => $campaigns->map(fn ($c) => [
                    'id' => $c->id,
                    'newsletterId' => $c->newsletter_id,
                    'subject' => $c->subject,
                    'previewText' => $c->preview_text,
                    'status' => $c->status,
                    'sentCount' => $c->sent_count,
                    'openCount' => $c->open_count,
                    'clickCount' => $c->click_count,
                    'scheduledAt' => $c->scheduled_at?->toIso8601String(),
                    'sentAt' => $c->sent_at?->toIso8601String(),
                    'createdAt' => $c->created_at?->toIso8601String(),
                ]),
                'meta' => [
                    'count' => $campaigns->count(),
                    'limit' => $limit,
                ],
            ]);
        });

        Route::post('/campaigns', function (Request $request) use ($authenticateApiKey) {
            $auth = $authenticateApiKey($request, ['full_access', 'send_only']);
            if (isset($auth['error'])) {
                return $auth['error'];
            }
            $apiKey = $auth['key'];

            $validated = $request->validate([
                'subject' => 'required|string|max:200',
                'preview_text' => 'nullable|string|max:255',
                'html' => 'required_without:markdown|nullable|string',
                'markdown' => 'nullable|string',
                'scheduled_at' => 'nullable|date',
                'newsletter_slug' => 'nullable|string|max:100',
            ]);

            $newsletterQuery = Newsletter::where('account_id', $apiKey->account_id);
            $newsletter = ! empty($validated['newsletter_slug'])
                ? (clone $newsletterQuery)->where('slug', $validated['newsletter_slug'])->first()
                : $newsletterQuery->first();

            if (! $newsletter) {
                return response()->json([
                    'error' => [
                        'code' => 'not_found',
                        'message' => 'No active newsletter found for this account.',
                    ],
                ], 404);
            }

            $html = $validated['html'] ?? '';
            if ($html === '' && ! empty($validated['markdown'])) {
                $html = Str::markdown($validated['markdown']);
            }

            $isScheduled = ! empty($validated['scheduled_at']);
            $status = $isScheduled ? 'scheduled' : 'draft';

            $campaign = Campaign::create([
                'id' => (string) Str::ulid(),
                'newsletter_id' => $newsletter->id,
                'subject' => $validated['subject'],
                'preview_text' => $validated['preview_text'] ?? null,
                'html' => $html,
                'markdown' => $validated['markdown'] ?? null,
                'status' => $status,
                'scheduled_at' => $isScheduled ? Carbon::parse($validated['scheduled_at']) : null,
                'sent_count' => 0,
                'open_count' => 0,
                'click_count' => 0,
            ]);

            return response()->json([
                'data' => [
                    'id' => $campaign->id,
                    'newsletterId' => $campaign->newsletter_id,
                    'subject' => $campaign->subject,
                    'previewText' => $campaign->preview_text,
                    'status' => $campaign->status,
                    'scheduledAt' => $campaign->scheduled_at?->toIso8601String(),
                    'createdAt' => $campaign->created_at?->toIso8601String(),
                ],
            ], 201);
        });

        Route::get('/deliverability', function (Request $request) use ($authenticateApiKey) {
            $auth = $authenticateApiKey($request);
            if (isset($auth['error'])) {
                return $auth['error'];
            }
            $apiKey = $auth['key'];

            $newsletterIds = Newsletter::where('account_id', $apiKey->account_id)->pluck('id');
            $subscribersCount = Contact::whereIn('newsletter_id', $newsletterIds)->where('status', 'subscribed')->count();
            $suppressedCount = Suppression::where(function ($q) use ($apiKey) {
                $q->where('account_id', $apiKey->account_id)->orWhereNull('account_id');
            })->count();

            $campaignIds = Campaign::whereIn('newsletter_id', $newsletterIds)->pluck('id');

            $recentSent = DB::table('messages')
                ->whereIn('campaign_id', $campaignIds)
                ->where('sent_at', '>=', now()->subDays(30))
                ->count();

            $bounces = DB::table('messages')
                ->whereIn('campaign_id', $campaignIds)
                ->where('status', 'bounced')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count();

            $complaints = DB::table('messages')
                ->whereIn('campaign_id', $campaignIds)
                ->where('status', 'complained')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count();

            $bounceRate = $recentSent > 0 ? round(($bounces / $recentSent) * 100, 2) : 0.0;
            $complaintRate = $recentSent > 0 ? round(($complaints / $recentSent) * 100, 3) : 0.0;

            $healthScore = max(0, min(100, (int) round(100 - ($bounceRate * 10) - ($complaintRate * 100))));

            return response()->json([
                'data' => [
                    'healthScore' => $healthScore,
                    'healthStatus' => $healthScore >= 90 ? 'optimal' : ($healthScore >= 75 ? 'good' : 'warning'),
                    'metrics' => [
                        'activeSubscribers' => $subscribersCount,
                        'suppressedCount' => $suppressedCount,
                        'sentLast30Days' => $recentSent,
                        'bounceRatePercent' => $bounceRate,
                        'complaintRatePercent' => $complaintRate,
                    ],
                    'circuitBreakers' => [
                        'bounceThreshold' => '5.0%',
                        'complaintThreshold' => '0.1%',
                        'status' => ($bounceRate >= 5.0 || $complaintRate >= 0.1) ? 'tripped' : 'healthy',
                    ],
                ],
            ]);
        });
    });

    // Model Context Protocol (MCP) Server Endpoint for AI Agents
    Route::match(['get', 'post'], '/mcp', function (Request $request) {
        return app(McpServerService::class)->handle($request);
    });
});

// --------------------------------------------------------------------------
// Inbound Deliverability Webhooks (AWS SNS / SES & Postmark)
// --------------------------------------------------------------------------

Route::post('/webhooks/ses', [SnsWebhookController::class, 'handle']);

Route::post('/webhooks/postmark', function (Request $request) {
    $expectedSecret = (string) config('services.postmark.webhook_secret', '');
    if ($expectedSecret !== '' || app()->environment('production')) {
        $provided = (string) ($request->header('X-Postmark-Secret') ?? $request->bearerToken() ?? '');
        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $provided)) {
            return response()->json(['error' => 'Unauthorized Postmark webhook'], 403);
        }
    }

    $payload = $request->all();
    $recordType = $payload['RecordType'] ?? null;
    $email = strtolower(trim((string) ($payload['Email'] ?? '')));

    if ($email === '') {
        return response()->json(['status' => 'ignored'], 200);
    }

    $hash = hash_hmac('sha256', $email, (string) config('app.key'));

    if ($recordType === 'Bounce') {
        Suppression::firstOrCreate(
            ['email_hash' => $hash],
            ['reason' => 'bounce']
        );
        Contact::where('email', $email)->update(['status' => 'bounced']);
    } elseif ($recordType === 'SpamComplaint') {
        Suppression::firstOrCreate(
            ['email_hash' => $hash],
            ['reason' => 'complaint']
        );
        Contact::where('email', $email)->update(['status' => 'complained']);
    }

    return response()->json(['status' => 'processed'], 200);
});
