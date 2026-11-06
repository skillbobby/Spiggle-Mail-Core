import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
  Users,
  Search,
  Upload,
  Download,
  Plus,
  Trash2,
  X,
  AlertCircle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react';
import { DashboardLayout } from '@/Layouts/DashboardLayout';
import { ImportContactsModal } from '@/Components/Contacts/ImportContactsModal';
import { getEmailTypoSuggestion } from '@/utils/emailTypos';
import type { Contact, Newsletter, ContactStatus } from '@/types';

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
  readonly subscribed: number;
  readonly pending: number;
  readonly dormant: number;
  readonly unsubscribed: number;
  readonly bounced: number;
}

interface ContactsPageProps {
  readonly currentNewsletter: Newsletter | null;
  readonly contacts: readonly Contact[];
  readonly totalContacts: number;
  readonly statusCounts: StatusCounts;
  readonly pagination: PaginationData;
  readonly filters: {
    readonly status: string;
    readonly search: string;
  };
}

const STATUS_BADGES: Record<string, { label: string; class: string }> = {
  subscribed: { label: 'Subscribed', class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
  unsubscribed: { label: 'Unsubscribed', class: 'bg-zinc-800 text-zinc-400 border-zinc-700/60' },
  bounced: { label: 'Bounced', class: 'bg-rose-500/10 text-rose-400 border-rose-500/20' },
  complained: { label: 'Complained', class: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
  dormant: { label: 'Dormant (Sunset)', class: 'bg-purple-500/10 text-purple-400 border-purple-500/20' },
  pending: { label: 'Pending Confirmation', class: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
};

export default function Contacts({
  currentNewsletter,
  contacts = [],
  totalContacts = 0,
  statusCounts = { all: 0, subscribed: 0, pending: 0, dormant: 0, unsubscribed: 0, bounced: 0 },
  pagination = { currentPage: 1, lastPage: 1, perPage: 25, total: 0, from: null, to: null },
  filters = { status: 'all', search: '' },
}: ContactsPageProps): React.JSX.Element {
  const [searchTerm, setSearchTerm] = useState<string>(filters.search || '');
  const [isImportModalOpen, setIsImportModalOpen] = useState<boolean>(false);
  const [isAddModalOpen, setIsAddModalOpen] = useState<boolean>(false);
  const [newEmail, setNewEmail] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const handleFilterStatus = (newStatus: string) => {
    router.get(
      '/contacts',
      { status: newStatus, search: searchTerm || undefined },
      { preserveState: true, replace: true }
    );
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(
      '/contacts',
      { status: filters.status !== 'all' ? filters.status : undefined, search: searchTerm || undefined },
      { preserveState: true, replace: true }
    );
  };

  const handleImportSubmit = (file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    setIsSubmitting(true);
    router.post('/contacts/import', formData, {
      onFinish: () => setIsSubmitting(false),
      onError: (errors) => {
        setActionError(errors.file || 'Failed to import CSV file.');
      },
    });
  };

  const handleAddSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newEmail.trim()) return;
    setIsSubmitting(true);
    setActionError(null);
    router.post(
      '/contacts',
      { email: newEmail.trim() },
      {
        onSuccess: () => {
          setNewEmail('');
          setIsAddModalOpen(false);
        },
        onError: (errors) => {
          setActionError(errors.email || 'Failed to add subscriber.');
        },
        onFinish: () => setIsSubmitting(false),
      }
    );
  };

  const handleDeleteContact = (contactId: string, email: string) => {
    if (!window.confirm(`Are you sure you want to remove ${email} from your mailing list?`)) {
      return;
    }
    router.delete(`/contacts/${contactId}`);
  };

  return (
    <DashboardLayout currentNewsletter={currentNewsletter}>
      <Head title="Contacts & Subscribers" />

      {/* Page Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-8 border-b border-zinc-800/80">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white">Contacts & Subscribers</h1>
          <p className="text-sm text-zinc-400 mt-1">
            {totalContacts.toLocaleString()} total readers across your active publication list.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <a
            href="/api/v1/contacts/export.csv"
            download
            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-zinc-900 border border-zinc-800 hover:bg-zinc-800 text-zinc-300 transition"
          >
            <Download className="w-3.5 h-3.5" aria-hidden="true" />
            Export CSV
          </a>
          <button
            type="button"
            onClick={() => setIsImportModalOpen(true)}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm shadow-indigo-600/20"
          >
            <Upload className="w-3.5 h-3.5" aria-hidden="true" />
            Import CSV
          </button>
          <button
            type="button"
            onClick={() => {
              setActionError(null);
              setIsAddModalOpen(true);
            }}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-zinc-800 hover:bg-zinc-700 text-white transition"
          >
            <Plus className="w-3.5 h-3.5" aria-hidden="true" />
            Add Contact
          </button>
        </div>
      </div>

      {actionError && (
        <div className="mt-4 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{actionError}</span>
          </div>
          <button type="button" onClick={() => setActionError(null)} className="p-1 hover:text-white">
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Status Filter Tabs */}
      <div className="mt-6 flex flex-wrap items-center gap-2 border-b border-zinc-800/80 pb-4">
        {[
          { key: 'all', label: 'All', count: statusCounts.all },
          { key: 'subscribed', label: 'Subscribed', count: statusCounts.subscribed },
          { key: 'pending', label: 'Pending', count: statusCounts.pending },
          { key: 'dormant', label: 'Dormant', count: statusCounts.dormant },
          { key: 'unsubscribed', label: 'Unsubscribed', count: statusCounts.unsubscribed },
          { key: 'bounced', label: 'Bounced', count: statusCounts.bounced },
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
            placeholder="Search contacts by email and press Enter..."
            className="w-full text-sm pl-10 pr-4 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition"
          />
        </form>
      </div>

      {/* Data Table */}
      <div className="mt-6 rounded-2xl border border-zinc-800 bg-zinc-900/30 overflow-hidden">
        {contacts.length === 0 ? (
          <div className="p-12 text-center">
            <Users className="w-10 h-10 text-zinc-600 mx-auto mb-3" aria-hidden="true" />
            <h3 className="text-base font-semibold text-white">No contacts found</h3>
            <p className="text-xs text-zinc-400 mt-1 max-w-sm mx-auto">
              Import a CSV from Beehiiv, Substack, Mailchimp, or add single addresses manually.
            </p>
          </div>
        ) : (
          <table className="w-full text-left text-sm text-zinc-300">
            <thead className="bg-zinc-900/80 border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400">
              <tr>
                <th scope="col" className="px-6 py-3.5 font-semibold">Subscriber</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Status</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Opens</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Clicks</th>
                <th scope="col" className="px-6 py-3.5 font-semibold">Subscribed</th>
                <th scope="col" className="px-6 py-3.5 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-800/60 font-sans">
              {contacts.map((c) => {
                const badge = STATUS_BADGES[c.status] || STATUS_BADGES.subscribed;
                return (
                  <tr key={c.id} className="hover:bg-zinc-800/25 transition">
                    <td className="px-6 py-4 font-mono text-xs text-white">{c.email}</td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border ${badge.class}`}>
                        {badge.label}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-zinc-400">{c.openCount}</td>
                    <td className="px-6 py-4 text-zinc-400">{c.clickCount}</td>
                    <td className="px-6 py-4 text-zinc-500 text-xs">{c.createdAt}</td>
                    <td className="px-6 py-4 text-right">
                      <button
                        type="button"
                        onClick={() => handleDeleteContact(c.id, c.email)}
                        className="p-1 rounded text-zinc-500 hover:text-rose-400 hover:bg-zinc-800 transition"
                        title="Delete Contact"
                        aria-label={`Delete ${c.email}`}
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
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
              Showing {pagination.from} to {pagination.to} of {pagination.total} subscribers
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                disabled={pagination.currentPage <= 1}
                onClick={() =>
                  router.get(
                    '/contacts',
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
                    '/contacts',
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

      {/* Add Single Contact Modal */}
      {isAddModalOpen && (
        <div
          role="dialog"
          aria-modal="true"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs"
        >
          <div className="w-full max-w-md rounded-2xl bg-zinc-950 border border-zinc-800 shadow-2xl p-6 text-left">
            <div className="flex items-center justify-between pb-4 border-b border-zinc-800">
              <h3 className="text-base font-bold text-white">Add New Subscriber</h3>
              <button
                type="button"
                onClick={() => setIsAddModalOpen(false)}
                className="text-zinc-400 hover:text-white"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleAddSubmit} className="mt-4 space-y-4">
              <div>
                <label htmlFor="new-email" className="block text-xs font-semibold text-zinc-300 mb-1">
                  Email Address
                </label>
                <input
                  id="new-email"
                  type="email"
                  required
                  value={newEmail}
                  onChange={(e) => setNewEmail(e.target.value)}
                  placeholder="subscriber@example.com"
                  className="w-full px-3.5 py-2.5 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition text-sm"
                />
                {getEmailTypoSuggestion(newEmail) && (
                  <div className="pt-1">
                    <button
                      type="button"
                      onClick={() => setNewEmail(getEmailTypoSuggestion(newEmail) || '')}
                      className="text-xs text-amber-400 hover:text-amber-300 transition text-left cursor-pointer"
                    >
                      Did you mean <strong className="underline underline-offset-2">{getEmailTypoSuggestion(newEmail)}</strong>?
                    </button>
                  </div>
                )}
              </div>

              {actionError && (
                <p className="text-xs text-rose-400 flex items-center gap-1.5">
                  <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                  {actionError}
                </p>
              )}

              <div className="flex items-center justify-end gap-2.5 pt-2">
                <button
                  type="button"
                  onClick={() => setIsAddModalOpen(false)}
                  className="px-4 py-2 rounded-lg text-xs font-semibold text-zinc-400 hover:text-white transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting || !newEmail.trim()}
                  className="px-4 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50 transition"
                >
                  {isSubmitting ? 'Adding...' : 'Add Subscriber'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CSV Import Modal */}
      <ImportContactsModal
        isOpen={isImportModalOpen}
        onClose={() => setIsImportModalOpen(false)}
        onConfirmImport={handleImportSubmit}
      />
    </DashboardLayout>
  );
}
