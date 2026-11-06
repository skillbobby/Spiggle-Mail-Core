import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Mail, ArrowRight, ShieldCheck, KeyRound, Loader2, ArrowLeft, Terminal, ExternalLink } from 'lucide-react';
import { useAuthOtp } from '@/hooks/useAuthOtp';

interface SignInProps {
    readonly initialDevCode?: string;
    readonly devCode?: string;
    readonly initialEmail?: string;
    readonly email?: string;
    readonly isLocal?: boolean;
    readonly isSingleUse?: boolean;
    readonly flash?: {
        readonly success?: string;
        readonly error?: string;
    };
}

export default function SignIn({
    initialDevCode,
    devCode: propDevCode,
    initialEmail,
    email: propEmail,
    isLocal = true,
    isSingleUse = false,
    flash,
}: SignInProps) {
    const activeInitialCode = propDevCode || initialDevCode || null;
    const activeInitialEmail = propEmail || initialEmail || '';

    const {
        email,
        setEmail,
        step,
        code,
        setCode,
        isSending,
        isVerifying,
        error,
        devCode,
        handleSendCode,
        handleVerifyCode,
        handleUseAnotherEmail,
        handleResendCode,
        quickFillAndSend,
    } = useAuthOtp(activeInitialCode, activeInitialEmail);

    return (
        <div className="min-h-screen bg-zinc-950 text-zinc-100 flex flex-col justify-between selection:bg-indigo-500/30">
            <Head title="Sign In - SpiggleMail" />

            {/* Top Bar */}
            <header className="px-6 py-5 max-w-6xl mx-auto w-full flex items-center justify-between">
                <Link href="/" className="flex items-center gap-2.5 text-white font-bold text-lg tracking-tight">
                    <div className="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white">
                        <Mail className="w-4 h-4" aria-hidden="true" />
                    </div>
                    <span>Spiggle<span className="text-indigo-400">Mail</span></span>
                </Link>

                <Link
                    href="/"
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-400 hover:text-white transition"
                >
                    <ArrowLeft className="w-3.5 h-3.5" aria-hidden="true" />
                    Back
                </Link>
            </header>

            {/* Main Auth Container */}
            <main className="flex-1 flex items-center justify-center p-6">
                <div className="w-full max-w-md space-y-6">
                    <div className="space-y-2 text-center">
                        <div className="w-12 h-12 rounded-2xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mx-auto mb-3">
                            <KeyRound className="w-6 h-6" aria-hidden="true" />
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">
                            {step === 'email' ? 'Sign in to SpiggleMail' : 'Check your email'}
                        </h1>
                        <p className="text-xs text-zinc-400">
                            {step === 'email'
                                ? 'Passwordless authentication with single-use 6-digit verification code.'
                                : `We sent a 6-digit confirmation code to ${email}`}
                        </p>
                        {isSingleUse && (
                            <p className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-900 border border-zinc-800 text-[11px] text-zinc-300 mt-2">
                                <ShieldCheck className="w-3.5 h-3.5 text-indigo-400" aria-hidden="true" />
                                Single-Operator Instance — Public Signups Disabled
                            </p>
                        )}
                    </div>

                    {/* Flash messages or errors */}
                    {(error || flash?.error) && (
                        <div role="alert" className="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-xs text-red-400">
                            {error || flash?.error}
                        </div>
                    )}

                    {flash?.success && (
                        <div role="status" className="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400">
                            {flash.success}
                        </div>
                    )}

                    {/* Step 1: Request OTP Code */}
                    {step === 'email' && (
                        <div className="space-y-4">
                            <form onSubmit={handleSendCode} className="space-y-4">
                                <div>
                                    <label htmlFor="auth-email" className="block text-xs font-medium text-zinc-300 mb-1.5">
                                        Email Address
                                    </label>
                                    <input
                                        id="auth-email"
                                        type="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        placeholder="you@company.com"
                                        required
                                        autoFocus
                                        className="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={isSending}
                                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold transition disabled:opacity-50 shadow-sm shadow-indigo-600/20"
                                >
                                    {isSending ? (
                                        <>
                                            <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />
                                            Sending code...
                                        </>
                                    ) : (
                                        <>
                                            Email me a code
                                            <ArrowRight className="w-4 h-4" aria-hidden="true" />
                                        </>
                                    )}
                                </button>
                            </form>

                            {/* Local / Testing Quick Login Helpers */}
                            {isLocal && (
                                <div className="mt-6 pt-5 border-t border-zinc-800/80 space-y-3">
                                    <div className="flex items-center gap-2 text-xs font-medium text-zinc-400">
                                        <Terminal className="w-3.5 h-3.5 text-indigo-400" aria-hidden="true" />
                                        <span>Local Development Quick Sign-In</span>
                                    </div>
                                    <div className="grid grid-cols-1 gap-2">
                                        <button
                                            type="button"
                                            onClick={() => quickFillAndSend('admin@spiggle-mail.local')}
                                            disabled={isSending}
                                            className="p-2.5 rounded-xl bg-zinc-900/90 border border-zinc-800 hover:border-indigo-500/50 hover:bg-zinc-800/60 text-left transition text-xs group"
                                        >
                                            <div className="flex items-center justify-between font-semibold text-white group-hover:text-indigo-400">
                                                <span>Instance Operator</span>
                                                <span className="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                                    Owner
                                                </span>
                                            </div>
                                            <div className="text-[11px] text-zinc-400 truncate mt-0.5 font-mono">
                                                admin@spiggle-mail.local
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 2: Verify 6-digit OTP */}
                    {step === 'code' && (
                        <div className="space-y-4">
                            {/* DevCode Banner matching original MailCheap local development helper */}
                            {devCode && (
                                <aside
                                    aria-label="Development helper"
                                    className="p-3.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-xs text-emerald-300 space-y-2 shadow-sm"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="font-medium text-emerald-300">
                                            Local mode (testing) — your sign-in code is:
                                        </span>
                                        <strong className="font-mono text-base tracking-widest text-white bg-zinc-950 px-2.5 py-1 rounded-md border border-emerald-500/40 shadow-inner">
                                            {devCode}
                                        </strong>
                                    </div>
                                    <p className="text-[11px] text-emerald-400/80">
                                        Auto-filled below. Press Enter or click &quot;Verify &amp; Enter Dashboard&quot; to sign in.
                                    </p>
                                </aside>
                            )}

                            <form onSubmit={handleVerifyCode} className="space-y-4">
                                <div>
                                    <label htmlFor="auth-otp-code" className="block text-xs font-medium text-zinc-300 mb-1.5 text-center">
                                        Enter 6-digit Verification Code
                                    </label>
                                    <input
                                        id="auth-otp-code"
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={6}
                                        value={code}
                                        onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                                        placeholder="000000"
                                        required
                                        autoFocus
                                        aria-label="Code"
                                        className="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3.5 py-2.5 text-center text-xl font-mono tracking-[0.5em] text-white placeholder-zinc-700 focus:outline-none focus:border-indigo-500 transition"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={isVerifying || code.length < 6}
                                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-semibold transition disabled:opacity-50 shadow-sm shadow-emerald-600/20"
                                >
                                    {isVerifying ? (
                                        <>
                                            <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />
                                            Verifying...
                                        </>
                                    ) : (
                                        <>
                                            <ShieldCheck className="w-4 h-4" aria-hidden="true" />
                                            Verify & Enter Dashboard
                                        </>
                                    )}
                                </button>
                            </form>

                            <div className="flex items-center justify-between pt-2 text-xs text-zinc-400">
                                <button
                                    type="button"
                                    onClick={handleUseAnotherEmail}
                                    className="hover:text-white transition underline"
                                >
                                    Use another email
                                </button>
                                <button
                                    type="button"
                                    onClick={handleResendCode}
                                    disabled={isSending}
                                    className="hover:text-white transition underline disabled:opacity-50"
                                >
                                    {isSending ? 'Resending...' : 'Resend code'}
                                </button>
                            </div>

                            {/* Mailpit Link */}
                            <div className="pt-2 text-center">
                                <a
                                    href="http://localhost:8025"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1.5 text-[11px] text-zinc-500 hover:text-zinc-300 transition"
                                >
                                    <span>Inspect email in Mailpit Web UI</span>
                                    <ExternalLink className="w-3 h-3" aria-hidden="true" />
                                </a>
                            </div>
                        </div>
                    )}
                </div>
            </main>

            {/* Footer */}
            <footer className="text-center py-6 text-xs text-zinc-600 border-t border-zinc-900">
                Secured with 2FA email verification &bull; SpiggleMail Enterprise
            </footer>
        </div>
    );
}
