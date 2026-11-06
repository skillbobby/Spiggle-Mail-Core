import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Mail, CheckCircle2, ShieldCheck } from 'lucide-react';
import { router } from '@inertiajs/react';

interface UnsubscribeProps {
    newsletter: {
        id: string;
        name: string;
        slug: string;
        fromEmail: string;
    };
    contact: {
        id: string;
        email: string;
        status: string;
    };
    token: string;
    alreadyUnsubscribed: boolean;
}

export default function Unsubscribe({ newsletter, contact, token, alreadyUnsubscribed }: UnsubscribeProps) {
    const [unsubscribed, setUnsubscribed] = useState(alreadyUnsubscribed);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleConfirmUnsubscribe = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        router.post('/unsubscribe', { token }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmitting(false);
                setUnsubscribed(true);
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    return (
        <div className="min-h-screen bg-zinc-950 text-zinc-100 flex flex-col justify-between selection:bg-indigo-500/30">
            <Head title={`Unsubscribe from ${newsletter.name} - SpiggleMail`} />

            {/* Header */}
            <header className="px-6 py-5 max-w-4xl mx-auto w-full flex items-center justify-between">
                <div className="flex items-center gap-2.5 text-white font-bold text-lg tracking-tight">
                    <div className="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white">
                        <Mail className="w-4 h-4" aria-hidden="true" />
                    </div>
                    <span>Spiggle<span className="text-indigo-400">Mail</span></span>
                </div>

                <span className="text-xs text-zinc-500 font-mono">RFC 8058 One-Click Compliant</span>
            </header>

            {/* Main Content */}
            <main className="flex-1 flex items-center justify-center p-6">
                <div className="w-full max-w-md p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
                    {unsubscribed ? (
                        <div className="text-center space-y-4">
                            <div className="w-14 h-14 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
                                <CheckCircle2 className="w-8 h-8" aria-hidden="true" />
                            </div>
                            <div className="space-y-1.5">
                                <h1 className="text-xl font-bold tracking-tight text-white">
                                    You have been unsubscribed
                                </h1>
                                <p className="text-xs text-zinc-400 leading-relaxed">
                                    <strong>{contact.email}</strong> will no longer receive issues from <strong>{newsletter.name}</strong>.
                                </p>
                            </div>

                            <p className="text-[11px] text-zinc-500 pt-2">
                                Unsubscribed by accident? You can resubscribe on the public archive page at any time.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            <div className="space-y-2">
                                <h1 className="text-2xl font-bold tracking-tight text-white">
                                    Unsubscribe from {newsletter.name}
                                </h1>
                                <p className="text-xs text-zinc-400 leading-relaxed">
                                    Are you sure you want to stop receiving newsletters sent to <strong className="text-zinc-200">{contact.email}</strong>?
                                </p>
                            </div>

                            <form onSubmit={handleConfirmUnsubscribe} className="space-y-4">
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="w-full py-3 px-4 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-xl text-sm transition disabled:opacity-50 shadow-sm shadow-red-600/25"
                                >
                                    {isSubmitting ? 'Unsubscribing...' : 'Yes, Unsubscribe Me'}
                                </button>
                            </form>

                            <footer className="pt-2 border-t border-zinc-800/80 flex items-center justify-between text-xs text-zinc-500">
                                <div className="flex items-center gap-1.5">
                                    <ShieldCheck className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                                    <span>Instant suppression guarantee</span>
                                </div>
                            </footer>
                        </div>
                    )}
                </div>
            </main>

            <footer className="text-center py-6 text-xs text-zinc-600 border-t border-zinc-900">
                SpiggleMail &bull; High Deliverability & Subscriber Protection
            </footer>
        </div>
    );
}
