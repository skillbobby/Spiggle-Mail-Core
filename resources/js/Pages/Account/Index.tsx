import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
  User as UserIcon,
  Lock,
  ShieldCheck,
  Cpu,
  AlertTriangle,
  Trash2,
  CheckCircle2,
  Calendar,
  Layers,
  Mail,
  Send,
  Download,
  History,
  Activity,
} from 'lucide-react';
import { DashboardLayout } from '@/Layouts/DashboardLayout';
import type { AccountInfo, Newsletter, User, ActivityLogItem } from '@/types';

export interface AccountBlockers {
  readonly hasInFlightCampaigns: boolean;
  readonly inFlightCampaigns: readonly { readonly id: string; readonly subject: string }[];
}

export interface AccountIndexProps {
  readonly user: User;
  readonly account: AccountInfo | null;
  readonly currentNewsletter: Newsletter | null;
  readonly recentActivity?: readonly ActivityLogItem[];
  readonly blockers: AccountBlockers;
}

export default function AccountIndex({
  user,
  account,
  currentNewsletter,
  recentActivity = [],
  blockers,
}: AccountIndexProps): React.JSX.Element {
  // Profile form state
  const [name, setName] = useState(user.name);
  const [isUpdatingProfile, setIsUpdatingProfile] = useState(false);

  // Deletion modal / confirmation state
  const [confirmEmail, setConfirmEmail] = useState('');
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const handleUpdateProfile = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim() || isUpdatingProfile) return;

    setIsUpdatingProfile(true);
    router.post(
      '/account/profile',
      { name },
      {
        onFinish: () => setIsUpdatingProfile(false),
      }
    );
  };

  const handleDeleteAccount = (e: React.FormEvent) => {
    e.preventDefault();
    if (confirmEmail.toLowerCase().trim() !== user.email.toLowerCase().trim() || isDeleting) return;

    if (blockers.hasInFlightCampaigns) {
      setDeleteError('Cannot delete account while campaigns are actively sending.');
      return;
    }

    setIsDeleting(true);
    setDeleteError(null);

    router.post(
      '/account/delete',
      { email: confirmEmail },
      {
        onError: (errors) => {
          setDeleteError(errors.email ?? 'Account deletion failed.');
          setIsDeleting(false);
        },
        onFinish: () => setIsDeleting(false),
      }
    );
  };

  const canConfirmDelete = confirmEmail.toLowerCase().trim() === user.email.toLowerCase().trim() && !blockers.hasInFlightCampaigns;

  return (
    <DashboardLayout currentNewsletter={currentNewsletter}>
      <Head title="Account & Security" />

      <div className="max-w-3xl mx-auto space-y-8">
        {/* Page Header */}
        <div className="border-b border-zinc-800 pb-5">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-sm shadow-xs border border-indigo-500/20">
              <UserIcon className="h-5 w-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-bold tracking-tight text-white">
                  Account & Security
                </h1>
                {user.isSuperAdmin && (
                  <span className="inline-flex items-center gap-1 rounded-full bg-amber-950/60 px-2 py-0.5 text-xs font-semibold text-amber-400 border border-amber-800/40">
                    <ShieldCheck className="h-3 w-3" /> Super Admin
                  </span>
                )}
              </div>
              <p className="mt-0.5 text-xs text-zinc-400">
                Manage your administrator profile, sign-in credentials, and workspace settings.
              </p>
            </div>
          </div>
        </div>

        {/* 1. Profile Details Card */}
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm overflow-hidden">
          <div className="border-b border-zinc-800 px-6 py-4">
            <h2 className="text-sm font-semibold text-white">Profile Details</h2>
            <p className="text-xs text-zinc-400 mt-0.5">
              Your name displayed across email author tags and team activity logs.
            </p>
          </div>

          <form onSubmit={handleUpdateProfile} className="p-6 space-y-4">
            <div>
              <label htmlFor="name-input" className="block text-xs font-medium text-zinc-300 mb-1.5">
                Display Name
              </label>
              <input
                id="name-input"
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full max-w-md rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div className="pt-2">
              <button
                type="submit"
                disabled={isUpdatingProfile || name.trim() === user.name}
                className="rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white px-4 py-2 text-xs font-semibold shadow-xs transition-colors"
              >
                {isUpdatingProfile ? 'Saving...' : 'Save Profile'}
              </button>
            </div>
          </form>
        </div>

        {/* 2. Authentication & Sign-in Method Card */}
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm overflow-hidden">
          <div className="border-b border-zinc-800 px-6 py-4">
            <h2 className="text-sm font-semibold text-white">Sign-in & Security</h2>
            <p className="text-xs text-zinc-400 mt-0.5">
              Secure passwordless authentication powered by 2FA verification codes.
            </p>
          </div>

          <div className="p-6 space-y-4">
            <div>
              <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                Primary Account Email
              </label>
              <div className="flex items-center justify-between max-w-md rounded-lg border border-zinc-700 bg-zinc-800/80 px-3.5 py-2 text-sm text-zinc-300">
                <span className="font-mono text-xs">{user.email}</span>
                <span className="flex items-center gap-1 text-[11px] text-zinc-500" title="Email is locked to your account">
                  <Lock className="h-3.5 w-3.5" />
                  <span>Primary</span>
                </span>
              </div>
              <p className="mt-1.5 text-xs text-zinc-500">
                You sign in with a single-use 6-digit verification code delivered to this inbox.
              </p>
            </div>

            <div className="flex items-center gap-3 pt-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-950/60 text-emerald-400 border border-emerald-800/40">
                <CheckCircle2 className="h-4 w-4" />
              </div>
              <div>
                <span className="block text-xs font-semibold text-white">
                  Two-Factor Email OTP Active
                </span>
                <span className="block text-[11px] text-zinc-400">
                  Brute-force protection enabled with 10-minute expiry and 3-attempt lockout.
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* 3. Workspace Plan & Dispatch Engine Card */}
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm overflow-hidden">
          <div className="border-b border-zinc-800 px-6 py-4">
            <h2 className="text-sm font-semibold text-white">Workspace & Deliverability Engine</h2>
            <p className="text-xs text-zinc-400 mt-0.5">
              Engine settings and operational quotas for your self-hosted instance.
            </p>
          </div>

          <div className="p-6 grid gap-4 sm:grid-cols-2">
            <div className="rounded-lg border border-zinc-800 bg-zinc-850 p-4 flex flex-col justify-between">
              <div>
                <span className="text-xs font-medium text-zinc-400">Active Plan</span>
                <p className="text-lg font-bold text-white mt-1">
                  {account?.plan ?? 'Starter Plan'}
                </p>
                <p className="text-xs text-indigo-400 mt-1">
                  Self-service Lemon Squeezy billing
                </p>
              </div>
              <div className="pt-3">
                <a
                  href="/billing"
                  className="inline-flex items-center gap-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 px-3 py-1.5 text-xs font-semibold text-white transition"
                >
                  <span>Manage Plan & Billing</span>
                </a>
              </div>
            </div>

            <div className="rounded-lg border border-zinc-800 bg-zinc-850 p-4">
              <span className="text-xs font-medium text-zinc-400">Active Mail Driver</span>
              <p className="text-lg font-bold text-white mt-1 flex items-center gap-2">
                <Cpu className="h-4 w-4 text-orange-400" />
                <span>{account?.emailProvider ?? 'Local Mailpit (Mock)'}</span>
              </p>
              <p className="text-xs text-zinc-400 mt-1">
                Configured via SES Driver Switcher in Settings
              </p>
            </div>
          </div>
        </div>

        {/* 4. Data Portability & Account Export Card */}
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm overflow-hidden">
          <div className="border-b border-zinc-800 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h2 className="text-sm font-semibold text-white flex items-center gap-2">
                <Download className="h-4 w-4 text-indigo-400" />
                Data Portability & Account Export
              </h2>
              <p className="text-xs text-zinc-400 mt-0.5">
                Download an immutable complete archive of all publications, contacts, campaign HTML, and forensic telemetry.
              </p>
            </div>
            <a
              href="/account/export"
              download
              className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-1.5 text-xs font-semibold shadow-xs transition-colors shrink-0"
            >
              <Download className="h-3.5 w-3.5" />
              <span>Export All Data (.json)</span>
            </a>
          </div>
          <div className="p-6 text-xs text-zinc-400 leading-relaxed">
            Exports adhere to standard GDPR / CCPA data portability formats. Includes full subscriber callsets, campaign drafts, sent issues, domain verification records, and complete activity history.
          </div>
        </div>

        {/* 5. Audit & Activity Trail Card */}
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm overflow-hidden">
          <div className="border-b border-zinc-800 px-6 py-4 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <History className="h-4 w-4 text-zinc-400" />
              <h2 className="text-sm font-semibold text-white">Activity & Audit Trail</h2>
            </div>
            <span className="text-xs text-zinc-500 font-mono">
              Last {recentActivity.length} events
            </span>
          </div>

          {recentActivity.length === 0 ? (
            <div className="p-8 text-center text-xs text-zinc-500">
              No recent audit trail entries logged yet for this account.
            </div>
          ) : (
            <div className="divide-y divide-zinc-800/60 max-h-80 overflow-y-auto">
              {recentActivity.map((log) => (
                <div key={log.id} className="p-4 hover:bg-zinc-850/50 transition-colors flex items-start justify-between gap-4 text-xs">
                  <div className="space-y-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-mono font-semibold uppercase tracking-wider bg-zinc-800 text-zinc-300 border border-zinc-700">
                        {log.action}
                      </span>
                      {log.ipAddress && (
                        <span className="text-[11px] text-zinc-500 font-mono">
                          {log.ipAddress}
                        </span>
                      )}
                    </div>
                    <p className="text-zinc-200 font-medium truncate">
                      {log.description}
                    </p>
                  </div>
                  <span className="text-[11px] text-zinc-500 whitespace-nowrap tabular-nums shrink-0">
                    {new Date(log.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} · {new Date(log.createdAt).toLocaleDateString()}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* 6. Danger Zone / Delete Account Safeguard Card */}
        <div className="rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-white dark:bg-zinc-900 shadow-xs overflow-hidden">
          <div className="border-b border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/30 px-6 py-4">
            <h2 className="text-sm font-bold text-rose-900 dark:text-rose-200 flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-rose-600 dark:text-rose-400" />
              Danger Zone
            </h2>
            <p className="text-xs text-rose-700 dark:text-rose-300 mt-0.5 font-medium">
              Permanently pause or delete your account, newsletters, and reader callsets.
            </p>
          </div>

          <div className="p-6 space-y-4">
            <p className="text-xs text-zinc-400 leading-relaxed">
              When deletion is requested, your account stops immediately. You enter a{' '}
              <strong className="text-zinc-200">30-day grace period</strong> during which signing back in
              restores your account. After 30 days, all newsletters, subscribers, campaigns, and tracking tokens
              are permanently wiped.
            </p>

            {blockers.hasInFlightCampaigns && (
              <div className="rounded-lg border border-amber-800/60 bg-amber-950/40 p-4 text-xs text-amber-200 space-y-2">
                <div className="flex items-center gap-2 font-semibold">
                  <Send className="h-4 w-4 text-amber-400 animate-spin" />
                  <span>Cannot delete account: Active campaigns in flight</span>
                </div>
                <p className="text-amber-300">
                  Please pause or finish sending the following campaigns before requesting account deletion:
                </p>
                <ul className="list-disc list-inside space-y-1 text-zinc-300">
                  {blockers.inFlightCampaigns.map((c) => (
                    <li key={c.id}>{c.subject}</li>
                  ))}
                </ul>
              </div>
            )}

            {deleteError && (
              <div className="rounded-lg border border-rose-800/60 bg-rose-950/40 p-3 text-xs text-rose-300">
                {deleteError}
              </div>
            )}

            <form onSubmit={handleDeleteAccount} className="pt-2 space-y-3">
              <label htmlFor="confirm-email-input" className="block text-xs font-medium text-zinc-300">
                To confirm deletion, type your exact email address (
                <span className="font-mono text-zinc-200">{user.email}</span>):
              </label>
              <div className="flex flex-col sm:flex-row gap-3">
                <input
                  id="confirm-email-input"
                  type="email"
                  value={confirmEmail}
                  onChange={(e) => setConfirmEmail(e.target.value)}
                  placeholder={user.email}
                  disabled={blockers.hasInFlightCampaigns || isDeleting}
                  className="flex-1 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-rose-500 disabled:opacity-50 font-mono"
                />
                <button
                  type="submit"
                  disabled={!canConfirmDelete || isDeleting}
                  className="rounded-lg bg-rose-600 hover:bg-rose-500 disabled:opacity-40 text-white px-4 py-2 text-xs font-semibold shadow-xs transition-colors flex items-center justify-center gap-1.5 shrink-0"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  <span>{isDeleting ? 'Scheduling...' : 'Delete Account'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
