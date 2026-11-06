import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  ShieldCheck,
  Cpu,
  Mail,
  Zap,
  CheckCircle2,
  Lock,
  ArrowLeft,
  Server,
  Layers,
  Database,
  ExternalLink,
} from 'lucide-react';

export default function Docs(): React.JSX.Element {
  return (
    <div className="min-h-screen bg-zinc-950 text-zinc-100 selection:bg-indigo-500/30">
      <Head title="Architecture & Deliverability Documentation - SpiggleMail" />

      {/* Navigation Top Bar */}
      <nav className="border-b border-zinc-850 px-6 py-4 flex items-center justify-between max-w-6xl mx-auto">
        <Link
          href="/"
          className="inline-flex items-center gap-2 text-sm text-zinc-400 hover:text-white transition"
        >
          <ArrowLeft className="w-4 h-4" aria-hidden="true" />
          Back to Welcome
        </Link>

        <div className="flex items-center gap-3">
          <Link
            href="/dashboard"
            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition shadow-sm"
          >
            Launch Dashboard &rarr;
          </Link>
        </div>
      </nav>

      {/* Main Container */}
      <main className="max-w-4xl mx-auto px-6 py-12 space-y-12">
        <header className="space-y-3">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
            <Layers className="w-3.5 h-3.5" aria-hidden="true" />
            System Architecture & Standards
          </div>
          <h1 className="text-3xl font-extrabold tracking-tight text-white">
            SpiggleMail Architecture & High-Deliverability Guide
          </h1>
          <p className="text-base text-zinc-400 leading-relaxed">
            Technical blueprint explaining our conversion from Next.js + MongoDB to Laravel 13 + MariaDB + Redis + Amazon SES, complete with our anti-blacklist circuit breaker.
          </p>
        </header>

        {/* Section 1: The Stack Conversion */}
        <section className="space-y-4">
          <h2 className="text-xl font-bold text-white flex items-center gap-2 border-b border-zinc-800 pb-3">
            <Cpu className="w-5 h-5 text-indigo-400" aria-hidden="true" />
            1. Core Technology Stack
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-5 rounded-xl bg-zinc-900/60 border border-zinc-800">
              <h3 className="text-sm font-semibold text-white mb-1">Frontend (React 19 + TypeScript)</h3>
              <p className="text-xs text-zinc-400 leading-relaxed">
                Powered by Inertia.js 3.11 with zero client-side routing latency, Tailwind CSS v4, custom hooks, and strictly typed component contracts.
              </p>
            </div>
            <div className="p-5 rounded-xl bg-zinc-900/60 border border-zinc-800">
              <h3 className="text-sm font-semibold text-white mb-1">Backend & Queues</h3>
              <p className="text-xs text-zinc-400 leading-relaxed">
                Laravel 13 with PHP 8.5.4, Redis-backed rate limiting, and Supervisor-managed daemon workers for concurrent batch dispatches.
              </p>
            </div>
            <div className="p-5 rounded-xl bg-zinc-900/60 border border-zinc-800">
              <h3 className="text-sm font-semibold text-white mb-1">Storage & Schema</h3>
              <p className="text-xs text-zinc-400 leading-relaxed">
                MariaDB 10.11+ using ULID primary keys for time-ordered index clustering and zero sequence collision during high-throughput imports.
              </p>
            </div>
            <div className="p-5 rounded-xl bg-zinc-900/60 border border-zinc-800">
              <h3 className="text-sm font-semibold text-white mb-1">Mail Engine & Mocking</h3>
              <p className="text-xs text-zinc-400 leading-relaxed">
                Switchable AWS SES v2 SDK driver with fallback to local Mailpit SMTP server (:1025) and web dashboard (:8025).
              </p>
            </div>
          </div>
        </section>

        {/* Section 2: The 5 Deliverability Pillars */}
        <section className="space-y-4">
          <h2 className="text-xl font-bold text-white flex items-center gap-2 border-b border-zinc-800 pb-3">
            <ShieldCheck className="w-5 h-5 text-indigo-400" aria-hidden="true" />
            2. The 5 Deliverability & Anti-Blacklist Pillars
          </h2>

          <div className="space-y-3">
            <div className="p-4 rounded-xl bg-zinc-900/40 border border-zinc-800/80">
              <h4 className="text-sm font-semibold text-white flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                Pillar 1: Complete DNS Authentication (SPF, DKIM, DMARC)
              </h4>
              <p className="text-xs text-zinc-400 mt-1 leading-relaxed">
                Our DNS verification scanner checks for <code className="text-zinc-300">include:amazonses.com</code>, 2048-bit CNAME DKIM keys, and strict <code className="text-zinc-300">p=reject</code> DMARC policies.
              </p>
            </div>

            <div className="p-4 rounded-xl bg-zinc-900/40 border border-zinc-800/80">
              <h4 className="text-sm font-semibold text-white flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                Pillar 2: Custom Return-Path / MAIL FROM Domain
              </h4>
              <p className="text-xs text-zinc-400 mt-1 leading-relaxed">
                Replaces Amazon SES shared return paths with your custom subdomain (<code className="text-zinc-300">mail.yourdomain.com</code>), eliminating the "sent via amazonses.com" disclaimer on Gmail and Outlook.
              </p>
            </div>

            <div className="p-4 rounded-xl bg-zinc-900/40 border border-zinc-800/80">
              <h4 className="text-sm font-semibold text-white flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                Pillar 3: Automated Rate Limiting & Circuit Breaker
              </h4>
              <p className="text-xs text-zinc-400 mt-1 leading-relaxed">
                Monitors real-time bounce and complaint rates during active campaign dispatches. Automatically halts sending if bounce rate exceeds 2.0% or complaint rate exceeds 0.08%, well before SES triggers probation.
              </p>
            </div>

            <div className="p-4 rounded-xl bg-zinc-900/40 border border-zinc-800/80">
              <h4 className="text-sm font-semibold text-white flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                Pillar 4: Continuous Disposable Domain Defense
              </h4>
              <p className="text-xs text-zinc-400 mt-1 leading-relaxed">
                A nightly cron automatically syncs 10,143+ known throwaway/disposable domains into memory and database indexes, preventing toxic signups and spam-traps.
              </p>
            </div>

            <div className="p-4 rounded-xl bg-zinc-900/40 border border-zinc-800/80">
              <h4 className="text-sm font-semibold text-white flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" aria-hidden="true" />
                Pillar 5: RFC 8058 One-Click List-Unsubscribe
              </h4>
              <p className="text-xs text-zinc-400 mt-1 leading-relaxed">
                Injects compliant <code className="text-zinc-300">List-Unsubscribe-Post: List-Unsubscribe=One-Click</code> headers and landing URLs into every outgoing email to satisfy Google & Yahoo 2024 mandates.
              </p>
            </div>
          </div>
        </section>

        {/* Section 3: Local Testing with Mailpit */}
        <section className="p-6 rounded-xl bg-indigo-950/20 border border-indigo-900/40 space-y-3">
          <h2 className="text-base font-bold text-indigo-300 flex items-center gap-2">
            <Zap className="w-4 h-4 text-indigo-400" aria-hidden="true" />
            Testing Dispatches Locally via Mailpit
          </h2>
          <p className="text-xs text-indigo-200/80 leading-relaxed">
            Mailpit is running as a local daemon on port 1025 (SMTP) and port 8025 (Web UI). Whenever <code className="text-white">SES_DRIVER=mock</code> is selected in Settings, all dispatches are safely delivered locally without using AWS credits.
          </p>
          <div className="pt-2">
            <a
              href="http://localhost:8025"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-300 hover:text-white transition underline"
            >
              Open Mailpit Web Mailbox &rarr;
            </a>
          </div>
        </section>
      </main>
    </div>
  );
}
