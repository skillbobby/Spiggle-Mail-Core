import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  Users,
  Send,
  Eye,
  MousePointerClick,
  AlertTriangle,
  ArrowUpRight,
  Plus,
} from 'lucide-react';
import { DashboardLayout } from '@/Layouts/DashboardLayout';
import type { Campaign, Newsletter } from '@/types';

interface MetricCardProps {
  readonly title: string;
  readonly value: string;
  readonly change?: string;
  readonly icon: React.ReactNode;
  readonly helper?: string;
}

const MetricCard: React.FC<MetricCardProps> = ({ title, value, change, icon, helper }) => (
  <div className="p-5 rounded-2xl bg-white dark:bg-[#1a1d26] border border-slate-200 dark:border-[#262c3b] shadow-xs">
    <div className="flex items-center justify-between text-slate-500 dark:text-zinc-400">
      <span className="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-400">{title}</span>
      <div className="p-2 rounded-xl bg-indigo-50 dark:bg-zinc-800/80 text-indigo-600 dark:text-zinc-300 border border-indigo-100/80 dark:border-zinc-700/60">{icon}</div>
    </div>
    <div className="mt-3 flex items-baseline gap-2">
      <p className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">{value}</p>
      {change && (
        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center">
          {change}
        </span>
      )}
    </div>
    {helper && <p className="mt-1 text-xs text-slate-500 dark:text-zinc-400">{helper}</p>}
  </div>
);

interface OverviewProps {
  readonly currentNewsletter: Newsletter | null;
  readonly stats: {
    readonly totalSubscribers: number;
    readonly totalSent: number;
    readonly avgOpenRate: number;
    readonly avgClickRate: number;
    readonly bounceRate: number;
  };
  readonly recentCampaigns: readonly Campaign[];
}

export default function Overview({
  currentNewsletter,
  stats,
  recentCampaigns = [],
}: OverviewProps): React.JSX.Element {
  return (
    <DashboardLayout currentNewsletter={currentNewsletter}>
      <Head title="Overview Dashboard" />

      {/* Top Header & Quick Actions */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-8 border-b border-slate-200 dark:border-zinc-800/80">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            {currentNewsletter?.name || 'Publication Overview'}
          </h1>
          <p className="text-sm text-slate-500 dark:text-zinc-400 mt-1">
            Performance metrics, list growth, and deliverability health for the last 30 days.
          </p>
        </div>

        <div className="flex items-center gap-3 shrink-0">
          <Link
            href="/contacts"
            className="px-4 py-2 text-sm font-semibold rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 transition shadow-xs whitespace-nowrap"
          >
            Manage Contacts
          </Link>
          <Link
            href="/campaigns/new"
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm shadow-indigo-600/20 whitespace-nowrap"
          >
            <Plus className="w-4 h-4" aria-hidden="true" />
            New Campaign
          </Link>
        </div>
      </div>

      {/* Metrics Row */}
      <section aria-label="Key Performance Metrics" className="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <MetricCard
          title="Active Subscribers"
          value={stats.totalSubscribers.toLocaleString()}
          change="+12% this mo"
          icon={<Users className="w-4 h-4" aria-hidden="true" />}
        />
        <MetricCard
          title="Total Sent"
          value={stats.totalSent.toLocaleString()}
          icon={<Send className="w-4 h-4" aria-hidden="true" />}
        />
        <MetricCard
          title="Avg Open Rate"
          value={`${(stats.avgOpenRate * 100).toFixed(1)}%`}
          helper="Cleaned from bot scanners"
          icon={<Eye className="w-4 h-4" aria-hidden="true" />}
        />
        <MetricCard
          title="Avg Click Rate"
          value={`${(stats.avgClickRate * 100).toFixed(1)}%`}
          icon={<MousePointerClick className="w-4 h-4" aria-hidden="true" />}
        />
        <MetricCard
          title="Bounce Rate"
          value={`${(stats.bounceRate * 100).toFixed(2)}%`}
          helper="Threshold: 2.00%"
          icon={<AlertTriangle className="w-4 h-4 text-emerald-400" aria-hidden="true" />}
        />
      </section>

      {/* Recent Campaigns Section */}
      <section aria-label="Recent Campaigns" className="mt-12">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-white tracking-tight">Recent Issues & Sends</h2>
          <Link
            href="/campaigns"
            className="text-xs font-semibold text-indigo-400 hover:text-indigo-300 flex items-center gap-1 transition"
          >
            View all
            <ArrowUpRight className="w-3.5 h-3.5" aria-hidden="true" />
          </Link>
        </div>

        {recentCampaigns.length === 0 ? (
          <div className="p-12 text-center rounded-2xl bg-slate-50 dark:bg-zinc-900/50 border border-slate-200 dark:border-zinc-800/60 shadow-xs">
            <div className="w-12 h-12 rounded-full bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex items-center justify-center mx-auto text-indigo-600 dark:text-zinc-400 mb-4 shadow-xs">
              <Send className="w-5 h-5" aria-hidden="true" />
            </div>
            <h3 className="text-base font-bold text-slate-900 dark:text-white">No campaigns sent yet</h3>
            <p className="text-sm text-slate-600 dark:text-zinc-400 mt-1 max-w-sm mx-auto">
              Ready to publish your first issue? Compose in our rich editor, verify deliverability, and send.
            </p>
            <Link
              href="/campaigns/new"
              className="inline-flex items-center gap-2 mt-6 px-4 py-2.5 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm"
            >
              <Plus className="w-4 h-4" aria-hidden="true" />
              Write First Issue
            </Link>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-zinc-800 bg-zinc-900/30">
            <table className="w-full text-left text-sm text-zinc-300">
              <thead className="bg-zinc-900/80 border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400">
                <tr>
                  <th scope="col" className="px-6 py-3.5 font-semibold">Subject</th>
                  <th scope="col" className="px-6 py-3.5 font-semibold">Status</th>
                  <th scope="col" className="px-6 py-3.5 font-semibold">Sent</th>
                  <th scope="col" className="px-6 py-3.5 font-semibold">Opens</th>
                  <th scope="col" className="px-6 py-3.5 font-semibold">Clicks</th>
                  <th scope="col" className="px-6 py-3.5 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-800/60">
                {recentCampaigns.map((c) => (
                  <tr key={c.id} className="hover:bg-zinc-800/30 transition">
                    <td className="px-6 py-4 font-medium text-white">{c.subject}</td>
                    <td className="px-6 py-4">
                      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        {c.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-zinc-400">{c.sentCount.toLocaleString()}</td>
                    <td className="px-6 py-4">{(c.openRate * 100).toFixed(1)}%</td>
                    <td className="px-6 py-4">{(c.clickRate * 100).toFixed(1)}%</td>
                    <td className="px-6 py-4 text-right">
                      <Link
                        href={`/campaigns/${c.id}`}
                        className="text-indigo-400 hover:text-indigo-300 font-medium text-xs"
                      >
                        Analytics &rarr;
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </DashboardLayout>
  );
}
