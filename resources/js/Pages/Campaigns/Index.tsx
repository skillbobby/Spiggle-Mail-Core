import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  Send,
  Plus,
  Search,
  Pin,
  Copy,
  Trash2,
  BarChart2,
  Calendar,
  Clock,
  Play,
  Pause,
  ExternalLink,
  ChevronLeft,
  ChevronRight,
  Mail,
  AlertCircle,
} from 'lucide-react';
import { DashboardLayout } from '@/Layouts/DashboardLayout';
import type { Newsletter } from '@/types';

interface CampaignItem {
  readonly id: string;
  readonly subject: string;
  readonly previewText: string;
  readonly status: 'draft' | 'scheduled' | 'sending' | 'sent' | 'paused' | 'canceled';
  readonly archivePinned: boolean;
  readonly archiveSlug: string;
  readonly sentCount: number;
  readonly totalRecipients: number;
  readonly openRate: number;
  readonly clickRate: number;
  readonly bounceRate: number;
  readonly scheduledAt?: string;
  readonly sentAt?: string;
  readonly createdAt: string;
}

interface PaginationData {
  readonly currentPage: number;
  readonly lastPage: number;
  readonly perPage: number;
  readonly total: number;
  readonly from: number | null;
  readonly to: number | null;
}

interface StatusCounts {
  readonly all: number;
  readonly draft: number;
  readonly scheduled: number;
  readonly sending: number;
  readonly sent: number;
  readonly paused: number;
}

interface CampaignsIndexProps {
  readonly currentNewsletter: Newsletter | null;
  readonly campaigns: readonly CampaignItem[];
  readonly statusCounts: StatusCounts;
  readonly pagination: PaginationData;
  readonly filters: {
    readonly status: string;
    readonly search: string;
  };
}

const STATUS_BADGES: Record<string, { label: string; class: string }> = {
  draft: { label: 'Draft', class: 'bg-zinc-800 text-zinc-400 border-zinc-700/60' },
  scheduled: { label: 'Scheduled', class: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
  sending: { label: 'Sending...', class: 'bg-blue-500/10 text-blue-400 border-blue-500/20 animate-pulse' },
  sent: { label: 'Sent', class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
  paused: { label: 'Paused', class: 'bg-purple-500/10 text-purple-400 border-purple-500/20' },
  canceled: { label: 'Canceled', class: 'bg-rose-500/10 text-rose-400 border-rose-500/20' },
};

export default function CampaignsIndex({
  currentNewsletter,
  campaigns = [],
  statusCounts = { all: 0, draft: 0, scheduled: 0, sending: 0, sent: 0, paused: 0 },
  pagination = { currentPage: 1, lastPage: 1, perPage: 15, total: 0, from: null, to: null },
  filters = { status: 'all', search: '' },
}: CampaignsIndexProps): React.JSX.Element {
  const [searchTerm, setSearchTerm] = useState<string>(filters.search || '');

  const handleFilterStatus = (newStatus: string) => {
    router.get(
      '/campaigns',
      { status: newStatus, search: searchTerm || undefined },
      { preserveState: true, replace: true }
    );
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(
      '/campaigns',
      { status: filters.status !== 'all' ? filters.status : undefined, search: searchTerm || undefined },
      { preserveState: true, replace: true }
    );
  };

  const handleDuplicate = (id: string) => {
    router.post(`/campaigns/${id}/duplicate`);
  };

  const handleTogglePin = (id: string) => {
    router.post(`/campaigns/${id}/toggle-pin`);
  };

  const handlePauseResume = (id: string, isSending: boolean) => {
    router.post(`/campaigns/${id}/${isSending ? 'pause' : 'resume'}`);
  };

  const handleDelete = (id: string, subject: string) => {
    if (!window.confirm(`Are you sure you want to delete campaign "${subject}"? This cannot be undone.`)) {
      return;
    }
    router.delete(`/campaigns/${id}`);
  };

  return (
    <DashboardLayout currentNewsletter={currentNewsletter}>
      <Head title="Campaigns & Dispatches" />

      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-8 border-b border-zinc-800/80">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Campaigns &amp; Newsletters</h1>
          <p className="text-sm text-slate-500 dark:text-zinc-400 mt-1">
            Manage your drafts, scheduled dispatches, and sent newsletter archive.
          </p>
        </div>

        <Link
          href="/campaigns/new"
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm shadow-indigo-600/20 whitespace-nowrap"
        >
          <Plus className="w-4 h-4" aria-hidden="true" />
          New Newsletter
        </Link>
      </div>

      {/* Filter Tabs */}
      <div className="mt-6 flex flex-wrap items-center gap-2 border-b border-zinc-800/80 pb-4">
        {[
          { key: 'all', label: 'All', count: statusCounts.all },
          { key: 'draft', label: 'Drafts', count: statusCounts.draft },
          { key: 'scheduled', label: 'Scheduled', count: statusCounts.scheduled },
          { key: 'sending', label: 'Sending', count: statusCounts.sending },
          { key: 'sent', label: 'Sent', count: statusCounts.sent },
          { key: 'paused', label: 'Paused', count: statusCounts.paused },
        ].map((tab) => {
          const isActive = filters.status === tab.key;
          return (
            <button
              key={tab.key}
              type="button"
              onClick={() => handleFilterStatus(tab.key)}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                isActive
                  ? 'bg-zinc-800 text-white border border-zinc-700'
                  : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900 border border-transparent'
              }`}
            >
              <span>{tab.label}</span>
              <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${isActive ? 'bg-zinc-700 text-zinc-200' : 'bg-zinc-900 text-zinc-500'}`}>
                {tab.count}
              </span>
            </button>
          );
        })}
      </div>

      {/* Search Toolbar */}
      <div className="mt-4 flex items-center justify-between gap-3">
        <form onSubmit={handleSearchSubmit} className="relative w-full sm:max-w-md">
          <Search className="w-4 h-4 text-zinc-500 absolute left-3.5 top-3" aria-hidden="true" />
          <input
            type="search"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="Search campaigns by subject and press Enter..."
            className="w-full text-sm pl-10 pr-4 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition"
          />
        </form>
      </div>

      {/* Campaigns Table */}
      <div className="mt-6 rounded-2xl border border-zinc-800 bg-zinc-900/30 overflow-hidden">
        {campaigns.length === 0 ? (
          <div className="p-12 text-center">
            <Mail className="w-10 h-10 text-zinc-600 mx-auto mb-3" aria-hidden="true" />
            <h3 className="text-base font-semibold text-white">No campaigns found</h3>
            <p className="text-xs text-zinc-400 mt-1 max-w-sm mx-auto">
              Draft your first newsletter issue or announcement in the composer.
            </p>
            <div className="mt-4">
              <Link
                href="/campaigns/new"
                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition"
              >
                <Plus className="w-3.5 h-3.5" />
                New Newsletter
              </Link>
            </div>
          </div>
        ) : (
          <table className="w-full text-left text-sm text-zinc-300">
            <thead className="bg-zinc-900/80 border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400">
              <tr>
                <th scope="col" className="px-6 py-3.5 font-semibold">Campaign</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Status</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Sent</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Open Rate</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Click Rate</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Timing</th>
                <th scope="col" className="px-6 py-3.5 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-800/60 font-sans">
              {campaigns.map((c) => {
                const badge = STATUS_BADGES[c.status] || STATUS_BADGES.draft;
                return (
                  <tr key={c.id} className="hover:bg-zinc-800/25 transition">
                    {/* Campaign Title & Snippet */}
                    <td className="px-6 py-4 max-w-xs">
                      <div className="flex items-center gap-2">
                        {c.archivePinned && (
                          <Pin className="w-3.5 h-3.5 text-amber-400 fill-amber-400 shrink-0" title="Pinned to Archive" />
                        )}
                        <Link
                          href={`/campaigns/${c.id}`}
                          className="font-semibold text-white hover:text-indigo-400 transition truncate block"
                        >
                          {c.subject}
                        </Link>
                      </div>
                      {c.previewText && (
                        <p className="text-xs text-zinc-500 truncate mt-0.5">{c.previewText}</p>
                      )}
                    </td>

                    {/* Status Badge */}
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border ${badge.class}`}>
                        {badge.label}
                      </span>
                    </td>

                    {/* Sent Count */}
                    <td className="px-6 py-4 font-mono text-xs text-zinc-300">
                      {c.sentCount.toLocaleString()}
                    </td>

                    {/* Open Rate */}
                    <td className="px-6 py-4">
                      {c.status === 'sent' || c.sentCount > 0 ? (
                        <span className="font-semibold text-emerald-400 text-xs">
                          {(c.openRate * 100).toFixed(1)}%
                        </span>
                      ) : (
                        <span className="text-zinc-600 text-xs">&mdash;</span>
                      )}
                    </td>

                    {/* Click Rate */}
                    <td className="px-6 py-4">
                      {c.status === 'sent' || c.sentCount > 0 ? (
                        <span className="font-semibold text-indigo-400 text-xs">
                          {(c.clickRate * 100).toFixed(1)}%
                        </span>
                      ) : (
                        <span className="text-zinc-600 text-xs">&mdash;</span>
                      )}
                    </td>

                    {/* Timing */}
                    <td className="px-6 py-4 whitespace-nowrap text-xs text-zinc-400">
                      {c.status === 'scheduled' && c.scheduledAt ? (
                        <span className="text-amber-400 flex items-center gap-1">
                          <Clock className="w-3.5 h-3.5" />
                          {c.scheduledAt}
                        </span>
                      ) : c.sentAt ? (
                        c.sentAt
                      ) : (
                        `Drafted ${c.createdAt}`
                      )}
                    </td>

                    {/* Actions */}
                    <td className="px-6 py-4 whitespace-nowrap text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <Link
                          href={`/campaigns/${c.id}`}
                          className="p-1.5 rounded-lg border border-zinc-800 text-zinc-400 hover:text-white hover:bg-zinc-800 transition"
                          title="View Delivery Report"
                        >
                          <BarChart2 className="w-3.5 h-3.5" />
                        </Link>

                        {(c.status === 'sending' || c.status === 'paused') && (
                          <button
                            type="button"
                            onClick={() => handlePauseResume(c.id, c.status === 'sending')}
                            className="p-1.5 rounded-lg border border-zinc-800 text-zinc-400 hover:text-white hover:bg-zinc-800 transition"
                            title={c.status === 'sending' ? 'Pause Campaign' : 'Resume Campaign'}
                          >
                            {c.status === 'sending' ? <Pause className="w-3.5 h-3.5" /> : <Play className="w-3.5 h-3.5" />}
                          </button>
                        )}

                        <button
                          type="button"
                          onClick={() => handleTogglePin(c.id)}
                          className={`p-1.5 rounded-lg border border-zinc-800 transition ${c.archivePinned ? 'text-amber-400 bg-amber-500/10 border-amber-500/20' : 'text-zinc-400 hover:text-white hover:bg-zinc-800'}`}
                          title={c.archivePinned ? 'Unpin from Archive' : 'Pin to Archive'}
                        >
                          <Pin className="w-3.5 h-3.5" />
                        </button>

                        <button
                          type="button"
                          onClick={() => handleDuplicate(c.id)}
                          className="p-1.5 rounded-lg border border-zinc-800 text-zinc-400 hover:text-white hover:bg-zinc-800 transition"
                          title="Duplicate Campaign"
                        >
                          <Copy className="w-3.5 h-3.5" />
                        </button>

                        <button
                          type="button"
                          onClick={() => handleDelete(c.id, c.subject)}
                          className="p-1.5 rounded-lg border border-zinc-800 text-zinc-500 hover:text-rose-400 hover:bg-zinc-800 transition"
                          title="Delete Campaign"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}

        {/* Pagination Bar */}
        {pagination.total > pagination.perPage && (
          <div className="flex items-center justify-between px-6 py-3 border-t border-zinc-800/80 bg-zinc-900/50 text-xs text-zinc-400">
            <div>
              Showing {pagination.from} to {pagination.to} of {pagination.total} campaigns
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                disabled={pagination.currentPage <= 1}
                onClick={() =>
                  router.get(
                    '/campaigns',
                    { page: pagination.currentPage - 1, status: filters.status, search: filters.search },
                    { preserveState: true }
                  )
                }
                className="p-1.5 rounded-lg border border-zinc-800 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-zinc-800 transition text-zinc-300"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <span className="font-semibold text-white">
                Page {pagination.currentPage} of {pagination.lastPage}
              </span>
              <button
                type="button"
                disabled={pagination.currentPage >= pagination.lastPage}
                onClick={() =>
                  router.get(
                    '/campaigns',
                    { page: pagination.currentPage + 1, status: filters.status, search: filters.search },
                    { preserveState: true }
                  )
                }
                className="p-1.5 rounded-lg border border-zinc-800 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-zinc-800 transition text-zinc-300"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
