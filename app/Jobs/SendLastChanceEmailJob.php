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

class SendLastChanceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $contactId
    ) {}

    public function handle(EmailProviderInterface $emailProvider): void
    {
        $contact = Contact::with('newsletter')->find($this->contactId);
        if (!$contact || $contact->status !== 'subscribed') {
            Log::info("SendLastChanceEmailJob: Contact {$this->contactId} not eligible.");
            return;
        }

        $newsletter = $contact->newsletter;
        if (!$newsletter) {
            return;
        }

        $token = $contact->ensureKeepToken();
        $keepUrl = url("/keep?token={$token}");
        $unsubscribeUrl = UnsubscribeHeaderService::landingUrl(
            $newsletter->slug,
            $contact->id,
            config('app.url', 'http://spiggle-mail.local')
        );

        $subject = "Do you still want to hear from {$newsletter->name}?";

        $fullHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$subject}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #fafafa; margin: 0; padding: 32px 16px;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 36px; border: 1px solid #e4e4e7;">
    <h2 style="font-size: 20px; font-weight: 700; color: #18181b; margin-top: 0;">Still interested in {$newsletter->name}?</h2>
    <p style="font-size: 15px; line-height: 1.6; color: #3f3f46;">
      We noticed you haven't opened our latest emails. We only want to land in your inbox if you find our content valuable.
    </p>
    <p style="font-size: 15px; line-height: 1.6; color: #3f3f46;">
      If you would like to keep receiving our newsletter, please click the button below within 14 days:
    </p>
    <div style="margin: 28px 0; text-align: center;">
      <a href="{$keepUrl}" style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-weight: 600; padding: 12px 28px; border-radius: 8px;">
        Yes, keep me subscribed
      </a>
    </div>
    <p style="font-size: 14px; line-height: 1.6; color: #71717a;">
      If you take no action, you'll be automatically removed from future dispatches to keep your inbox clean.
    </p>
    <hr style="border: none; border-top: 1px solid #f4f4f5; margin: 32px 0 20px 0;" />
    <div style="font-size: 12px; color: #a1a1aa; text-align: center; line-height: 1.5;">
      <p style="margin: 0 0 6px 0;">{$newsletter->name} &bull; {$newsletter->postal_address}</p>
      <p style="margin: 0;">
        <a href="{$unsubscribeUrl}" style="color: #71717a; text-decoration: underline;">Unsubscribe immediately</a>
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

            $contact->last_chance_at = now();
            $contact->save();

            Message::create([
                'id' => (string) Str::ulid(),
                'contact_id' => $contact->id,
                'ses_message_id' => $messageId,
                'status' => $failed ? 'failed' : 'sent',
                'error' => is_array($response) ? ($response['error'] ?? null) : null,
                'sent_at' => $failed ? null : now(),
            ]);

            Log::info("Last-chance re-engagement email sent to {$contact->email}");
        } catch (\Throwable $e) {
            Log::error("Failed to send last-chance email to {$contact->email}: {$e->getMessage()}");
            throw $e;
        }
    }
}
