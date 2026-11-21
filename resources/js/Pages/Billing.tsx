import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
  CreditCard,
  CheckCircle2,
  AlertCircle,
  ExternalLink,
  ShieldCheck,
  Zap,
  ArrowRight,
  Loader2,
  Calendar,
  Layers,
  Check
} from 'lucide-react';

interface BillingProps {
  edition: string;
  polarConfigured: boolean;
  plan: string;
  monthlyQuota: number;
  subscriptionStatus: string;
  pauseAtQuotaLimit: boolean;
  cancelAtPeriodEnd: boolean;
  currentPeriodEnd: string | null;
  newsletters?: any[];
  currentNewsletter?: any;
}

export default function Billing({
  edition,
  polarConfigured,
  plan,
  monthlyQuota,
  subscriptionStatus,
  pauseAtQuotaLimit: initialPause,
  cancelAtPeriodEnd,
  currentPeriodEnd,
  newsletters = [],
  currentNewsletter = null,
}: BillingProps) {
  const [pauseAtLimit, setPauseAtLimit] = useState(initialPause);
  const [savingSettings, setSavingSettings] = useState(false);
  const [loadingCheckout, setLoadingCheckout] = useState<string | null>(null);
  const [loadingPortal, setLoadingPortal] = useState(false);
  const [billingInterval, setBillingInterval] = useState<'month' | 'year'>('month');

  const handleTogglePause = async () => {
    const nextVal = !pauseAtLimit;
    setPauseAtLimit(nextVal);
    setSavingSettings(true);
    try {
      await fetch('/api/v1/billing/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pause_at_quota_limit: nextVal }),
      });
    } catch (err) {
      console.error('Failed to update quota settings', err);
    } finally {
      setSavingSettings(false);
    }
  };

  const handleUpgrade = async (tier: string) => {
    setLoadingCheckout(tier);
    try {
      const res = await fetch('/api/v1/billing/checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tier, interval: billingInterval }),
      });
      const data = await res.json();
      if (data.ok && data.checkout_url) {
        window.location.href = data.checkout_url;
      } else {
        alert(data.error || 'Failed to initiate checkout session');
      }
    } catch (err) {
      alert('Network error initiating checkout session');
    } finally {
      setLoadingCheckout(null);
    }
  };

  const handleOpenPortal = async () => {
    setLoadingPortal(true);
    try {
      const res = await fetch('/api/v1/billing/portal', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
      });
      const data = await res.json();
      if (data.ok && data.portal_url) {
        window.location.href = data.portal_url;
      } else {
        alert(data.error || 'No active portal session found.');
      }
    } catch (err) {
      alert('Failed to connect to billing portal.');
    } finally {
      setLoadingPortal(false);
    }
  };

  const tiers = [
    {
      id: 'starter',
      name: 'Starter / Indie',
      desc: 'For solo creators and author publications.',
      monthlyPrice: 19,
      annualPrice: 15,
      sends: '25,000 sends / mo',
      bullets: [
        '25,000 monthly sends',
        'Unlimited contacts',
        'Custom sending domain',
        'Standard email support',
      ],
    },
    {
      id: 'growth',
      name: 'Growth / Pro',
      desc: 'For active weekly publications and SaaS dispatches.',
      monthlyPrice: 39,
      annualPrice: 29,
      sends: '100,000 sends / mo',
      popular: true,
      bullets: [
        '100,000 monthly sends',
        'Unlimited contacts',
        'Up to 3 custom sending domains',
        'Priority software support',
      ],
    },
    {
      id: 'scale',
      name: 'Publisher / Scale',
      desc: 'For high-frequency dispatchers and scaled publications.',
      monthlyPrice: 119,
      annualPrice: 99,
      sends: '500,000 sends / mo',
      bullets: [
        '500,000 monthly sends',
        'Unlimited contacts',
        'Unlimited custom domains',
        'Priority uptime alerts',
      ],
    },
  ];

  return (
    <DashboardLayout
      newsletters={newsletters}
      currentNewsletter={currentNewsletter}
    >
      <div className="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
            <CreditCard className="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
            Billing & Subscription
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Manage your MailJinny Cloud plan, monthly send quotas, and metered overage preferences.
          </p>
        </div>

        {/* Current Plan Overview Card */}
        <div className="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-xs">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-gray-700">
            <div>
              <div className="flex items-center gap-3">
                <h2 className="text-xl font-bold capitalize text-gray-900 dark:text-white">
                  {plan} Plan
                </h2>
                <span className={`text-xs font-semibold px-2.5 py-0.5 rounded-full capitalize ${
                  subscriptionStatus === 'active'
                    ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400'
                    : subscriptionStatus === 'canceled'
                    ? 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400'
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                }`}>
                  {subscriptionStatus}
                </span>
                {cancelAtPeriodEnd && (
                  <span className="text-xs font-medium text-amber-600 dark:text-amber-400">
                    (Cancels at end of cycle)
                  </span>
                )}
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {monthlyQuota > 0
                  ? `${monthlyQuota.toLocaleString()} monthly dispatches included`
                  : 'Self-hosted or free tier (no cloud send quota)'}
              </p>
            </div>

            {polarConfigured && subscriptionStatus === 'active' && (
              <button
                onClick={handleOpenPortal}
                disabled={loadingPortal}
                className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 transition-colors"
              >
                {loadingPortal ? (
                  <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                  <ExternalLink className="w-4 h-4" />
                )}
                Manage Cards & Invoices
              </button>
            )}
          </div>

          {/* Overage Protection Toggle */}
          <div className="pt-6">
            <div className="flex items-start justify-between gap-4">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <ShieldCheck className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                  <span className="font-semibold text-sm text-gray-900 dark:text-white">
                    Overage Protection (Quota Cap)
                  </span>
                  {savingSettings && (
                    <span className="text-[10px] text-gray-400 flex items-center gap-1">
                      <Loader2 className="w-3 h-3 animate-spin" /> Saving...
                    </span>
                  )}
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 max-w-2xl leading-relaxed">
                  When enabled, your outgoing campaigns will automatically pause if your dispatches reach your plan limit ({monthlyQuota > 0 ? monthlyQuota.toLocaleString() : 'limit'}). When disabled, dispatches continue seamlessly and overages are billed via monthly metered invoicing.
                </p>
              </div>

              <label className="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                <input
                  type="checkbox"
                  checked={pauseAtLimit}
                  onChange={handleTogglePause}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-hidden rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-600"></div>
              </label>
            </div>
          </div>
        </div>

        {/* Change / Upgrade Tier Grid */}
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                Available Cloud Plans
              </h2>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Switch plans anytime. Upgrades take effect immediately with pro-rated billing via Polar.sh.
              </p>
            </div>

            {/* Monthly / Annual Toggle */}
            <div className="inline-flex items-center p-1 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
              <button
                type="button"
                onClick={() => setBillingInterval('month')}
                className={`px-3 py-1 text-xs font-semibold rounded-md transition-colors ${
                  billingInterval === 'month'
                    ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                }`}
              >
                Monthly
              </button>
              <button
                type="button"
                onClick={() => setBillingInterval('year')}
                className={`px-3 py-1 text-xs font-semibold rounded-md flex items-center gap-1.5 transition-colors ${
                  billingInterval === 'year'
                    ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                }`}
              >
                <span>Annual</span>
                <span className="text-[10px] bg-amber-200 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 font-bold px-1.5 py-0.2 rounded-sm">
                  SAVE 20%
                </span>
              </button>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {tiers.map((tier) => {
              const price = billingInterval === 'year' ? tier.annualPrice : tier.monthlyPrice;
              const isCurrent = plan.toLowerCase() === tier.id;

              return (
                <div
                  key={tier.id}
                  className={`rounded-2xl p-6 flex flex-col justify-between relative transition-all border ${
                    tier.popular
                      ? 'bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-400 dark:border-indigo-600 shadow-md ring-1 ring-indigo-400/20'
                      : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 shadow-xs'
                  }`}
                >
                  {tier.popular && (
                    <span className="absolute -top-3 left-6 text-[10px] font-bold uppercase tracking-wider bg-indigo-600 text-white px-2 py-0.5 rounded-full">
                      Most Popular
                    </span>
                  )}

                  <div>
                    <h3 className="font-bold text-base text-gray-900 dark:text-white">
                      {tier.name}
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 min-h-[32px]">
                      {tier.desc}
                    </p>

                    <div className="my-5">
                      <div className="flex items-baseline gap-1">
                        <span className="text-3xl font-extrabold text-gray-900 dark:text-white">
                          ${price}
                        </span>
                        <span className="text-xs text-gray-500 dark:text-gray-400">
                          {billingInterval === 'year' ? '/mo (billed annually)' : '/month'}
                        </span>
                      </div>
                      <div className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 mt-1">
                        {tier.sends}
                      </div>
                    </div>

                    <ul className="space-y-2.5 pt-4 border-t border-gray-100 dark:border-gray-700 text-xs">
                      {tier.bullets.map((b, bIdx) => (
                        <li key={bIdx} className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                          <Check className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                          <span>{b}</span>
                        </li>
                      ))}
                    </ul>
                  </div>

                  <div className="pt-6">
                    <button
                      type="button"
                      disabled={isCurrent || loadingCheckout !== null}
                      onClick={() => handleUpgrade(tier.id)}
                      className={`w-full py-2 px-4 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all ${
                        isCurrent
                          ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 cursor-not-allowed'
                          : tier.popular
                          ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs'
                          : 'bg-gray-900 hover:bg-gray-800 dark:bg-gray-100 dark:hover:bg-white text-white dark:text-gray-900'
                      }`}
                    >
                      {loadingCheckout === tier.id ? (
                        <>
                          <Loader2 className="w-3.5 h-3.5 animate-spin" />
                          <span>Opening Polar...</span>
                        </>
                      ) : isCurrent ? (
                        <span>Current Plan</span>
                      ) : (
                        <>
                          <span>Upgrade to {tier.name.split(' ')[0]}</span>
                          <ArrowRight className="w-3.5 h-3.5" />
                        </>
                      )}
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Polar MoR Trust Notice */}
        <div className="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
          <div className="flex items-center gap-2.5">
            <ShieldCheck className="w-4 h-4 text-emerald-500 shrink-0" />
            <span>Payments and merchant compliance securely handled by <strong>Polar.sh</strong> as Merchant of Record.</span>
          </div>
          <span className="font-medium text-emerald-600 dark:text-emerald-400">
            14-Day Money-Back Guarantee
          </span>
        </div>
      </div>
    </DashboardLayout>
  );
}
