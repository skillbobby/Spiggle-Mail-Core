import React, { useState } from 'react';
import { CheckCircle2, AlertCircle, RefreshCw, Copy, LucideIcon } from 'lucide-react';

export type DnsRecordStatus = 'verified' | 'pending' | 'missing' | 'failed';

export interface DnsPillarCardProps {
    title: string;
    recordType: string;
    description: string;
    value: string;
    status: DnsRecordStatus;
    icon: LucideIcon;
    fieldName: string;
}

export const DnsPillarCard: React.FC<DnsPillarCardProps> = ({
    title,
    recordType,
    description,
    value,
    status,
    icon: Icon,
    fieldName,
}) => {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const renderStatusBadge = () => {
        switch (status) {
            case 'verified':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <CheckCircle2 className="w-3.5 h-3.5" aria-hidden="true" />
                        Verified
                    </span>
                );
            case 'pending':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <RefreshCw className="w-3.5 h-3.5 animate-spin" aria-hidden="true" />
                        Pending DNS
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                        <AlertCircle className="w-3.5 h-3.5" aria-hidden="true" />
                        Missing Record
                    </span>
                );
        }
    };

    return (
        <article className="bg-zinc-900/60 border border-zinc-800 rounded-xl p-6 flex flex-col justify-between">
            <div>
                <header className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2.5">
                        <div className="p-2 rounded-lg bg-zinc-800 text-indigo-400">
                            <Icon className="w-4 h-4" aria-hidden="true" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-white">{title}</h3>
                            <p className="text-xs text-zinc-400">{recordType}</p>
                        </div>
                    </div>
                    {renderStatusBadge()}
                </header>

                <p className="text-xs text-zinc-300 mb-3 leading-relaxed">
                    {description}
                </p>

                <div className="bg-zinc-950 border border-zinc-800/80 rounded-lg p-3 font-mono text-xs text-zinc-300 flex items-center justify-between">
                    <span className="truncate mr-2 select-all">{value}</span>
                    <button
                        type="button"
                        onClick={handleCopy}
                        aria-label={`Copy ${fieldName} record value`}
                        className="p-1 hover:text-white text-zinc-400 transition-colors"
                    >
                        {copied ? (
                            <CheckCircle2 className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                        ) : (
                            <Copy className="w-4 h-4" aria-hidden="true" />
                        )}
                    </button>
                </div>
            </div>
        </article>
    );
};
