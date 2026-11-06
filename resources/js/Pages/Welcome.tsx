import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Mail, ShieldCheck, Zap, Send, BarChart2 } from 'lucide-react';

interface FeatureCardProps {
  readonly icon: React.ReactNode;
  readonly title: string;
  readonly description: string;
}

const FeatureCard: React.FC<FeatureCardProps> = ({ icon, title, description }) => (
  <article className="p-6 rounded-2xl bg-zinc-900/50 border border-zinc-800/80">
    <div className="mb-3">{icon}</div>
    <h3 className="font-semibold text-white text-base">{title}</h3>
    <p className="mt-2 text-sm text-zinc-400 leading-relaxed">{description}</p>
  </article>
);

const FEATURES: readonly FeatureCardProps[] = [
  {
    icon: <ShieldCheck className="w-6 h-6 text-emerald-400" aria-hidden="true" />,
    title: 'AWS SES Reputation Guardrails',
    description:
      'Cryptographic X.509 SNS bounce/complaint feedback loops, 3-strike soft bounce handling, disposable & MX hygiene checks, and automated 2% bounce / 0.08% complaint circuit breakers.',
  },
  {
    icon: <Send className="w-6 h-6 text-indigo-400" aria-hidden="true" />,
    title: 'Warm-up & Rate Pacing',
    description:
      'Rate-paced dispatch honoring your AWS SES per-second quota, 5% warm-up pilot slices, RFC 8058 one-click List-Unsubscribe, and multipart text/plain + HTML delivery.',
  },
  {
    icon: <BarChart2 className="w-6 h-6 text-amber-400" aria-hidden="true" />,
    title: 'Single-Operator Self-Hosted',
    description:
      'Locked down for your personal or company newsletter with zero public signups, AES-256 encrypted credentials at rest, REST API v1, and MCP server for AI agents.',
  },
];

export default function Welcome(): React.JSX.Element {
  return (
    <div className="min-h-screen bg-zinc-950 text-zinc-100 flex flex-col justify-between">
      <Head title="SpiggleMail Core — Self-Hosted Single-User Newsletter Platform" />

      {/* Top Navigation */}
      <header className="border-b border-zinc-800/80 px-6 py-4 flex items-center justify-between max-w-7xl mx-auto w-full">
        <div className="flex items-center gap-2.5">
          <div className="h-9 w-9 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold shadow-lg shadow-indigo-500/20">
            <Mail className="w-5 h-5" aria-hidden="true" />
          </div>
          <span className="font-semibold text-lg tracking-tight">
            Spiggle<span className="text-indigo-400">Mail</span>{' '}
            <span className="text-xs font-mono text-zinc-400">Core</span>
          </span>
          <span className="text-xs px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700/60 font-mono">
            MIT &bull; Single-User
          </span>
        </div>
        <nav aria-label="Main Navigation" className="flex items-center gap-4">
          <Link
            href="/docs"
            className="text-sm font-medium text-zinc-300 hover:text-white transition"
          >
            Docs &amp; API
          </Link>
          <Link
            href="/signin"
            className="text-sm font-medium px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm"
          >
            Operator Sign In
          </Link>
        </nav>
      </header>

      {/* Hero Section */}
      <main className="max-w-5xl mx-auto px-6 py-20 text-center flex-1 flex flex-col items-center justify-center">
        <aside className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-medium mb-6">
          <Zap className="w-3.5 h-3.5" aria-hidden="true" /> Self-Hosted Single-Operator Edition (MIT)
        </aside>

        <h1 className="text-4xl sm:text-6xl font-bold tracking-tight text-white max-w-3xl leading-tight">
          Own your audience, sender reputation, and AWS SES costs.
        </h1>

        <p className="mt-6 text-lg text-zinc-400 max-w-2xl">
          A self-hosted newsletter &amp; broadcast engine built with Laravel 12, React 19, and Amazon SES v2. Locked to a single operator out of the box with automated bounce/complaint protection.
        </p>

        <div className="mt-8 flex items-center gap-3">
          <Link
            href="/signin"
            className="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium shadow-lg shadow-indigo-600/25 transition"
          >
            Operator Sign In
          </Link>
          <Link
            href="/docs"
            className="px-6 py-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-800 font-medium transition"
          >
            API &amp; MCP Docs
          </Link>
        </div>

        {/* Feature Grid */}
        <section aria-label="Key Features" className="mt-20 grid grid-cols-1 md:grid-cols-3 gap-6 text-left w-full">
          {FEATURES.map((feature) => (
            <FeatureCard
              key={feature.title}
              icon={feature.icon}
              title={feature.title}
              description={feature.description}
            />
          ))}
        </section>
      </main>

      {/* Footer */}
      <footer className="border-t border-zinc-900 py-6 text-center text-xs text-zinc-500">
        SpiggleMail Core (Community Edition) &bull; Single-Operator Mode &bull; MIT License
      </footer>
    </div>
  );
}
