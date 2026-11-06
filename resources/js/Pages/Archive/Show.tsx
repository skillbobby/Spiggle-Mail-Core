import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  ArrowLeft,
  Calendar,
  Eye,
  Share2,
  Check,
  Copy,
  Mail,
  ArrowRight,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react';
import type { ArchiveIssue, CampaignDetail, Newsletter } from '@/types';

export interface ArchiveShowProps {
  readonly newsletter: Newsletter;
  readonly issue: CampaignDetail;
  readonly moreIssues: readonly ArchiveIssue[];
}

export default function ArchiveShow({
  newsletter,
  issue,
  moreIssues = [],
}: ArchiveShowProps): React.JSX.Element {
  const [copied, setCopied] = useState(false);
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Fallback
    }
  };

  const handleShareTwitter = () => {
    const text = encodeURIComponent(`Reading "${issue.subject}" by ${newsletter.name}`);
    const url = encodeURIComponent(window.location.href);
    window.open(`https://twitter.com/intent/tweet?text=${text}&url=${url}`, '_blank');
  };

  const handleShareLinkedIn = () => {
    const url = encodeURIComponent(window.location.href);
    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${url}`, '_blank');
  };

  const handleSubscribe = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email || isSubmitting) return;

    setIsSubmitting(true);
    setStatusMessage(null);

    try {
      const response = await fetch('/embed/subscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          email,
          newsletter_slug: newsletter.slug,
        }),
      });

      const data = await response.json();
      if (response.ok && data.success) {
        setStatusMessage({
          type: 'success',
          text: data.message ?? 'Thanks for subscribing! Check your inbox to confirm.',
        });
        setEmail('');
      } else {
        setStatusMessage({
          type: 'error',
          text: data.error ?? 'Unable to subscribe. Please try again.',
        });
      }
    } catch {
      setStatusMessage({
        type: 'error',
        text: 'Network error. Please try again later.',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 flex flex-col font-sans">
      <Head title={`${issue.subject} - ${newsletter.name}`} />

      {/* Header Bar */}
      <header className="border-b border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md sticky top-0 z-30">
        <div className="max-w-4xl mx-auto px-5 h-14 flex items-center justify-between">
          <Link
            href={`/archive/${newsletter.slug}`}
            className="flex items-center gap-2 text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            <span>All Issues</span>
          </Link>

          <Link
            href={`/archive/${newsletter.slug}`}
            className="flex items-center gap-2 font-bold tracking-tight text-sm text-zinc-900 dark:text-zinc-100"
          >
            <div className="h-6 w-6 rounded-md bg-orange-600 text-white flex items-center justify-center font-bold text-xs">
              {newsletter.name.charAt(0)}
            </div>
            <span>{newsletter.name}</span>
          </Link>

          <a
            href="#subscribe"
            className="rounded-lg bg-orange-600 hover:bg-orange-500 text-white px-3 py-1.5 text-xs font-semibold shadow-xs transition-colors"
          >
            Subscribe
          </a>
        </div>
      </header>

      {/* Main Content Article */}
      <main className="flex-1 max-w-3xl mx-auto w-full px-5 py-12 sm:py-16">
        <article className="space-y-8">
          {/* Article Header */}
          <header className="space-y-4 border-b border-zinc-200 dark:border-zinc-800 pb-8">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div className="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span className="flex items-center gap-1">
                  <Calendar className="h-3.5 w-3.5" />
                  {new Date(issue.sentAt ?? issue.createdAt).toLocaleDateString(undefined, {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                  })}
                </span>
                {issue.webViews > 0 && (
                  <>
                    <span>·</span>
                    <span className="flex items-center gap-1">
                      <Eye className="h-3.5 w-3.5" />
                      {issue.webViews} views
                    </span>
                  </>
                )}
              </div>

              {/* Share actions */}
              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  onClick={handleShareTwitter}
                  className="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition-colors"
                  title="Share on X (Twitter)"
                >
                  X
                </button>
                <button
                  type="button"
                  onClick={handleShareLinkedIn}
                  className="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition-colors"
                  title="Share on LinkedIn"
                >
                  LinkedIn
                </button>
                <button
                  type="button"
                  onClick={handleCopyLink}
                  className="inline-flex items-center gap-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition-colors"
                  title="Copy link to clipboard"
                >
                  {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                  <span>{copied ? 'Copied' : 'Share'}</span>
                </button>
              </div>
            </div>

            <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-100 leading-tight">
              {issue.subject}
            </h1>

            {issue.previewText && (
              <p className="text-lg text-zinc-600 dark:text-zinc-400 font-normal leading-relaxed">
                {issue.previewText}
              </p>
            )}

            <div className="flex items-center gap-3 pt-2">
              <div className="h-9 w-9 rounded-full bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center font-bold text-xs shadow-xs">
                {newsletter.fromName.charAt(0)}
              </div>
              <div className="text-xs">
                <span className="block font-semibold text-zinc-900 dark:text-zinc-100">
                  {newsletter.fromName}
                </span>
                <span className="block text-zinc-500 dark:text-zinc-400">
                  Author & Publisher
                </span>
              </div>
            </div>
          </header>

          {/* HTML Email / Issue Body Content */}
          <div className="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 sm:p-10 shadow-sm overflow-hidden">
            <div
              className="prose dark:prose-invert max-w-none text-zinc-800 dark:text-zinc-200"
              dangerouslySetInnerHTML={{ __html: issue.html ?? '<p>No content.</p>' }}
            />
          </div>

          {/* Bottom Subscribe Call to Action */}
          <section id="subscribe" className="rounded-2xl border border-orange-200 dark:border-orange-900/60 bg-gradient-to-br from-orange-50/70 to-amber-50/50 dark:from-zinc-900 dark:to-zinc-850 p-6 sm:p-8 text-center scroll-mt-20">
            <div className="mx-auto h-12 w-12 rounded-xl bg-orange-600 text-white flex items-center justify-center font-bold text-lg shadow-md mb-4">
              {newsletter.name.charAt(0)}
            </div>
            <h3 className="text-xl font-bold text-zinc-900 dark:text-zinc-100">
              Enjoyed this dispatch from {newsletter.name}?
            </h3>
            <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400 max-w-md mx-auto">
              Never miss an issue. Join readers receiving insightful updates directly in their inbox.
            </p>

            <form onSubmit={handleSubscribe} className="mt-6 max-w-md mx-auto flex flex-col sm:flex-row gap-2">
              <div className="relative flex-1">
                <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="Enter your email address..."
                  className="w-full rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 pl-10 pr-4 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-500 shadow-xs"
                />
              </div>
              <button
                type="submit"
                disabled={isSubmitting}
                className="rounded-xl bg-orange-600 hover:bg-orange-500 text-white px-5 py-2.5 text-sm font-semibold shadow-xs transition-colors flex items-center justify-center gap-2 shrink-0 disabled:opacity-60"
              >
                {isSubmitting ? 'Joining...' : 'Subscribe'}
                <ArrowRight className="h-4 w-4" />
              </button>
            </form>

            {statusMessage && (
              <div
                className={`mt-4 max-w-md mx-auto flex items-center gap-2 rounded-lg p-3 text-xs text-left ${
                  statusMessage.type === 'success'
                    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                    : 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800'
                }`}
              >
                {statusMessage.type === 'success' ? (
                  <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                ) : (
                  <AlertCircle className="h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" />
                )}
                <span>{statusMessage.text}</span>
              </div>
            )}
          </section>

          {/* Keep Reading Recommendations */}
          {moreIssues.length > 0 && (
            <section className="space-y-4 pt-6 border-t border-zinc-200 dark:border-zinc-800">
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                  Keep Reading
                </h3>
                <Link
                  href={`/archive/${newsletter.slug}`}
                  className="text-xs font-semibold text-orange-600 dark:text-orange-400 hover:underline flex items-center gap-1"
                >
                  <span>All issues</span>
                  <ArrowRight className="h-3 w-3" />
                </Link>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                {moreIssues.map((other) => (
                  <Link
                    key={other.id}
                    href={`/archive/${newsletter.slug}/p/${other.archiveSlug}`}
                    className="group block rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-xs hover:border-orange-300 dark:hover:border-orange-900/60 transition-all"
                  >
                    <span className="block text-[11px] text-zinc-400 mb-1">
                      {new Date(other.sentAt ?? other.createdAt).toLocaleDateString()}
                    </span>
                    <h4 className="text-sm font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors line-clamp-1">
                      {other.subject}
                    </h4>
                    {other.previewText && (
                      <p className="mt-1 text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">
                        {other.previewText}
                      </p>
                    )}
                  </Link>
                ))}
              </div>
            </section>
          )}
        </article>
      </main>

      {/* Footer */}
      <footer className="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 py-8 px-5 text-center text-xs text-zinc-500">
        <p>
          Published with{' '}
          <Link href="/" className="font-semibold text-zinc-700 dark:text-zinc-300 hover:underline">
            Spiggle Mail
          </Link>
        </p>
      </footer>
    </div>
  );
}
