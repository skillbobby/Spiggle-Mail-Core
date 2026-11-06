import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Mail, CheckCircle2, ArrowRight, AlertCircle } from 'lucide-react';

interface ConfirmProps {
    readonly status?: 'confirmed' | 'invalid';
    readonly newsletter?: {
        readonly id: string;
        readonly name: string;
        readonly slug: string;
    };
    readonly contact?: {
        readonly id: string;
        readonly email: string;
    };
    readonly error?: string;
}

export default function Confirm({ status = 'confirmed', newsletter, contact, error }: ConfirmProps) {
    const isConfirmed = status === 'confirmed';

    return (
        <div className="min-h-screen bg-zinc-950 text-zinc-100 flex flex-col justify-between selection:bg-indigo-500/30">
            <Head title={isConfirmed ? `Subscription Confirmed - ${newsletter?.name}` : 'Invalid Confirmation Link'} />

            {/* Header */}
            <header className="px-6 py-5 max-w-4xl mx-auto w-full flex items-center justify-between">
                <Link href="/" className="flex items-center gap-2.5 text-white font-bold text-lg tracking-tight">
                    <div className="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white">
                        <Mail className="w-4 h-4" aria-hidden="true" />
                    </div>
                    <span>Spiggle<span className="text-indigo-400">Mail</span></span>
                </Link>
            </header>

            {/* Main Content */}
            <main className="flex-1 flex items-center justify-center p-6">
                <div className="w-full max-w-md p-8 rounded-2xl bg-zinc-900 border border-zinc-800 text-center space-y-6">
                    {isConfirmed ? (
                        <>
                            <div className="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
                                <CheckCircle2 className="w-8 h-8" aria-hidden="true" />
                            </div>

                            <div className="space-y-2">
                                <h1 className="text-2xl font-bold tracking-tight text-white">
                                    Subscription Confirmed!
                                </h1>
                                <p className="text-xs text-zinc-400 leading-relaxed">
                                    Thank you for verifying your address! <strong className="text-white">{contact?.email}</strong> is now officially subscribed to <strong className="text-white">{newsletter?.name}</strong>.
                                </p>
                            </div>

                            <p className="text-xs text-zinc-500">
                                You will receive our upcoming issues directly in your inbox.
                            </p>

                            <div className="pt-2">
                                <Link
                                    href="/"
                                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-sm"
                                >
                                    Return to Homepage
                                    <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                                </Link>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mx-auto">
                                <AlertCircle className="w-8 h-8" aria-hidden="true" />
                            </div>

                            <div className="space-y-2">
                                <h1 className="text-2xl font-bold tracking-tight text-white">
                                    Invalid Confirmation Link
                                </h1>
                                <p className="text-xs text-zinc-400 leading-relaxed">
                                    {error || 'This confirmation link has expired or has already been used.'}
                                </p>
                            </div>

                            <div className="pt-2">
                                <Link
                                    href="/"
                                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-semibold transition"
                                >
                                    Return to Homepage
                                    <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
                                </Link>
                            </div>
                        </>
                    )}
                </div>
            </main>

            {/* Footer */}
            <footer className="text-center py-6 text-xs text-zinc-600 border-t border-zinc-900">
                SpiggleMail &bull; Built with Privacy and Deliverability First
            </footer>
        </div>
    );
}
