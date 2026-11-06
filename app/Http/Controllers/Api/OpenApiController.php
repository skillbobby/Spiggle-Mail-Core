<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $baseUrl = url('/api/v1');

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'SpiggleMail API',
                'description' => 'High-deliverability newsletter infrastructure REST API and MCP protocol endpoints.',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'SpiggleMail Support',
                    'email' => 'support@spiggle-mail.local',
                ],
            ],
            'servers' => [
                [
                    'url' => $baseUrl,
                    'description' => 'Primary API Endpoint',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Authenticate via Bearer API Key generated in Dashboard (/api-keys)',
                    ],
                ],
                'schemas' => [
                    'Contact' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'example' => '01m6q32ksg5ject72rq6sznn45'],
                            'newsletterId' => ['type' => 'string', 'example' => '01M6M1N0SN7MTZJY6BNTYCXB9H'],
                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'subscriber@example.com'],
                            'status' => ['type' => 'string', 'enum' => ['subscribed', 'pending', 'unsubscribed', 'bounced', 'complained']],
                            'source' => ['type' => 'string', 'example' => 'api'],
                            'openCount' => ['type' => 'integer', 'example' => 0],
                            'clickCount' => ['type' => 'integer', 'example' => 0],
                            'lastSentAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'lastOpenedAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'subscribedAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Campaign' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'example' => '01m6m2yxj7w81cxnyc78ss11xb'],
                            'newsletterId' => ['type' => 'string'],
                            'subject' => ['type' => 'string', 'example' => 'Production Release Notes'],
                            'previewText' => ['type' => 'string', 'nullable' => true],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'scheduled', 'sending', 'sent', 'paused']],
                            'sentCount' => ['type' => 'integer', 'example' => 1420],
                            'openCount' => ['type' => 'integer', 'example' => 635],
                            'clickCount' => ['type' => 'integer', 'example' => 184],
                            'scheduledAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'sentAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'DeliverabilityStats' => [
                        'type' => 'object',
                        'properties' => [
                            'healthScore' => ['type' => 'integer', 'example' => 98],
                            'healthStatus' => ['type' => 'string', 'enum' => ['optimal', 'good', 'warning']],
                            'metrics' => [
                                'type' => 'object',
                                'properties' => [
                                    'activeSubscribers' => ['type' => 'integer', 'example' => 2450],
                                    'suppressedCount' => ['type' => 'integer', 'example' => 12],
                                    'sentLast30Days' => ['type' => 'integer', 'example' => 14500],
                                    'bounceRatePercent' => ['type' => 'number', 'example' => 0.42],
                                    'complaintRatePercent' => ['type' => 'number', 'example' => 0.01],
                                ],
                            ],
                            'circuitBreakers' => [
                                'type' => 'object',
                                'properties' => [
                                    'bounceThreshold' => ['type' => 'string', 'example' => '5.0%'],
                                    'complaintThreshold' => ['type' => 'string', 'example' => '0.1%'],
                                    'status' => ['type' => 'string', 'enum' => ['healthy', 'tripped']],
                                ],
                            ],
                        ],
                    ],
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => ['type' => 'string', 'example' => 'unauthorized'],
                                    'message' => ['type' => 'string', 'example' => 'Missing Authorization Bearer token.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'security' => [
                ['BearerAuth' => []],
            ],
            'paths' => [
                '/contacts' => [
                    'get' => [
                        'summary' => 'List Subscribers',
                        'description' => 'Retrieve a paginated list of subscribers with delivery engagement metrics.',
                        'parameters' => [
                            [
                                'name' => 'status',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string', 'enum' => ['subscribed', 'pending', 'unsubscribed', 'bounced', 'complained']],
                            ],
                            [
                                'name' => 'search',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string'],
                            ],
                            [
                                'name' => 'per_page',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'integer', 'default' => 25],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Paginated contacts list'],
                            '401' => ['description' => 'Unauthorized'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Add or Update Subscriber',
                        'description' => 'Create or update subscriber with hygiene validation and suppression list filtering.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'newsletter_slug' => ['type' => 'string'],
                                            'status' => ['type' => 'string', 'enum' => ['subscribed', 'pending']],
                                            'source' => ['type' => 'string', 'default' => 'api'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Subscriber created or updated'],
                            '401' => ['description' => 'Unauthorized'],
                            '422' => ['description' => 'Hygiene check failed or suppressed email'],
                        ],
                    ],
                ],
                '/contacts/export.csv' => [
                    'get' => [
                        'summary' => 'Export Subscribers CSV',
                        'description' => 'Download streamable CSV of subscriber records.',
                        'parameters' => [
                            [
                                'name' => 'token',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string'],
                            ],
                            [
                                'name' => 'status',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'CSV file download',
                                'content' => [
                                    'text/csv' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                                ],
                            ],
                            '401' => ['description' => 'Unauthorized'],
                        ],
                    ],
                ],
                '/campaigns' => [
                    'get' => [
                        'summary' => 'List Campaigns',
                        'description' => 'Retrieve dispatched, scheduled, or draft campaigns.',
                        'parameters' => [
                            [
                                'name' => 'status',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string', 'enum' => ['draft', 'scheduled', 'sending', 'sent', 'paused']],
                            ],
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'integer', 'default' => 20],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'List of campaigns'],
                            '401' => ['description' => 'Unauthorized'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create Campaign',
                        'description' => 'Create a new draft or schedule an automated newsletter broadcast.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['subject'],
                                        'properties' => [
                                            'subject' => ['type' => 'string'],
                                            'preview_text' => ['type' => 'string'],
                                            'html' => ['type' => 'string'],
                                            'markdown' => ['type' => 'string'],
                                            'scheduled_at' => ['type' => 'string', 'format' => 'date-time'],
                                            'newsletter_slug' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Campaign created'],
                            '401' => ['description' => 'Unauthorized'],
                            '403' => ['description' => 'Forbidden (read_only key)'],
                        ],
                    ],
                ],
                '/deliverability' => [
                    'get' => [
                        'summary' => 'Deliverability & Health Metrics',
                        'description' => 'Inspect rolling health score, bounce and complaint rates, and safety circuit breakers.',
                        'responses' => [
                            '200' => ['description' => 'Deliverability metrics'],
                            '401' => ['description' => 'Unauthorized'],
                        ],
                    ],
                ],
            ],
        ];

        return response()->json($spec, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
