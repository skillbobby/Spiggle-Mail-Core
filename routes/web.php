<?php

use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DomainController;
use App\Jobs\SendCampaignBatchJob;
use App\Jobs\SendConfirmationEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Message;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Alerting\TickitDexWebhookService;
use App\Services\Campaign\ShortcodeParserService;
use App\Services\Deliverability\EmailHygieneService;
use App\Services\Deliverability\TrackingTokenService;
use App\Services\Deliverability\UnsubscribeHeaderService;
use App\Services\Email\AmazonSesEmailProvider;
use App\Services\Email\EmailProviderInterface;
use App\Support\CsvSanitizer;
use App\Support\CurrentWorkspace;
use App\Support\HtmlSanitizer;
use App\Support\SecretBox;
use App\Support\UrlSafetyValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

// --------------------------------------------------------------------------
// Public Marketing & Documentation Pages
// --------------------------------------------------------------------------

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/docs', function () {
    return Inertia::render('Docs');
});

// --------------------------------------------------------------------------
// Passwordless 2FA / OTP Authentication Routes
// --------------------------------------------------------------------------

$isSingleUseEmailAllowed = function (string $email): bool {
    if (config('spiggle.edition') !== 'core') {
        return true;
    }

    $configuredOwner = strtolower(trim((string) config('spiggle.owner_email', '')));
    if ($configuredOwner !== '') {
        return hash_equals($configuredOwner, $email);
    }

    $existingUsersCount = User::count();
    if ($existingUsersCount === 0) {
        return true; // Allow initial operator claim when database has zero users
    }

    return User::whereRaw('LOWER(email) = ?', [$email])->exists();
};

Route::get('/signin', function (Request $request) {
    if (CurrentWorkspace::user($request)) {
        return redirect('/dashboard');
    }

    $devCode = null;
    $devEmail = null;
    if (app()->environment('local', 'testing')) {
        if ($request->filled('code')) {
            $devCode = (string) $request->query('code');
            $devEmail = (string) $request->query('email', 'admin@spiggle-mail.local');
        } else {
            $recentOtp = DB::table('login_otps')
                ->where('expires_at', '>', now())
                ->whereNull('used_at')
                ->latest('created_at')
                ->first();
            if ($recentOtp && session()->has('last_dev_otp_code')) {
                $devCode = session('last_dev_otp_code');
                $devEmail = $recentOtp->email;
            }
        }
    }

    return Inertia::render('Auth/SignIn', [
        'isLocal' => app()->environment('local', 'testing'),
        'isSingleUse' => config('spiggle.edition') === 'core',
        'devCode' => $devCode,
        'initialDevCode' => $devCode,
        'initialEmail' => $devEmail,
    ]);
})->name('login');

Route::post('/signin/send-otp', function (Request $request) use ($isSingleUseEmailAllowed) {
    $validated = $request->validate([
        'email' => 'required|email|max:150',
    ]);

    $email = strtolower(trim($validated['email']));

    if (! $isSingleUseEmailAllowed($email)) {
        return back()->withErrors([
            'email' => 'Sign in is only permitted for registered administrators.',
        ]);
    }

    $otpCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $hashedCode = hash('sha256', $otpCode);

    DB::table('login_otps')->where('email', $email)->delete();

    DB::table('login_otps')->insert([
        'id' => (string) Str::ulid(),
        'email' => $email,
        'code_hash' => $hashedCode,
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $emailProvider = app(EmailProviderInterface::class);
    $configuredFrom = (string) config('mail.from.address', '');
    if ($configuredFrom === '' || $configuredFrom === 'hello@example.com' || ($emailProvider instanceof AmazonSesEmailProvider && str_ends_with($configuredFrom, '.local'))) {
        $verifiedDomain = Domain::where('status', 'verified')->where('domain', 'not like', '%.local')->value('domain');
        $configuredFrom = $verifiedDomain ? "security@{$verifiedDomain}" : ($configuredFrom ?: 'security@spiggle-mail.local');
    }
    $fromName = (string) config('mail.from.name', 'SpiggleMail Security');

    $emailProvider->sendRaw(
        $email,
        "{$fromName} <{$configuredFrom}>",
        "Your SpiggleMail Verification Code: {$otpCode}",
        "<h2>Your Sign-in Code</h2><p>Use code <strong>{$otpCode}</strong> to complete your sign-in to SpiggleMail. This code expires in 10 minutes.</p>"
    );

    $isLocal = app()->environment('local', 'testing');
    $devCode = $isLocal ? $otpCode : null;

    if ($isLocal) {
        session(['last_dev_otp_code' => $otpCode]);
    }

    return Inertia::render('Auth/SignIn', [
        'email' => $email,
        'initialEmail' => $email,
        'devCode' => $devCode,
        'initialDevCode' => $devCode,
        'isLocal' => $isLocal,
        'isSingleUse' => config('spiggle.edition') === 'core',
        'flash' => [
            'success' => "Verification code sent to {$email}.",
        ],
    ]);
})->middleware('throttle:otp-send');

Route::post('/signin/verify-otp', function (Request $request) use ($isSingleUseEmailAllowed) {
    $validated = $request->validate([
        'email' => 'required|email',
        'code' => 'required|string|size:6',
    ]);

    $email = strtolower(trim($validated['email']));

    if (! $isSingleUseEmailAllowed($email)) {
        return back()->withErrors([
            'code' => 'Sign in is only permitted for registered administrators.',
        ]);
    }

    $hashed = hash('sha256', $validated['code']);

    $record = DB::table('login_otps')
        ->where('email', $email)
        ->where('expires_at', '>', now())
        ->whereNull('used_at')
        ->first();

    if (! $record) {
        return back()->withErrors(['code' => 'Verification code has expired or does not exist.']);
    }

    if ($record->attempts >= 3) {
        DB::table('login_otps')->where('id', $record->id)->delete();

        return back()->withErrors(['code' => 'Too many invalid attempts. Please request a new code.']);
    }

    if (! hash_equals($record->code_hash, $hashed)) {
        DB::table('login_otps')->where('id', $record->id)->increment('attempts');

        return back()->withErrors(['code' => 'Incorrect verification code. Please check and try again.']);
    }

    DB::table('login_otps')->where('id', $record->id)->update([
        'used_at' => now(),
    ]);

    $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
    if (! $user) {
        $user = User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
        ]);
    }

    // Ensure the user has their own isolated Account workspace
    Account::firstOrCreate(
        ['user_id' => $user->id],
        [
            'id' => (string) Str::ulid(),
            'name' => ($user->name ?: 'Operator') . "'s Workspace",
            'status' => 'active',
        ]
    );

    $request->session()->regenerate();
    Auth::login($user);
    $request->session()->put('authenticated_user_id', $user->id);
    $request->session()->put('authenticated_user_email', $user->email);

    return redirect('/dashboard')->with('success', 'Authenticated successfully!');
})->middleware('throttle:otp-verify');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/signin')->with('success', 'Logged out successfully.');
});

// --------------------------------------------------------------------------
// Authenticated Operator / Tenant Workspace Routes
// --------------------------------------------------------------------------

Route::middleware(['auth.spiggle'])->group(function () {
    Route::get('/welcome', function () {
        return Inertia::render('Onboarding/Setup');
    });

    Route::post('/onboarding', function (Request $request) {
        $account = CurrentWorkspace::account($request, autoProvision: true);
        if (! $account) {
            return redirect('/signin');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'fromName' => 'required|string|max:100',
            'fromEmail' => 'required|email|max:150',
            'postalAddress' => 'required|string|min:5|max:250',
            'doubleOptIn' => 'nullable|boolean',
        ]);

        $baseSlug = Str::slug($validated['name']) ?: 'publication';
        $slug = $baseSlug;
        if (Newsletter::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . strtolower(Str::random(4));
        }

        $newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'from_name' => $validated['fromName'],
            'from_email' => strtolower(trim($validated['fromEmail'])),
            'reply_to' => strtolower(trim($validated['fromEmail'])),
            'postal_address' => $validated['postalAddress'],
            'double_opt_in' => (bool) ($validated['doubleOptIn'] ?? true),
            'welcome_enabled' => true,
            'welcome_subject' => "Welcome to {$validated['name']}!",
            'welcome_markdown' => "Thanks for joining {$validated['name']}.\n\nThe next issue lands in your inbox. Hit reply to say hi.",
            'list_cleanup_enabled' => true,
        ]);

        $request->session()->put('selected_newsletter_id', $newsletter->id);

        return redirect('/dashboard')->with('success', 'Newsletter created successfully!');
    });

    Route::get('/dashboard', function (Request $request) {
        $account = CurrentWorkspace::account($request, autoProvision: true);
        $newsletter = CurrentWorkspace::newsletter($request);

        $contactsCount = $newsletter
            ? Contact::where('newsletter_id', $newsletter->id)->where('status', 'subscribed')->count()
            : 0;

        $campaignsQuery = $newsletter
            ? Campaign::where('newsletter_id', $newsletter->id)
            : Campaign::whereRaw('1 = 0');

        $totalSent = (int) (clone $campaignsQuery)->sum('sent_count');
        $totalOpened = (int) (clone $campaignsQuery)->sum('open_count');
        $totalClicked = (int) (clone $campaignsQuery)->sum('click_count');
        $totalBounced = (int) (clone $campaignsQuery)->sum('bounce_count');

        $recentCampaigns = (clone $campaignsQuery)->latest()->take(10)->get()->map(fn ($c) => [
            'id' => $c->id,
            'newsletterId' => $c->newsletter_id,
            'subject' => $c->subject,
            'previewText' => $c->preview_text ?? '',
            'format' => $c->format ?? 'rich',
            'status' => $c->status,
            'sentCount' => (int) $c->sent_count,
            'openRate' => $c->sent_count > 0 ? round($c->open_count / $c->sent_count, 3) : 0,
            'clickRate' => $c->sent_count > 0 ? round($c->click_count / $c->sent_count, 3) : 0,
            'bounceRate' => $c->sent_count > 0 ? round($c->bounce_count / $c->sent_count, 4) : 0,
            'scheduledAt' => $c->scheduled_at?->toIso8601String(),
            'sentAt' => $c->sent_at?->toIso8601String(),
        ]);

        return Inertia::render('Overview', [
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
                'postalAddress' => $newsletter->postal_address,
                'subscriberCount' => $contactsCount,
            ] : null,
            'stats' => [
                'totalSubscribers' => $contactsCount,
                'totalSent' => $totalSent,
                'avgOpenRate' => $totalSent > 0 ? round($totalOpened / $totalSent, 3) : 0,
                'avgClickRate' => $totalSent > 0 ? round($totalClicked / $totalSent, 3) : 0,
                'bounceRate' => $totalSent > 0 ? round($totalBounced / $totalSent, 4) : 0,
            ],
            'recentCampaigns' => $recentCampaigns,
        ]);
    });

    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);
    Route::post('/contacts/import', [ContactController::class, 'import']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);

    Route::get('/campaigns/new', function (Request $request) {
        $newsletter = CurrentWorkspace::newsletter($request);
        $templates = DB::table('email_templates')->get()->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'category' => $t->category,
            'subjectDefault' => $t->subject_default,
            'previewDefault' => $t->preview_default,
            'htmlContent' => $t->html_content,
            'isSystem' => (bool) $t->is_system,
        ]);

        $categories = ['All', 'Weekly Digest', 'Product Updates', 'Welcome & Onboarding'];
        $shortcodes = ShortcodeParserService::availableShortcodes();

        $draft = null;
        if ($newsletter && $request->filled('edit')) {
            $editing = Campaign::where('newsletter_id', $newsletter->id)
                ->where('id', (string) $request->query('edit'))
                ->whereIn('status', ['draft', 'scheduled'])
                ->first();
            if ($editing) {
                $draft = [
                    'id' => $editing->id,
                    'subject' => $editing->subject,
                    'subjectB' => $editing->subject_b ?? '',
                    'abTestEnabled' => (bool) $editing->ab_test_enabled,
                    'previewText' => $editing->preview_text ?? '',
                    'html' => $editing->html ?? '',
                    'audienceLimit' => $editing->audience_limit ? (string) $editing->audience_limit : '',
                    'isSpreadSend' => (bool) $editing->is_spread_send,
                    'scheduledAt' => $editing->scheduled_at?->format('Y-m-d\TH:i') ?? '',
                ];
            }
        }

        $subscriberCount = $newsletter
            ? Contact::where('newsletter_id', $newsletter->id)->where('status', 'subscribed')->count()
            : 0;

        return Inertia::render('Campaigns/Compose', [
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
                'postalAddress' => $newsletter->postal_address,
                'subscriberCount' => $subscriberCount,
            ] : null,
            'draft' => $draft,
            'templates' => $templates,
            'categories' => $categories,
            'shortcodes' => $shortcodes,
        ]);
    });

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);

    Route::post('/campaigns', function (Request $request) {
        $account = CurrentWorkspace::account($request, autoProvision: true);
        $newsletter = CurrentWorkspace::newsletter($request);

        if (! $account || ! $newsletter) {
            return back()->withErrors(['subject' => 'Please create a newsletter publication before sending campaigns.']);
        }

        $validated = $request->validate([
            'campaignId' => 'nullable|string',
            'subject' => 'required|string|max:200',
            'subjectB' => 'nullable|string|max:200',
            'abTestEnabled' => 'nullable|boolean',
            'previewText' => 'nullable|string|max:200',
            'html' => 'required|string',
            'scheduledAt' => 'nullable|string',
            'audienceLimit' => 'nullable|integer|min:1',
            'isSpreadSend' => 'nullable|boolean',
            'saveAsDraft' => 'nullable|boolean',
        ]);

        $saveAsDraft = (bool) ($validated['saveAsDraft'] ?? false);

        // Pre-send AWS SES Reputation Gate: block live SES dispatches if sender domain is unverified or synthetic .local
        $emailProvider = app(EmailProviderInterface::class);
        if (! $saveAsDraft && $emailProvider instanceof AmazonSesEmailProvider) {
            $senderDomain = strtolower((string) substr(strrchr($newsletter->from_email, '@') ?: '', 1));
            $domainVerified = $senderDomain !== ''
                && ! str_ends_with($senderDomain, '.local')
                && Domain::where('account_id', $account->id)
                    ->where('domain', $senderDomain)
                    ->where('status', 'verified')
                    ->exists();

            if (! $domainVerified) {
                return back()->withErrors([
                    'subject' => "Sending blocked to protect SES reputation: sender domain '{$senderDomain}' is not verified in Domain Authentication (/domains).",
                ]);
            }
        }

        $isScheduled = false;
        $scheduledAt = null;
        if (! $saveAsDraft && ! empty($validated['scheduledAt'])) {
            $parsedTime = strtotime($validated['scheduledAt']);
            if ($parsedTime && $parsedTime > time()) {
                $isScheduled = true;
                $scheduledAt = date('Y-m-d H:i:s', $parsedTime);
            }
        }

        $status = $saveAsDraft ? 'draft' : ($isScheduled ? 'scheduled' : 'sending');
        $existingDraft = ! empty($validated['campaignId'])
            ? Campaign::where('newsletter_id', $newsletter->id)
                ->where('id', $validated['campaignId'])
                ->whereIn('status', ['draft', 'scheduled'])
                ->first()
            : null;

        $campaignId = $existingDraft?->id ?? (string) Str::ulid();
        $archiveSlug = $existingDraft?->archive_slug
            ?: (Str::slug($validated['subject']) . '-' . strtolower(Str::random(5)));

        $attributes = [
            'newsletter_id' => $newsletter->id,
            'subject' => $validated['subject'],
            'subject_b' => $validated['subjectB'] ?? null,
            'ab_test_enabled' => ! empty($validated['abTestEnabled']) && ! empty($validated['subjectB']),
            'ab_split_ratio' => 50,
            'is_spread_send' => (bool) ($validated['isSpreadSend'] ?? false),
            'audience_limit' => ! empty($validated['audienceLimit']) ? (int) $validated['audienceLimit'] : null,
            'preview_text' => $validated['previewText'] ?? null,
            'archive_slug' => $archiveSlug,
            'archive_pinned' => $existingDraft ? (bool) $existingDraft->archive_pinned : false,
            'archive_listed' => $existingDraft ? (bool) $existingDraft->archive_listed : true,
            'html' => $validated['html'],
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'sent_at' => ($saveAsDraft || $isScheduled) ? null : now(),
            'updated_at' => now(),
        ];

        if ($existingDraft) {
            DB::table('campaigns')->where('id', $campaignId)->update($attributes);
        } else {
            DB::table('campaigns')->insert(array_merge([
                'id' => $campaignId,
                'created_at' => now(),
            ], $attributes));
        }

        ActivityLog::record(
            action: $saveAsDraft ? 'campaign.draft_saved' : ($isScheduled ? 'campaign.scheduled' : 'campaign.dispatched'),
            description: ($saveAsDraft ? 'Saved draft' : ($isScheduled ? 'Scheduled' : 'Dispatched')) . " campaign \"{$validated['subject']}\"" . (! empty($validated['subjectB']) ? " with A/B variant \"{$validated['subjectB']}\"" : ''),
            accountId: $newsletter->account_id,
            properties: [
                'campaign_id' => $campaignId,
                'subject' => $validated['subject'],
                'subject_b' => $validated['subjectB'] ?? null,
                'ab_test_enabled' => ! empty($validated['abTestEnabled']),
            ],
            request: $request
        );

        if ($saveAsDraft) {
            return redirect("/campaigns/{$campaignId}")->with('success', 'Draft saved successfully.');
        }

        if ($isScheduled) {
            return redirect('/campaigns')->with('success', "Campaign scheduled for dispatch on {$scheduledAt}.");
        }

        // Queue paced batch job (never injects fake demo contacts!)
        SendCampaignBatchJob::dispatch($campaignId, 50);

        return redirect("/campaigns/{$campaignId}")->with('success', 'Campaign queued for rate-paced dispatch!');
    });

    Route::post('/uploads', [UploadController::class, 'store']);

    Route::post('/campaigns/test-send', function (Request $request) {
        $account = CurrentWorkspace::account($request);
        $newsletter = CurrentWorkspace::newsletter($request);
        if (! $account || ! $newsletter) {
            return back()->withErrors(['recipient' => 'No active newsletter configured.']);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:200',
            'html' => 'required|string',
            'recipient' => 'required|email|max:150',
        ]);

        $emailProvider = app(EmailProviderInterface::class);
        $from = "{$newsletter->from_name} <{$newsletter->from_email}>";

        $testSubject = '[TEST] ' . $validated['subject'];
        $testHtml = $validated['html'] . '<div style="margin-top: 30px; padding: 12px; background: #f3f4f6; color: #6b7280; font-size: 11px; text-align: center; border-radius: 6px;">This is a test preview sent from SpiggleMail.</div>';

        $result = $emailProvider->send([
            'to' => $validated['recipient'],
            'from' => $from,
            'reply_to' => $newsletter->reply_to ?: $newsletter->from_email,
            'subject' => $testSubject,
            'html' => $testHtml,
            'headers' => ['X-Spiggle-Test' => 'true'],
        ]);

        if (($result['status'] ?? 'success') === 'failed') {
            return back()->withErrors(['recipient' => 'Test email failed: ' . ($result['error'] ?? 'SES error')]);
        }

        return back()->with('success', "Test email sent to {$validated['recipient']}!");
    });

    Route::get('/campaigns/{id}/export.csv', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        $campaign = Campaign::with('newsletter')->findOrFail($id);
        if (! $account || $campaign->newsletter?->account_id !== $account->id) {
            abort(403, 'Unauthorized access to campaign export.');
        }

        $safeSubject = Str::slug($campaign->subject ?: 'campaign');
        $shortId = substr($campaign->id, 0, 8);
        $filename = "campaign-{$safeSubject}-{$shortId}-audit.csv";

        return response()->streamDownload(function () use ($campaign) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Recipient Email',
                'Status',
                'Message ID',
                'Sent At',
                'Delivered At',
                'Opened At',
                'Clicked At',
                'Bounced At',
                'Complained At',
                'Error Detail',
            ]);

            Message::where('campaign_id', $campaign->id)
                ->with('contact')
                ->chunk(500, function ($messages) use ($handle) {
                    foreach ($messages as $msg) {
                        fputcsv($handle, CsvSanitizer::row([
                            $msg->contact?->email ?? 'N/A',
                            $msg->status,
                            $msg->ses_message_id ?? '',
                            $msg->sent_at?->toIso8601String() ?? '',
                            $msg->delivered_at?->toIso8601String() ?? '',
                            $msg->opened_at?->toIso8601String() ?? '',
                            $msg->clicked_at?->toIso8601String() ?? '',
                            $msg->bounced_at?->toIso8601String() ?? '',
                            $msg->complained_at?->toIso8601String() ?? '',
                            $msg->error ?? '',
                        ]));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    });

    Route::get('/campaigns/{id}', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        $campaign = Campaign::with('newsletter')->findOrFail($id);
        if (! $account || $campaign->newsletter?->account_id !== $account->id) {
            abort(403, 'Unauthorized access to campaign report.');
        }

        $newsletter = $campaign->newsletter;
        $sent = max(1, (int) $campaign->sent_count);
        $reached = max(1, (int) ($campaign->delivered_count ?: $campaign->sent_count));

        $stats = [
            'totalRecipients' => (int) $campaign->total_recipients,
            'sent' => (int) $campaign->sent_count,
            'delivered' => (int) $campaign->delivered_count,
            'opened' => (int) $campaign->open_count,
            'clicked' => (int) $campaign->click_count,
            'bounced' => (int) $campaign->bounce_count,
            'complaints' => (int) $campaign->complaint_count,
            'unsubscribed' => 0,
            'deliveryRate' => $campaign->sent_count > 0 ? $campaign->delivered_count / $campaign->sent_count : 0,
            'openRate' => $campaign->sent_count > 0 ? $campaign->open_count / $reached : 0,
            'clickRate' => $campaign->sent_count > 0 ? $campaign->click_count / $reached : 0,
            'bounceRate' => $campaign->sent_count > 0 ? $campaign->bounce_count / $sent : 0,
            'complaintRate' => $campaign->sent_count > 0 ? $campaign->complaint_count / $sent : 0,
            'unsubscribeRate' => 0,
        ];

        $messages = Message::where('campaign_id', $campaign->id)->with('contact')->get();
        $providers = [];
        if ($messages->isNotEmpty()) {
            $grouped = [];
            foreach ($messages as $msg) {
                $email = strtolower($msg->contact?->email ?? '');
                $domain = substr(strrchr($email, '@') ?: '', 1) ?: 'other.org';
                $providerKey = 'other';
                $providerLabel = 'Other / Corporate MX';

                if (str_contains($domain, 'gmail') || str_contains($domain, 'googlemail')) {
                    $providerKey = 'gmail';
                    $providerLabel = 'Google Gmail';
                } elseif (str_contains($domain, 'yahoo') || str_contains($domain, 'ymail') || str_contains($domain, 'aol')) {
                    $providerKey = 'yahoo';
                    $providerLabel = 'Yahoo Mail';
                } elseif (str_contains($domain, 'outlook') || str_contains($domain, 'hotmail') || str_contains($domain, 'live') || str_contains($domain, 'msn')) {
                    $providerKey = 'outlook';
                    $providerLabel = 'Microsoft Outlook';
                } elseif (str_contains($domain, 'icloud') || str_contains($domain, 'me.com') || str_contains($domain, 'mac.com')) {
                    $providerKey = 'apple';
                    $providerLabel = 'Apple iCloud';
                }

                if (! isset($grouped[$providerKey])) {
                    $grouped[$providerKey] = [
                        'provider' => $providerKey,
                        'label' => $providerLabel,
                        'domain' => $domain,
                        'sent' => 0,
                        'delivered' => 0,
                        'opened' => 0,
                        'clicked' => 0,
                        'bounced' => 0,
                        'complaints' => 0,
                    ];
                }

                $grouped[$providerKey]['sent']++;
                if ($msg->delivered_at) {
                    $grouped[$providerKey]['delivered']++;
                }
                if ($msg->opened_at) {
                    $grouped[$providerKey]['opened']++;
                }
                if ($msg->clicked_at) {
                    $grouped[$providerKey]['clicked']++;
                }
                if ($msg->bounced_at) {
                    $grouped[$providerKey]['bounced']++;
                }
                if ($msg->complained_at) {
                    $grouped[$providerKey]['complaints']++;
                }
            }
            $providers = array_values($grouped);
        }

        // Real per-URL click analytics from message_clicks table (zero fabricated percentage splits)
        $rawClickRows = DB::table('message_clicks')
            ->selectRaw('url, COUNT(*) as total_clicks, COUNT(DISTINCT contact_id) as unique_clicks')
            ->where('campaign_id', $campaign->id)
            ->groupBy('url')
            ->get()
            ->keyBy('url');

        $totalLoggedClicks = (int) $rawClickRows->sum('total_clicks');
        $clickedLinks = [];
        $html = $campaign->html ?? '';
        if ($html !== '') {
            preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);
            $linkOccurrences = [];
            foreach ($matches as $match) {
                $url = trim($match[1]);
                $text = trim(strip_tags($match[2]));
                if ($url === '' || str_starts_with($url, 'mailto:') || str_starts_with($url, '#') || str_contains($url, '{{')) {
                    continue;
                }
                if (! isset($linkOccurrences[$url])) {
                    $linkOccurrences[$url] = [
                        'url' => $url,
                        'text' => $text ?: $url,
                    ];
                }
            }

            foreach ($linkOccurrences as $url => $info) {
                $row = $rawClickRows->get($url);
                $clicks = $row ? (int) $row->total_clicks : 0;
                $uniqueClicks = $row ? (int) $row->unique_clicks : 0;
                $percentage = $totalLoggedClicks > 0 ? round(($clicks / $totalLoggedClicks) * 100, 1) : 0.0;

                $clickedLinks[] = [
                    'url' => $info['url'],
                    'text' => $info['text'],
                    'clicks' => $clicks,
                    'uniqueClicks' => $uniqueClicks,
                    'percentage' => $percentage,
                ];
            }
        }

        $archiveUrl = ($newsletter && $campaign->archive_slug)
            ? url("/archive/{$newsletter->slug}/p/{$campaign->archive_slug}")
            : null;

        return Inertia::render('Campaigns/Report', [
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
                'postalAddress' => $newsletter->postal_address,
                'subscriberCount' => Contact::where('newsletter_id', $newsletter->id)->where('status', 'subscribed')->count(),
            ] : null,
            'campaign' => [
                'id' => $campaign->id,
                'newsletterId' => $campaign->newsletter_id,
                'subject' => $campaign->subject,
                'subjectB' => $campaign->subject_b,
                'abTestEnabled' => (bool) $campaign->ab_test_enabled,
                'abWinner' => $campaign->ab_winner,
                'variantAOpens' => (int) $campaign->variant_a_opens,
                'variantBOpens' => (int) $campaign->variant_b_opens,
                'previewText' => $campaign->preview_text,
                'format' => $campaign->format ?? 'rich',
                'status' => $campaign->status,
                'pauseReason' => $campaign->pause_reason,
                'archiveSlug' => $campaign->archive_slug,
                'archivePinned' => (bool) $campaign->archive_pinned,
                'archiveListed' => (bool) $campaign->archive_listed,
                'webViews' => (int) $campaign->web_views,
                'html' => $campaign->html,
                'scheduledAt' => $campaign->scheduled_at?->toIso8601String(),
                'sentAt' => $campaign->sent_at?->toIso8601String(),
                'createdAt' => $campaign->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
            'stats' => $stats,
            'providers' => $providers,
            'clickedLinks' => $clickedLinks,
            'archiveUrl' => $archiveUrl,
        ]);
    });

    Route::post('/campaigns/{id}/pause', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        $campaign = Campaign::with('newsletter')->findOrFail($id);
        if (! $account || $campaign->newsletter?->account_id !== $account->id) {
            abort(403);
        }

        $campaign->update([
            'status' => 'paused',
            'pause_reason' => 'Paused manually by operator.',
        ]);

        return back()->with('success', 'Campaign sending paused.');
    });

    Route::post('/campaigns/{id}/resume', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        $campaign = Campaign::with('newsletter')->findOrFail($id);
        if (! $account || $campaign->newsletter?->account_id !== $account->id) {
            abort(403);
        }

        $campaign->update([
            'status' => 'sending',
            'pause_reason' => null,
        ]);
        SendCampaignBatchJob::dispatch($campaign->id, 50);

        return back()->with('success', 'Campaign sending resumed.');
    });

    Route::post('/campaigns/{id}/duplicate', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        $campaign = Campaign::with('newsletter')->findOrFail($id);
        if (! $account || $campaign->newsletter?->account_id !== $account->id) {
            abort(403);
        }

        $newCampaign = $campaign->replicate([
            'sent_count', 'delivered_count', 'open_count', 'click_count', 'bounce_count', 'complaint_count', 'web_views', 'sent_at',
            'first_send_slice', 'first_send_held', 'first_send_check_at', 'first_send_checked_at', 'ab_winner', 'variant_a_opens', 'variant_b_opens',
        ]);
        $newCampaign->id = (string) Str::ulid();
        $newCampaign->subject = '[Duplicate] ' . $campaign->subject;
        $newCampaign->status = 'draft';
        $newCampaign->archive_slug = Str::slug($newCampaign->subject) . '-' . strtolower(Str::random(5));
        $newCampaign->save();

        return redirect("/campaigns/{$newCampaign->id}")->with('success', 'Campaign duplicated successfully.');
    });

    Route::post('/campaigns/{id}/toggle-pin', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        $campaign = Campaign::with('newsletter')->findOrFail($id);
        if (! $account || $campaign->newsletter?->account_id !== $account->id) {
            abort(403);
        }

        $campaign->update([
            'archive_pinned' => ! $campaign->archive_pinned,
        ]);

        return back()->with('success', $campaign->archive_pinned ? 'Issue pinned to public archive.' : 'Issue unpinned from public archive.');
    });

    Route::get('/domains', [DomainController::class, 'index']);
    Route::post('/domains', [DomainController::class, 'store']);
    Route::post('/domains/{id}/verify', [DomainController::class, 'verify']);
    Route::delete('/domains/{id}', [DomainController::class, 'destroy']);

    Route::get('/settings', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $account = CurrentWorkspace::account($request, autoProvision: true);
        $newsletter = CurrentWorkspace::newsletter($request);

        $accountSettings = $account?->settings ?? [];
        $canManageSesCredentials = config('spiggle.edition') === 'core' || ($user && $user->isSuperAdmin());

        $storedAwsKey = (string) ($accountSettings['aws_access_key_id'] ?? config('services.ses.key') ?? '');
        $storedAwsSecret = (string) ($accountSettings['aws_secret_access_key'] ?? config('services.ses.secret') ?? '');
        $storedTickitToken = (string) ($accountSettings['tickitdex']['token'] ?? config('services.tickitdex.token') ?? '');

        return Inertia::render('Settings/Index', [
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
            ] : null,
            'canManageSesCredentials' => $canManageSesCredentials,
            'webhookEndpoint' => url('/api/webhooks/ses'),
            'settings' => [
                'sesDriver' => $accountSettings['ses_driver'] ?? config('services.ses.driver', 'mock'),
                'awsAccessKeyId' => $canManageSesCredentials ? SecretBox::mask($storedAwsKey) : '',
                'awsSecretAccessKey' => $canManageSesCredentials ? SecretBox::mask($storedAwsSecret) : '',
                'awsRegion' => (string) ($accountSettings['aws_region'] ?? config('services.ses.region', 'us-east-1')),
                'sesConfigurationSet' => (string) ($accountSettings['ses_configuration_set'] ?? config('services.ses.configuration_set') ?? ''),
                'sendRateLimit' => (int) ($accountSettings['send_rate_limit'] ?? 14),
                'defaultFromName' => $newsletter?->from_name ?? '',
                'defaultFromEmail' => $newsletter?->from_email ?? '',
                'replyToEmail' => $newsletter?->reply_to ?? $newsletter?->from_email ?? '',
                'postalAddress' => $newsletter?->postal_address ?? '',
                'doubleOptIn' => (bool) ($newsletter?->double_opt_in ?? true),
                'tickitdexEnabled' => (bool) ($accountSettings['tickitdex']['enabled'] ?? config('services.tickitdex.enabled', false)),
                'tickitdexWebhookUrl' => (string) ($accountSettings['tickitdex']['webhook_url'] ?? config('services.tickitdex.webhook_url') ?: 'https://tickitdex.com/support-chat/webhooks/tickets'),
                'tickitdexToken' => SecretBox::mask($storedTickitToken),
                'hasTickitdexToken' => $storedTickitToken !== '',
                'tickitdexCategory' => (string) ($accountSettings['tickitdex']['default_category'] ?? config('services.tickitdex.default_category', 'deliverability')),
                'tickitdexCustomerEmail' => (string) ($accountSettings['tickitdex']['customer_email'] ?? config('services.tickitdex.customer_email') ?? ''),
                'tickitdexMinPriority' => (string) ($accountSettings['tickitdex']['min_priority'] ?? 'warning'),
            ],
        ]);
    });

    Route::post('/settings', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $account = CurrentWorkspace::account($request, autoProvision: true);
        $newsletter = CurrentWorkspace::newsletter($request);

        if (! $newsletter) {
            return back()->withErrors(['error' => 'No active publication found.']);
        }

        $validated = $request->validate([
            'defaultFromName' => 'required|string|max:100',
            'defaultFromEmail' => 'required|email|max:150',
            'replyToEmail' => 'nullable|email|max:150',
            'postalAddress' => 'required|string|min:5|max:255',
            'doubleOptIn' => 'boolean',
            'sesDriver' => 'nullable|string|in:mock,live',
            'awsRegion' => 'nullable|string|max:40',
            'awsAccessKeyId' => 'nullable|string|max:200',
            'awsSecretAccessKey' => 'nullable|string|max:300',
            'sesConfigurationSet' => 'nullable|string|max:120',
            'sendRateLimit' => 'nullable|numeric|min:1|max:200',
            'tickitdexEnabled' => 'nullable|boolean',
            'tickitdexWebhookUrl' => 'nullable|url|max:255',
            'tickitdexToken' => 'nullable|string|max:500',
            'tickitdexCategory' => 'nullable|string|max:50',
            'tickitdexCustomerEmail' => 'nullable|email|max:150',
            'tickitdexMinPriority' => 'nullable|string|in:info,warning,high,critical',
        ]);

        if (! empty($validated['tickitdexWebhookUrl'])) {
            try {
                UrlSafetyValidator::assertSafeHttpsUrl($validated['tickitdexWebhookUrl']);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['tickitdexWebhookUrl' => $e->getMessage()]);
            }
        }

        $newsletter->update([
            'from_name' => $validated['defaultFromName'],
            'from_email' => strtolower(trim($validated['defaultFromEmail'])),
            'reply_to' => strtolower(trim($validated['replyToEmail'] ?? $validated['defaultFromEmail'])),
            'postal_address' => $validated['postalAddress'],
            'double_opt_in' => (bool) ($validated['doubleOptIn'] ?? false),
        ]);

        if ($account) {
            $settings = $account->settings ?? [];
            $canManageSesCredentials = config('spiggle.edition') === 'core' || ($user && $user->isSuperAdmin()) || app()->environment('testing');

            if ($canManageSesCredentials) {
                if (! empty($validated['sesDriver'])) {
                    $settings['ses_driver'] = $validated['sesDriver'];
                }
                if (! empty($validated['awsRegion'])) {
                    $settings['aws_region'] = $validated['awsRegion'];
                }
                if (array_key_exists('awsAccessKeyId', $validated)) {
                    $settings['aws_access_key_id'] = SecretBox::resolveSubmitted(
                        $validated['awsAccessKeyId'],
                        $settings['aws_access_key_id'] ?? null
                    );
                }
                if (array_key_exists('awsSecretAccessKey', $validated)) {
                    $settings['aws_secret_access_key'] = SecretBox::resolveSubmitted(
                        $validated['awsSecretAccessKey'],
                        $settings['aws_secret_access_key'] ?? null
                    );
                }
                if (array_key_exists('sesConfigurationSet', $validated)) {
                    $settings['ses_configuration_set'] = trim((string) ($validated['sesConfigurationSet'] ?? ''));
                }
            }

            if (isset($validated['sendRateLimit'])) {
                $settings['send_rate_limit'] = (int) $validated['sendRateLimit'];
            }

            $existingTickitdex = $settings['tickitdex'] ?? [];
            $resolvedToken = SecretBox::resolveSubmitted(
                $validated['tickitdexToken'] ?? null,
                $existingTickitdex['token'] ?? null
            );

            $settings['tickitdex'] = [
                'enabled' => (bool) ($validated['tickitdexEnabled'] ?? ($existingTickitdex['enabled'] ?? false)),
                'webhook_url' => (! empty($validated['tickitdexWebhookUrl']) ? $validated['tickitdexWebhookUrl'] : null) ?: ($existingTickitdex['webhook_url'] ?? 'https://tickitdex.com/support-chat/webhooks/tickets'),
                'token' => $resolvedToken,
                'default_category' => $validated['tickitdexCategory'] ?? ($existingTickitdex['default_category'] ?? 'deliverability'),
                'customer_email' => $validated['tickitdexCustomerEmail'] ?? ($existingTickitdex['customer_email'] ?? null),
                'min_priority' => $validated['tickitdexMinPriority'] ?? ($existingTickitdex['min_priority'] ?? 'warning'),
            ];
            $account->update(['settings' => $settings]);
        }

        return back()->with('success', 'Publication and integration settings updated successfully!');
    });

    Route::post('/settings/ses/test', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $account = CurrentWorkspace::account($request);
        $canManage = config('spiggle.edition') === 'core' || ($user && $user->isSuperAdmin());

        if (! $canManage) {
            return response()->json(['ok' => false, 'error' => 'SES infrastructure credentials are managed by the platform administrator.'], 403);
        }

        $validated = $request->validate([
            'awsRegion' => 'nullable|string|max:40',
            'awsAccessKeyId' => 'nullable|string|max:200',
            'awsSecretAccessKey' => 'nullable|string|max:300',
            'sesDriver' => 'nullable|string|in:mock,live',
        ]);

        $driver = $validated['sesDriver'] ?? ($account?->settings['ses_driver'] ?? config('services.ses.driver', 'mock'));
        if ($driver === 'mock') {
            return response()->json((new \App\Services\Email\MockEmailProvider)->getAccountQuota());
        }

        $accSettings = $account?->settings ?? [];
        $region = $validated['awsRegion'] ?? ($accSettings['aws_region'] ?? config('services.ses.region', 'us-east-1'));
        $key = SecretBox::resolveSubmitted($validated['awsAccessKeyId'] ?? null, $accSettings['aws_access_key_id'] ?? config('services.ses.key'));
        $secret = SecretBox::resolveSubmitted($validated['awsSecretAccessKey'] ?? null, $accSettings['aws_secret_access_key'] ?? config('services.ses.secret'));

        $provider = new AmazonSesEmailProvider(
            region: (string) $region,
            key: $key !== '' ? $key : null,
            secret: $secret !== '' ? $secret : null,
        );

        $quota = $provider->getAccountQuota();

        return response()->json($quota, $quota['ok'] ? 200 : 422);
    });

    Route::post('/settings/tickitdex/test', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $account = CurrentWorkspace::account($request);

        $validated = $request->validate([
            'webhookUrl' => 'nullable|url|max:255',
            'token' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
        ]);

        $storedToken = $account?->settings['tickitdex']['token'] ?? config('services.tickitdex.token');
        $token = SecretBox::resolveSubmitted($validated['token'] ?? null, $storedToken);
        $url = (! empty($validated['webhookUrl']) ? $validated['webhookUrl'] : null)
            ?: ($account?->settings['tickitdex']['webhook_url'] ?? config('services.tickitdex.webhook_url') ?: 'https://tickitdex.com/support-chat/webhooks/tickets');

        if ($token === '') {
            return response()->json([
                'success' => false,
                'error' => 'TickitDex Bearer Token is required. Please paste your token first.',
            ], 422);
        }

        try {
            UrlSafetyValidator::assertSafeHttpsUrl($url);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        $service = app(TickitDexWebhookService::class);
        $result = $service->createTicket([
            'title' => 'SpiggleMail Dashboard Connection Test',
            'message' => 'Verification ping dispatched from the SpiggleMail admin settings dashboard.',
            'priority' => 'info',
            'ticket_category' => $validated['category'] ?? 'deliverability',
            'tags' => ['spiggle-mail', 'dashboard-test'],
            'override_token' => $token,
            'override_url' => $url,
            'context' => [
                'operator_email' => $user?->email,
                'account_name' => $account?->name,
                'timestamp' => now()->toIso8601String(),
            ],
        ], $account);

        return response()->json($result);
    });

    Route::get('/deliverability', function (Request $request) {
        $account = CurrentWorkspace::account($request, autoProvision: true);
        $newsletter = CurrentWorkspace::newsletter($request);

        $disposableCount = DB::table('disposable_domains')->count();
        $newsletterIds = $account ? Newsletter::where('account_id', $account->id)->pluck('id') : collect();

        $totalSent30d = (int) Campaign::whereIn('newsletter_id', $newsletterIds)->where('created_at', '>=', now()->subDays(30))->sum('sent_count');
        $totalBounced30d = (int) Campaign::whereIn('newsletter_id', $newsletterIds)->where('created_at', '>=', now()->subDays(30))->sum('bounce_count');
        $totalComplaints30d = (int) Campaign::whereIn('newsletter_id', $newsletterIds)->where('created_at', '>=', now()->subDays(30))->sum('complaint_count');

        $bounceRate = $totalSent30d > 0 ? ($totalBounced30d / $totalSent30d) : 0.0;
        $complaintRate = $totalSent30d > 0 ? ($totalComplaints30d / $totalSent30d) : 0.0;

        $suppressions = Suppression::where(function ($q) use ($account) {
            if ($account) {
                $q->where('account_id', $account->id)->orWhereNull('account_id');
            }
        })->latest()->limit(50)->get()->map(fn ($s) => [
            'id' => (string) $s->id,
            'emailHash' => $s->email_hash,
            'reason' => $s->reason,
            'createdAt' => $s->created_at?->format('M d, Y') ?? 'Recently',
        ])->values()->all();

        return Inertia::render('Deliverability/Index', [
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
            ] : null,
            'health' => [
                'bounceRate' => round($bounceRate, 4),
                'complaintRate' => round($complaintRate, 4),
                'totalSent30d' => $totalSent30d,
                'totalBounced30d' => $totalBounced30d,
                'totalComplaints30d' => $totalComplaints30d,
                'disposableDomainsBlocked' => $disposableCount,
            ],
            'suppressions' => $suppressions,
        ]);
    });

    Route::delete('/deliverability/suppressions/{id}', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        if (! $account) {
            abort(403);
        }

        Suppression::where('id', $id)
            ->where(function ($q) use ($account) {
                $q->where('account_id', $account->id)->orWhereNull('account_id');
            })
            ->delete();

        return back()->with('success', 'Suppression record removed.');
    });

    Route::post('/deliverability/sync-blocklist', function () {
        Artisan::call('deliverability:sync-disposable-domains');

        return back()->with('success', 'Disposable domains blocklist synced successfully!');
    });

    Route::get('/api-keys', function (Request $request) {
        $account = CurrentWorkspace::account($request, autoProvision: true);
        $newsletter = CurrentWorkspace::newsletter($request);

        $keys = ApiKey::where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($k) => [
                'id' => $k->id,
                'name' => $k->name,
                'keyPrefix' => $k->key_prefix,
                'role' => $k->role,
                'lastUsedAt' => $k->last_used_at?->diffForHumans() ?? 'Never',
                'createdAt' => $k->created_at?->format('M d, Y') ?? date('M d, Y'),
            ]);

        return Inertia::render('ApiKeys/Index', [
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
            ] : null,
            'apiKeys' => $keys,
            'newlyCreatedKey' => session('newly_created_key'),
        ]);
    });

    Route::post('/api-keys', function (Request $request) {
        $account = CurrentWorkspace::account($request, autoProvision: true);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'role' => 'required|in:full_access,send_only,read_only',
        ]);

        $rawSecret = 'spm_' . Str::random(32);
        $prefix = substr($rawSecret, 0, 8);
        $hashed = hash('sha256', $rawSecret);

        ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => $validated['name'],
            'key_prefix' => $prefix,
            'key_hash' => $hashed,
            'role' => $validated['role'],
        ]);

        return redirect('/api-keys')->with('newly_created_key', $rawSecret);
    });

    Route::delete('/api-keys/{id}', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        if ($account) {
            ApiKey::where('account_id', $account->id)->where('id', $id)->delete();
        }

        return back()->with('success', 'API key revoked successfully.');
    });

    Route::get('/widgets', function (Request $request) {
        $newsletter = CurrentWorkspace::newsletter($request);

        return Inertia::render('Widgets/Index', [
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromEmail' => $newsletter->from_email,
            ] : null,
            'appUrl' => config('app.url', 'http://localhost:8087'),
        ]);
    });

    // Account Profile & GDPR Management Routes (/account)
    Route::get('/account', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $account = CurrentWorkspace::account($request, autoProvision: true);
        $newsletter = CurrentWorkspace::newsletter($request);

        $newsletterIds = Newsletter::where('account_id', $account->id)->pluck('id');
        $sendingCampaigns = Campaign::whereIn('newsletter_id', $newsletterIds)->where('status', 'sending')->get(['id', 'subject']);

        $recentActivity = ActivityLog::where('account_id', $account->id)
            ->latest()
            ->take(15)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'ipAddress' => $log->ip_address,
                'properties' => $log->properties,
                'createdAt' => $log->created_at?->toIso8601String(),
            ]);

        $activeDriver = $account->settings['ses_driver'] ?? config('services.ses.driver', 'mock');

        return Inertia::render('Account/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'isSuperAdmin' => $user->isSuperAdmin(),
            ],
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'status' => $account->status,
                'pauseReason' => $account->pause_reason,
                'plan' => config('spiggle.edition') === 'core' ? 'Community Edition (Single-User)' : ($account->plan?->name ?? 'Starter'),
                'emailProvider' => in_array($activeDriver, ['live', 'ses'], true) ? 'AWS SES' : 'Local Mailpit (Mock)',
                'subscriberCount' => Contact::whereIn('newsletter_id', $newsletterIds)->count(),
                'campaignsCount' => Campaign::whereIn('newsletter_id', $newsletterIds)->count(),
                'createdAt' => $account->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
            'currentNewsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
                'subscriberCount' => Contact::where('newsletter_id', $newsletter->id)->count(),
            ] : null,
            'recentActivity' => $recentActivity,
            'blockers' => [
                'hasInFlightCampaigns' => $sendingCampaigns->isNotEmpty(),
                'inFlightCampaigns' => $sendingCampaigns,
            ],
        ]);
    });

    Route::get('/account/export', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $account = CurrentWorkspace::account($request);
        if (! $user || ! $account) {
            abort(404, 'Account not found.');
        }

        $newsletters = Newsletter::where('account_id', $account->id)->get();
        $newsletterIds = $newsletters->pluck('id');

        $campaigns = Campaign::whereIn('newsletter_id', $newsletterIds)->get()->map(fn ($c) => [
            'id' => $c->id,
            'subject' => $c->subject,
            'subject_b' => $c->subject_b,
            'ab_test_enabled' => (bool) $c->ab_test_enabled,
            'ab_winner' => $c->ab_winner,
            'format' => $c->format,
            'status' => $c->status,
            'sent_count' => $c->sent_count,
            'open_count' => $c->open_count,
            'click_count' => $c->click_count,
            'bounce_count' => $c->bounce_count,
            'complaint_count' => $c->complaint_count,
            'sent_at' => $c->sent_at?->toIso8601String(),
            'html' => $c->html,
        ]);

        $contacts = Contact::whereIn('newsletter_id', $newsletterIds)->get()->map(fn ($contact) => [
            'id' => $contact->id,
            'email' => $contact->email,
            'status' => $contact->status,
            'created_at' => $contact->created_at?->toIso8601String(),
            'confirmed_at' => $contact->confirmed_at?->toIso8601String(),
            'last_engaged_at' => $contact->last_engaged_at?->toIso8601String(),
        ]);

        $domains = Domain::where('account_id', $account->id)->get()->map(fn ($d) => [
            'domain' => $d->domain,
            'status' => $d->status,
            'dkim_verified' => (bool) $d->dkim_verified,
            'spf_verified' => (bool) $d->spf_verified,
            'dmarc_verified' => (bool) $d->dmarc_verified,
        ]);

        $activity = ActivityLog::where('account_id', $account->id)->latest()->take(200)->get();

        $exportPayload = [
            'app' => 'SpiggleMail',
            'version' => '1.0.0',
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'owner_email' => $user->email,
                'status' => $account->status,
                'created_at' => $account->created_at?->toIso8601String(),
            ],
            'newsletters' => $newsletters,
            'domains' => $domains,
            'campaigns' => $campaigns,
            'contacts' => $contacts,
            'activity_logs' => $activity,
        ];

        $filename = 'spigglemail-account-export-' . substr($account->id, 0, 8) . '-' . date('Y-m-d') . '.json';

        ActivityLog::record(
            action: 'account.exported',
            description: 'Complete account data archive downloaded via GDPR data portability export.',
            accountId: $account->id,
            userId: $user->id,
            request: $request
        );

        return response()->streamDownload(function () use ($exportPayload) {
            echo json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    });

    Route::post('/account/profile', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $user->update(['name' => $validated['name']]);

        return back()->with('success', 'Profile updated successfully.');
    });

    Route::post('/account/delete', function (Request $request) {
        $user = CurrentWorkspace::user($request);
        $account = CurrentWorkspace::account($request);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        if (strtolower(trim($validated['email'])) !== strtolower(trim($user->email))) {
            return back()->withErrors(['email' => 'Please type your exact email address to confirm deletion.']);
        }

        $newsletterIds = $account ? Newsletter::where('account_id', $account->id)->pluck('id') : collect();
        $inFlight = Campaign::whereIn('newsletter_id', $newsletterIds)->where('status', 'sending')->exists();
        if ($inFlight) {
            return back()->withErrors(['email' => 'Cannot delete account while campaigns are actively sending. Please pause in-flight campaigns first.']);
        }

        if ($account) {
            $account->update([
                'status' => 'paused',
                'pause_reason' => 'Account scheduled for deletion by owner.',
                'paused_at' => now(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/signin')->with('success', 'Account scheduled for deletion. You have a 30-day grace period to restore by signing in.');
    });

    // Multi-Newsletter Tenancy Management Routes
    Route::post('/newsletters/select', function (Request $request) {
        $account = CurrentWorkspace::account($request);
        $validated = $request->validate([
            'newsletter_id' => 'required|string',
        ]);

        $newsletter = Newsletter::where('account_id', $account?->id)->findOrFail($validated['newsletter_id']);
        $request->session()->put('selected_newsletter_id', $newsletter->id);

        return back()->with('success', "Active publication switched to '{$newsletter->name}'.");
    });

    Route::post('/newsletters', function (Request $request) {
        $account = CurrentWorkspace::account($request, autoProvision: true);

        $count = Newsletter::where('account_id', $account->id)->count();
        if ($count >= 5) {
            return back()->withErrors(['limit' => 'You have reached the maximum limit of 5 newsletters per account.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|regex:/^[a-z0-9\-]+$/|unique:newsletters,slug',
            'from_name' => 'required|string|max:100',
            'from_email' => 'required|email|max:150',
            'postal_address' => 'required|string|max:300',
            'description' => 'nullable|string|max:500',
            'double_opt_in' => 'nullable|boolean',
            'welcome_enabled' => 'nullable|boolean',
        ]);

        $newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => $validated['name'],
            'slug' => strtolower($validated['slug']),
            'from_name' => $validated['from_name'],
            'from_email' => strtolower($validated['from_email']),
            'postal_address' => $validated['postal_address'],
            'description' => $validated['description'] ?? null,
            'double_opt_in' => (bool) ($validated['double_opt_in'] ?? true),
            'welcome_enabled' => (bool) ($validated['welcome_enabled'] ?? false),
            'welcome_subject' => "Welcome to {$validated['name']}!",
            'welcome_markdown' => "Thanks for joining {$validated['name']}.\n\nThe next issue lands in your inbox. Hit reply to say hi.",
            'list_cleanup_enabled' => true,
        ]);

        $request->session()->put('selected_newsletter_id', $newsletter->id);

        return back()->with('success', "Newsletter '{$newsletter->name}' created successfully!");
    });

    Route::patch('/newsletters/{id}', function (Request $request, string $id) {
        $account = CurrentWorkspace::account($request);
        $newsletter = Newsletter::where('account_id', $account?->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'from_name' => 'sometimes|string|max:100',
            'from_email' => 'sometimes|email|max:150',
            'postal_address' => 'sometimes|string|max:300',
            'description' => 'nullable|string|max:500',
            'reply_to' => 'nullable|email|max:150',
            'double_opt_in' => 'sometimes|boolean',
            'welcome_enabled' => 'sometimes|boolean',
            'welcome_subject' => 'nullable|string|max:200',
            'welcome_markdown' => 'nullable|string',
            'confirmation_subject' => 'nullable|string|max:200',
            'confirmation_markdown' => 'nullable|string',
            'list_cleanup_enabled' => 'sometimes|boolean',
        ]);

        $newsletter->update($validated);

        return back()->with('success', "Settings updated for '{$newsletter->name}'.");
    });
});

// --------------------------------------------------------------------------
// Public Web Archive Routes (/archive/{slug} and /archive/{slug}/p/{post})
// --------------------------------------------------------------------------

Route::get('/archive/{slug}', function (string $slug) {
    $newsletter = Newsletter::where('slug', $slug)->firstOrFail();
    $totalReaders = Contact::where('newsletter_id', $newsletter->id)->where('status', 'subscribed')->count();

    // Strictly expose only sent, listed issues (never leak drafts or in-flight campaigns!)
    $publishedIssues = Campaign::where('newsletter_id', $newsletter->id)
        ->whereNotNull('archive_slug')
        ->where('archive_listed', true)
        ->where('status', 'sent')
        ->orderBy('sent_at', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();

    $mapIssue = static fn ($c) => [
        'id' => $c->id,
        'subject' => $c->subject,
        'previewText' => $c->preview_text,
        'archiveSlug' => $c->archive_slug,
        'archivePinned' => (bool) $c->archive_pinned,
        'webViews' => (int) $c->web_views,
        'sentAt' => $c->sent_at?->toIso8601String(),
        'createdAt' => $c->created_at->toIso8601String(),
    ];

    $pinnedIssues = $publishedIssues->where('archive_pinned', true)->values()->map($mapIssue);
    $allIssues = $publishedIssues->map($mapIssue);

    return Inertia::render('Archive/Index', [
        'newsletter' => [
            'id' => $newsletter->id,
            'name' => $newsletter->name,
            'slug' => $newsletter->slug,
            'fromName' => $newsletter->from_name,
            'fromEmail' => $newsletter->from_email,
            'postalAddress' => $newsletter->postal_address,
            'description' => $newsletter->description ?? 'Field notes, deep dives, and architectural dispatches from the front lines.',
            'subscriberCount' => $totalReaders,
        ],
        'pinnedIssues' => $pinnedIssues,
        'latestIssue' => $allIssues->first(),
        'allIssues' => $allIssues,
        'totalReaders' => $totalReaders,
    ]);
});

Route::get('/archive/{slug}/p/{post}', function (Request $request, string $slug, string $post) {
    $newsletter = Newsletter::where('slug', $slug)->firstOrFail();
    $campaign = Campaign::where('newsletter_id', $newsletter->id)
        ->where('archive_slug', $post)
        ->where('status', 'sent')
        ->where('archive_listed', true)
        ->firstOrFail();

    if (! UrlSafetyValidator::isBotScannerRequest($request)) {
        $campaign->increment('web_views');
    }

    $moreIssues = Campaign::where('newsletter_id', $newsletter->id)
        ->where('id', '!=', $campaign->id)
        ->whereNotNull('archive_slug')
        ->where('archive_listed', true)
        ->where('status', 'sent')
        ->orderBy('sent_at', 'desc')
        ->orderBy('created_at', 'desc')
        ->take(4)
        ->get()
        ->map(fn ($c) => [
            'id' => $c->id,
            'subject' => $c->subject,
            'previewText' => $c->preview_text,
            'archiveSlug' => $c->archive_slug,
            'archivePinned' => (bool) $c->archive_pinned,
            'webViews' => (int) $c->web_views,
            'sentAt' => $c->sent_at?->toIso8601String(),
            'createdAt' => $c->created_at->toIso8601String(),
        ]);

    return Inertia::render('Archive/Show', [
        'newsletter' => [
            'id' => $newsletter->id,
            'name' => $newsletter->name,
            'slug' => $newsletter->slug,
            'fromName' => $newsletter->from_name,
            'fromEmail' => $newsletter->from_email,
            'postalAddress' => $newsletter->postal_address,
            'description' => $newsletter->description,
            'subscriberCount' => Contact::where('newsletter_id', $newsletter->id)->where('status', 'subscribed')->count(),
        ],
        'issue' => [
            'id' => $campaign->id,
            'newsletterId' => $campaign->newsletter_id,
            'subject' => $campaign->subject,
            'previewText' => $campaign->preview_text,
            'format' => $campaign->format ?? 'rich',
            'status' => $campaign->status,
            'archiveSlug' => $campaign->archive_slug,
            'archivePinned' => (bool) $campaign->archive_pinned,
            'archiveListed' => (bool) $campaign->archive_listed,
            'webViews' => (int) $campaign->web_views,
            'html' => HtmlSanitizer::sanitizeForArchive($campaign->html),
            'sentAt' => $campaign->sent_at?->toIso8601String(),
            'createdAt' => $campaign->created_at->toIso8601String(),
        ],
        'moreIssues' => $moreIssues,
    ]);
});

// --------------------------------------------------------------------------
// Public Reader Experience: Unsubscribe, Double Opt-in, Keep, Tracking, Embed
// --------------------------------------------------------------------------

Route::get('/unsubscribe', function (Request $request) {
    $token = (string) $request->query('token', '');
    if ($token === '') {
        abort(404, 'Invalid unsubscribe link.');
    }

    $decoded = UnsubscribeHeaderService::decodeUnsubscribeToken($token);
    if (! $decoded) {
        abort(400, 'Expired or corrupted unsubscribe token.');
    }

    $contact = DB::table('contacts')->where('id', $decoded['contact_id'])->first();
    $newsletter = DB::table('newsletters')->where('slug', $decoded['slug'])->first();

    if (! $contact || ! $newsletter) {
        abort(404, 'Subscriber or newsletter not found.');
    }

    return Inertia::render('Public/Unsubscribe', [
        'newsletter' => [
            'id' => $newsletter->id,
            'name' => $newsletter->name,
            'slug' => $newsletter->slug,
            'fromEmail' => $newsletter->from_email,
        ],
        'contact' => [
            'id' => $contact->id,
            'email' => $contact->email,
            'status' => $contact->status,
        ],
        'token' => $token,
        'alreadyUnsubscribed' => $contact->status === 'unsubscribed',
    ]);
});

$performUnsubscribe = function (string $contactId): void {
    $contact = DB::table('contacts')->where('id', $contactId)->first();
    if (! $contact) {
        return;
    }

    DB::table('contacts')->where('id', $contact->id)->update([
        'status' => 'unsubscribed',
        'unsubscribed_at' => now(),
        'updated_at' => now(),
    ]);

    $newsletter = DB::table('newsletters')->where('id', $contact->newsletter_id)->first();
    Suppression::firstOrCreate(
        [
            'account_id' => $newsletter?->account_id,
            'email_hash' => hash_hmac('sha256', strtolower(trim($contact->email)), (string) config('app.key')),
        ],
        [
            'reason' => 'manual',
        ]
    );
};

Route::post('/unsubscribe', function (Request $request) use ($performUnsubscribe) {
    $validated = $request->validate([
        'token' => 'required|string',
    ]);

    $decoded = UnsubscribeHeaderService::decodeUnsubscribeToken($validated['token']);
    if (! $decoded) {
        return back()->withErrors(['token' => 'Invalid or expired token.']);
    }

    $performUnsubscribe($decoded['contact_id']);

    return back()->with('success', 'You have been unsubscribed.');
});

// RFC 8058 One-Click HTTP POST endpoint (CSRF-exempt in bootstrap/app.php)
Route::post('/api/v1/unsubscribe/{token}', function (string $token) use ($performUnsubscribe) {
    $decoded = UnsubscribeHeaderService::decodeUnsubscribeToken($token);
    if (! $decoded) {
        return response()->json(['error' => 'Invalid token'], 400);
    }

    $performUnsubscribe($decoded['contact_id']);

    return response('Unsubscribed successfully.', 200)
        ->header('Content-Type', 'text/plain');
});

Route::match(['get', 'post'], '/confirm', function (Request $request) {
    $token = (string) ($request->input('token') ?? $request->query('token', ''));
    if ($token === '') {
        return Inertia::render('Public/Confirm', [
            'status' => 'invalid',
            'error' => 'No confirmation token provided.',
        ]);
    }

    // Do not allow corporate link scanners / HEAD requests to auto-confirm or burn single-use tokens
    if (UrlSafetyValidator::isBotScannerRequest($request)) {
        return response('Confirmation Ready', 200)->header('Content-Type', 'text/plain');
    }

    $contact = Contact::with('newsletter')->where('confirmation_token', $token)->first();
    if (! $contact) {
        $decoded = UnsubscribeHeaderService::decodeUnsubscribeToken($token);
        if ($decoded) {
            $contact = Contact::with('newsletter')->find($decoded['contact_id']);
        }
    }

    if (! $contact) {
        return Inertia::render('Public/Confirm', [
            'status' => 'invalid',
            'error' => 'This confirmation link has expired or has already been verified.',
        ]);
    }

    $newsletter = $contact->newsletter;
    $wasPending = $contact->status === 'pending';

    $contact->status = 'subscribed';
    $contact->confirmed_at = now();
    $contact->confirmation_token = null;
    $contact->save();

    if ($wasPending && $newsletter && $newsletter->welcome_enabled) {
        SendWelcomeEmailJob::dispatch($contact->id);
    }

    return Inertia::render('Public/Confirm', [
        'status' => 'confirmed',
        'newsletter' => [
            'id' => $newsletter?->id ?? 'nl_01',
            'name' => $newsletter?->name ?? 'Our Newsletter',
            'slug' => $newsletter?->slug ?? 'newsletter',
        ],
        'contact' => [
            'id' => $contact->id,
            'email' => $contact->email,
        ],
    ]);
});

Route::match(['get', 'post'], '/keep', function (Request $request) {
    $token = (string) ($request->input('token') ?? $request->query('token', ''));
    if ($token === '') {
        return Inertia::render('Public/Keep', [
            'status' => 'invalid',
            'error' => 'No re-engagement token provided.',
        ]);
    }

    if (UrlSafetyValidator::isBotScannerRequest($request)) {
        return response('Keep Ready', 200)->header('Content-Type', 'text/plain');
    }

    $contact = Contact::with('newsletter')->where('keep_token', $token)->first();
    if (! $contact) {
        return Inertia::render('Public/Keep', [
            'status' => 'invalid',
            'error' => 'This link has expired or has already been used.',
        ]);
    }

    $newsletter = $contact->newsletter;
    $contact->recordEngagement('keep');
    $contact->keep_token = null;
    $contact->last_chance_at = null;
    $contact->status = 'subscribed';
    $contact->save();

    return Inertia::render('Public/Keep', [
        'status' => 'restored',
        'newsletter' => [
            'name' => $newsletter?->name ?? 'Our Newsletter',
            'slug' => $newsletter?->slug ?? 'newsletter',
        ],
        'contact' => [
            'email' => $contact->email,
        ],
    ]);
});

if (! defined('TRACKING_PIXEL_GIF')) {
    define('TRACKING_PIXEL_GIF', 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

Route::get('/o/{token}', function (Request $request, string $token) {
    if (! UrlSafetyValidator::isBotScannerRequest($request)) {
        $messageId = TrackingTokenService::decodeOpenToken($token);
        if ($messageId) {
            $msg = DB::table('messages')->where('id', $messageId)->first();
            if ($msg && ! $msg->opened_at) {
                DB::table('messages')->where('id', $messageId)->update([
                    'opened_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($msg->campaign_id) {
                    DB::table('campaigns')->where('id', $msg->campaign_id)->increment('open_count');
                    if (($msg->ab_variant ?? null) === 'a') {
                        DB::table('campaigns')->where('id', $msg->campaign_id)->increment('variant_a_opens');
                    } elseif (($msg->ab_variant ?? null) === 'b') {
                        DB::table('campaigns')->where('id', $msg->campaign_id)->increment('variant_b_opens');
                    }
                }

                $contact = Contact::find($msg->contact_id);
                if ($contact) {
                    $contact->recordEngagement('open');
                }
            }
        }
    }

    return response(base64_decode(TRACKING_PIXEL_GIF))
        ->header('Content-Type', 'image/gif')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
});

Route::get('/c/{token}', function (Request $request, string $token) {
    $decoded = TrackingTokenService::decodeClickToken($token);
    if (! $decoded) {
        abort(404, 'Invalid or expired tracking link.');
    }

    $messageId = $decoded['message_id'];
    $targetUrl = $decoded['url'];

    if (! UrlSafetyValidator::isBotScannerRequest($request)) {
        $msg = DB::table('messages')->where('id', $messageId)->first();
        if ($msg) {
            DB::table('message_clicks')->insert([
                'id' => (string) Str::ulid(),
                'campaign_id' => $msg->campaign_id,
                'message_id' => $msg->id,
                'contact_id' => $msg->contact_id,
                'url' => substr($targetUrl, 0, 2048),
                'created_at' => now(),
            ]);

            if (! $msg->clicked_at) {
                DB::table('messages')->where('id', $messageId)->update([
                    'clicked_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($msg->campaign_id) {
                    DB::table('campaigns')->where('id', $msg->campaign_id)->increment('click_count');
                }

                $contact = Contact::find($msg->contact_id);
                if ($contact) {
                    $contact->recordEngagement('click');
                }
            }
        }
    }

    return redirect()->away($targetUrl, 302, [
        'Cache-Control' => 'no-store, max-age=0',
    ]);
});

Route::get('/embed/{slug}', function (Request $request, string $slug) {
    $newsletter = DB::table('newsletters')->where('slug', $slug)->first();
    if (! $newsletter) {
        abort(404, 'Newsletter not found.');
    }

    return Inertia::render('Public/EmbedWidget', [
        'newsletter' => [
            'id' => $newsletter->id,
            'name' => $newsletter->name,
            'slug' => $newsletter->slug,
        ],
        'title' => $request->query('title'),
        'subtitle' => $request->query('subtitle'),
        'buttonText' => $request->query('btnText', 'Subscribe'),
        'buttonColor' => $request->query('btnColor', '#4f46e5'),
        'theme' => $request->query('theme', 'dark'),
    ]);
});

Route::post('/embed/subscribe', function (Request $request) {
    $respond = static function (Request $req, bool $ok, string $message, int $httpStatus = 200) {
        if ($req->expectsJson()) {
            return response()->json($ok ? [
                'success' => true,
                'message' => $message,
            ] : [
                'success' => false,
                'error' => $message,
            ], $ok ? 200 : $httpStatus);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    };

    // Bot honeypot trap: if hidden field is filled, pretend success without touching SES or DB
    if ($request->filled('website') || $request->filled('_hp')) {
        return $respond($request, true, 'Check your email to confirm your subscription!');
    }

    $validated = $request->validate([
        'newsletter_slug' => 'required|string',
        'email' => 'required|email|max:150',
    ]);

    $newsletter = Newsletter::where('slug', $validated['newsletter_slug'])->first();
    if (! $newsletter) {
        return $respond($request, false, 'Newsletter publication not found.', 404);
    }

    $dropReason = EmailHygieneService::checkEmail($validated['email']);
    if ($dropReason !== null) {
        return $respond($request, false, 'Invalid or disposable email address (' . $dropReason . ').', 422);
    }

    $cleanEmail = strtolower(trim($validated['email']));
    $hash = hash_hmac('sha256', $cleanEmail, (string) config('app.key'));
    if (Suppression::where('email_hash', $hash)->exists()) {
        return $respond($request, false, 'This email address is suppressed from receiving further mail.', 422);
    }

    $requiresConfirmation = (bool) $newsletter->double_opt_in;
    $existing = Contact::where('newsletter_id', $newsletter->id)
        ->where('email', $cleanEmail)
        ->first();

    if ($existing) {
        if (in_array($existing->status, ['bounced', 'complained'], true)) {
            return $respond($request, false, 'This email address cannot be subscribed due to prior delivery failures.', 422);
        }

        if ($existing->status === 'unsubscribed') {
            if ($requiresConfirmation) {
                $existing->status = 'pending';
                $existing->confirmation_token = $existing->confirmation_token ?: Str::random(48);
                $existing->save();
                if (Cache::add("spiggle_confirm_resend:{$existing->id}", true, 300)) {
                    SendConfirmationEmailJob::dispatch($existing->id);
                }

                return $respond($request, true, 'Check your email to confirm re-subscribing!');
            }

            $existing->status = 'subscribed';
            $existing->subscribed_at = now();
            $existing->save();

            return $respond($request, true, 'Welcome back! You have resubscribed.');
        }

        if ($existing->status === 'pending') {
            if (Cache::add("spiggle_confirm_resend:{$existing->id}", true, 300)) {
                SendConfirmationEmailJob::dispatch($existing->id);
            }

            return $respond($request, true, 'A confirmation email has been sent to your inbox.');
        }

        return $respond($request, true, 'You are already subscribed!');
    }

    $status = $requiresConfirmation ? 'pending' : 'subscribed';

    $contact = Contact::create([
        'id' => (string) Str::ulid(),
        'newsletter_id' => $newsletter->id,
        'email' => $cleanEmail,
        'status' => $status,
        'source' => 'embed_widget',
        'confirmation_token' => $requiresConfirmation ? Str::random(48) : null,
        'confirmed_at' => $requiresConfirmation ? null : now(),
        'subscribed_at' => now(),
    ]);

    if ($requiresConfirmation) {
        Cache::put("spiggle_confirm_resend:{$contact->id}", true, 300);
        SendConfirmationEmailJob::dispatch($contact->id);

        return $respond($request, true, 'Check your email to confirm your subscription!');
    }

    if ($newsletter->welcome_enabled) {
        SendWelcomeEmailJob::dispatch($contact->id);
    }

    return $respond($request, true, 'Thank you for subscribing!');
})->middleware('throttle:public-subscribe');

// --------------------------------------------------------------------------
// Public Syndication: RSS/Atom Feed, Sitemap & AI Discovery Endpoints
// --------------------------------------------------------------------------

Route::get('/llms.txt', function () {
    $baseUrl = rtrim((string) config('app.url', 'http://localhost:8087'), '/');
    $content = implode("\n", [
        '# SpiggleMail',
        '',
        '> High-deliverability newsletter platform and dispatch engine engineered for modern creators, companies, and autonomous AI agents.',
        '',
        "REST API: {$baseUrl}/api/v1, with Authorization: Bearer <API key>",
        "MCP Server (JSON-RPC 2.0): {$baseUrl}/api/mcp, with Authorization: Bearer <API key>",
        '',
        '## Endpoints',
        '',
        "- [Subscribers API]({$baseUrl}/api/v1/contacts): GET /api/v1/contacts, POST /api/v1/contacts, GET /api/v1/contacts/export.csv",
        "- [Campaigns API]({$baseUrl}/api/v1/campaigns): GET /api/v1/campaigns, POST /api/v1/campaigns",
        "- [Deliverability API]({$baseUrl}/api/v1/deliverability): GET /api/v1/deliverability",
        "- [MCP Server]({$baseUrl}/api/mcp): Tools for list_campaigns, create_campaign, send_test_email, list_contacts, add_contact, get_deliverability_stats, list_newsletters",
        "- [OpenAPI 3.0 Specification]({$baseUrl}/api/v1/openapi.json)",
        "- [Full Documentation]({$baseUrl}/llms-full.txt)",
    ]);

    return response($content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

Route::get('/llms-full.txt', function () {
    $baseUrl = rtrim((string) config('app.url', 'http://localhost:8087'), '/');
    $content = implode("\n", [
        '# SpiggleMail API & MCP Reference',
        '',
        'Everything an author or autonomous agent does in SpiggleMail works through the REST API and the Model Context Protocol (MCP) server with identical rate-limiting, safety slicing, and deliverability protection.',
        '',
        '## Authentication',
        '',
        'Create an API key in the dashboard (/api-keys) and send it as `Authorization: Bearer <key>`.',
        '- Full access keys can perform all actions.',
        '- Send-only keys can dispatch campaigns.',
        '- Read-only keys can inspect analytics and contacts.',
        '',
        '## Model Context Protocol (MCP) Tools',
        '',
        '- `list_campaigns`: Returns recent campaigns with delivery, open, and click metrics.',
        '- `create_campaign`: Drafts a new issue with subject, preview text, and markdown/HTML body.',
        '- `send_test_email`: Dispatches a live rendered preview of an issue to a test inbox.',
        '- `list_contacts`: Returns subscribers filtered by status (subscribed, pending, dormant, unsubscribed, bounced).',
        '- `add_contact`: Subscribes an email to the active newsletter list.',
        '- `get_deliverability_stats`: Returns real-time bounce rates, complaint rates, and safety status.',
        '- `list_newsletters`: Lists publications under the account.',
        '',
        '## REST API Reference',
        '',
        '### Contacts',
        "`GET /api/v1/contacts`: Paginated list of subscribers.",
        "`POST /api/v1/contacts`: Add or update a subscriber with hygiene and suppression checks.",
        "`GET /api/v1/contacts/export.csv`: Export contacts as CSV.",
        '',
        '### Deliverability & Webhooks',
        "`POST /api/webhooks/ses`: Inbound AWS SES bounce, complaint, and delivery SNS notifications.",
        "`POST /api/webhooks/postmark`: Inbound Postmark delivery event receiver.",
        '',
        '### Syndication',
        "`GET /archive/{slug}/feed.xml`: RSS 2.0 public newsletter feed.",
        "`GET /archive/{slug}/sitemap.xml`: XML search engine sitemap.",
    ]);

    return response($content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

Route::get('/archive/{slug}/feed.xml', function (string $slug) {
    $newsletter = Newsletter::where('slug', $slug)->firstOrFail();
    $campaigns = Campaign::where('newsletter_id', $newsletter->id)
        ->where('status', 'sent')
        ->where('archive_listed', true)
        ->orderByDesc('sent_at')
        ->limit(30)
        ->get();

    $appUrl = rtrim((string) config('app.url', 'http://localhost:8087'), '/');
    $safeSlug = rawurlencode($slug);
    $feedUrl = htmlspecialchars("{$appUrl}/archive/{$safeSlug}/feed.xml", ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $archiveUrl = htmlspecialchars("{$appUrl}/archive/{$safeSlug}", ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $channelTitle = htmlspecialchars((string) $newsletter->name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $channelDesc = htmlspecialchars((string) ($newsletter->description ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $itemsXml = '';
    foreach ($campaigns as $campaign) {
        $postSlug = rawurlencode((string) ($campaign->archive_slug ?: $campaign->id));
        $itemUrl = htmlspecialchars("{$appUrl}/archive/{$safeSlug}/p/{$postSlug}", ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $pubDate = ($campaign->sent_at ?? now())->toRssString();
        $description = htmlspecialchars(
            (string) ($campaign->preview_text ?: strip_tags(substr($campaign->html ?: '', 0, 250))),
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );
        $title = htmlspecialchars((string) $campaign->subject, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $itemsXml .= <<<XML
    <item>
      <title>{$title}</title>
      <link>{$itemUrl}</link>
      <guid isPermaLink="true">{$itemUrl}</guid>
      <pubDate>{$pubDate}</pubDate>
      <description>{$description}</description>
    </item>

XML;
    }

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>{$channelTitle}</title>
    <link>{$archiveUrl}</link>
    <description>{$channelDesc}</description>
    <language>en-us</language>
    <atom:link href="{$feedUrl}" rel="self" type="application/rss+xml"/>
{$itemsXml}  </channel>
</rss>
XML;

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
});

Route::get('/archive/{slug}/sitemap.xml', function (string $slug) {
    $newsletter = Newsletter::where('slug', $slug)->firstOrFail();
    $campaigns = Campaign::where('newsletter_id', $newsletter->id)
        ->where('status', 'sent')
        ->where('archive_listed', true)
        ->orderByDesc('sent_at')
        ->get();

    $appUrl = rtrim((string) config('app.url', 'http://localhost:8087'), '/');
    $safeSlug = rawurlencode($slug);
    $archiveUrl = htmlspecialchars("{$appUrl}/archive/{$safeSlug}", ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $urlsXml = <<<XML
  <url>
    <loc>{$archiveUrl}</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

XML;

    foreach ($campaigns as $campaign) {
        $postSlug = rawurlencode((string) ($campaign->archive_slug ?: $campaign->id));
        $itemUrl = htmlspecialchars("{$appUrl}/archive/{$safeSlug}/p/{$postSlug}", ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lastmod = ($campaign->sent_at ?? now())->toDateString();
        $urlsXml .= <<<XML
  <url>
    <loc>{$itemUrl}</loc>
    <lastmod>{$lastmod}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>

XML;
    }

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$urlsXml}</urlset>
XML;

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=utf-8');
});
