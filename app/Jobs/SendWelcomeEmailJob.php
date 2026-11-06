<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Message;
use App\Services\Deliverability\UnsubscribeHeaderService;
use App\Services\Email\EmailProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $contactId
    ) {}

    public function handle(EmailProviderInterface $emailProvider): void
    {
        $contact = Contact::with('newsletter')->find($this->contactId);
        if (!$contact || $contact->status !== 'subscribed') {
            Log::info("SendWelcomeEmailJob: Contact {$this->contactId} not in subscribed state.");
            return;
        }

        $newsletter = $contact->newsletter;
        if (!$newsletter || !$newsletter->welcome_enabled) {
            return;
        }

        $subject = $newsletter->welcome_subject ?: $newsletter->getDefaultWelcomeSubject();
        $rawMarkdown = $newsletter->welcome_markdown ?: $newsletter->getDefaultWelcomeMarkdown();

        $converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $bodyHtml = (string) $converter->convert($rawMarkdown);

        $unsubscribeUrl = UnsubscribeHeaderService::landingUrl(
            $newsletter->slug,
            $contact->id,
            config('app.url', 'http://spiggle-mail.local')
        );

        $fullHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$subject}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #fafafa; margin: 0; padding: 32px 16px;">
  <div style="max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 36px; border: 1px solid #e4e4e7;">
    <div style="font-size: 16px; line-height: 1.6; color: #18181b;">
      {$bodyHtml}
    </div>
    <hr style="border: none; border-top: 1px solid #f4f4f5; margin: 32px 0 20px 0;" />
    <div style="font-size: 12px; color: #a1a1aa; text-align: center; line-height: 1.5;">
      <p style="margin: 0 0 6px 0;">{$newsletter->name} &bull; {$newsletter->postal_address}</p>
      <p style="margin: 0;">
        <a href="{$unsubscribeUrl}" style="color: #71717a; text-decoration: underline;">Unsubscribe</a> from this newsletter.
      </p>
    </div>
  </div>
</body>
</html>
HTML;

        try {
            $from = "{$newsletter->from_name} <{$newsletter->from_email}>";
            $headers = UnsubscribeHeaderService::buildHeaders(
                $newsletter->slug,
                $contact->id,
                config('app.url', 'http://spiggle-mail.local')
            );
            $response = $emailProvider->send([
                'to' => $contact->email,
                'from' => $from,
                'reply_to' => $newsletter->reply_to ?: $newsletter->from_email,
                'subject' => $subject,
                'html' => $fullHtml,
                'headers' => $headers,
            ]);
            $failed = is_array($response) && ($response['status'] ?? 'success') === 'failed';
            $messageId = is_string($response) ? $response : ($response['message_id'] ?? null);

            // Audit message
            Message::create([
                'id' => (string) Str::ulid(),
                'contact_id' => $contact->id,
                'ses_message_id' => $messageId,
                'status' => $failed ? 'failed' : 'sent',
                'error' => is_array($response) ? ($response['error'] ?? null) : null,
                'sent_at' => $failed ? null : now(),
            ]);

            Log::info("Welcome email sent to {$contact->email} for newsletter {$newsletter->slug}");
        } catch (\Throwable $e) {
            Log::error("Failed to send welcome email to {$contact->email}: {$e->getMessage()}");
            throw $e;
        }
    }
}
