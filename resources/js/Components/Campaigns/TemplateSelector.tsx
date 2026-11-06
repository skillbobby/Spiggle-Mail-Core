import React from 'react';
import { LayoutTemplate, Sparkles, ArrowRight } from 'lucide-react';
import type { EmailTemplate } from '@/types';

interface TemplateSelectorProps {
  readonly templates: readonly EmailTemplate[];
  readonly categories: readonly string[];
  readonly selectedCategory: string;
  readonly onSelectCategory: (category: string) => void;
  readonly onSelectTemplate: (template: EmailTemplate) => void;
  readonly onSkipToBlank: () => void;
}

export const TemplateSelector: React.FC<TemplateSelectorProps> = ({
  templates,
  categories,
  selectedCategory,
  onSelectCategory,
  onSelectTemplate,
  onSkipToBlank,
}) => {
  return (
    <section aria-label="Template Library" className="space-y-6">
      {/* Category Pills & Skip Action */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0">
          {categories.map((cat) => (
            <button
              key={cat}
              type="button"
              onClick={() => onSelectCategory(cat)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                selectedCategory === cat
                  ? 'bg-indigo-600 text-white shadow-xs'
                  : 'bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white hover:bg-zinc-800'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>

        <button
          type="button"
          onClick={onSkipToBlank}
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-400 hover:text-white transition"
        >
          Start with blank issue
          <ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />
        </button>
      </div>

      {/* Grid of Templates */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        {templates.map((tpl) => (
          <article
            key={tpl.id}
            onClick={() => onSelectTemplate(tpl)}
            className="group cursor-pointer rounded-2xl bg-zinc-900/40 border border-zinc-800/80 hover:border-indigo-500/60 p-5 flex flex-col justify-between transition-all hover:shadow-xl hover:shadow-indigo-500/5 text-left"
          >
            <div>
              <div className="flex items-center justify-between text-zinc-500 mb-3">
                <span className="text-[11px] font-semibold uppercase tracking-wider text-indigo-400">
                  {tpl.category}
                </span>
                <LayoutTemplate className="w-4 h-4 group-hover:text-indigo-400 transition" aria-hidden="true" />
              </div>
              <h3 className="font-semibold text-white text-base group-hover:text-indigo-300 transition">
                {tpl.title}
              </h3>
              <p className="mt-2 text-xs text-zinc-400 line-clamp-2">
                Default subject: &ldquo;{tpl.subjectDefault}&rdquo;
              </p>
            </div>

            <div className="mt-6 pt-4 border-t border-zinc-800/60 flex items-center justify-between text-xs font-medium text-zinc-400 group-hover:text-white">
              <span className="inline-flex items-center gap-1">
                <Sparkles className="w-3.5 h-3.5 text-amber-400" aria-hidden="true" />
                Prebuilt Shortcodes
              </span>
              <span>Use template &rarr;</span>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
};
