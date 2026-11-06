<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\Email\EmailProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;

class SendConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $contactId
    ) {}

    public function handle(EmailProviderInterface $emailProvider): void
    {
        $contact = Contact::with('newsletter')->find($this->contactId);
        if (!$contact || $contact->status !== 'pending') {
            Log::info("SendConfirmationEmailJob: Contact {$this->contactId} not in pending state.");
            return;
        }

        $newsletter = $contact->newsletter;
        if (!$newsletter) {
            Log::error("SendConfirmationEmailJob: Newsletter not found for contact {$this->contactId}");
            return;
        }

        $token = $contact->ensureConfirmationToken();
        $confirmUrl = url("/confirm?token={$token}");

        $subject = $newsletter->confirmation_subject ?: $newsletter->getDefaultConfirmationSubject();
        $rawMarkdown = $newsletter->confirmation_markdown ?: $newsletter->getDefaultConfirmationMarkdown();

        // Replace placeholder
        $markdown = str_replace(['{{confirm_url}}', '%7B%7Bconfirm_url%7D%7D'], $confirmUrl, $rawMarkdown);

        $converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $bodyHtml = (string) $converter->convert($markdown);

        // Wrap in clean responsive template
        $fullHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$subject}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f4f4f5; margin: 0; padding: 32px 16px;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e4e4e7;">
    <div style="font-size: 16px; line-height: 1.6; color: #18181b;">
      {$bodyHtml}
    </div>
    <hr style="border: none; border-top: 1px solid #e4e4e7; margin: 32px 0 16px 0;" />
    <p style="font-size: 12px; color: #71717a; text-align: center; margin: 0;">
      {$newsletter->name} &bull; {$newsletter->postal_address}
    </p>
  </div>
</body>
</html>
HTML;

        try {
            $from = "{$newsletter->from_name} <{$newsletter->from_email}>";
            $emailProvider->sendRaw($contact->email, $from, $subject, $fullHtml);
            Log::info("Confirmation email sent to {$contact->email} for newsletter {$newsletter->slug}");
        } catch (\Throwable $e) {
            Log::error("Failed to send confirmation email to {$contact->email}: {$e->getMessage()}");
            throw $e;
        }
    }
}
