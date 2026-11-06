import React from 'react';
import { Tag } from 'lucide-react';
import type { Shortcode } from '@/types';

interface ShortcodeBarProps {
  readonly shortcodes: readonly Shortcode[];
  readonly onInsert: (tag: string) => void;
}

export const ShortcodeBar: React.FC<ShortcodeBarProps> = ({ shortcodes, onInsert }) => {
  return (
    <div className="flex items-center gap-2 flex-wrap p-2.5 rounded-xl bg-zinc-900/60 border border-zinc-800">
      <div className="flex items-center gap-1.5 text-xs font-semibold text-zinc-400 pl-1 mr-1 shrink-0">
        <Tag className="w-3.5 h-3.5 text-indigo-400" aria-hidden="true" />
        <span>Insert Shortcode:</span>
      </div>
      {shortcodes.map((item) => (
        <button
          key={item.tag}
          type="button"
          onClick={() => onInsert(item.tag)}
          title={`Example: ${item.example}`}
          className="px-2.5 py-1 text-xs font-mono rounded-md bg-zinc-800/80 hover:bg-indigo-600 hover:text-white text-zinc-300 border border-zinc-700/60 transition"
        >
          {item.tag}
        </button>
      ))}
    </div>
  );
};
