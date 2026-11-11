import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useSettingsForm, SettingsFormData } from '@/hooks/useSettingsForm';
import {
    Settings,
    ShieldCheck,
    Cpu,
    Key,
    Mail,
    CheckCircle2,
    AlertCircle,
    Bot,
    Loader2,
    Send,
    Copy,
    Check,
    Activity,
} from 'lucide-react';

interface SettingsPageProps {
    currentNewsletter: {
        id: string;
        name: string;
        slug: string;
        fromEmail: string;
    } | null;
    canManageSesCredentials?: boolean;
    webhookEndpoint?: string;
    settings: SettingsFormData;
}

interface SesQuotaResult {
    ok: boolean;
    driver: string;
    region: string;
    production_access: boolean;
    sending_enabled: boolean;
    max_24_hour_send: number;
    max_send_rate: number;
    sent_last_24_hours: number;
    error?: string;
}

export default function SettingsIndex({
    currentNewsletter,
    canManageSesCredentials = true,
    webhookEndpoint = '',
    settings,
}: SettingsPageProps) {
    const { formData, updateField, handleSubmit, isSaving, savedSuccess } = useSettingsForm(settings);

    const [copiedWebhook, setCopiedWebhook] = useState(false);
    const [isTestingSes, setIsTestingSes] = useState(false);
    const [sesQuotaResult, setSesQuotaResult] = useState<SesQuotaResult | null>(null);

    const [isTestingTickitdex, setIsTestingTickitdex] = useState(false);
    const [testResult, setTestResult] = useState<{
        success: boolean;
        message?: string;
        ticketId?: string;
        error?: string;
    } | null>(null);

    const getCsrfHeaders = (): Record<string, string> => {
        const metaToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const xsrfCookie = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(metaToken ? { 'X-CSRF-TOKEN': metaToken } : {}),
            ...(xsrfCookie ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie) } : {}),
        };
    };

    const handleCopyWebhook = () => {
        if (!webhookEndpoint) return;
        navigator.clipboard.writeText(webhookEndpoint);
        setCopiedWebhook(true);
        setTimeout(() => setCopiedWebhook(false), 2000);
    };

    const handleVerifySes = async () => {
        setIsTestingSes(true);
        setSesQuotaResult(null);

        try {
            const res = await fetch('/settings/ses/test', {
                method: 'POST',
                headers: getCsrfHeaders(),
                body: JSON.stringify({
                    sesDriver: formData.sesDriver,
                    awsRegion: formData.awsRegion,
                    awsAccessKeyId: formData.awsAccessKeyId,
                    awsSecretAccessKey: formData.awsSecretAccessKey,
                }),
            });
            const data = (await res.json()) as SesQuotaResult;
            setSesQuotaResult(data);
        } catch (err: unknown) {
            setSesQuotaResult({
                ok: false,
                driver: formData.sesDriver,
                region: formData.awsRegion,
                production_access: false,
                sending_enabled: false,
                max_24_hour_send: 0,
                max_send_rate: 0,
                sent_last_24_hours: 0,
                error: err instanceof Error ? err.message : 'Network error verifying SES credentials',
            });
        } finally {
            setIsTestingSes(false);
        }
    };

    const handleTestTickitdex = async () => {
        if (!formData.tickitdexToken?.trim() && !settings.hasTickitdexToken) {
            setTestResult({
                success: false,
                error: 'Please enter a TickitDex API Bearer Token first.',
            });
            return;
        }

        setIsTestingTickitdex(true);
        setTestResult(null);

        try {
            const res = await fetch('/settings/tickitdex/test', {
                method: 'POST',
                headers: getCsrfHeaders(),
                body: JSON.stringify({
                    webhookUrl: formData.tickitdexWebhookUrl,
                    token: formData.tickitdexToken,
                    category: formData.tickitdexCategory,
                }),
            });

            const data = await res.json();
            if (res.ok && data.success) {
                setTestResult({
                    success: true,
                    ticketId: data.ticket_id,
                    message: 'Successfully verified connection! A test incident ticket was created in TickitDex.',
                });
            } else {
                setTestResult({
                    success: false,
                    error: data.error || `TickitDex returned status HTTP ${res.status}`,
                });
            }
        } catch (err: unknown) {
            setTestResult({
                success: false,
                error: err instanceof Error ? err.message : 'Network error testing TickitDex endpoint',
            });
        } finally {
            setIsTestingTickitdex(false);
        }
    };

    return (
        <DashboardLayout currentNewsletter={currentNewsletter}>
            <form onSubmit={handleSubmit} className="space-y-8 max-w-4xl">
                {/* Header */}
                <header className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-800 pb-6">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2.5">
                            <Settings className="w-7 h-7 text-indigo-400" aria-hidden="true" />
                            Publication & SES Engine Settings
                        </h1>
                        <p className="text-sm text-zinc-400 mt-1">
                            Configure mail delivery infrastructure, AWS SES reputation protection, and sender compliance.
                        </p>
                    </div>

                    <button
                        type="submit"
                        disabled={isSaving}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition-colors disabled:opacity-50 shadow-sm"
                    >
                        {savedSuccess ? (
                            <>
                                <CheckCircle2 className="w-4 h-4 text-emerald-300" aria-hidden="true" />
                                Settings Saved!
                            </>
                        ) : (
                            <>
                                <ShieldCheck className="w-4 h-4" aria-hidden="true" />
                                {isSaving ? 'Saving...' : 'Save Configuration'}
                            </>
                        )}
                    </button>
                </header>

                {/* Section 1: Mail Engine Switcher */}
                <section className="bg-zinc-900 border border-zinc-800 rounded-xl p-6 space-y-6">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-lg bg-zinc-800 text-indigo-400">
                                <Cpu className="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 className="text-base font-semibold text-white">Mail Dispatch Engine</h2>
                                <p className="text-xs text-zinc-400">
                                    {canManageSesCredentials
                                        ? 'Toggle between local zero-cost testing and production Amazon SES'
                                        : 'Administrator-managed AWS SES infrastructure with automated reputation guardrails'}
                                </p>
                            </div>
                        </div>

                        <span
                            className={`px-2.5 py-1 rounded-full text-xs font-medium border ${
                                formData.sesDriver === 'mock'
                                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                    : 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20'
                            }`}
                        >
                            {formData.sesDriver === 'mock' ? 'Mock Driver (Mailpit Active)' : 'Amazon SES Live'}
                        </span>
                    </header>

                    {canManageSesCredentials ? (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label
                                    className={`flex flex-col p-4 rounded-xl border cursor-pointer transition ${
                                        formData.sesDriver === 'mock'
                                            ? 'bg-indigo-950/20 border-indigo-500 ring-1 ring-indigo-500'
                                            : 'bg-zinc-950 border-zinc-800 hover:border-zinc-700'
                                    }`}
                                >
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-sm font-medium text-white">Local Mock / Mailpit</span>
                                        <input
                                            type="radio"
                                            name="sesDriver"
                                            value="mock"
                                            checked={formData.sesDriver === 'mock'}
                                            onChange={() => updateField('sesDriver', 'mock')}
                                            className="text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <p className="text-xs text-zinc-400 leading-relaxed">
                                        Sends to local Mailpit service (`:1025`) and logs dispatches. Never hits AWS quotas or incurs costs.
                                    </p>
                                </label>

                                <label
                                    className={`flex flex-col p-4 rounded-xl border cursor-pointer transition ${
                                        formData.sesDriver === 'live'
                                            ? 'bg-indigo-950/20 border-indigo-500 ring-1 ring-indigo-500'
                                            : 'bg-zinc-950 border-zinc-800 hover:border-zinc-700'
                                    }`}
                                >
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-sm font-medium text-white">Amazon SES Live API</span>
                                        <input
                                            type="radio"
                                            name="sesDriver"
                                            value="live"
                                            checked={formData.sesDriver === 'live'}
                                            onChange={() => updateField('sesDriver', 'live')}
                                            className="text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <p className="text-xs text-zinc-400 leading-relaxed">
                                        Uses AWS SDK v3 with encrypted IAM credentials, Configuration Set bounce/complaint feedback, and RFC 8058 alignment.
                                    </p>
                                </label>
                            </div>

                            <div className="space-y-4 pt-4 border-t border-zinc-800">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                        <Key className="w-4 h-4" aria-hidden="true" />
                                        AWS Credentials, Configuration Set &amp; SNS Feedback
                                    </div>

                                    <button
                                        type="button"
                                        onClick={handleVerifySes}
                                        disabled={isTestingSes}
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 text-xs font-medium text-zinc-200 transition disabled:opacity-50"
                                    >
                                        {isTestingSes ? (
                                            <>
                                                <Loader2 className="w-3.5 h-3.5 animate-spin text-indigo-400" />
                                                Checking SES...
                                            </>
                                        ) : (
                                            <>
                                                <Activity className="w-3.5 h-3.5 text-indigo-400" />
                                                Verify SES Account &amp; Quotas
                                            </>
                                        )}
                                    </button>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-xs font-medium text-zinc-300 mb-1.5">AWS Region</label>
                                        <select
                                            value={formData.awsRegion}
                                            onChange={(e) => updateField('awsRegion', e.target.value)}
                                            className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                        >
                                            <option value="us-east-1">US East (N. Virginia) us-east-1</option>
                                            <option value="us-east-2">US East (Ohio) us-east-2</option>
                                            <option value="us-west-2">US West (Oregon) us-west-2</option>
                                            <option value="eu-west-1">Europe (Ireland) eu-west-1</option>
                                            <option value="eu-central-1">Europe (Frankfurt) eu-central-1</option>
                                            <option value="ap-southeast-1">Asia Pacific (Singapore) ap-southeast-1</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-zinc-300 mb-1.5">AWS Access Key ID</label>
                                        <input
                                            type="text"
                                            value={formData.awsAccessKeyId}
                                            onChange={(e) => updateField('awsAccessKeyId', e.target.value)}
                                            placeholder="AKIAIOSFODNN7EXAMPLE"
                                            className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 font-mono focus:outline-none focus:border-indigo-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-zinc-300 mb-1.5">AWS Secret Access Key</label>
                                        <input
                                            type="password"
                                            value={formData.awsSecretAccessKey}
                                            onChange={(e) => updateField('awsSecretAccessKey', e.target.value)}
                                            placeholder="••••••••••••••••••••••••"
                                            className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 font-mono focus:outline-none focus:border-indigo-500"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                                            Configuration Set <span className="text-zinc-500 font-normal">(Optional)</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={formData.sesConfigurationSet || ''}
                                            onChange={(e) => updateField('sesConfigurationSet', e.target.value)}
                                            placeholder="e.g., spiggle-events"
                                            className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 font-mono focus:outline-none focus:border-indigo-500"
                                        />
                                        <p className="text-[11px] text-zinc-500 mt-1">
                                            SES event destination for bounce tracking.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                                            Send Rate Limit <span className="text-zinc-500 font-normal">(Emails / sec)</span>
                                        </label>
                                        <input
                                            type="number"
                                            min={1}
                                            max={200}
                                            value={formData.sendRateLimit}
                                            onChange={(e) => updateField('sendRateLimit', parseInt(e.target.value, 10) || 14)}
                                            className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                        />
                                        <p className="text-[11px] text-zinc-500 mt-1">
                                            Paces batch dispatches below AWS quota.
                                        </p>
                                    </div>
                                </div>

                                {webhookEndpoint && (
                                    <div className="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 space-y-1.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs font-semibold text-zinc-300">
                                                AWS SNS HTTPS Feedback Endpoint (Auto-Confirms Subscription &amp; Verifies X.509 Signature)
                                            </span>
                                            <button
                                                type="button"
                                                onClick={handleCopyWebhook}
                                                className="inline-flex items-center gap-1 text-xs text-indigo-400 hover:text-indigo-300"
                                            >
                                                {copiedWebhook ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                                                <span>{copiedWebhook ? 'Copied' : 'Copy URL'}</span>
                                            </button>
                                        </div>
                                        <div className="font-mono text-xs text-zinc-400 break-all select-all">{webhookEndpoint}</div>
                                    </div>
                                )}

                                {sesQuotaResult && (
                                    <div
                                        className={`p-4 rounded-xl border text-xs space-y-2 ${
                                            sesQuotaResult.ok
                                                ? 'bg-emerald-950/30 border-emerald-500/30 text-emerald-200'
                                                : 'bg-rose-950/30 border-rose-500/30 text-rose-300'
                                        }`}
                                    >
                                        {sesQuotaResult.ok ? (
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div className="flex items-center gap-2 font-semibold">
                                                    <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                                                    <span>
                                                        SES Connected ({sesQuotaResult.region}) —{' '}
                                                        {sesQuotaResult.production_access ? 'Production Access Enabled' : 'Sandbox Mode (200/day cap)'}
                                                    </span>
                                                </div>
                                                <div className="font-mono text-[11px] text-emerald-300">
                                                    Max Rate: {sesQuotaResult.max_send_rate}/sec &bull; 24h Quota:{' '}
                                                    {sesQuotaResult.sent_last_24_hours.toLocaleString()} / {sesQuotaResult.max_24_hour_send.toLocaleString()}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex items-start gap-2">
                                                <AlertCircle className="w-4 h-4 text-rose-400 shrink-0 mt-0.5" />
                                                <div>
                                                    <div className="font-semibold">SES Verification Failed</div>
                                                    <div>{sesQuotaResult.error}</div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <div className="p-4 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-between gap-4">
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-white">Administrator-Managed SES Infrastructure</p>
                                <p className="text-xs text-zinc-400">
                                    Outbound delivery, SNS bounce/complaint loops, and IP reputation are managed at the instance level. Authenticate your custom domain in{' '}
                                    <a href="/domains" className="text-indigo-400 underline hover:text-indigo-300">
                                        Domain Authentication
                                    </a>{' '}
                                    before sending.
                                </p>
                            </div>
                            <div className="shrink-0">
                                <label className="block text-[11px] font-medium text-zinc-400 mb-1">Workspace Pacing (/sec)</label>
                                <input
                                    type="number"
                                    min={1}
                                    max={100}
                                    value={formData.sendRateLimit}
                                    onChange={(e) => updateField('sendRateLimit', parseInt(e.target.value, 10) || 14)}
                                    className="w-28 bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>
                        </div>
                    )}
                </section>

                {/* Section 2: Sender Identity & Anti-Spam (CAN-SPAM) */}
                <section className="bg-zinc-900 border border-zinc-800 rounded-xl p-6 space-y-6">
                    <header className="flex items-center gap-3">
                        <div className="p-2.5 rounded-lg bg-zinc-800 text-indigo-400">
                            <Mail className="w-5 h-5" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-white">Default Sender Identity &amp; Compliance</h2>
                            <p className="text-xs text-zinc-400">CAN-SPAM physical address, Reply-To routing, and Double Opt-In hygiene</p>
                        </div>
                    </header>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-zinc-300 mb-1.5">Sender From Name</label>
                            <input
                                type="text"
                                value={formData.defaultFromName}
                                onChange={(e) => updateField('defaultFromName', e.target.value)}
                                className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-zinc-300 mb-1.5">Sender From Email</label>
                            <input
                                type="email"
                                value={formData.defaultFromEmail}
                                onChange={(e) => updateField('defaultFromEmail', e.target.value)}
                                className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-zinc-300 mb-1.5">Reply-To Email</label>
                            <input
                                type="email"
                                value={formData.replyToEmail || ''}
                                onChange={(e) => updateField('replyToEmail', e.target.value)}
                                placeholder={formData.defaultFromEmail}
                                className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-zinc-300 mb-1.5">Physical Postal Address (Required by CAN-SPAM)</label>
                        <textarea
                            rows={2}
                            value={formData.postalAddress}
                            onChange={(e) => updateField('postalAddress', e.target.value)}
                            className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div className="flex items-center justify-between p-4 bg-zinc-950 border border-zinc-800 rounded-lg">
                        <div className="space-y-0.5">
                            <label htmlFor="double-opt-in-toggle" className="text-sm font-medium text-white cursor-pointer">
                                Enforce Double Opt-In Confirmation (Recommended for SES Reputation)
                            </label>
                            <p className="text-xs text-zinc-400">
                                Public widget signups receive a confirmation email and must click to verify before receiving broadcasts.
                            </p>
                        </div>
                        <input
                            id="double-opt-in-toggle"
                            type="checkbox"
                            checked={formData.doubleOptIn}
                            onChange={(e) => updateField('doubleOptIn', e.target.checked)}
                            className="w-4 h-4 text-indigo-600 rounded bg-zinc-900 border-zinc-700 focus:ring-indigo-500"
                        />
                    </div>
                </section>

                {/* Section 3: TickitDex Support-Chat Incident Integration */}
                <section className="bg-zinc-900 border border-zinc-800 rounded-xl p-6 space-y-6">
                    <header className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-lg bg-zinc-800 text-indigo-400">
                                <Bot className="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 className="text-base font-semibold text-white">TickitDex Support-Chat Integration</h2>
                                <p className="text-xs text-zinc-400">
                                    Automate emergency ticket creation in TickitDex when deliverability or queue incidents occur
                                </p>
                            </div>
                        </div>

                        <span
                            className={`px-2.5 py-1 rounded-full text-xs font-medium border w-fit ${
                                formData.tickitdexEnabled
                                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                    : 'bg-zinc-800 text-zinc-400 border-zinc-700'
                            }`}
                        >
                            {formData.tickitdexEnabled ? 'Integration Enabled' : 'Disabled'}
                        </span>
                    </header>

                    {/* Enable Toggle */}
                    <div className="flex items-center justify-between p-4 bg-zinc-950 border border-zinc-800 rounded-lg">
                        <div className="space-y-0.5">
                            <label htmlFor="tickitdex-toggle" className="text-sm font-medium text-white cursor-pointer">
                                Enable Real-Time Incident Escalation
                            </label>
                            <p className="text-xs text-zinc-400">
                                Automatically opens high/critical support tickets in TickitDex when SES bounce spikes or delivery failures happen.
                            </p>
                        </div>
                        <input
                            id="tickitdex-toggle"
                            type="checkbox"
                            checked={formData.tickitdexEnabled}
                            onChange={(e) => updateField('tickitdexEnabled', e.target.checked)}
                            className="w-4 h-4 text-indigo-600 rounded bg-zinc-900 border-zinc-700 focus:ring-indigo-500"
                        />
                    </div>

                    {/* Parameters Grid */}
                    <div className="space-y-4 transition">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                                    TickitDex Webhook URL (HTTPS)
                                </label>
                                <input
                                    type="url"
                                    value={formData.tickitdexWebhookUrl}
                                    onChange={(e) => updateField('tickitdexWebhookUrl', e.target.value)}
                                    placeholder="https://tickitdex.com/support-chat/webhooks/tickets"
                                    className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 font-mono focus:outline-none focus:border-indigo-500"
                                />
                                <p className="text-xs text-zinc-500 mt-1">Default: TickitDex Support-Chat `/support-chat/webhooks/tickets` endpoint.</p>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                                    TickitDex API Bearer Token
                                </label>
                                <input
                                    type="password"
                                    value={formData.tickitdexToken}
                                    onChange={(e) => updateField('tickitdexToken', e.target.value)}
                                    placeholder="Paste token from TickitDex..."
                                    className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 font-mono focus:outline-none focus:border-indigo-500"
                                />
                                <p className="text-xs text-zinc-500 mt-1">Encrypted at rest and masked after saving.</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                                    Default Ticket Category
                                </label>
                                <select
                                    value={formData.tickitdexCategory}
                                    onChange={(e) => updateField('tickitdexCategory', e.target.value)}
                                    className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="deliverability">Deliverability &amp; Bounces</option>
                                    <option value="incident">Infrastructure &amp; Incident</option>
                                    <option value="support">General Support</option>
                                    <option value="operations">Operations</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                                    Minimum Alert Priority
                                </label>
                                <select
                                    value={formData.tickitdexMinPriority}
                                    onChange={(e) => updateField('tickitdexMinPriority', e.target.value)}
                                    className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="critical">Critical Only (&gt; 5% bounce rate, suspension)</option>
                                    <option value="high">High &amp; Critical (&gt; 2% bounce rate)</option>
                                    <option value="warning">Warning and above (Recommended)</option>
                                    <option value="info">All notifications (including Info)</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">
                                    Customer / Escalation Email
                                </label>
                                <input
                                    type="email"
                                    value={formData.tickitdexCustomerEmail}
                                    onChange={(e) => updateField('tickitdexCustomerEmail', e.target.value)}
                                    placeholder="ops@yourdomain.com"
                                    className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 focus:outline-none focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        {/* Test Connection Box */}
                        <div className="pt-3 border-t border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h4 className="text-xs font-medium text-zinc-200">Test Webhook Connectivity</h4>
                                <p className="text-xs text-zinc-500">Dispatches an instant verification ping to TickitDex using the token above.</p>
                            </div>

                            <button
                                type="button"
                                onClick={handleTestTickitdex}
                                disabled={isTestingTickitdex || (!formData.tickitdexToken && !settings.hasTickitdexToken)}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 hover:text-white rounded-lg text-xs font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0 border border-zinc-700"
                            >
                                {isTestingTickitdex ? (
                                    <>
                                        <Loader2 className="w-3.5 h-3.5 animate-spin text-indigo-400" aria-hidden="true" />
                                        Testing Connection...
                                    </>
                                ) : (
                                    <>
                                        <Send className="w-3.5 h-3.5" aria-hidden="true" />
                                        Test Webhook Connection
                                    </>
                                )}
                            </button>
                        </div>

                        {/* Test Result Banner */}
                        {testResult && (
                            <div
                                className={`p-3.5 rounded-lg border text-xs flex items-start gap-2.5 ${
                                    testResult.success
                                        ? 'bg-emerald-950/30 border-emerald-500/30 text-emerald-300'
                                        : 'bg-rose-950/30 border-rose-500/30 text-rose-300'
                                }`}
                            >
                                {testResult.success ? (
                                    <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" aria-hidden="true" />
                                ) : (
                                    <AlertCircle className="w-4 h-4 text-rose-400 shrink-0 mt-0.5" aria-hidden="true" />
                                )}
                                <div className="space-y-1">
                                    <div className="font-medium">
                                        {testResult.success ? 'Connection Verified' : 'Connection Test Failed'}
                                    </div>
                                    <div>{testResult.message || testResult.error}</div>
                                    {testResult.ticketId && (
                                        <div className="font-mono text-[11px] text-emerald-400/80">
                                            Ticket ID: <span className="underline">{testResult.ticketId}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            </form>
        </DashboardLayout>
    );
}
