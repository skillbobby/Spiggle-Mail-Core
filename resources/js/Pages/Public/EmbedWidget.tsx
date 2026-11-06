import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { CheckCircle2, ArrowRight } from 'lucide-react';
import { router } from '@inertiajs/react';
import { getEmailTypoSuggestion } from '@/utils/emailTypos';

interface EmbedWidgetProps {
    newsletter: {
        id: string;
        name: string;
        slug: string;
    };
    title?: string;
    subtitle?: string;
    buttonText?: string;
    buttonColor?: string;
    theme?: 'dark' | 'light';
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function EmbedWidget({
    newsletter,
    title = `Join ${newsletter.name}`,
    subtitle = 'Get curated updates and weekly technical insights directly in your inbox.',
    buttonText = 'Subscribe',
    buttonColor = '#4f46e5',
    theme = 'dark',
    flash,
}: EmbedWidgetProps) {
    const [email, setEmail] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isSuccess, setIsSuccess] = useState(Boolean(flash?.success));
    const suggestion = getEmailTypoSuggestion(email);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!email || isSubmitting) return;

        setIsSubmitting(true);
        router.post('/embed/subscribe', {
            newsletter_slug: newsletter.slug,
            email,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmitting(false);
                setIsSuccess(true);
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    const isDark = theme === 'dark';

    return (
        <div className={`p-5 rounded-2xl border transition-colors ${
            isDark ? 'bg-zinc-950 text-white border-zinc-800' : 'bg-white text-zinc-900 border-zinc-200'
        }`}>
            <Head title={`Subscribe to ${newsletter.name}`} />

            {isSuccess ? (
                <div className="text-center py-4 space-y-2 animate-enter">
                    <div className="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
                        <CheckCircle2 className="w-5 h-5" aria-hidden="true" />
                    </div>
                    <h3 className="text-sm font-bold tracking-tight">You are subscribed!</h3>
                    <p className={`text-xs ${isDark ? 'text-zinc-400' : 'text-zinc-500'}`}>
                        Check your inbox for our latest issues and welcome letter.
                    </p>
                </div>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-3">
                    <div>
                        <h3 className="text-sm font-bold tracking-tight leading-snug">{title}</h3>
                        <p className={`text-xs mt-0.5 leading-relaxed ${isDark ? 'text-zinc-400' : 'text-zinc-500'}`}>
                            {subtitle}
                        </p>
                    </div>

                    {flash?.error && (
                        <p className="text-xs text-red-400 font-medium">{flash.error}</p>
                    )}

                    <div className="flex gap-2">
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="you@domain.com"
                            required
                            className={`flex-1 px-3 py-2 rounded-lg text-xs border focus:outline-none transition ${
                                isDark
                                    ? 'bg-zinc-900 border-zinc-800 text-white placeholder-zinc-500 focus:border-indigo-500'
                                    : 'bg-zinc-50 border-zinc-300 text-zinc-900 placeholder-zinc-400 focus:border-indigo-500'
                            }`}
                        />
                        <button
                            type="submit"
                            disabled={isSubmitting}
                            style={{ backgroundColor: buttonColor }}
                            className="px-4 py-2 rounded-lg text-xs font-semibold text-white transition shadow-sm hover:opacity-90 disabled:opacity-50 whitespace-nowrap"
                        >
                            {isSubmitting ? '...' : buttonText}
                        </button>
                    </div>

                    {suggestion && (
                        <div className="pt-0.5">
                            <button
                                type="button"
                                onClick={() => setEmail(suggestion)}
                                className="inline-flex items-center gap-1.5 text-[11px] text-amber-400 hover:text-amber-300 transition text-left cursor-pointer"
                            >
                                <span>Did you mean <strong className="underline underline-offset-2">{suggestion}</strong>?</span>
                            </button>
                        </div>
                    )}
                </form>
            )}
        </div>
    );
}
