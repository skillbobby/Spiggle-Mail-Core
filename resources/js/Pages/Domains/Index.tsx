import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  ShieldCheck,
  RefreshCw,
  CheckCircle2,
  Globe,
  Lock,
  Send,
  AlertCircle,
  Plus,
  Trash2,
  X,
  Clock,
  Copy,
} from 'lucide-react';
import { router } from '@inertiajs/react';
import { DnsPillarCard } from '@/Components/Domains/DnsPillarCard';

interface DkimRecordItem {
  host: string;
  value: string;
}

interface DnsDetail {
  status: 'verified' | 'pending' | 'missing' | 'failed';
  record?: string;
  expected?: string;
  selector?: string;
  cname?: string;
  records?: DkimRecordItem[];
  policy?: string;
  mx?: string;
  spf?: string;
}

interface DomainRecord {
  id: string;
  domain: string;
  status: 'verified' | 'pending' | 'failed';
  mailFromDomain: string;
  verifiedAt: string | null;
  dnsRecords: {
    spf: DnsDetail;
    dkim: DnsDetail;
    dmarc: DnsDetail;
    mailFrom: DnsDetail;
  };
}

interface DomainListItem {
  id: string;
  domain: string;
  status: 'verified' | 'pending' | 'failed';
  verifiedAt: string | null;
}

interface DomainsProps {
  currentNewsletter: {
    id: string;
    name: string;
    slug: string;
    fromEmail: string;
  };
  domain: DomainRecord;
  domainsList?: readonly DomainListItem[];
}

export default function DomainsIndex({
  currentNewsletter,
  domain,
  domainsList = [],
}: DomainsProps) {
  const [isVerifying, setIsVerifying] = useState(false);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [newDomain, setNewDomain] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  const copyValue = (key: string, text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  const handleVerifyNow = () => {
    setIsVerifying(true);
    router.post(
      `/domains/${domain.id}/verify`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setIsVerifying(false),
      }
    );
  };

  const handleAddDomain = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newDomain.trim()) return;
    setIsSubmitting(true);
    setFormError(null);

    router.post(
      '/domains',
      { domain: newDomain.trim() },
      {
        onSuccess: () => {
          setNewDomain('');
          setIsAddModalOpen(false);
        },
        onError: (errors) => {
          setFormError(errors.domain || 'Failed to add domain.');
        },
        onFinish: () => setIsSubmitting(false),
      }
    );
  };

  const handleDeleteDomain = (domainId: string, domainName: string) => {
    if (!window.confirm(`Are you sure you want to delete sending domain "${domainName}"?`)) {
      return;
    }
    router.delete(`/domains/${domainId}`);
  };

  const dkimRecords: DkimRecordItem[] =
    domain.dnsRecords.dkim.records && domain.dnsRecords.dkim.records.length > 0
      ? domain.dnsRecords.dkim.records
      : domain.dnsRecords.dkim.selector && domain.dnsRecords.dkim.cname
        ? [{ host: domain.dnsRecords.dkim.selector, value: domain.dnsRecords.dkim.cname }]
        : [];

  return (
    <DashboardLayout currentNewsletter={currentNewsletter}>
      <div className="space-y-8">
        {/* Header */}
        <header className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
              <ShieldCheck className="w-7 h-7 text-indigo-500" aria-hidden="true" />
              Domain Authentication &amp; Deliverability
            </h1>
            <p className="text-sm text-slate-500 dark:text-zinc-400 mt-1">
              Configure SPF, all 3 Easy DKIM CNAMEs, DMARC, and custom MAIL FROM to guarantee inbox delivery and protect against spoofing.
            </p>
          </div>

          <div className="flex items-center gap-3 shrink-0 flex-nowrap">
            <button
              type="button"
              onClick={() => {
                setFormError(null);
                setIsAddModalOpen(true);
              }}
              className="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 dark:bg-zinc-800 hover:bg-slate-800 dark:hover:bg-zinc-700 text-white rounded-xl text-sm font-semibold transition-all shadow-sm hover:shadow active:scale-[0.98] whitespace-nowrap shrink-0"
            >
              <Plus className="w-4 h-4 shrink-0" />
              <span>Add Domain</span>
            </button>
            <button
              type="button"
              onClick={handleVerifyNow}
              disabled={isVerifying}
              className="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold transition-all shadow-sm hover:shadow-indigo-500/25 active:scale-[0.98] whitespace-nowrap shrink-0 disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 shrink-0 ${isVerifying ? 'animate-spin' : ''}`} aria-hidden="true" />
              <span>{isVerifying ? 'Verifying DNS...' : 'Recheck DNS Records'}</span>
            </button>
          </div>
        </header>

        {/* Domain Selector Pill Tabs (if multiple domains exist) */}
        {domainsList.length > 1 && (
          <div className="flex flex-wrap items-center gap-2 p-2 bg-zinc-900/60 border border-zinc-800 rounded-xl">
            <span className="text-xs font-semibold text-zinc-500 px-2 uppercase tracking-wider">Domains:</span>
            {domainsList.map((d) => {
              const isActive = d.id === domain.id;
              return (
                <button
                  key={d.id}
                  type="button"
                  onClick={() => router.get(`/domains?selected=${d.id}`, {}, { preserveState: true })}
                  className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                    isActive
                      ? 'bg-zinc-800 text-white border border-zinc-700'
                      : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/50 border border-transparent'
                  }`}
                >
                  <Globe className="w-3.5 h-3.5" />
                  <span>{d.domain}</span>
                  {d.status === 'verified' ? (
                    <span className="w-2 h-2 rounded-full bg-emerald-400" />
                  ) : (
                    <span className="w-2 h-2 rounded-full bg-amber-400" />
                  )}
                </button>
              );
            })}
          </div>
        )}

        {/* Active Domain Status Banner */}
        <section className="bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-zinc-800 flex items-center justify-center border border-indigo-100 dark:border-zinc-700 text-indigo-600 dark:text-zinc-200 shadow-xs shrink-0">
              <Globe className="w-6 h-6" aria-hidden="true" />
            </div>
            <div>
              <div className="flex flex-wrap items-center gap-3">
                <h2 className="text-base font-bold text-slate-900 dark:text-white">{domain.domain}</h2>
                {domain.status === 'verified' ? (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 shadow-xs">
                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                    Verified
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 shadow-xs">
                    <Clock className="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                    Pending DNS Propagation
                  </span>
                )}
              </div>
              <p className="text-xs text-slate-500 dark:text-zinc-400 mt-1 font-medium">
                Custom MAIL FROM: <span className="font-mono text-slate-800 dark:text-zinc-200 font-semibold">{domain.mailFromDomain}</span>
              </p>
            </div>
          </div>

          <div className="flex items-center gap-4">
            <div className="text-right hidden sm:block">
              <p className="text-xs text-zinc-400">SES Sending Identity</p>
              <p className="text-xs font-mono text-emerald-400 font-medium">Auto-Aligned (RFC 8058)</p>
            </div>

            {domainsList.length > 1 && (
              <button
                type="button"
                onClick={() => handleDeleteDomain(domain.id, domain.domain)}
                className="p-2 rounded-lg border border-zinc-800 text-zinc-500 hover:text-rose-400 hover:bg-zinc-800 transition"
                title="Remove Domain"
                aria-label={`Remove domain ${domain.domain}`}
              >
                <Trash2 className="w-4 h-4" />
              </button>
            )}
          </div>
        </section>

        {/* 4 Pillars Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <DnsPillarCard
            title="1. SPF (Sender Policy)"
            recordType={`Type TXT at ${domain.domain}`}
            description="Authorizes Amazon SES to send emails on behalf of your root domain."
            value={domain.dnsRecords.spf.expected ?? 'v=spf1 include:amazonses.com ~all'}
            status={domain.dnsRecords.spf.status}
            icon={Lock}
            fieldName="SPF"
          />

          {/* 2. Easy DKIM (All 3 CNAME Records) */}
          <article className="bg-zinc-900/60 border border-zinc-800 rounded-xl p-6 flex flex-col justify-between">
            <div>
              <header className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-lg bg-zinc-800 text-indigo-400">
                    <ShieldCheck className="w-4 h-4" aria-hidden="true" />
                  </div>
                  <div>
                    <h3 className="text-sm font-semibold text-white">2. Easy DKIM (3 CNAME Signatures)</h3>
                    <p className="text-xs text-zinc-400">Add all 3 CNAME records issued by Amazon SES</p>
                  </div>
                </div>
                {domain.dnsRecords.dkim.status === 'verified' ? (
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <CheckCircle2 className="w-3.5 h-3.5" aria-hidden="true" />
                    Verified
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <RefreshCw className="w-3.5 h-3.5 animate-spin" aria-hidden="true" />
                    Pending DNS
                  </span>
                )}
              </header>

              <p className="text-xs text-zinc-300 mb-3 leading-relaxed">
                Cryptographically signs outgoing emails. AWS SES requires all three `.dkim.amazonses.com` CNAMEs to resolve before activating Easy DKIM.
              </p>

              <div className="space-y-2">
                {dkimRecords.map((rec, idx) => (
                  <div
                    key={rec.host}
                    className="bg-zinc-950 border border-zinc-800/80 rounded-lg p-2.5 font-mono text-[11px] text-zinc-300 space-y-1"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-zinc-500">Host #{idx + 1}:</span>
                      <div className="flex items-center gap-1.5 min-w-0">
                        <span className="truncate select-all text-zinc-200">{rec.host}</span>
                        <button
                          type="button"
                          onClick={() => copyValue(`dkim-host-${idx}`, rec.host)}
                          aria-label={`Copy DKIM host ${idx + 1}`}
                          className="p-0.5 text-zinc-400 hover:text-white shrink-0"
                        >
                          {copiedKey === `dkim-host-${idx}` ? (
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />
                          ) : (
                            <Copy className="w-3.5 h-3.5" />
                          )}
                        </button>
                      </div>
                    </div>
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-zinc-500">Value:</span>
                      <div className="flex items-center gap-1.5 min-w-0">
                        <span className="truncate select-all text-indigo-300">{rec.value}</span>
                        <button
                          type="button"
                          onClick={() => copyValue(`dkim-val-${idx}`, rec.value)}
                          aria-label={`Copy DKIM value ${idx + 1}`}
                          className="p-0.5 text-zinc-400 hover:text-white shrink-0"
                        >
                          {copiedKey === `dkim-val-${idx}` ? (
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />
                          ) : (
                            <Copy className="w-3.5 h-3.5" />
                          )}
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </article>

          <DnsPillarCard
            title="3. DMARC Policy"
            recordType={`Type TXT at _dmarc.${domain.domain}`}
            description="Mandates Gmail/Yahoo inbox placement rules and receives automated spoofing reports."
            value={domain.dnsRecords.dmarc.record ?? ''}
            status={domain.dnsRecords.dmarc.status}
            icon={AlertCircle}
            fieldName="DMARC"
          />

          {/* 4. Custom MAIL FROM (MX + TXT SPF) */}
          <article className="bg-zinc-900/60 border border-zinc-800 rounded-xl p-6 flex flex-col justify-between">
            <div>
              <header className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-lg bg-zinc-800 text-indigo-400">
                    <Send className="w-4 h-4" aria-hidden="true" />
                  </div>
                  <div>
                    <h3 className="text-sm font-semibold text-white">4. Custom MAIL FROM Domain</h3>
                    <p className="text-xs text-zinc-400">MX + TXT on {domain.mailFromDomain}</p>
                  </div>
                </div>
                {domain.dnsRecords.mailFrom.status === 'verified' ? (
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <CheckCircle2 className="w-3.5 h-3.5" aria-hidden="true" />
                    Verified
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <RefreshCw className="w-3.5 h-3.5 animate-spin" aria-hidden="true" />
                    Pending DNS
                  </span>
                )}
              </header>

              <p className="text-xs text-zinc-300 mb-3 leading-relaxed">
                Aligns Return-Path with your From domain (eliminates &quot;via amazonses.com&quot; and satisfies strict DMARC SPF alignment).
              </p>

              <div className="space-y-2">
                <div className="bg-zinc-950 border border-zinc-800/80 rounded-lg p-2.5 font-mono text-xs text-zinc-300 flex items-center justify-between">
                  <div className="truncate mr-2">
                    <span className="text-zinc-500 mr-2">MX:</span>
                    <span className="select-all">{domain.dnsRecords.mailFrom.mx ?? ''}</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => copyValue('mailfrom-mx', domain.dnsRecords.mailFrom.mx ?? '')}
                    aria-label="Copy MAIL FROM MX record"
                    className="p-1 text-zinc-400 hover:text-white shrink-0"
                  >
                    {copiedKey === 'mailfrom-mx' ? (
                      <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                    ) : (
                      <Copy className="w-4 h-4" />
                    )}
                  </button>
                </div>

                <div className="bg-zinc-950 border border-zinc-800/80 rounded-lg p-2.5 font-mono text-xs text-zinc-300 flex items-center justify-between">
                  <div className="truncate mr-2">
                    <span className="text-zinc-500 mr-2">TXT:</span>
                    <span className="select-all">
                      {domain.dnsRecords.mailFrom.spf ?? 'v=spf1 include:amazonses.com ~all'}
                    </span>
                  </div>
                  <button
                    type="button"
                    onClick={() =>
                      copyValue('mailfrom-spf', domain.dnsRecords.mailFrom.spf ?? 'v=spf1 include:amazonses.com ~all')
                    }
                    aria-label="Copy MAIL FROM SPF TXT record"
                    className="p-1 text-zinc-400 hover:text-white shrink-0"
                  >
                    {copiedKey === 'mailfrom-spf' ? (
                      <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                    ) : (
                      <Copy className="w-4 h-4" />
                    )}
                  </button>
                </div>
              </div>
            </div>
          </article>
        </div>

        {/* Deliverability Checklist Note */}
        <aside className="bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/50 rounded-2xl p-5 flex items-start gap-3.5 shadow-xs">
          <ShieldCheck className="w-5 h-5 text-indigo-600 dark:text-indigo-400 mt-0.5 shrink-0" aria-hidden="true" />
          <div>
            <h4 className="text-sm font-bold text-indigo-950 dark:text-indigo-200">Google &amp; Yahoo Bulk Sender Compliance</h4>
            <p className="text-xs text-indigo-900/80 dark:text-indigo-300 mt-1 leading-relaxed font-normal">
              SpiggleMail automatically applies RFC 8058 one-click list-unsubscribe headers, multipart text/plain alternatives, and strict Return-Path DKIM alignment to protect your AWS SES reputation.
            </p>
          </div>
        </aside>
      </div>

      {/* Add Domain Modal */}
      {isAddModalOpen && (
        <div
          role="dialog"
          aria-modal="true"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs"
        >
          <div className="w-full max-w-md rounded-2xl bg-zinc-950 border border-zinc-800 shadow-2xl p-6 text-left">
            <div className="flex items-center justify-between pb-4 border-b border-zinc-800">
              <h3 className="text-base font-bold text-white">Add Sending Domain</h3>
              <button
                type="button"
                onClick={() => setIsAddModalOpen(false)}
                className="text-zinc-400 hover:text-white"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleAddDomain} className="mt-4 space-y-4">
              <div>
                <label htmlFor="domain-input" className="block text-xs font-semibold text-zinc-300 mb-1">
                  Domain Name
                </label>
                <input
                  id="domain-input"
                  type="text"
                  required
                  value={newDomain}
                  onChange={(e) => setNewDomain(e.target.value)}
                  placeholder="news.yourdomain.com or yourdomain.com"
                  className="w-full px-3.5 py-2.5 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition text-sm font-mono"
                />
                <p className="text-[11px] text-zinc-500 mt-1">
                  Enter your root domain or dedicated sending subdomain.
                </p>
              </div>

              {formError && (
                <p className="text-xs text-rose-400 flex items-center gap-1.5">
                  <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                  {formError}
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
                  disabled={isSubmitting || !newDomain.trim()}
                  className="px-4 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50 transition"
                >
                  {isSubmitting ? 'Registering...' : 'Add Domain'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
}
