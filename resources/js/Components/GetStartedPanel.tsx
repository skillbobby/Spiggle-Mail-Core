import React, { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import {
  Check,
  ChevronDown,
  ChevronUp,
  X,
  Sparkles,
  ArrowRight,
  ExternalLink,
} from 'lucide-react';

export interface GetStartedStep {
  readonly id: string;
  readonly title: string;
  readonly description: string;
  readonly href: string;
  readonly buttonText: string;
  readonly isDone: boolean;
  readonly isOptional?: boolean;
}

export interface GetStartedData {
  readonly steps: readonly GetStartedStep[];
  readonly completedCount: number;
  readonly totalCount: number;
  readonly isFinished: boolean;
}

interface GetStartedPanelProps {
  readonly data?: GetStartedData | null;
}

const STORAGE_KEY_MINIMIZED = 'spiggle_mail:get_started_minimized';
const STORAGE_KEY_DISMISSED = 'spiggle_mail:get_started_dismissed';

export const GetStartedPanel: React.FC<GetStartedPanelProps> = ({ data }) => {
  if (!data || !data.steps || data.steps.length === 0) {
    return null;
  }

  const [isDismissed, setIsDismissed] = useState<boolean>(() => {
    try {
      return localStorage.getItem(STORAGE_KEY_DISMISSED) === 'true';
    } catch {
      return false;
    }
  });

  const [isMinimized, setIsMinimized] = useState<boolean>(() => {
    try {
      return localStorage.getItem(STORAGE_KEY_MINIMIZED) === 'true';
    } catch {
      return false;
    }
  });

  const handleMinimize = (minimized: boolean) => {
    setIsMinimized(minimized);
    try {
      localStorage.setItem(STORAGE_KEY_MINIMIZED, String(minimized));
    } catch {
      // Storage unavailable
    }
  };

  const handleDismiss = () => {
    setIsDismissed(true);
    try {
      localStorage.setItem(STORAGE_KEY_DISMISSED, 'true');
    } catch {
      // Storage unavailable
    }
  };

  if (isDismissed) {
    return null;
  }

  const { steps, completedCount, totalCount, isFinished } = data;
  const nextStep = steps.find((s) => !s.isDone && !s.isOptional) || steps.find((s) => !s.isDone);
  const percent = Math.round((completedCount / totalCount) * 100);

  // Minimized Pill
  if (isMinimized) {
    return (
      <aside
        aria-label="Onboarding quick checklist"
        className="fixed bottom-5 right-5 z-40"
      >
        <button
          type="button"
          onClick={() => handleMinimize(false)}
          className="flex items-center gap-2 px-3.5 py-2 rounded-full bg-zinc-900/95 hover:bg-zinc-800 border border-zinc-700/80 text-white shadow-xl shadow-black/50 text-xs font-semibold backdrop-blur-md transition-all hover:scale-105 active:scale-95 group"
        >
          <div className="w-2 h-2 rounded-full bg-indigo-500 animate-pulse" />
          <span>Get started</span>
          <span className="text-zinc-400 font-mono text-[11px]">
            &bull; {completedCount}/{totalCount}
          </span>
          <ChevronUp className="w-3.5 h-3.5 text-zinc-400 group-hover:text-white transition" aria-hidden="true" />
        </button>
      </aside>
    );
  }

  // Expanded Panel
  return (
    <aside
      aria-label="Get started onboarding checklist"
      className="fixed bottom-5 right-5 z-40 w-84 sm:w-96 rounded-2xl bg-zinc-900/95 border border-zinc-800 p-4 shadow-2xl shadow-black/80 backdrop-blur-md text-zinc-100 animate-in fade-in slide-in-from-bottom-4 duration-200"
    >
      {/* Header */}
      <div className="flex items-center justify-between pb-3 border-b border-zinc-800/80">
        <div className="flex items-center gap-2">
          <div className="w-6 h-6 rounded-lg bg-indigo-600/20 text-indigo-400 flex items-center justify-center">
            <Sparkles className="w-3.5 h-3.5" aria-hidden="true" />
          </div>
          <div>
            <h2 className="text-sm font-bold tracking-tight text-white">Get started</h2>
            <p className="text-[11px] text-zinc-400">
              {completedCount} of {totalCount} steps completed ({percent}%)
            </p>
          </div>
        </div>

        <div className="flex items-center gap-1 text-zinc-400">
          <button
            type="button"
            onClick={() => handleMinimize(true)}
            aria-label="Minimize checklist"
            className="p-1 rounded-md hover:bg-zinc-800 hover:text-white transition"
            title="Minimize"
          >
            <ChevronDown className="w-4 h-4" aria-hidden="true" />
          </button>
          <button
            type="button"
            onClick={handleDismiss}
            aria-label="Dismiss checklist"
            className="p-1 rounded-md hover:bg-zinc-800 hover:text-white transition"
            title="Dismiss for good"
          >
            <X className="w-4 h-4" aria-hidden="true" />
          </button>
        </div>
      </div>

      {/* Progress Bar */}
      <div className="my-3">
        <div className="w-full h-1.5 rounded-full bg-zinc-800 overflow-hidden">
          <div
            className="h-full bg-indigo-500 rounded-full transition-all duration-500"
            style={{ width: `${percent}%` }}
          />
        </div>
      </div>

      {/* Checklist items */}
      <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
        {steps.map((step, index) => {
          const isNext = step.id === nextStep?.id;

          if (step.isDone) {
            return (
              <div
                key={step.id}
                className="flex items-center gap-2.5 p-2 rounded-xl text-xs text-zinc-400 bg-zinc-950/40 border border-transparent"
              >
                <div className="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                  <Check className="w-2.5 h-2.5 stroke-[3]" aria-hidden="true" />
                </div>
                <span className="truncate line-through opacity-70">{step.title}</span>
                <span className="sr-only">(completed)</span>
              </div>
            );
          }

          if (isNext) {
            return (
              <div
                key={step.id}
                className="p-3 rounded-xl bg-indigo-950/20 border border-indigo-500/30 text-xs space-y-2 shadow-sm"
              >
                <div className="flex items-start gap-2.5">
                  <div className="w-5 h-5 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center shrink-0 text-[10px]">
                    {index + 1}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-1">
                      <p className="font-semibold text-white">{step.title}</p>
                      {step.isOptional && (
                        <span className="text-[9px] uppercase tracking-wider text-zinc-400 bg-zinc-800 px-1 py-0.2 rounded">
                          Optional
                        </span>
                      )}
                    </div>
                    <p className="text-[11px] text-zinc-300 mt-0.5 leading-relaxed">
                      {step.description}
                    </p>
                  </div>
                </div>

                <div className="pt-1 pl-7">
                  <Link
                    href={step.href}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-sm"
                  >
                    <span>{step.buttonText}</span>
                    <ArrowRight className="w-3 h-3" aria-hidden="true" />
                  </Link>
                </div>
              </div>
            );
          }

          // Upcoming step
          return (
            <Link
              key={step.id}
              href={step.href}
              className="flex items-center justify-between p-2 rounded-xl text-xs text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60 transition group border border-transparent"
            >
              <div className="flex items-center gap-2.5 truncate">
                <div className="w-4 h-4 rounded-full bg-zinc-800 text-zinc-500 text-[10px] font-mono flex items-center justify-center shrink-0">
                  {index + 1}
                </div>
                <span className="truncate">{step.title}</span>
              </div>
              {step.isOptional ? (
                <span className="text-[9px] text-zinc-500 shrink-0">Optional</span>
              ) : (
                <ArrowRight className="w-3 h-3 text-zinc-600 group-hover:text-zinc-400 transition shrink-0" aria-hidden="true" />
              )}
            </Link>
          );
        })}
      </div>

      {isFinished && (
        <div className="mt-3 p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-center text-xs text-emerald-300">
          🎉 All set! Your newsletter platform is ready to scale.
        </div>
      )}
    </aside>
  );
};

export default GetStartedPanel;
