import React from 'react';
import { Tag } from 'lucide-react';
import type { Shortcode } from '@/types';

interface ShortcodeBarProps {
  readonly shortcodes: readonly Shortcode[];
  readonly onInsert: (tag: string) => void;
}

export const ShortcodeBar: React.FC<ShortcodeBarProps> = ({ shortcodes, onInsert }) => {
  return (
    <div className="flex items-center gap-2 flex-wrap min-w-0">
      <div className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 pr-1 shrink-0">
        <Tag className="w-3.5 h-3.5 text-indigo-500" aria-hidden="true" />
        <span>Personalize:</span>
      </div>
      {shortcodes.map((item) => (
        <button
          key={item.tag}
          type="button"
          onClick={() => onInsert(item.tag)}
          title={`Insert ${item.tag} (e.g. ${item.example})`}
          className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-mono font-medium rounded-lg bg-white dark:bg-zinc-800 text-slate-800 dark:text-zinc-200 border border-slate-200 dark:border-zinc-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-300 shadow-2xs hover:shadow-xs transition active:scale-[0.98] cursor-pointer"
        >
          <span>{item.tag}</span>
        </button>
      ))}
    </div>
  );
};
