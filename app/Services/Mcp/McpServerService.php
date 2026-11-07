<?php

namespace App\Services\Mcp;

use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Services\Deliverability\EmailHygieneService;
use App\Services\Email\EmailProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class McpServerService
{
    public function handle(Request $request): JsonResponse
    {
        // 1. Strict Bearer API Key Authentication in ALL environments (no local/testing bypass)
        $bearerToken = $request->bearerToken();
        if (! $bearerToken) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => ['code' => -32000, 'message' => 'Unauthorized: Missing Authorization Bearer token.'],
            ], 401);
        }

        $keyHash = hash('sha256', $bearerToken);
        $apiKey = ApiKey::where('key_hash', $keyHash)->first();
        if (! $apiKey || ($apiKey->expires_at && $apiKey->expires_at->isPast())) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => ['code' => -32000, 'message' => 'Unauthorized: Invalid or expired Bearer API key.'],
            ], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $body = $request->json()->all();
        $id = $body['id'] ?? null;
        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];

        if ($request->isMethod('GET') && empty($method)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'serverInfo' => [
                        'name' => 'spiggle-mail',
                        'title' => 'SpiggleMail MCP Server',
                        'version' => '1.0.0',
                    ],
                    'instructions' => 'SpiggleMail MCP Server is active and operational. Send JSON-RPC 2.0 POST requests with methods: initialize, tools/list, tools/call.',
                    'toolsCount' => count($this->getToolDefinitions()),
                    'capabilities' => ['tools' => new \stdClass],
                ],
            ]);
        }

        switch ($method) {
            case 'initialize':
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => ['tools' => new \stdClass],
                        'serverInfo' => [
                            'name' => 'spiggle-mail',
                            'title' => 'SpiggleMail MCP Server',
                            'version' => '1.0.0',
                        ],
                        'instructions' => 'SpiggleMail runs high-deliverability newsletters. Use tools to manage campaigns, subscribers, and deliverability.',
                    ],
                ]);

            case 'tools/list':
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'tools' => $this->getToolDefinitions(),
                    ],
                ]);

            case 'tools/call':
                $toolName = (string) ($params['name'] ?? '');
                $arguments = (array) ($params['arguments'] ?? []);
                try {
                    $result = $this->executeTool($toolName, $arguments, $apiKey);

                    return response()->json([
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => [
                            'content' => [
                                ['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT)],
                            ],
                            'structuredContent' => $result,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    return response()->json([
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'error' => [
                            'code' => -32603,
                            'message' => $e->getMessage(),
                        ],
                    ]);
                }

            default:
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => "Method '{$method}' not found.",
                    ],
                ], 400);
        }
    }

    private function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'list_campaigns',
                'description' => 'Lists recent newsletter campaigns with delivery and engagement metrics.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Filter by status: draft, scheduled, sending, sent, paused'],
                        'limit' => ['type' => 'number', 'description' => 'Max number of campaigns to return (default 20)'],
                        'newsletter_slug' => ['type' => 'string', 'description' => 'Optional newsletter slug'],
                    ],
                ],
            ],
            [
                'name' => 'create_campaign',
                'description' => 'Drafts a new newsletter campaign.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Email subject line'],
                        'preview_text' => ['type' => 'string', 'description' => 'Preview snippet text'],
                        'markdown' => ['type' => 'string', 'description' => 'Campaign content in Markdown'],
                        'html' => ['type' => 'string', 'description' => 'Campaign content in HTML'],
                        'newsletter_slug' => ['type' => 'string', 'description' => 'Optional newsletter slug'],
                    ],
                    'required' => ['subject'],
                ],
            ],
            [
                'name' => 'list_contacts',
                'description' => 'Lists subscribers with status, open rate, and engagement data.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Filter by status: subscribed, pending, dormant, unsubscribed, bounced'],
                        'limit' => ['type' => 'number', 'description' => 'Limit (default 50)'],
                        'newsletter_slug' => ['type' => 'string', 'description' => 'Optional newsletter slug'],
                    ],
                ],
            ],
            [
                'name' => 'add_contact',
                'description' => 'Subscribes a contact to the active newsletter with hygiene and suppression checks.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => 'Subscriber email address'],
                        'status' => ['type' => 'string', 'description' => 'Status: subscribed or pending'],
                        'newsletter_slug' => ['type' => 'string', 'description' => 'Optional newsletter slug'],
                    ],
                    'required' => ['email'],
                ],
            ],
            [
                'name' => 'get_deliverability_stats',
                'description' => 'Retrieves deliverability health scores, bounce and complaint rates, and safety status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
            [
                'name' => 'send_test_email',
                'description' => 'Sends a test preview of a newsletter campaign to an author or test inbox before full dispatch.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Subject line for the test email'],
                        'html' => ['type' => 'string', 'description' => 'HTML content of the test issue'],
                        'recipient' => ['type' => 'string', 'description' => 'Email address to receive the preview'],
                    ],
                    'required' => ['subject', 'html', 'recipient'],
                ],
            ],
            [
                'name' => 'list_newsletters',
                'description' => 'Lists all publications under the authenticated account.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
        ];
    }

    private function resolveScopedNewsletter(ApiKey $apiKey, ?string $slug = null): Newsletter
    {
        if (! $apiKey->account_id) {
            throw new \RuntimeException('API key is not bound to an active workspace account.');
        }

        $query = Newsletter::where('account_id', $apiKey->account_id);
        if ($slug) {
            $matched = (clone $query)->where('slug', $slug)->first();
            if ($matched) {
                return $matched;
            }
        }

        $newsletter = $query->first();
        if (! $newsletter) {
            throw new \RuntimeException('No newsletter publication found for this account.');
        }

        return $newsletter;
    }

    private function executeTool(string $name, array $args, ApiKey $apiKey): array
    {
        if ($name === 'list_newsletters') {
            return Newsletter::where('account_id', $apiKey->account_id)->get()->map(fn ($n) => [
                'id' => $n->id,
                'name' => $n->name,
                'slug' => $n->slug,
                'fromEmail' => $n->from_email,
                'doubleOptIn' => (bool) $n->double_opt_in,
                'welcomeEnabled' => (bool) $n->welcome_enabled,
            ])->all();
        }

        $newsletter = $this->resolveScopedNewsletter(
            $apiKey,
            ! empty($args['newsletter_slug']) ? (string) $args['newsletter_slug'] : null
        );

        switch ($name) {
            case 'list_campaigns':
                $query = Campaign::where('newsletter_id', $newsletter->id)->orderByDesc('created_at');
                if (! empty($args['status'])) {
                    $query->where('status', $args['status']);
                }
                $limit = min(50, max(1, (int) ($args['limit'] ?? 20)));

                return $query->limit($limit)->get()->map(fn ($c) => [
                    'id' => $c->id,
                    'subject' => $c->subject,
                    'status' => $c->status,
                    'sentCount' => $c->sent_count,
                    'openCount' => $c->open_count,
                    'clickCount' => $c->click_count,
                    'sentAt' => $c->sent_at?->toIso8601String(),
                ])->all();

            case 'create_campaign':
                if ($apiKey->role === 'read_only') {
                    throw new \RuntimeException('API key has read_only access.');
                }
                $campaign = Campaign::create([
                    'id' => (string) Str::ulid(),
                    'newsletter_id' => $newsletter->id,
                    'subject' => (string) $args['subject'],
                    'preview_text' => $args['preview_text'] ?? null,
                    'markdown' => $args['markdown'] ?? null,
                    'html' => $args['html'] ?? (! empty($args['markdown']) ? Str::markdown((string) $args['markdown']) : null),
                    'status' => 'draft',
                ]);

                return [
                    'success' => true,
                    'campaign' => [
                        'id' => $campaign->id,
                        'subject' => $campaign->subject,
                        'status' => $campaign->status,
                    ],
                ];

            case 'list_contacts':
                $query = Contact::where('newsletter_id', $newsletter->id)->orderByDesc('created_at');
                if (! empty($args['status'])) {
                    $query->where('status', $args['status']);
                }
                $limit = min(100, max(1, (int) ($args['limit'] ?? 50)));

                return $query->limit($limit)->get()->map(fn ($ct) => [
                    'id' => $ct->id,
                    'email' => $ct->email,
                    'status' => $ct->status,
                    'openCount' => $ct->open_count,
                    'clickCount' => $ct->click_count,
                    'subscribedAt' => $ct->subscribed_at?->toIso8601String(),
                ])->all();

            case 'add_contact':
                if ($apiKey->role === 'read_only') {
                    throw new \RuntimeException('API key has read_only access.');
                }
                $cleanEmail = strtolower(trim((string) ($args['email'] ?? '')));
                $hygieneReason = EmailHygieneService::checkEmail($cleanEmail);
                if ($hygieneReason !== null) {
                    throw new \InvalidArgumentException("Email rejected by hygiene filter: {$hygieneReason}.");
                }

                $emailHash = hash_hmac('sha256', $cleanEmail, (string) config('app.key'));
                if (Suppression::where('email_hash', $emailHash)->exists()) {
                    throw new \RuntimeException('Email address is on the suppression list.');
                }

                $existing = Contact::where('newsletter_id', $newsletter->id)->where('email', $cleanEmail)->first();
                if ($existing && in_array($existing->status, ['bounced', 'complained', 'unsubscribed'], true)) {
                    throw new \RuntimeException("Cannot re-subscribe contact with status '{$existing->status}'.");
                }

                $requestedStatus = in_array($args['status'] ?? null, ['subscribed', 'pending'], true)
                    ? (string) $args['status']
                    : ($newsletter->double_opt_in ? 'pending' : 'subscribed');

                $contact = Contact::updateOrCreate(
                    ['newsletter_id' => $newsletter->id, 'email' => $cleanEmail],
                    [
                        'status' => $requestedStatus,
                        'source' => 'mcp',
                        'subscribed_at' => now(),
                    ]
                );

                return [
                    'success' => true,
                    'contact' => [
                        'id' => $contact->id,
                        'email' => $contact->email,
                        'status' => $contact->status,
                    ],
                ];

            case 'get_deliverability_stats':
                $totalSent = (int) Campaign::where('newsletter_id', $newsletter->id)->sum('sent_count');
                $totalBounced = (int) Campaign::where('newsletter_id', $newsletter->id)->sum('bounce_count');
                $totalComplaints = (int) Campaign::where('newsletter_id', $newsletter->id)->sum('complaint_count');
                $bounceRate = $totalSent > 0 ? round(($totalBounced / $totalSent) * 100, 2) : 0.0;
                $complaintRate = $totalSent > 0 ? round(($totalComplaints / $totalSent) * 100, 3) : 0.0;

                return [
                    'totalSent' => $totalSent,
                    'totalBounced' => $totalBounced,
                    'totalComplaints' => $totalComplaints,
                    'bounceRatePercent' => $bounceRate,
                    'complaintRatePercent' => $complaintRate,
                    'accountStatus' => $newsletter->account?->status ?? 'active',
                    'safetyStatus' => $bounceRate < 2.0 && $complaintRate < 0.08 ? 'healthy' : 'warning',
                ];

            case 'send_test_email':
                if ($apiKey->role === 'read_only') {
                    throw new \RuntimeException('API key has read_only access.');
                }
                $recipient = strtolower(trim((string) ($args['recipient'] ?? '')));
                if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Valid recipient email address is required.');
                }
                $emailProvider = app(EmailProviderInterface::class);
                $from = "{$newsletter->from_name} <{$newsletter->from_email}>";
                $testSubject = '[TEST] '.($args['subject'] ?? 'Test Newsletter');
                $testHtml = ($args['html'] ?? '<p>Test issue content.</p>').'<div style="margin-top:20px;padding:10px;background:#f3f4f6;color:#6b7280;font-size:11px;text-align:center;">This is a test preview sent via SpiggleMail MCP.</div>';
                $emailProvider->sendRaw($recipient, $from, $testSubject, $testHtml, ['X-Spiggle-Test' => 'true']);

                return [
                    'success' => true,
                    'message' => "Test email dispatched to {$recipient}.",
                ];

            default:
                throw new \RuntimeException("Unknown tool '{$name}'.");
        }
    }
}
