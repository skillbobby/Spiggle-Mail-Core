import { useState, useCallback, useMemo } from 'react';
import type { EmailTemplate } from '@/types';

export interface CampaignDraftInitial {
  readonly id: string;
  readonly subject: string;
  readonly subjectB?: string;
  readonly abTestEnabled?: boolean;
  readonly previewText?: string;
  readonly html: string;
  readonly audienceLimit?: string;
  readonly isSpreadSend?: boolean;
  readonly scheduledAt?: string;
}

export interface CampaignComposerState {
  readonly step: 'templates' | 'write' | 'review' | 'send';
  readonly selectedCategory: string;
  readonly subject: string;
  readonly previewText: string;
  readonly htmlContent: string;
  readonly audienceLimit: string;
  readonly isSpreadSend: boolean;
  readonly scheduledAt: string;
}

export interface UseCampaignComposerResult {
  readonly state: CampaignComposerState;
  readonly setStep: (step: CampaignComposerState['step']) => void;
  readonly setSelectedCategory: (category: string) => void;
  readonly setSubject: (subject: string) => void;
  readonly setPreviewText: (previewText: string) => void;
  readonly setHtmlContent: (html: string) => void;
  readonly setAudienceLimit: (limit: string) => void;
  readonly setIsSpreadSend: (spread: boolean) => void;
  readonly setScheduledAt: (date: string) => void;
  readonly applyTemplate: (template: EmailTemplate) => void;
  readonly insertShortcode: (tag: string) => void;
  readonly filteredTemplates: readonly EmailTemplate[];
}

export function useCampaignComposer(
  allTemplates: readonly EmailTemplate[],
  initialDraft?: CampaignDraftInitial | null
): UseCampaignComposerResult {
  const [step, setStep] = useState<CampaignComposerState['step']>(
    initialDraft ? 'write' : 'templates'
  );
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [subject, setSubject] = useState<string>(initialDraft?.subject ?? '');
  const [previewText, setPreviewText] = useState<string>(initialDraft?.previewText ?? '');
  const [htmlContent, setHtmlContent] = useState<string>(initialDraft?.html ?? '');
  const [audienceLimit, setAudienceLimit] = useState<string>(initialDraft?.audienceLimit ?? '');
  const [isSpreadSend, setIsSpreadSend] = useState<boolean>(initialDraft?.isSpreadSend ?? true);
  const [scheduledAt, setScheduledAt] = useState<string>(initialDraft?.scheduledAt ?? '');

  const filteredTemplates = useMemo(() => {
    if (selectedCategory === 'All') return allTemplates;
    return allTemplates.filter((t) => t.category === selectedCategory);
  }, [allTemplates, selectedCategory]);

  const applyTemplate = useCallback((template: EmailTemplate) => {
    setSubject(template.subjectDefault);
    setPreviewText(template.previewDefault || '');
    setHtmlContent(template.htmlContent);
    setStep('write');
  }, []);

  const insertShortcode = useCallback((tag: string) => {
    setHtmlContent((prev) => `${prev} ${tag}`);
  }, []);

  return {
    state: {
      step,
      selectedCategory,
      subject,
      previewText,
      htmlContent,
      audienceLimit,
      isSpreadSend,
      scheduledAt,
    },
    setStep,
    setSelectedCategory,
    setSubject,
    setPreviewText,
    setHtmlContent,
    setAudienceLimit,
    setIsSpreadSend,
    setScheduledAt,
    applyTemplate,
    insertShortcode,
    filteredTemplates,
  };
}
