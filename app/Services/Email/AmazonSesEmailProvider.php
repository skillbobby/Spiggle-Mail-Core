<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Support\HtmlSanitizer;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AmazonSesEmailProvider implements EmailProviderInterface
{
    private ?SesV2Client $client = null;

    public function __construct(
        private readonly string $region,
        private readonly ?string $key = null,
        private readonly ?string $secret = null,
        private readonly ?string $configurationSet = null,
    ) {}

    public function getRegion(): string
    {
        return $this->region;
    }

    private function getClient(): SesV2Client
    {
        if ($this->client === null) {
            $config = [
                'version' => 'latest',
                'region' => $this->region,
            ];

            if (! empty($this->key) && ! empty($this->secret)) {
                $config['credentials'] = [
                    'key' => $this->key,
                    'secret' => $this->secret,
                ];
            }

            $this->client = new SesV2Client($config);
        }

        return $this->client;
    }

    public function send(array $payload): array
    {
        try {
            $client = $this->getClient();

            $plainText = ! empty($payload['text'])
                ? (string) $payload['text']
                : HtmlSanitizer::toPlainText((string) ($payload['html'] ?? ''));

            $emailContent = [
                'Simple' => [
                    'Subject' => [
                        'Data' => $payload['subject'],
                        'Charset' => 'UTF-8',
                    ],
                    'Body' => [
                        'Html' => [
                            'Data' => $payload['html'],
                            'Charset' => 'UTF-8',
                        ],
                        'Text' => [
                            'Data' => $plainText !== '' ? $plainText : strip_tags((string) $payload['html']),
                            'Charset' => 'UTF-8',
                        ],
                    ],
                ],
            ];

            $sendArgs = [
                'FromEmailAddress' => $payload['from'],
                'Destination' => [
                    'ToAddresses' => [$payload['to']],
                ],
                'Content' => $emailContent,
            ];

            if (! empty($payload['reply_to'])) {
                $sendArgs['ReplyToAddresses'] = [(string) $payload['reply_to']];
            }

            if (! empty($payload['headers'])) {
                $customHeaders = [];
                foreach ($payload['headers'] as $hName => $hVal) {
                    $customHeaders[] = [
                        'Name' => (string) $hName,
                        'Value' => (string) $hVal,
                    ];
                }
                $sendArgs['Content']['Simple']['Headers'] = $customHeaders;
            }

            $configSet = $payload['configuration_set'] ?? $this->configurationSet;
            if (! empty($configSet)) {
                $sendArgs['ConfigurationSetName'] = (string) $configSet;
            }

            if (! empty($payload['feedback_id'])) {
                $sendArgs['EmailTags'] = [
                    [
                        'Name' => 'Feedback-ID',
                        'Value' => preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', (string) $payload['feedback_id']) ?: 'spiggle',
                    ],
                ];
            }

            $result = $client->sendEmail($sendArgs);
            $messageId = (string) $result->get('MessageId');

            return [
                'status' => 'success',
                'message_id' => $messageId,
            ];
        } catch (Throwable $e) {
            Log::error('Amazon SES sending error: ' . $e->getMessage(), [
                'to' => $payload['to'] ?? null,
                'subject' => $payload['subject'] ?? null,
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendRaw(string $to, string $from, string $subject, string $html, array $headers = []): array
    {
        return $this->send([
            'to' => $to,
            'from' => $from,
            'subject' => $subject,
            'html' => $html,
            'headers' => $headers,
        ]);
    }

    public function getOrCreateDomainIdentity(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $mailFrom = 'mail.' . $domain;
        $client = $this->getClient();

        try {
            $identity = $client->getEmailIdentity(['EmailIdentity' => $domain]);
        } catch (Throwable) {
            $identity = $client->createEmailIdentity(['EmailIdentity' => $domain]);
        }

        // Configure custom MAIL FROM domain so Return-Path aligns with From domain (DMARC SPF alignment)
        try {
            $existingMailFrom = $identity->get('MailFromAttributes') ?? [];
            if (($existingMailFrom['MailFromDomain'] ?? null) !== $mailFrom) {
                $client->putEmailIdentityMailFromAttributes([
                    'EmailIdentity' => $domain,
                    'MailFromDomain' => $mailFrom,
                    'BehaviorOnMxFailure' => 'USE_DEFAULT_VALUE',
                ]);
            }
        } catch (Throwable $e) {
            Log::warning("Unable to configure SES custom MAIL FROM for {$domain}: " . $e->getMessage());
        }

        $dkim = $identity->get('DkimAttributes') ?? [];
        $dkimStatus = strtolower((string) ($dkim['Status'] ?? 'pending'));
        $verifiedForSending = (bool) ($identity->get('VerifiedForSendingStatus') ?? false);
        $tokens = array_values(array_map('strval', (array) ($dkim['Tokens'] ?? [])));

        $status = ($verifiedForSending && $dkimStatus === 'success')
            ? 'verified'
            : ($dkimStatus === 'failed' ? 'failed' : 'pending');

        return [
            'domain' => $domain,
            'status' => $status,
            'dkim_tokens' => $tokens,
            'mail_from' => $mailFrom,
        ];
    }

    public function getAccountQuota(): array
    {
        try {
            $account = $this->getClient()->getAccount();
            $quota = (array) ($account->get('SendQuota') ?? []);

            return [
                'ok' => true,
                'driver' => 'live',
                'region' => $this->region,
                'production_access' => (bool) ($account->get('ProductionAccessEnabled') ?? false),
                'sending_enabled' => (bool) ($account->get('SendingEnabled') ?? false),
                'max_24_hour_send' => (float) ($quota['Max24HourSend'] ?? 0),
                'max_send_rate' => (float) ($quota['MaxSendRate'] ?? 0),
                'sent_last_24_hours' => (float) ($quota['SentLast24Hours'] ?? 0),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'driver' => 'live',
                'region' => $this->region,
                'production_access' => false,
                'sending_enabled' => false,
                'max_24_hour_send' => 0.0,
                'max_send_rate' => 0.0,
                'sent_last_24_hours' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
