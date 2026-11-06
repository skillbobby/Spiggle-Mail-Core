import React from 'react';
import { Mail, CheckCircle2 } from 'lucide-react';
import type { OnboardingFormValues } from '@/hooks/useOnboardingWizard';

interface LiveNewsletterPreviewProps {
  readonly values: OnboardingFormValues;
}

export const LiveNewsletterPreview: React.FC<LiveNewsletterPreviewProps> = ({ values }) => {
  const displayName = values.name.trim() || 'Your Newsletter';
  const displaySender = values.fromName.trim() || 'Author Name';
  const displayAddress = values.postalAddress.trim() || '123 Media Street, City, Country';

  return (
    <aside
      aria-label="Newsletter Live Preview"
      className="hidden lg:flex flex-1 flex-col items-center justify-center bg-zinc-900/40 border-l border-zinc-800/60 p-12"
    >
      <div className="w-full max-w-md rounded-2xl bg-zinc-950 border border-zinc-800 shadow-2xl p-6 text-left">
        {/* Email Header Preview */}
        <div className="flex items-center gap-3 border-b border-zinc-800/80 pb-4">
          <div className="w-10 h-10 rounded-full bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold">
            <Mail className="w-5 h-5" aria-hidden="true" />
          </div>
          <div>
            <h3 className="font-semibold text-white text-base leading-none">{displayName}</h3>
            <p className="text-xs text-zinc-400 mt-1">
              From: <span className="text-zinc-300 font-medium">{displaySender}</span>
              {values.fromEmail && ` <${values.fromEmail}>`}
            </p>
          </div>
        </div>

        {/* Email Mock Body */}
        <div className="py-6 space-y-3">
          <div className="h-4 bg-zinc-800/60 rounded w-3/4"></div>
          <div className="h-4 bg-zinc-800/60 rounded w-full"></div>
          <div className="h-4 bg-zinc-800/60 rounded w-5/6"></div>
          <p className="text-sm text-zinc-300 italic pt-2">
            &ldquo;{values.description.trim() || 'Here is where your stories, insights, and issues will engage your readers.'}&rdquo;
          </p>
        </div>

        {/* Footer with Anti-Spam Compliance Preview */}
        <div className="border-t border-zinc-900 pt-4 text-[11px] text-zinc-500 space-y-1">
          <div className="flex items-center gap-1.5 text-emerald-400/90 font-medium">
            <CheckCircle2 className="w-3.5 h-3.5" aria-hidden="true" />
            <span>CAN-SPAM / GDPR Compliant Footer</span>
          </div>
          <p className="truncate">{displayAddress}</p>
          <p className="underline hover:text-zinc-400 cursor-pointer">Unsubscribe</p>
        </div>
      </div>
    </aside>
  );
};
