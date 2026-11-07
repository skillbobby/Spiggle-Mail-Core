<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Newsletter;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $newsletter = $this->getActiveNewsletter($request);
        if (! $newsletter) {
            return redirect('/dashboard')->with('error', 'Please configure a newsletter publication first.');
        }

        $query = Campaign::where('newsletter_id', $newsletter->id);

        $statusCounts = [
            'all' => Campaign::where('newsletter_id', $newsletter->id)->count(),
            'draft' => Campaign::where('newsletter_id', $newsletter->id)->where('status', 'draft')->count(),
            'scheduled' => Campaign::where('newsletter_id', $newsletter->id)->where('status', 'scheduled')->count(),
            'sending' => Campaign::where('newsletter_id', $newsletter->id)->where('status', 'sending')->count(),
            'sent' => Campaign::where('newsletter_id', $newsletter->id)->where('status', 'sent')->count(),
            'paused' => Campaign::where('newsletter_id', $newsletter->id)->where('status', 'paused')->count(),
        ];

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && in_array($status, ['draft', 'scheduled', 'sending', 'sent', 'paused'], true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('subject', 'like', "%{$search}%");
        }

        $paginated = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $campaigns = collect($paginated->items())->map(fn (Campaign $c) => [
            'id' => $c->id,
            'subject' => $c->subject,
            'previewText' => $c->preview_text ?? '',
            'status' => $c->status,
            'archivePinned' => (bool) $c->archive_pinned,
            'archiveSlug' => $c->archive_slug,
            'sentCount' => (int) $c->sent_count,
            'totalRecipients' => (int) $c->total_recipients,
            'openRate' => $c->sent_count > 0 ? round($c->open_count / $c->sent_count, 3) : 0,
            'clickRate' => $c->sent_count > 0 ? round($c->click_count / $c->sent_count, 3) : 0,
            'bounceRate' => $c->sent_count > 0 ? round($c->bounce_count / $c->sent_count, 4) : 0,
            'scheduledAt' => $c->scheduled_at?->format('M d, Y H:i'),
            'sentAt' => $c->sent_at?->format('M d, Y H:i'),
            'createdAt' => $c->created_at?->format('M d, Y'),
        ])->values()->all();

        return Inertia::render('Campaigns/Index', [
            'currentNewsletter' => [
                'id' => $newsletter->id,
                'name' => $newsletter->name,
                'slug' => $newsletter->slug,
                'fromName' => $newsletter->from_name,
                'fromEmail' => $newsletter->from_email,
            ],
            'campaigns' => $campaigns,
            'statusCounts' => $statusCounts,
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

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $newsletter = $this->getActiveNewsletter($request);
        if (! $newsletter) {
            return back()->with('error', 'No active newsletter found.');
        }

        $campaign = Campaign::where('newsletter_id', $newsletter->id)->where('id', $id)->first();
        if (! $campaign) {
            return back()->with('error', 'Campaign not found.');
        }

        if ($campaign->status === 'sending') {
            return back()->withErrors(['error' => 'Cannot delete a campaign that is actively sending. Please pause it first.']);
        }

        $campaign->delete();

        return back()->with('success', 'Campaign discarded successfully.');
    }

    private function getActiveNewsletter(Request $request): ?Newsletter
    {
        return CurrentWorkspace::newsletter($request);
    }
}
