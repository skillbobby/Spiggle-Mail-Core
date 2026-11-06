import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';

export interface UseCampaignReportOptions {
  readonly campaignId: string;
}

export interface UseCampaignReportReturn {
  readonly isProcessing: boolean;
  readonly isCopied: boolean;
  readonly pauseCampaign: () => void;
  readonly resumeCampaign: () => void;
  readonly duplicateCampaign: () => void;
  readonly togglePin: () => void;
  readonly copyArchiveUrl: (url: string) => Promise<void>;
}

export function useCampaignReport({ campaignId }: UseCampaignReportOptions): UseCampaignReportReturn {
  const [isProcessing, setIsProcessing] = useState(false);
  const [isCopied, setIsCopied] = useState(false);

  const pauseCampaign = useCallback(() => {
    setIsProcessing(true);
    router.post(
      `/campaigns/${campaignId}/pause`,
      {},
      {
        onFinish: () => setIsProcessing(false),
      }
    );
  }, [campaignId]);

  const resumeCampaign = useCallback(() => {
    setIsProcessing(true);
    router.post(
      `/campaigns/${campaignId}/resume`,
      {},
      {
        onFinish: () => setIsProcessing(false),
      }
    );
  }, [campaignId]);

  const duplicateCampaign = useCallback(() => {
    setIsProcessing(true);
    router.post(
      `/campaigns/${campaignId}/duplicate`,
      {},
      {
        onFinish: () => setIsProcessing(false),
      }
    );
  }, [campaignId]);

  const togglePin = useCallback(() => {
    setIsProcessing(true);
    router.post(
      `/campaigns/${campaignId}/toggle-pin`,
      {},
      {
        onFinish: () => setIsProcessing(false),
      }
    );
  }, [campaignId]);

  const copyArchiveUrl = useCallback(async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      setIsCopied(true);
      setTimeout(() => setIsCopied(false), 2000);
    } catch {
      // Fallback
      const textArea = document.createElement('textarea');
      textArea.value = url;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      setIsCopied(true);
      setTimeout(() => setIsCopied(false), 2000);
    }
  }, []);

  return {
    isProcessing,
    isCopied,
    pauseCampaign,
    resumeCampaign,
    duplicateCampaign,
    togglePin,
    copyArchiveUrl,
  };
}
