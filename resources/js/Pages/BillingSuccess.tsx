import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { CheckCircle2, ArrowRight, ShieldCheck, Mail } from 'lucide-react';

interface BillingSuccessProps {
  checkoutId?: string;
  newsletters?: any[];
  currentNewsletter?: any;
}

export default function BillingSuccess({
  checkoutId,
  newsletters = [],
  currentNewsletter = null,
}: BillingSuccessProps) {
  return (
    <DashboardLayout
      newsletters={newsletters}
      currentNewsletter={currentNewsletter}
    >
      <div className="max-w-2xl mx-auto py-16 px-4 text-center">
        <div className="w-16 h-16 bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-6 shadow-xs ring-8 ring-emerald-50 dark:ring-emerald-900/20">
          <CheckCircle2 className="w-10 h-10" />
        </div>

        <h1 className="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
          Subscription Confirmed!
        </h1>

        <p className="text-sm text-gray-600 dark:text-gray-300 mt-3 leading-relaxed">
          Your MailJinny Cloud subscription is active. Your monthly sending quota has been granted and your delivery queue is fully operational.
        </p>

        {checkoutId && (
          <div className="my-6 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-xs font-mono text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 max-w-md mx-auto truncate">
            Session ID: {checkoutId}
          </div>
        )}

        <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
          <a
            href="/campaigns"
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md transition-all"
          >
            <span>Go to Campaigns</span>
            <ArrowRight className="w-4 h-4" />
          </a>

          <a
            href="/billing"
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600 transition-all"
          >
            <span>View Billing Dashboard</span>
          </a>
        </div>

        <div className="mt-12 flex items-center justify-center gap-2 text-xs text-gray-400 dark:text-gray-500">
          <ShieldCheck className="w-4 h-4 text-emerald-500" />
          <span>Receipt and tax invoice sent to your email via Polar.sh</span>
        </div>
      </div>
    </DashboardLayout>
  );
}
