import React from 'react';
import { Mail, Globe, CheckCircle2 } from 'lucide-react';
import type { ProviderStat } from '@/types';

export interface ProviderTableProps {
  readonly providers: readonly ProviderStat[];
}

export const ProviderTable: React.FC<ProviderTableProps> = ({ providers }) => {
  if (providers.length === 0) {
    return (
      <div className="rounded-xl border border-zinc-800 bg-zinc-900 p-8 text-center text-sm text-zinc-400">
        <Mail className="mx-auto h-8 w-8 text-zinc-600 mb-2" />
        No inbox telemetry recorded yet.
      </div>
    );
  }

  const formatPercent = (count: number, base: number): string => {
    if (base <= 0) return '0.0%';
    return `${((count / base) * 100).toFixed(1)}%`;
  };

  return (
    <div className="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
      <div className="border-b border-zinc-800 px-5 py-4">
        <h3 className="text-sm font-semibold text-white flex items-center gap-2">
          <CheckCircle2 className="h-4 w-4 text-emerald-500" />
          Inbox Provider Breakdown
        </h3>
        <p className="mt-1 text-xs text-zinc-400">
          Real-time performance across major consumer and enterprise email providers.
        </p>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs">
          <thead className="bg-zinc-800/60 text-zinc-400 font-medium border-b border-zinc-800">
            <tr>
              <th scope="col" className="px-5 py-3">Inbox / MX</th>
              <th scope="col" className="px-4 py-3 text-right">Sent</th>
              <th scope="col" className="px-4 py-3 text-right">Delivered</th>
              <th scope="col" className="px-4 py-3 text-right">Opened</th>
              <th scope="col" className="px-4 py-3 text-right">Clicked</th>
              <th scope="col" className="px-4 py-3 text-right">Bounced</th>
              <th scope="col" className="px-5 py-3 text-right">Complaints</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-zinc-800 tabular-nums">
            {providers.map((row) => (
              <tr key={row.provider} className="hover:bg-zinc-800/40 transition-colors text-zinc-300">
                <td className="px-5 py-3.5 font-medium text-white flex items-center gap-2.5">
                  <span className="flex h-6 w-6 items-center justify-center rounded-md bg-zinc-800 text-zinc-400">
                    <Globe className="h-3.5 w-3.5" />
                  </span>
                  <div>
                    <span className="block font-semibold">{row.label}</span>
                    {row.domain && (
                      <span className="block text-[11px] text-zinc-500">{row.domain}</span>
                    )}
                  </div>
                </td>
                <td className="px-4 py-3.5 text-right font-medium text-zinc-200">
                  {row.sent.toLocaleString()}
                </td>
                <td className="px-4 py-3.5 text-right text-zinc-300">
                  <span className="font-semibold text-white">{formatPercent(row.delivered, row.sent)}</span>
                  <span className="ml-1 text-[11px] text-zinc-500">({row.delivered})</span>
                </td>
                <td className="px-4 py-3.5 text-right text-zinc-300">
                  <span className="font-semibold text-white">{formatPercent(row.opened, row.delivered)}</span>
                  <span className="ml-1 text-[11px] text-zinc-500">({row.opened})</span>
                </td>
                <td className="px-4 py-3.5 text-right text-zinc-300">
                  <span className="font-semibold text-white">{formatPercent(row.clicked, row.delivered)}</span>
                  <span className="ml-1 text-[11px] text-zinc-500">({row.clicked})</span>
                </td>
                <td className="px-4 py-3.5 text-right">
                  <span className={`font-semibold ${row.bounced > 0 ? 'text-amber-400' : 'text-zinc-300'}`}>
                    {formatPercent(row.bounced, row.sent)}
                  </span>
                  <span className="ml-1 text-[11px] text-zinc-500">({row.bounced})</span>
                </td>
                <td className="px-5 py-3.5 text-right">
                  <span className={`font-semibold ${row.complaints > 0 ? 'text-rose-400' : 'text-zinc-300'}`}>
                    {formatPercent(row.complaints, row.sent)}
                  </span>
                  <span className="ml-1 text-[11px] text-zinc-500">({row.complaints})</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};
