import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  ArrowLeft,
  Pause,
  Play,
  Copy,
  Pin,
  PinOff,
  ExternalLink,
  Check,
  AlertTriangle,
  Send,
  Layers,
  Download,
  MousePointerClick,
  Split,
} from 'lucide-react';
import { DashboardLayout } from '@/Layouts/DashboardLayout';
import { StatCard } from '@/Components/Campaigns/StatCard';
import { ProviderTable } from '@/Components/Campaigns/ProviderTable';
import { EmailPreviewFrame } from '@/Components/Campaigns/EmailPreviewFrame';
import { useCampaignReport } from '@/hooks/useCampaignReport';
import type {
  CampaignDetail,
  CampaignStats,
  Newsletter,
  ProviderStat,
  ClickedLink,
} from '@/types';

export interface CampaignReportProps {
  readonly currentNewsletter: Newsletter | null;
  readonly campaign: CampaignDetail;
  readonly stats: CampaignStats;
  readonly providers: readonly ProviderStat[];
  readonly clickedLinks?: readonly ClickedLink[];
  readonly archiveUrl?: string;
}

export default function CampaignReport({
  currentNewsletter,
  campaign,
  stats,
  providers = [],
  clickedLinks = [],
  archiveUrl,
}: CampaignReportProps): React.JSX.Element {
  const {
    isProcessing,
    isCopied,
    pauseCampaign,
    resumeCampaign,
    duplicateCampaign,
    togglePin,
    copyArchiveUrl,
  } = useCampaignReport({ campaignId: campaign.id });

  const progressPercent = stats.totalRecipients > 0
    ? Math.min(100, Math.round((stats.sent / stats.totalRecipients) * 100))
    : (campaign.status === 'sent' ? 100 : 0);

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'sent':
        return 'bg-emerald-950/60 text-emerald-400 border-emerald-800/40';
      case 'sending':
        return 'bg-blue-950/60 text-blue-400 border-blue-800/40 animate-pulse';
      case 'paused':
        return 'bg-amber-950/60 text-amber-400 border-amber-800/40';
      case 'draft':
        return 'bg-zinc-800 text-zinc-300 border-zinc-700';
      default:
        return 'bg-zinc-800 text-zinc-300 border-zinc-700';
    }
  };

  return (
    <DashboardLayout currentNewsletter={currentNewsletter}>
      <Head title={`${campaign.subject} - Report`} />

      <div className="space-y-6">
        {/* Top Header Bar */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
          <div className="flex items-center gap-3">
            <Link
              href="/dashboard"
              className="flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-white hover:border-zinc-700 shadow-xs transition-colors"
              title="Back to Overview"
            >
              <ArrowLeft className="h-4 w-4" />
            </Link>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-bold tracking-tight text-white">
                  {campaign.subject}
                </h1>
                <span
                  className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider border ${getStatusBadge(
                    campaign.status
                  )}`}
                >
                  {campaign.status}
                </span>
                {campaign.archivePinned && (
                  <span className="inline-flex items-center gap-1 rounded-full bg-orange-950/40 px-2 py-0.5 text-xs font-medium text-orange-400 border border-orange-800/40">
                    <Pin className="h-3 w-3" /> Pinned
                  </span>
                )}
              </div>
              <p className="mt-0.5 text-xs text-zinc-400">
                Created {new Date(campaign.createdAt).toLocaleDateString()} · {currentNewsletter?.name}
              </p>
            </div>
          </div>

          {/* Action buttons */}
          <div className="flex flex-wrap items-center gap-2">
            {(campaign.status === 'draft' || campaign.status === 'scheduled') && (
              <Link
                href={`/campaigns/new?edit=${campaign.id}`}
                className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-700/60 bg-indigo-950/40 px-3 py-1.5 text-xs font-medium text-indigo-300 hover:bg-indigo-900/50 shadow-xs transition-colors"
              >
                <Send className="h-3.5 w-3.5" />
                <span>Edit &amp; Send</span>
              </Link>
            )}

            {campaign.status === 'sending' && (
              <button
                type="button"
                disabled={isProcessing}
                onClick={pauseCampaign}
                className="inline-flex items-center gap-1.5 rounded-lg border border-amber-700/60 bg-amber-950/40 px-3 py-1.5 text-xs font-medium text-amber-300 hover:bg-amber-900/50 shadow-xs transition-colors"
              >
                <Pause className="h-3.5 w-3.5" />
                <span>Pause</span>
              </button>
            )}

            {campaign.status === 'paused' && (
              <button
                type="button"
                disabled={isProcessing}
                onClick={resumeCampaign}
                className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-700/60 bg-emerald-950/40 px-3 py-1.5 text-xs font-medium text-emerald-300 hover:bg-emerald-900/50 shadow-xs transition-colors"
              >
                <Play className="h-3.5 w-3.5" />
                <span>Resume</span>
              </button>
            )}

            {archiveUrl && (
              <>
                <a
                  href={archiveUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 shadow-xs transition-colors"
                >
                  <ExternalLink className="h-3.5 w-3.5" />
                  <span>Public View</span>
                </a>
                <button
                  type="button"
                  disabled={isProcessing}
                  onClick={togglePin}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 shadow-xs transition-colors"
                >
                  {campaign.archivePinned ? (
                    <>
                      <PinOff className="h-3.5 w-3.5" />
                      <span>Unpin</span>
                    </>
                  ) : (
                    <>
                      <Pin className="h-3.5 w-3.5" />
                      <span>Pin to Web</span>
                    </>
                  )}
                </button>
              </>
            )}

            <button
              type="button"
              disabled={isProcessing}
              onClick={duplicateCampaign}
              className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 shadow-xs transition-colors"
            >
              <Copy className="h-3.5 w-3.5" />
              <span>Duplicate</span>
            </button>

            <a
              href={`/campaigns/${campaign.id}/export.csv`}
              download
              className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-850 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-750 hover:text-white shadow-xs transition-colors"
              title="Download recipient delivery audit log CSV"
            >
              <Download className="h-3.5 w-3.5 text-indigo-400" />
              <span>Export Audit CSV</span>
            </a>
          </div>
        </div>

        {/* Warning if paused */}
        {campaign.status === 'paused' && (
          <div className="flex items-start gap-3 rounded-xl border border-amber-800/60 bg-amber-950/30 p-4 text-xs text-amber-200">
            <AlertTriangle className="h-4 w-4 shrink-0 text-amber-400 mt-0.5" />
            <div>
              <p className="font-semibold text-amber-200">Campaign Sending Paused</p>
              <p className="mt-0.5 text-amber-300">
                {campaign.pauseReason ?? 'Sending has been paused by safety monitoring or manually by an operator.'}
              </p>
            </div>
          </div>
        )}

        {/* Progress bar if sending */}
        {campaign.status === 'sending' && (
          <div className="space-y-1.5 rounded-xl border border-blue-900 bg-blue-950/20 p-4">
            <div className="flex justify-between text-xs font-medium text-blue-200">
              <span className="flex items-center gap-1.5">
                <Send className="h-3.5 w-3.5 animate-spin" />
                Dispatching issues...
              </span>
              <span className="tabular-nums font-semibold">
                {stats.sent.toLocaleString()} of {stats.totalRecipients.toLocaleString()} ({progressPercent}%)
              </span>
            </div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-blue-900">
              <div
                className="h-full rounded-full bg-blue-600 transition-all duration-500"
                style={{ width: `${progressPercent}%` }}
              />
            </div>
          </div>
        )}

        {/* Primary Stat Cards */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <StatCard
            label="Delivered"
            value={`${(stats.deliveryRate * 100).toFixed(1)}%`}
            detail={`${stats.delivered.toLocaleString()}`}
            tone="good"
            subtitle={`${stats.sent} total dispatched`}
          />
          <StatCard
            label="Opened"
            value={`${(stats.openRate * 100).toFixed(1)}%`}
            detail={`${stats.opened.toLocaleString()}`}
            tone="neutral"
            subtitle="Unique readers"
          />
          <StatCard
            label="Clicked"
            value={`${(stats.clickRate * 100).toFixed(1)}%`}
            detail={`${stats.clicked.toLocaleString()}`}
            tone="neutral"
            subtitle="Engaged clicks"
          />
          <StatCard
            label="Bounced"
            value={`${(stats.bounceRate * 100).toFixed(2)}%`}
            detail={`${stats.bounced.toLocaleString()}`}
            tone={stats.bounceRate > 0.02 ? 'danger' : stats.bounceRate > 0.01 ? 'warning' : 'good'}
            subtitle="Safety target < 2.0%"
          />
          <StatCard
            label="Complaints"
            value={`${(stats.complaintRate * 100).toFixed(3)}%`}
            detail={`${stats.complaints.toLocaleString()}`}
            tone={stats.complaintRate > 0.0008 ? 'danger' : stats.complaintRate > 0.0004 ? 'warning' : 'good'}
            subtitle="Safety target < 0.08%"
          />
          <StatCard
            label="Unsubscribed"
            value={`${(stats.unsubscribeRate * 100).toFixed(2)}%`}
            detail={`${stats.unsubscribed.toLocaleString()}`}
            tone="neutral"
            subtitle="Reader opt-outs"
          />
        </div>

        {/* A/B Test Results Comparison Card */}
        {campaign.abTestEnabled && (
          <div className="rounded-xl border border-indigo-900/60 bg-zinc-900 shadow-xs overflow-hidden">
            <div className="border-b border-indigo-900/50 bg-indigo-950/20 px-5 py-4 flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-400">
                  <Split className="h-4 w-4" />
                </div>
                <div>
                  <h2 className="text-sm font-semibold text-white">A/B Subject Line Split Test</h2>
                  <p className="text-xs text-zinc-400">Pilot slice engagement comparing subject line variants</p>
                </div>
              </div>
              {campaign.abWinner ? (
                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-950/60 px-2.5 py-0.5 text-xs font-semibold text-emerald-400 border border-emerald-800/40">
                  <Check className="h-3 w-3" /> Winner: Variant {campaign.abWinner.toUpperCase()}
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 rounded-full bg-indigo-950/60 px-2.5 py-0.5 text-xs font-semibold text-indigo-300 border border-indigo-800/40 animate-pulse">
                  Evaluating Test Slice...
                </span>
              )}
            </div>

            <div className="p-5 grid gap-4 sm:grid-cols-2">
              {/* Variant A */}
              <div className={`p-4 rounded-xl border transition-all ${
                campaign.abWinner === 'a'
                  ? 'border-emerald-700/60 bg-emerald-950/20'
                  : 'border-zinc-800 bg-zinc-850/60'
              }`}>
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">
                    Variant A (Original)
                  </span>
                  {campaign.abWinner === 'a' && (
                    <span className="text-[10px] uppercase font-bold text-emerald-400 bg-emerald-950 px-1.5 py-0.5 rounded border border-emerald-700/60">
                      Selected Winner
                    </span>
                  )}
                </div>
                <p className="text-sm font-semibold text-white mb-3">
                  &ldquo;{campaign.subject}&rdquo;
                </p>
                <div className="flex items-center justify-between text-xs pt-2 border-t border-zinc-800/80">
                  <span className="text-zinc-400">Unique Opens</span>
                  <span className="font-mono font-bold text-white tabular-nums">
                    {(campaign.variantAOpens ?? 0).toLocaleString()} opens
                  </span>
                </div>
              </div>

              {/* Variant B */}
              <div className={`p-4 rounded-xl border transition-all ${
                campaign.abWinner === 'b'
                  ? 'border-emerald-700/60 bg-emerald-950/20'
                  : 'border-zinc-800 bg-zinc-850/60'
              }`}>
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">
                    Variant B (Challenger)
                  </span>
                  {campaign.abWinner === 'b' && (
                    <span className="text-[10px] uppercase font-bold text-emerald-400 bg-emerald-950 px-1.5 py-0.5 rounded border border-emerald-700/60">
                      Selected Winner
                    </span>
                  )}
                </div>
                <p className="text-sm font-semibold text-white mb-3">
                  &ldquo;{campaign.subjectB ?? 'Variant B'}&rdquo;
                </p>
                <div className="flex items-center justify-between text-xs pt-2 border-t border-zinc-800/80">
                  <span className="text-zinc-400">Unique Opens</span>
                  <span className="font-mono font-bold text-white tabular-nums">
                    {(campaign.variantBOpens ?? 0).toLocaleString()} opens
                  </span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Public Web Archive Share Card */}
        {archiveUrl && (
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-xs">
            <div className="flex items-center gap-3 min-w-0 w-full sm:w-auto">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-500/10 text-orange-400">
                <Layers className="h-4 w-4" />
              </div>
              <div className="min-w-0">
                <span className="block text-xs font-semibold text-white">
                  Published to Web Archive
                </span>
                <span className="block truncate text-xs text-zinc-400 font-mono">
                  {archiveUrl}
                </span>
              </div>
            </div>
            <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
              <button
                type="button"
                onClick={() => copyArchiveUrl(archiveUrl)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 shadow-xs transition-colors"
              >
                {isCopied ? (
                  <>
                    <Check className="h-3.5 w-3.5 text-emerald-400" />
                    <span>Copied!</span>
                  </>
                ) : (
                  <>
                    <Copy className="h-3.5 w-3.5" />
                    <span>Copy Link</span>
                  </>
                )}
              </button>
              <a
                href={archiveUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white px-3 py-1.5 text-xs font-medium shadow-xs transition-colors"
              >
                <ExternalLink className="h-3.5 w-3.5" />
                <span>Visit Page</span>
              </a>
            </div>
          </div>
        )}

        {/* Provider Breakdown Table */}
        <ProviderTable providers={providers} />

        {/* Top Clicked Links Table */}
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shadow-xs">
          <div className="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
            <div className="flex items-center gap-2.5">
              <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-400">
                <MousePointerClick className="h-4 w-4" />
              </div>
              <div>
                <h2 className="text-sm font-semibold text-white">Top Clicked Links</h2>
                <p className="text-xs text-zinc-400">Engagement breakdown for links embedded in this campaign</p>
              </div>
            </div>
            <span className="text-xs font-medium text-zinc-400">
              {clickedLinks.length} distinct {clickedLinks.length === 1 ? 'link' : 'links'} detected
            </span>
          </div>

          {clickedLinks.length === 0 ? (
            <div className="p-8 text-center text-xs text-zinc-400">
              No hyperlinks were detected in this campaign&apos;s email body.
            </div>
          ) : (
            <div className="divide-y divide-zinc-800/60 overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-zinc-950/50 text-[11px] font-medium uppercase tracking-wider text-zinc-400">
                  <tr>
                    <th className="px-5 py-3">Link Destination</th>
                    <th className="px-5 py-3 text-right">Clicks</th>
                    <th className="px-5 py-3 text-right">Unique</th>
                    <th className="px-5 py-3 text-right w-44">Share</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-zinc-800/40 text-zinc-300">
                  {clickedLinks.map((link, idx) => (
                    <tr key={idx} className="hover:bg-zinc-800/30 transition-colors">
                      <td className="px-5 py-3.5 max-w-md">
                        <div className="flex items-center gap-2">
                          <a
                            href={link.url}
                            target="_blank"
                            rel="noreferrer"
                            className="font-medium text-white hover:text-indigo-400 truncate max-w-sm flex items-center gap-1.5 group"
                            title={link.url}
                          >
                            <span className="truncate">{link.text || link.url}</span>
                            <ExternalLink className="h-3 w-3 text-zinc-500 group-hover:text-indigo-400 shrink-0" />
                          </a>
                        </div>
                        <div className="font-mono text-[11px] text-zinc-400 truncate mt-0.5">
                          {link.url}
                        </div>
                      </td>
                      <td className="px-5 py-3.5 text-right font-semibold text-white tabular-nums">
                        {link.clicks.toLocaleString()}
                      </td>
                      <td className="px-5 py-3.5 text-right text-zinc-400 tabular-nums">
                        {(link.uniqueClicks ?? link.clicks).toLocaleString()}
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <div className="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-800">
                            <div
                              className="h-full rounded-full bg-indigo-500"
                              style={{ width: `${Math.min(100, Math.max(0, link.percentage))}%` }}
                            />
                          </div>
                          <span className="font-mono text-xs text-zinc-300 tabular-nums w-10 text-right">
                            {link.percentage.toFixed(1)}%
                          </span>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Email Preview Frame */}
        <EmailPreviewFrame
          subject={campaign.subject}
          previewText={campaign.previewText}
          htmlContent={campaign.html ?? '<p>No content preview available.</p>'}
          newsletter={currentNewsletter}
          sentAt={campaign.sentAt}
        />
      </div>
    </DashboardLayout>
  );
}
