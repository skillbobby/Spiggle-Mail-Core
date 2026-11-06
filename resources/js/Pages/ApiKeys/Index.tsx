import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { KeyRound, Plus, Copy, CheckCircle2, Trash2, Shield, AlertCircle } from 'lucide-react';
import { router } from '@inertiajs/react';

interface ApiKeyItem {
    id: string;
    name: string;
    keyPrefix: string;
    role: string;
    lastUsedAt: string | null;
    createdAt: string;
}

interface ApiKeysPageProps {
    currentNewsletter: {
        id: string;
        name: string;
        slug: string;
        fromEmail: string;
    };
    apiKeys: ApiKeyItem[];
    newlyCreatedKey?: string | null;
}

export default function ApiKeysIndex({ currentNewsletter, apiKeys, newlyCreatedKey }: ApiKeysPageProps) {
    const [isCreating, setIsCreating] = useState(false);
    const [name, setName] = useState('');
    const [role, setRole] = useState<'full_access' | 'send_only' | 'read_only'>('full_access');
    const [copiedKey, setCopiedKey] = useState(false);

    const handleCreateKey = (e: React.FormEvent) => {
        e.preventDefault();
        if (!name.trim()) return;

        router.post('/api-keys', { name, role }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreating(false);
                setName('');
            },
        });
    };

    const handleDeleteKey = (id: string) => {
        if (confirm('Are you sure you want to revoke this API key? Any integration using it will cease to function.')) {
            router.delete(`/api-keys/${id}`, { preserveScroll: true });
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopiedKey(true);
        setTimeout(() => setCopiedKey(false), 2000);
    };

    return (
        <DashboardLayout currentNewsletter={currentNewsletter}>
            <div className="space-y-8 max-w-5xl">
                {/* Header */}
                <header className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-800 pb-6">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2.5">
                            <KeyRound className="w-7 h-7 text-indigo-400" aria-hidden="true" />
                            Programmatic API Keys
                        </h1>
                        <p className="text-sm text-zinc-400 mt-1">
                            Authenticate scripts, webhooks, and backend integrations via Bearer tokens.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setIsCreating(true)}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition shadow-sm"
                    >
                        <Plus className="w-4 h-4" aria-hidden="true" />
                        Create New API Key
                    </button>
                </header>

                {/* Newly generated secret banner */}
                {newlyCreatedKey && (
                    <aside aria-label="Newly generated key" className="p-5 rounded-xl bg-emerald-950/30 border border-emerald-500/40 text-emerald-200 space-y-3">
                        <div className="flex items-center gap-2 font-semibold text-emerald-300 text-sm">
                            <CheckCircle2 className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                            API Key Created Successfully!
                        </div>
                        <p className="text-xs text-emerald-200/90 leading-relaxed">
                            Please copy your secret key right now. <strong>You will not be able to view it again.</strong>
                        </p>
                        <div className="p-3 bg-zinc-950 border border-zinc-800 rounded-lg flex items-center justify-between font-mono text-sm text-white">
                            <span className="select-all truncate mr-2">{newlyCreatedKey}</span>
                            <button
                                type="button"
                                onClick={() => copyToClipboard(newlyCreatedKey)}
                                className="inline-flex items-center gap-1.5 px-3 py-1 bg-zinc-800 hover:bg-zinc-700 rounded text-xs text-zinc-200 transition"
                            >
                                {copiedKey ? (
                                    <>
                                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" aria-hidden="true" />
                                        Copied!
                                    </>
                                ) : (
                                    <>
                                        <Copy className="w-3.5 h-3.5" aria-hidden="true" />
                                        Copy Secret Key
                                    </>
                                )}
                            </button>
                        </div>
                    </aside>
                )}

                {/* Create modal/drawer */}
                {isCreating && (
                    <div className="p-6 rounded-xl bg-zinc-900 border border-zinc-800 space-y-4">
                        <h2 className="text-base font-semibold text-white">Generate Access Token</h2>
                        <form onSubmit={handleCreateKey} className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-zinc-300 mb-1.5">Key Description / Name</label>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        placeholder="e.g. Production Blog Sync, Zapier"
                                        required
                                        autoFocus
                                        className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 focus:outline-none focus:border-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-zinc-300 mb-1.5">Permission Role</label>
                                    <select
                                        value={role}
                                        onChange={(e) => setRole(e.target.value as 'full_access' | 'send_only' | 'read_only')}
                                        className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="full_access">Full Access (Manage & Send)</option>
                                        <option value="send_only">Send Only (Dispatches)</option>
                                        <option value="read_only">Read Only (Analytics & Subscribers)</option>
                                    </select>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 justify-end pt-2">
                                <button
                                    type="button"
                                    onClick={() => setIsCreating(false)}
                                    className="px-4 py-2 text-xs font-medium text-zinc-400 hover:text-white transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition"
                                >
                                    Generate Key
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Keys list */}
                <section className="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-xs">
                    <header className="p-4 bg-slate-50 dark:bg-zinc-950/80 border-b border-slate-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <span className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300">
                            Active Keys ({apiKeys.length})
                        </span>
                        <span className="text-xs font-mono text-slate-500 dark:text-zinc-400">
                            Bearer Token Header: <code className="text-indigo-600 dark:text-indigo-400 font-semibold bg-indigo-50 dark:bg-indigo-950/60 px-1.5 py-0.5 rounded border border-indigo-200 dark:border-indigo-800/60">Authorization: Bearer spm_...</code>
                        </span>
                    </header>

                    {apiKeys.length === 0 ? (
                        <div className="text-center py-12 text-slate-500 dark:text-zinc-400 text-sm font-medium">
                            No API keys generated yet. Click &quot;Create New API Key&quot; to begin.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs text-zinc-300">
                                <thead className="bg-zinc-950 text-zinc-400 uppercase font-mono border-b border-zinc-800">
                                    <tr>
                                        <th scope="col" className="px-6 py-3 font-semibold">Name</th>
                                        <th scope="col" className="px-6 py-3 font-semibold">Key Token</th>
                                        <th scope="col" className="px-6 py-3 font-semibold">Role</th>
                                        <th scope="col" className="px-6 py-3 font-semibold">Last Used</th>
                                        <th scope="col" className="px-6 py-3 font-semibold">Created</th>
                                        <th scope="col" className="px-6 py-3 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-800/60">
                                    {apiKeys.map((k) => (
                                        <tr key={k.id} className="hover:bg-zinc-800/30 transition">
                                            <td className="px-6 py-4 font-semibold text-white">{k.name}</td>
                                            <td className="px-6 py-4 font-mono text-zinc-400">{k.keyPrefix}••••••••••••</td>
                                            <td className="px-6 py-4">
                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                                    {k.role.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-zinc-400">{k.lastUsedAt || 'Never'}</td>
                                            <td className="px-6 py-4 text-zinc-500">{k.createdAt}</td>
                                            <td className="px-6 py-4 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => handleDeleteKey(k.id)}
                                                    className="p-1 text-zinc-500 hover:text-red-400 transition"
                                                    aria-label={`Revoke ${k.name} key`}
                                                >
                                                    <Trash2 className="w-4 h-4" aria-hidden="true" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </DashboardLayout>
    );
}
