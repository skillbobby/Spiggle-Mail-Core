import React, { useState } from 'react';
import { Monitor, Smartphone, Mail, ExternalLink } from 'lucide-react';
import type { Newsletter } from '@/types';

export interface EmailPreviewFrameProps {
  readonly subject: string;
  readonly previewText?: string;
  readonly htmlContent: string;
  readonly newsletter: Newsletter | null;
  readonly sentAt?: string;
}

export const EmailPreviewFrame: React.FC<EmailPreviewFrameProps> = ({
  subject,
  previewText,
  htmlContent,
  newsletter,
  sentAt,
}) => {
  const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');

  return (
    <div className="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
      {/* Email header bar */}
      <div className="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40 px-5 py-3 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-400 font-semibold text-xs">
            {newsletter?.name?.charAt(0) ?? 'N'}
          </div>
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                {newsletter?.fromName ?? 'Newsletter Sender'}
              </span>
              <span className="text-[11px] text-zinc-400 truncate">
                &lt;{newsletter?.fromEmail ?? 'noreply@newsletter.local'}&gt;
              </span>
            </div>
            {sentAt && (
              <span className="text-[11px] text-zinc-400">
                Dispatched on {new Date(sentAt).toLocaleString()}
              </span>
            )}
          </div>
        </div>

        <div className="flex items-center gap-1.5">
          <div className="inline-flex rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-0.5">
            <button
              type="button"
              onClick={() => setDevice('desktop')}
              className={`flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                device === 'desktop'
                  ? 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-xs'
                  : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200'
              }`}
              title="Desktop View"
            >
              <Monitor className="h-3.5 w-3.5" />
              <span>Desktop</span>
            </button>
            <button
              type="button"
              onClick={() => setDevice('mobile')}
              className={`flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                device === 'mobile'
                  ? 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-xs'
                  : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200'
              }`}
              title="Mobile View"
            >
              <Smartphone className="h-3.5 w-3.5" />
              <span>Mobile</span>
            </button>
          </div>
        </div>
      </div>

      {/* Subject line header */}
      <div className="border-b border-zinc-100 dark:border-zinc-800/80 px-6 py-3">
        <h4 className="text-base font-semibold text-zinc-900 dark:text-zinc-100">
          {subject}
        </h4>
        {previewText && (
          <p className="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
            {previewText}
          </p>
        )}
      </div>

      {/* Render container */}
      <div className="bg-zinc-100 dark:bg-zinc-950 p-4 sm:p-6 flex justify-center">
        <div
          className={`w-full transition-all duration-300 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-800 bg-white shadow-sm ${
            device === 'mobile' ? 'max-w-sm' : 'max-w-2xl'
          }`}
        >
          <iframe
            srcDoc={htmlContent}
            title={subject}
            className="w-full border-0 min-h-[500px]"
            sandbox="allow-same-origin allow-popups"
          />
        </div>
      </div>
    </div>
  );
};
