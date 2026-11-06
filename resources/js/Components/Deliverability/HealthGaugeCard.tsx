import React from 'react';
import { AlertCircle, CheckCircle2, AlertTriangle } from 'lucide-react';

export interface HealthGaugeCardProps {
    title: string;
    description: string;
    currentRate: number; // e.g. 0.0035 = 0.35%
    warningThreshold: number; // e.g. 0.02 = 2.0%
    blockThreshold: number; // e.g. 0.05 = 5.0%
    metricUnit?: string;
    helperText?: string;
}

export const HealthGaugeCard: React.FC<HealthGaugeCardProps> = ({
    title,
    description,
    currentRate,
    warningThreshold,
    blockThreshold,
    helperText,
}) => {
    const percent = currentRate * 100;
    const warningPercent = warningThreshold * 100;
    const blockPercent = blockThreshold * 100;

    // Status evaluation
    let status: 'healthy' | 'warning' | 'danger' = 'healthy';
    if (percent >= blockPercent) {
        status = 'danger';
    } else if (percent >= warningPercent) {
        status = 'warning';
    }

    const getStatusTheme = () => {
        switch (status) {
            case 'healthy':
                return {
                    badge: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                    bar: 'bg-emerald-500',
                    label: 'Safe',
                    icon: CheckCircle2,
                };
            case 'warning':
                return {
                    badge: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                    bar: 'bg-amber-500',
                    label: 'Auto-Pause Risk',
                    icon: AlertTriangle,
                };
            case 'danger':
                return {
                    badge: 'bg-red-500/10 text-red-400 border-red-500/20',
                    bar: 'bg-red-500',
                    label: 'Account Lock Risk',
                    icon: AlertCircle,
                };
        }
    };

    const theme = getStatusTheme();
    const Icon = theme.icon;
    const barWidth = Math.min(Math.max((percent / blockPercent) * 100, 2), 100);

    return (
        <article className="bg-zinc-900 border border-zinc-800 rounded-xl p-6 flex flex-col justify-between">
            <div>
                <header className="flex items-center justify-between mb-4">
                    <div>
                        <h3 className="text-sm font-semibold text-white">{title}</h3>
                        <p className="text-xs text-zinc-400">{description}</p>
                    </div>
                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border ${theme.badge}`}>
                        <Icon className="w-3.5 h-3.5" aria-hidden="true" />
                        {theme.label}
                    </span>
                </header>

                <div className="flex items-baseline gap-2 mb-3">
                    <span className="text-3xl font-bold tracking-tight text-white">
                        {percent.toFixed(2)}%
                    </span>
                    <span className="text-xs text-zinc-500">
                        / warning threshold {warningPercent.toFixed(2)}%
                    </span>
                </div>

                {/* Progress bar */}
                <div className="w-full bg-zinc-950 h-2.5 rounded-full overflow-hidden border border-zinc-800 mb-2">
                    <div
                        className={`h-full transition-all duration-500 rounded-full ${theme.bar}`}
                        style={{ width: `${barWidth}%` }}
                    />
                </div>

                <div className="flex justify-between text-[11px] text-zinc-500 font-mono">
                    <span>0.00%</span>
                    <span>Pause: {warningPercent}%</span>
                    <span>Lock: {blockPercent}%</span>
                </div>
            </div>

            {helperText && (
                <footer className="mt-4 pt-3 border-t border-zinc-800/80 text-xs text-zinc-400 leading-relaxed">
                    {helperText}
                </footer>
            )}
        </article>
    );
};
