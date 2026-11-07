<?php

namespace App\Http\Controllers;

use App\Jobs\SendConfirmationEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\Contact;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Services\Contact\ContactCsvImportService;
use App\Services\Deliverability\EmailHygieneService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $newsletter = $this->getActiveNewsletter($request);
        if (! $newsletter) {
            return redirect('/dashboard')->with('error', 'Please configure a newsletter publication first.');
        }

        $query = Contact::where('newsletter_id', $newsletter->id);

        // Status counts for badge tabs
        $statusCounts = [
            'all' => Contact::where('newsletter_id', $newsletter->id)->count(),
            'subscribed' => Contact::where('newsletter_id', $newsletter->id)->where('status', 'subscribed')->count(),
            'pending' => Contact::where('newsletter_id', $newsletter->id)->where('status', 'pending')->count(),
            'dormant' => Contact::where('newsletter_id', $newsletter->id)->where('status', 'dormant')->count(),
            'unsubscribed' => Contact::where('newsletter_id', $newsletter->id)->where('status', 'unsubscribed')->count(),
            'bounced' => Contact::where('newsletter_id', $newsletter->id)->where('status', 'bounced')->count(),
        ];

        // Status filter
        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && in_array($status, ['subscribed', 'pending', 'dormant', 'unsubscribed', 'bounced', 'complained'], true)) {
            $query->where('status', $status);
        }

        // Search query filter
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('email', 'like', "%{$search}%");
        }

        $paginated = $query->orderBy('subscribed_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $contacts = collect($paginated->items())->map(fn (Contact $c) => [
            'id' => $c->id,
            'email' => $c->email,
            'status' => $c->status,
            'source' => $c->source ?? 'website',
            'openCount' => $c->open_count ?? 0,
            'clickCount' => $c->click_count ?? 0,
            'createdAt' => $c->subscribed_at?->format('M d, Y') ?? $c->created_at?->format('M d, Y') ?? 'Recently',
        ])->values()->all();

        return Inertia::render('Contacts/Index', [
            'currentNewsletter' => [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
                'postalAddress' => $newsletter->postal_address,
                'subscriberCount' => $statusCounts['subscribed'],
            ],
            'totalContacts' => $statusCounts['all'],
            'statusCounts' => $statusCounts,
            'contacts' => $contacts,
            'pagination' => [
                'currentPage' => $paginated->currentPage(),
                'lastPage' => $paginated->lastPage(),
                'perPage' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $newsletter = $this->getActiveNewsletter($request);
        if (! $newsletter) {
            return back()->withErrors(['email' => 'No active newsletter publication found.']);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($validated['email']));

        // Hygiene & Disposable Check
        $hygieneReason = EmailHygieneService::checkEmail($email);
        if ($hygieneReason !== null) {
            return back()->withErrors(['email' => "Invalid or disposable email address ({$hygieneReason})."]);
        }

        // Suppression Check
        $emailHash = hash_hmac('sha256', $email, config('app.key'));
        if (Suppression::where('email_hash', $emailHash)->exists()) {
            return back()->withErrors(['email' => 'This email address is on the global suppression list due to a prior bounce or complaint.']);
        }

        // Check if already on list
        $existing = Contact::where('newsletter_id', $newsletter->id)->where('email', $email)->first();
        if ($existing) {
            if ($existing->status === 'subscribed') {
                return back()->withErrors(['email' => 'This email address is already subscribed.']);
            }
            if (in_array($existing->status, ['bounced', 'complained', 'unsubscribed'], true)) {
                return back()->withErrors(['email' => "Cannot re-subscribe a contact whose status is '{$existing->status}'."]);
            }
            $existing->update(['status' => 'subscribed', 'subscribed_at' => now()]);

            return back()->with('success', 'Subscriber status updated to subscribed.');
        }

        $status = $newsletter->double_opt_in ? 'pending' : 'subscribed';

        $contact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'email' => $email,
            'status' => $status,
            'source' => 'dashboard_manual',
            'subscribed_at' => now(),
            'confirmation_token' => $newsletter->double_opt_in ? Str::random(48) : null,
        ]);

        if ($newsletter->double_opt_in) {
            SendConfirmationEmailJob::dispatch($contact->id);

            return back()->with('success', 'Subscriber added as pending; confirmation email sent.');
        }

        if ($newsletter->welcome_enabled) {
            SendWelcomeEmailJob::dispatch($contact->id);
        }

        return back()->with('success', 'Subscriber added successfully.');
    }

    public function import(Request $request, ContactCsvImportService $importService): RedirectResponse
    {
        $newsletter = $this->getActiveNewsletter($request);
        if (! $newsletter) {
            return back()->withErrors(['file' => 'No active newsletter publication found.']);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $result = $importService->import($newsletter, $file);

        if (! empty($result['errors'])) {
            return back()->withErrors(['file' => implode(' ', $result['errors'])]);
        }

        return back()->with('success', "Imported {$result['imported']} subscribers successfully ({$result['skipped']} skipped or already on list).");
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $newsletter = $this->getActiveNewsletter($request);
        if (! $newsletter) {
            return back()->with('error', 'No active newsletter found.');
        }

        $contact = Contact::where('newsletter_id', $newsletter->id)->where('id', $id)->first();
        if (! $contact) {
            return back()->with('error', 'Subscriber not found.');
        }

        $contact->delete();

        return back()->with('success', 'Subscriber removed successfully.');
    }

    private function getActiveNewsletter(Request $request): ?Newsletter
    {
        return CurrentWorkspace::newsletter($request);
    }
}
