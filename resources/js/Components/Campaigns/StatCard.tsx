import React from 'react';

export type StatTone = 'neutral' | 'good' | 'warning' | 'danger';

export interface StatCardProps {
  readonly label: string;
  readonly value: string;
  readonly detail?: string;
  readonly tone?: StatTone;
  readonly subtitle?: string;
}

export const StatCard: React.FC<StatCardProps> = ({
  label,
  value,
  detail,
  tone = 'neutral',
  subtitle,
}) => {
  const toneClasses: Record<StatTone, string> = {
    neutral: 'text-white',
    good: 'text-emerald-400',
    warning: 'text-amber-400',
    danger: 'text-rose-400',
  };

  const badgeBg: Record<StatTone, string> = {
    neutral: 'bg-zinc-800 text-zinc-400 border border-zinc-700/60',
    good: 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/40',
    warning: 'bg-amber-950/60 text-amber-400 border border-amber-800/40',
    danger: 'bg-rose-950/60 text-rose-400 border border-rose-800/40',
  };

  return (
    <div className="flex flex-col justify-between rounded-xl border border-zinc-800 bg-zinc-900 p-4 transition-all hover:border-zinc-700 shadow-sm">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold uppercase tracking-wider text-zinc-400">
          {label}
        </span>
        {detail !== undefined && (
          <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium tabular-nums ${badgeBg[tone]}`}>
            {detail}
          </span>
        )}
      </div>

      <div className="mt-3">
        <div className={`text-2xl font-bold tracking-tight tabular-nums ${toneClasses[tone]}`}>
          {value}
        </div>
        {subtitle && (
          <p className="mt-1 text-xs text-zinc-400">
            {subtitle}
          </p>
        )}
      </div>
    </div>
  );
};
