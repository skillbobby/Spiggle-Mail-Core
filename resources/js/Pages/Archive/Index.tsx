import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Mail, Calendar, Eye, Pin, ArrowRight, CheckCircle2, AlertCircle } from 'lucide-react';
import type { ArchiveIssue, Newsletter } from '@/types';

export interface ArchiveIndexProps {
  readonly newsletter: Newsletter;
  readonly pinnedIssues: readonly ArchiveIssue[];
  readonly latestIssue?: ArchiveIssue | null;
  readonly allIssues: readonly ArchiveIssue[];
  readonly totalReaders: number;
}

export default function ArchiveIndex({
  newsletter,
  pinnedIssues = [],
  latestIssue = null,
  allIssues = [],
  totalReaders = 0,
}: ArchiveIndexProps): React.JSX.Element {
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

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
      <Head title={`${newsletter.name} - Publication Archive`} />

      {/* Header bar */}
      <header className="border-b border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md sticky top-0 z-30">
        <div className="max-w-4xl mx-auto px-5 h-14 flex items-center justify-between">
          <Link
            href={`/archive/${newsletter.slug}`}
            className="flex items-center gap-2.5 font-bold tracking-tight text-sm text-zinc-900 dark:text-zinc-100"
          >
            <div className="h-7 w-7 rounded-lg bg-orange-600 text-white flex items-center justify-center font-bold text-xs shadow-xs">
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

      {/* Hero Section */}
      <main className="flex-1">
        <section className="relative overflow-hidden border-b border-zinc-200 dark:border-zinc-800 bg-gradient-to-b from-white to-zinc-50/50 dark:from-zinc-900 dark:to-zinc-950 py-16 sm:py-20 px-5">
          <div className="max-w-2xl mx-auto text-center">
            <div className="mx-auto h-16 w-16 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg shadow-orange-500/20 mb-6">
              {newsletter.name.charAt(0)}
            </div>

            <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-100">
              {newsletter.name}
            </h1>

            {newsletter.description && (
              <p className="mt-3 text-base sm:text-lg text-zinc-600 dark:text-zinc-400 max-w-lg mx-auto">
                {newsletter.description}
              </p>
            )}

            {/* Subscribe widget block */}
            <div id="subscribe" className="mt-8 max-w-md mx-auto scroll-mt-20">
              <form onSubmit={handleSubscribe} className="flex flex-col sm:flex-row gap-2">
                <div className="relative flex-1">
                  <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
                  <input
                    type="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Enter your email address..."
                    className="w-full rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-850 pl-10 pr-4 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-500 shadow-xs"
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
                  className={`mt-3 flex items-center gap-2 rounded-lg p-3 text-xs text-left ${
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

              <p className="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                Join {totalReaders > 0 ? totalReaders.toLocaleString() : 'our'} readers. Unsubscribe at any time.
              </p>
            </div>
          </div>
        </section>

        {/* Issues List Container */}
        <div className="max-w-4xl mx-auto px-5 py-12 space-y-12">
          {/* Pinned / Featured Section */}
          {pinnedIssues.length > 0 && (
            <section className="space-y-4">
              <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-orange-600 dark:text-orange-400">
                <Pin className="h-3.5 w-3.5" />
                <span>Featured Issues</span>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                {pinnedIssues.map((issue) => (
                  <Link
                    key={issue.id}
                    href={`/archive/${newsletter.slug}/p/${issue.archiveSlug}`}
                    className="group block rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs transition-all hover:border-orange-300 dark:hover:border-orange-900/60 hover:shadow-md"
                  >
                    <div className="flex items-center gap-2 text-[11px] text-zinc-400 mb-2">
                      <Calendar className="h-3 w-3" />
                      <span>{new Date(issue.sentAt ?? issue.createdAt).toLocaleDateString()}</span>
                      {issue.webViews > 0 && (
                        <>
                          <span>·</span>
                          <span className="flex items-center gap-1">
                            <Eye className="h-3 w-3" />
                            {issue.webViews}
                          </span>
                        </>
                      )}
                    </div>
                    <h3 className="text-base font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors line-clamp-2">
                      {issue.subject}
                    </h3>
                    {issue.previewText && (
                      <p className="mt-2 text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">
                        {issue.previewText}
                      </p>
                    )}
                  </Link>
                ))}
              </div>
            </section>
          )}

          {/* Latest Issue Hero (if not pinned) */}
          {latestIssue && pinnedIssues.every((p) => p.id !== latestIssue.id) && (
            <section className="space-y-4">
              <h2 className="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                Latest Dispatch
              </h2>
              <Link
                href={`/archive/${newsletter.slug}/p/${latestIssue.archiveSlug}`}
                className="group block rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 sm:p-8 shadow-xs transition-all hover:border-zinc-300 dark:hover:border-zinc-700 hover:shadow-md"
              >
                <div className="flex items-center gap-2 text-xs text-zinc-400 mb-3">
                  <Calendar className="h-3.5 w-3.5" />
                  <span>{new Date(latestIssue.sentAt ?? latestIssue.createdAt).toLocaleDateString()}</span>
                  {latestIssue.webViews > 0 && (
                    <>
                      <span>·</span>
                      <span className="flex items-center gap-1">
                        <Eye className="h-3.5 w-3.5" />
                        {latestIssue.webViews} views
                      </span>
                    </>
                  )}
                </div>
                <h3 className="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                  {latestIssue.subject}
                </h3>
                {latestIssue.previewText && (
                  <p className="mt-3 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    {latestIssue.previewText}
                  </p>
                )}
                <div className="mt-4 flex items-center gap-1 text-xs font-semibold text-orange-600 dark:text-orange-400">
                  <span>Read full issue</span>
                  <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-1 transition-transform" />
                </div>
              </Link>
            </section>
          )}

          {/* All Issues Feed */}
          <section className="space-y-4">
            <h2 className="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
              All Issues
            </h2>

            {allIssues.length === 0 ? (
              <div className="rounded-2xl border border-dashed border-zinc-300 dark:border-zinc-800 p-12 text-center text-sm text-zinc-500">
                No past issues published yet. Subscribe to receive the very first one!
              </div>
            ) : (
              <div className="divide-y divide-zinc-200 dark:divide-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
                {allIssues.map((issue) => (
                  <Link
                    key={issue.id}
                    href={`/archive/${newsletter.slug}/p/${issue.archiveSlug}`}
                    className="group flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 hover:bg-zinc-50/60 dark:hover:bg-zinc-850/50 transition-colors"
                  >
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2 text-xs text-zinc-400 mb-1">
                        <Calendar className="h-3 w-3" />
                        <span>{new Date(issue.sentAt ?? issue.createdAt).toLocaleDateString()}</span>
                        {issue.webViews > 0 && (
                          <>
                            <span>·</span>
                            <span className="flex items-center gap-1">
                              <Eye className="h-3 w-3" />
                              {issue.webViews}
                            </span>
                          </>
                        )}
                      </div>
                      <h4 className="text-base font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors truncate">
                        {issue.subject}
                      </h4>
                      {issue.previewText && (
                        <p className="mt-1 text-xs text-zinc-500 dark:text-zinc-400 truncate">
                          {issue.previewText}
                        </p>
                      )}
                    </div>
                    <div className="flex items-center gap-1 text-xs font-semibold text-zinc-400 group-hover:text-orange-600 shrink-0">
                      <span>Read</span>
                      <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-1 transition-transform" />
                    </div>
                  </Link>
                ))}
              </div>
            )}
          </section>
        </div>
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
