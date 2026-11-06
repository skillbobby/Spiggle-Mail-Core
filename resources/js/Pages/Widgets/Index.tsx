import React, { useState } from 'react';
import { DashboardLayout } from '@/Layouts/DashboardLayout';
import { Layers, Copy, CheckCircle2, Code2, Eye, Sparkles, Smartphone, Monitor } from 'lucide-react';

interface WidgetsPageProps {
    currentNewsletter: {
        id: string;
        name: string;
        slug: string;
        fromEmail: string;
    };
    appUrl: string;
}

export default function WidgetsIndex({ currentNewsletter, appUrl }: WidgetsPageProps): React.JSX.Element {
    const [title, setTitle] = useState(`Join ${currentNewsletter?.name || 'our newsletter'}`);
    const [subtitle, setSubtitle] = useState('Get curated updates and weekly technical insights directly in your inbox.');
    const [buttonText, setButtonText] = useState('Subscribe');
    const [buttonColor, setButtonColor] = useState('#4f46e5');
    const [theme, setTheme] = useState<'dark' | 'light'>('dark');
    const [previewDevice, setPreviewDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [copiedSnippet, setCopiedSnippet] = useState<'iframe' | 'html' | null>(null);

    const slug = currentNewsletter?.slug || 'engineering-dispatch';

    // Dynamic embed codes
    const iframeSnippet = `<iframe src="${appUrl}/embed/${slug}?theme=${theme}&btnColor=${encodeURIComponent(buttonColor)}&btnText=${encodeURIComponent(buttonText)}&title=${encodeURIComponent(title)}" width="100%" height="220" frameborder="0" scrolling="no" style="border-radius: 12px; max-width: 540px; margin: 0 auto; display: block;"></iframe>`;

    const rawHtmlSnippet = `<!-- SpiggleMail Subscribe Widget -->
<form action="${appUrl}/embed/subscribe" method="POST" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 480px; padding: 24px; border-radius: 16px; background: ${theme === 'dark' ? '#18181b' : '#ffffff'}; color: ${theme === 'dark' ? '#ffffff' : '#09090b'}; border: 1px solid ${theme === 'dark' ? '#27272a' : '#e4e4e7'};">
  <input type="hidden" name="newsletter_slug" value="${slug}" />
  <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700;">${title}</h3>
  <p style="margin: 0 0 16px 0; font-size: 13px; color: ${theme === 'dark' ? '#a1a1aa' : '#71717a'}; line-height: 1.5;">${subtitle}</p>
  <div style="display: flex; gap: 8px;">
    <input type="email" name="email" placeholder="your@email.com" required style="flex: 1; padding: 10px 14px; border-radius: 8px; border: 1px solid ${theme === 'dark' ? '#27272a' : '#d4d4d8'}; background: ${theme === 'dark' ? '#09090b' : '#f4f4f5'}; color: inherit; font-size: 14px; outline: none;" />
    <button type="submit" style="padding: 10px 20px; border-radius: 8px; border: none; background: ${buttonColor}; color: #ffffff; font-weight: 600; font-size: 14px; cursor: pointer;">${buttonText}</button>
  </div>
</form>`;

    const copyToClipboard = (text: string, type: 'iframe' | 'html') => {
        navigator.clipboard.writeText(text);
        setCopiedSnippet(type);
        setTimeout(() => setCopiedSnippet(null), 2000);
    };

    return (
        <DashboardLayout currentNewsletter={currentNewsletter}>
            <div className="space-y-8 max-w-6xl">
                {/* Header */}
                <header className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-800 pb-6">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2.5">
                            <Layers className="w-7 h-7 text-indigo-400" aria-hidden="true" />
                            Embeddable Subscribe Widgets
                        </h1>
                        <p className="text-sm text-zinc-400 mt-1">
                            Customize a beehiiv-style subscriber capture block. Copy the embed code and drop it into Webflow, WordPress, Ghost, or any custom website.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 bg-zinc-900 border border-zinc-800 p-1 rounded-lg">
                        <button
                            type="button"
                            onClick={() => setPreviewDevice('desktop')}
                            className={`p-1.5 rounded transition ${previewDevice === 'desktop' ? 'bg-zinc-800 text-white' : 'text-zinc-500 hover:text-zinc-300'}`}
                            aria-label="Desktop preview"
                        >
                            <Monitor className="w-4 h-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            onClick={() => setPreviewDevice('mobile')}
                            className={`p-1.5 rounded transition ${previewDevice === 'mobile' ? 'bg-zinc-800 text-white' : 'text-zinc-500 hover:text-zinc-300'}`}
                            aria-label="Mobile preview"
                        >
                            <Smartphone className="w-4 h-4" aria-hidden="true" />
                        </button>
                    </div>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    {/* Controls Panel (Left 5 Cols) */}
                    <div className="lg:col-span-5 space-y-6 bg-zinc-900 border border-zinc-800 rounded-xl p-6">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-zinc-400 flex items-center gap-2">
                            <Sparkles className="w-4 h-4 text-indigo-400" aria-hidden="true" />
                            Widget Customizer
                        </h2>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">Headline Title</label>
                                <input
                                    type="text"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">Subtitle Description</label>
                                <textarea
                                    rows={2}
                                    value={subtitle}
                                    onChange={(e) => setSubtitle(e.target.value)}
                                    className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-zinc-300 mb-1.5">Button Label</label>
                                    <input
                                        type="text"
                                        value={buttonText}
                                        onChange={(e) => setButtonText(e.target.value)}
                                        className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-zinc-300 mb-1.5">Theme Mode</label>
                                    <select
                                        value={theme}
                                        onChange={(e) => setTheme(e.target.value as 'dark' | 'light')}
                                        className="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="dark">Dark Theme</option>
                                        <option value="light">Light Theme</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-zinc-300 mb-1.5">Button Accent Color</label>
                                <div className="flex items-center gap-3">
                                    <input
                                        type="color"
                                        value={buttonColor}
                                        onChange={(e) => setButtonColor(e.target.value)}
                                        className="w-10 h-10 rounded cursor-pointer bg-transparent border-0"
                                    />
                                    <input
                                        type="text"
                                        value={buttonColor}
                                        onChange={(e) => setButtonColor(e.target.value)}
                                        className="w-32 font-mono bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-white"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Live Preview & Code Export (Right 7 Cols) */}
                    <div className="lg:col-span-7 space-y-6">
                        {/* Live Widget Preview Card */}
                        <div className="bg-zinc-900 border border-zinc-800 rounded-xl p-6">
                            <header className="flex items-center justify-between mb-4">
                                <span className="text-xs font-semibold uppercase tracking-wider text-zinc-400 flex items-center gap-1.5">
                                    <Eye className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                                    Live Preview ({previewDevice})
                                </span>
                                <span className="text-xs text-zinc-500">Updates dynamically</span>
                            </header>

                            <div className="bg-zinc-950/80 rounded-xl p-6 border border-zinc-800/80 flex items-center justify-center min-h-[220px]">
                                <div className={`w-full transition-all duration-300 ${previewDevice === 'mobile' ? 'max-w-[320px]' : 'max-w-[480px]'}`}>
                                    {/* Rendered Embed Preview */}
                                    <div
                                        className={`p-6 rounded-2xl border shadow-xl ${
                                            theme === 'dark'
                                                ? 'bg-zinc-900 border-zinc-800 text-white'
                                                : 'bg-white border-zinc-200 text-zinc-900'
                                        }`}
                                    >
                                        <h3 className="text-base font-bold tracking-tight mb-1">{title}</h3>
                                        <p className={`text-xs mb-4 leading-relaxed ${theme === 'dark' ? 'text-zinc-400' : 'text-zinc-600'}`}>
                                            {subtitle}
                                        </p>
                                        <div className={`flex gap-2 ${previewDevice === 'mobile' ? 'flex-col' : 'flex-row'}`}>
                                            <input
                                                type="email"
                                                readOnly
                                                placeholder="your@email.com"
                                                className={`flex-1 px-3.5 py-2 rounded-lg text-xs border focus:outline-none ${
                                                    theme === 'dark'
                                                        ? 'bg-zinc-950 border-zinc-800 text-white placeholder-zinc-500'
                                                        : 'bg-zinc-50 border-zinc-300 text-zinc-900 placeholder-zinc-400'
                                                }`}
                                            />
                                            <button
                                                type="button"
                                                style={{ backgroundColor: buttonColor }}
                                                className="px-4 py-2 rounded-lg text-xs font-semibold text-white transition shadow-sm hover:opacity-90"
                                            >
                                                {buttonText}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Embed Code Snippets */}
                        <div className="space-y-4">
                            {/* Option 1: Responsive Iframe */}
                            <div className="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Code2 className="w-4 h-4 text-indigo-400" aria-hidden="true" />
                                        <h3 className="text-sm font-semibold text-white">Embed via Iframe (Recommended)</h3>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => copyToClipboard(iframeSnippet, 'iframe')}
                                        className="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold transition"
                                    >
                                        {copiedSnippet === 'iframe' ? (
                                            <>
                                                <CheckCircle2 className="w-3.5 h-3.5 text-emerald-300" aria-hidden="true" />
                                                Copied!
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="w-3.5 h-3.5" aria-hidden="true" />
                                                Copy Iframe
                                            </>
                                        )}
                                    </button>
                                </div>
                                <pre className="bg-zinc-950 border border-zinc-800/80 rounded-lg p-3 text-[11px] font-mono text-zinc-300 overflow-x-auto whitespace-pre-wrap select-all">
                                    {iframeSnippet}
                                </pre>
                            </div>

                            {/* Option 2: Pure HTML Form */}
                            <div className="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Code2 className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                                        <h3 className="text-sm font-semibold text-white">Embed via Pure HTML Form (Custom Styling)</h3>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => copyToClipboard(rawHtmlSnippet, 'html')}
                                        className="inline-flex items-center gap-1.5 px-3 py-1 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 rounded text-xs font-semibold transition"
                                    >
                                        {copiedSnippet === 'html' ? (
                                            <>
                                                <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" aria-hidden="true" />
                                                Copied!
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="w-3.5 h-3.5" aria-hidden="true" />
                                                Copy HTML
                                            </>
                                        )}
                                    </button>
                                </div>
                                <pre className="bg-zinc-950 border border-zinc-800/80 rounded-lg p-3 text-[11px] font-mono text-zinc-400 overflow-x-auto whitespace-pre-wrap max-h-36 select-all">
                                    {rawHtmlSnippet}
                                </pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
