import React, { useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
  ArrowLeft,
  ArrowRight,
  Send,
  Eye,
  CheckCircle2,
  AlertTriangle,
  Calendar,
  ShieldCheck,
  LayoutTemplate,
  Edit3,
  Mail,
  Image as ImageIcon,
  UploadCloud,
  Loader2,
  Split,
  Save,
} from 'lucide-react';
import { DashboardLayout } from '@/Layouts/DashboardLayout';
import { TemplateSelector } from '@/Components/Campaigns/TemplateSelector';
import { ShortcodeBar } from '@/Components/Campaigns/ShortcodeBar';
import { useCampaignComposer, type CampaignDraftInitial } from '@/hooks/useCampaignComposer';
import type { EmailTemplate, Newsletter, Shortcode } from '@/types';

interface ComposeCampaignProps {
  readonly currentNewsletter: Newsletter | null;
  readonly draft?: CampaignDraftInitial | null;
  readonly templates: readonly EmailTemplate[];
  readonly categories: readonly string[];
  readonly shortcodes: readonly Shortcode[];
}

export default function ComposeCampaign({
  currentNewsletter,
  draft = null,
  templates = [],
  categories = [],
  shortcodes = [],
}: ComposeCampaignProps): React.JSX.Element {
  const { props } = usePage<{ errors?: Record<string, string> }>();
  const serverErrors = props.errors || {};

  const {
    state,
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
  } = useCampaignComposer(templates, draft);

  const hasTemplates = (templates?.length ?? 0) > 0;
  const canProceedFromWrite = state.subject.trim().length > 0 && state.htmlContent.trim().length > 0;

  const [testEmail, setTestEmail] = useState<string>('');
  const [isSendingTest, setIsSendingTest] = useState<boolean>(false);
  const [isAbTestActive, setIsAbTestActive] = useState<boolean>(Boolean(draft?.abTestEnabled));
  const [subjectB, setSubjectB] = useState<string>(draft?.subjectB ?? '');
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);

  const handleSendTest = () => {
    const targetRecipient = testEmail.trim() || currentNewsletter?.fromEmail;
    if (!targetRecipient) return;

    setIsSendingTest(true);
    router.post(
      '/campaigns/test-send',
      {
        subject: state.subject,
        html: state.htmlContent,
        recipient: targetRecipient,
      },
      {
        preserveScroll: true,
        onFinish: () => setIsSendingTest(false),
      }
    );
  };

  const submitCampaign = (saveAsDraft: boolean) => {
    setIsSubmitting(true);
    router.post(
      '/campaigns',
      {
        campaignId: draft?.id || null,
        subject: state.subject,
        subjectB: isAbTestActive ? subjectB : null,
        abTestEnabled: isAbTestActive && subjectB.trim().length > 0,
        previewText: state.previewText,
        html: state.htmlContent,
        audienceLimit: state.audienceLimit ? Number(state.audienceLimit) : null,
        isSpreadSend: state.isSpreadSend,
        scheduledAt: state.scheduledAt || null,
        saveAsDraft,
      },
      {
        onFinish: () => setIsSubmitting(false),
      }
    );
  };

  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const [isUploadingImage, setIsUploadingImage] = useState<boolean>(false);
  const [isDraggingOver, setIsDraggingOver] = useState<boolean>(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const uploadAndInsertImage = async (file: File) => {
    if (!file.type.startsWith('image/')) {
      setUploadError('Only images (PNG, JPEG, GIF, WebP) are supported.');
      setTimeout(() => setUploadError(null), 4000);
      return;
    }

    if (file.size > 4 * 1024 * 1024) {
      setUploadError('Image size exceeds 4 MB limit.');
      setTimeout(() => setUploadError(null), 4000);
      return;
    }

    setIsUploadingImage(true);
    setUploadError(null);

    const formData = new FormData();
    formData.append('image', file);

    try {
      const xsrfToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

      const response = await fetch('/uploads', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        body: formData,
      });

      if (!response.ok) {
        const errorJson = await response.json().catch(() => ({}));
        throw new Error(errorJson?.error?.message || 'Failed to upload image.');
      }

      const data = await response.json();
      const imageUrl = data.fullUrl || data.url;
      const isHtml = state.htmlContent.includes('<') && state.htmlContent.includes('>');
      const altText = data.originalName.replace(/\.[^.]+$/, '');
      const snippet = isHtml
        ? `\n<img src="${imageUrl}" alt="${altText}" style="max-width: 100%; height: auto; border-radius: 8px; display: block; margin: 16px auto;" />\n`
        : `\n\n![${altText}](${imageUrl})\n\n`;

      setHtmlContent((prev) => prev + snippet);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Error uploading image.';
      setUploadError(msg);
      setTimeout(() => setUploadError(null), 4000);
    } finally {
      setIsUploadingImage(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handlePaste = (e: React.ClipboardEvent<HTMLTextAreaElement>) => {
    const items = e.clipboardData.items;
    for (let i = 0; i < items.length; i++) {
      if (items[i].type.startsWith('image/')) {
        const file = items[i].getAsFile();
        if (file) {
          e.preventDefault();
          uploadAndInsertImage(file);
          break;
        }
      }
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDraggingOver(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDraggingOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDraggingOver(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      uploadAndInsertImage(e.dataTransfer.files[0]);
    }
  };

  // Dynamic Pre-Flight Deliverability Checks
  const subjectLen = state.subject.trim().length;
  const isSubjectConcise = subjectLen > 0 && subjectLen <= 120;
  const hasExplicitUnsubTag = /\{\{\s*unsubscribe_url\s*\}\}/i.test(state.htmlContent);
  const hasPostalAddress = Boolean(currentNewsletter?.postalAddress && currentNewsletter.postalAddress.trim().length >= 5);
  const htmlBytes = new Blob([state.htmlContent]).size;
  const htmlKb = htmlBytes / 1024;
  const isClipSafe = htmlKb < 102;

  return (
    <DashboardLayout currentNewsletter={currentNewsletter}>
      <Head title={draft ? 'Edit Campaign' : 'Compose Issue'} />

      {/* Wizard Step Breadcrumb Navigation */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-zinc-800/80">
        <div className="flex items-center gap-3">
          <Link
            href="/campaigns"
            className="p-1.5 rounded-lg border border-zinc-800 text-zinc-400 hover:text-white hover:bg-zinc-900 transition"
          >
            <ArrowLeft className="w-4 h-4" aria-hidden="true" />
          </Link>
          <div>
            <h1 className="text-xl font-bold tracking-tight text-white">
              {draft ? 'Edit Draft Campaign' : 'Create New Campaign'}
            </h1>
            <p className="text-xs text-zinc-400 mt-0.5">
              Draft, customize with shortcodes, review compliance, and schedule delivery.
            </p>
          </div>
        </div>

        {/* Step Tabs */}
        <nav aria-label="Composer Step Tabs" className="flex items-center gap-1.5 p-1 rounded-xl bg-zinc-900 border border-zinc-800">
          {hasTemplates && (
            <button
              type="button"
              onClick={() => setStep('templates')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                state.step === 'templates'
                  ? 'bg-zinc-800 text-white shadow-xs'
                  : 'text-zinc-400 hover:text-white'
              }`}
            >
              <LayoutTemplate className="w-3.5 h-3.5" aria-hidden="true" />
              1. Templates
            </button>
          )}
          <button
            type="button"
            onClick={() => setStep('write')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
              state.step === 'write'
                ? 'bg-zinc-800 text-white shadow-xs'
                : 'text-zinc-400 hover:text-white'
            }`}
          >
            <Edit3 className="w-3.5 h-3.5" aria-hidden="true" />
            {hasTemplates ? '2. Write' : '1. Write'}
          </button>
          <button
            type="button"
            onClick={() => canProceedFromWrite && setStep('review')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
              state.step === 'review'
                ? 'bg-zinc-800 text-white shadow-xs'
                : 'text-zinc-400 hover:text-white'
            }`}
          >
            <Eye className="w-3.5 h-3.5" aria-hidden="true" />
            {hasTemplates ? '3. Review' : '2. Review'}
          </button>
          <button
            type="button"
            onClick={() => canProceedFromWrite && setStep('send')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
              state.step === 'send'
                ? 'bg-zinc-800 text-white shadow-xs'
                : 'text-zinc-400 hover:text-white'
            }`}
          >
            <Send className="w-3.5 h-3.5" aria-hidden="true" />
            {hasTemplates ? '4. Send & Schedule' : '3. Send & Schedule'}
          </button>
        </nav>
      </div>

      {/* Server Validation / Pre-Send Gate Error Banner */}
      {Object.keys(serverErrors).length > 0 && (
        <div
          role="alert"
          className="mt-6 p-4 rounded-xl bg-rose-950/40 border border-rose-500/40 text-xs text-rose-200 flex items-start gap-2.5"
        >
          <AlertTriangle className="w-4 h-4 text-rose-400 shrink-0 mt-0.5" />
          <div className="space-y-1">
            {Object.values(serverErrors).map((errMsg, idx) => (
              <div key={idx}>{errMsg}</div>
            ))}
          </div>
        </div>
      )}

      {/* Main Step Area */}
      <div className="mt-8">
        {/* Step 1: Templates */}
        {state.step === 'templates' && (
          <TemplateSelector
            templates={filteredTemplates}
            categories={categories}
            selectedCategory={state.selectedCategory}
            onSelectCategory={setSelectedCategory}
            onSelectTemplate={applyTemplate}
            onSkipToBlank={() => setStep('write')}
          />
        )}

        {/* Step 2: Write */}
        {state.step === 'write' && (
          <div className="space-y-6 max-w-4xl mx-auto">
            {/* Subject & Preview Input */}
            <div className="space-y-4">
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label htmlFor="subject-input" className="block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                    Subject Line {isAbTestActive && <span className="text-indigo-400 font-bold">(Variant A)</span>}
                  </label>
                  <button
                    type="button"
                    onClick={() => setIsAbTestActive(!isAbTestActive)}
                    className="text-xs font-semibold text-indigo-400 hover:text-indigo-300 flex items-center gap-1 transition-colors"
                  >
                    <Split className="h-3 w-3" />
                    <span>{isAbTestActive ? 'Disable A/B Test' : '+ A/B Test Subject'}</span>
                  </button>
                </div>
                <input
                  id="subject-input"
                  type="text"
                  required
                  value={state.subject}
                  onChange={(e) => setSubject(e.target.value)}
                  placeholder="e.g., Issue #42: Scalable Redis Token Buckets"
                  className="w-full px-4 py-2.5 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition font-medium"
                />
              </div>

              {isAbTestActive && (
                <div className="rounded-xl border border-indigo-900/60 bg-indigo-950/20 p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <label htmlFor="subject-b-input" className="block text-xs font-semibold uppercase tracking-wider text-indigo-300">
                      Variant B Subject Line
                    </label>
                    <span className="text-[10px] text-indigo-400 font-mono uppercase bg-indigo-950/80 px-2 py-0.5 rounded border border-indigo-800/40">
                      50% / 50% Pilot Split
                    </span>
                  </div>
                  <input
                    id="subject-b-input"
                    type="text"
                    value={subjectB}
                    onChange={(e) => setSubjectB(e.target.value)}
                    placeholder="e.g., Alternative high-converting subject line..."
                    className="w-full px-4 py-2 rounded-xl bg-zinc-900 border border-indigo-800/80 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-400 transition font-medium text-sm"
                  />
                  <p className="text-[11px] text-zinc-400 leading-relaxed">
                    Variant A and Variant B will be split evenly across the initial pilot slice. The subject line with higher opens will automatically be dispatched to the remaining audience.
                  </p>
                </div>
              )}

              <div>
                <label htmlFor="preview-input" className="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">
                  Preview Text (Shown in inbox snippet)
                </label>
                <input
                  id="preview-input"
                  type="text"
                  value={state.previewText}
                  onChange={(e) => setPreviewText(e.target.value)}
                  placeholder="A quick summary of what this issue covers..."
                  className="w-full px-4 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition text-sm"
                />
              </div>
            </div>

            {/* Editor Action Toolbar (Shortcodes & Image Upload) */}
            <div className="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/80 dark:bg-zinc-900/60 p-3 shadow-xs">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <ShortcodeBar shortcodes={shortcodes} onInsert={insertShortcode} />

                {/* Prominent Media Upload Area */}
                <div className="flex items-center gap-2 shrink-0 border-t md:border-t-0 md:border-l border-slate-200 dark:border-zinc-800 pt-2.5 md:pt-0 md:pl-3">
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/gif,image/webp"
                    className="hidden"
                    onChange={(e) => {
                      if (e.target.files && e.target.files[0]) {
                        uploadAndInsertImage(e.target.files[0]);
                      }
                    }}
                  />
                  <button
                    type="button"
                    disabled={isUploadingImage}
                    onClick={() => fileInputRef.current?.click()}
                    className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-xs hover:shadow-indigo-500/20 active:scale-[0.98] transition disabled:opacity-50 whitespace-nowrap cursor-pointer"
                    title="Upload an image (PNG, JPEG, GIF, WebP up to 4MB) or paste directly into the editor"
                  >
                    {isUploadingImage ? (
                      <>
                        <Loader2 className="w-3.5 h-3.5 animate-spin text-white" aria-hidden="true" />
                        <span>Uploading...</span>
                      </>
                    ) : (
                      <>
                        <ImageIcon className="w-3.5 h-3.5 text-white" aria-hidden="true" />
                        <span>Upload Image</span>
                      </>
                    )}
                  </button>
                </div>
              </div>
            </div>

            {uploadError && (
              <div className="p-3 rounded-lg bg-red-950/40 border border-red-500/40 text-xs text-red-300 flex items-center justify-between">
                <span>{uploadError}</span>
                <button type="button" onClick={() => setUploadError(null)} className="text-red-400 hover:text-white">&times;</button>
              </div>
            )}

            {/* HTML / Rich Body Content with Drag & Drop & Paste Support */}
            <div
              className="relative"
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
            >
              <div className="flex items-center justify-between mb-2">
                <label htmlFor="body-input" className="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300">
                  Email Content (HTML / Markdown)
                </label>
                <div className="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-zinc-400">
                  <span className="hidden sm:inline">Drag &amp; drop or paste (Ctrl+V) images directly</span>
                  <span className="hidden sm:inline">&middot;</span>
                  <span className="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-mono text-[11px] border border-slate-200 dark:border-zinc-700">
                    Gmail 102KB Safe
                  </span>
                </div>
              </div>

              <div className="relative">
                <textarea
                  id="body-input"
                  rows={15}
                  value={state.htmlContent}
                  onChange={(e) => setHtmlContent(e.target.value)}
                  onPaste={handlePaste}
                  placeholder="Write your email body here or select a prebuilt template..."
                  className={`w-full p-4 rounded-2xl bg-white dark:bg-[#12141a] border text-slate-900 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-600 focus:outline-none font-mono text-sm leading-relaxed transition shadow-2xs ${
                    isDraggingOver
                      ? 'border-indigo-500 ring-2 ring-indigo-500/30 bg-indigo-50/20'
                      : 'border-slate-300 dark:border-zinc-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20'
                  }`}
                />

                {isDraggingOver && (
                  <div className="absolute inset-0 rounded-2xl bg-indigo-950/70 border-2 border-dashed border-indigo-400 flex flex-col items-center justify-center pointer-events-none text-indigo-200 space-y-2 backdrop-blur-xs">
                    <UploadCloud className="w-8 h-8 text-indigo-400 animate-bounce" aria-hidden="true" />
                    <span className="text-sm font-semibold">Drop image to upload &amp; embed</span>
                    <span className="text-xs text-indigo-300/80">Supports PNG, JPEG, GIF, WebP up to 4MB</span>
                  </div>
                )}
              </div>
            </div>

            {/* Proceed Bar */}
            <div className="flex items-center justify-between pt-5 border-t border-slate-200 dark:border-zinc-800">
              <button
                type="button"
                onClick={() => setStep('templates')}
                className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white transition py-2"
              >
                <ArrowLeft className="w-3.5 h-3.5" />
                <span>Back to Templates</span>
              </button>

              <div className="flex items-center gap-3">
                <button
                  type="button"
                  disabled={!canProceedFromWrite || isSubmitting}
                  onClick={() => submitCampaign(true)}
                  className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-700 transition shadow-xs disabled:opacity-40"
                >
                  <Save className="w-3.5 h-3.5 text-slate-500 dark:text-zinc-400" aria-hidden="true" />
                  <span>Save Draft</span>
                </button>

                <button
                  type="button"
                  disabled={!canProceedFromWrite}
                  onClick={() => setStep('review')}
                  className={`inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition shadow-sm ${
                    canProceedFromWrite
                      ? 'bg-indigo-600 hover:bg-indigo-500 shadow-indigo-500/20 active:scale-[0.98]'
                      : 'bg-slate-300 dark:bg-zinc-800 text-slate-500 dark:text-zinc-500 cursor-not-allowed'
                  }`}
                >
                  <span>Proceed to Review</span>
                  <ArrowRight className="w-4 h-4 text-white" aria-hidden="true" />
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Step 3: Review */}
        {state.step === 'review' && (
          <div className="max-w-2xl mx-auto space-y-6">
            <div className="rounded-2xl border border-zinc-800 bg-zinc-900/40 p-6 space-y-4">
              <h3 className="text-base font-bold text-white flex items-center gap-2">
                <ShieldCheck className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                Pre-Flight Deliverability &amp; Compliance Checks
              </h3>

              <div className="space-y-3 pt-2 text-xs">
                <div className="flex items-center justify-between p-3 rounded-lg bg-zinc-900 border border-zinc-800/80">
                  <span className="text-zinc-300">Subject line present &amp; concise ({subjectLen} chars)</span>
                  {isSubjectConcise ? (
                    <span className="font-semibold text-emerald-400 flex items-center gap-1">
                      <CheckCircle2 className="w-4 h-4" /> Passed
                    </span>
                  ) : (
                    <span className="font-semibold text-amber-400 flex items-center gap-1">
                      <AlertTriangle className="w-4 h-4" /> {subjectLen === 0 ? 'Missing' : 'Long (> 120 chars)'}
                    </span>
                  )}
                </div>

                <div className="flex items-center justify-between p-3 rounded-lg bg-zinc-900 border border-zinc-800/80">
                  <span className="text-zinc-300">RFC 8058 One-Click Unsubscribe + Body Link</span>
                  <span className="font-semibold text-emerald-400 flex items-center gap-1">
                    <CheckCircle2 className="w-4 h-4" />
                    {hasExplicitUnsubTag ? 'Custom Tag Present' : 'Auto-Injected in Footer'}
                  </span>
                </div>

                <div className="flex items-center justify-between p-3 rounded-lg bg-zinc-900 border border-zinc-800/80">
                  <span className="text-zinc-300">CAN-SPAM Physical Mailing Address</span>
                  {hasPostalAddress ? (
                    <span className="font-semibold text-emerald-400 flex items-center gap-1 truncate max-w-[240px]">
                      <CheckCircle2 className="w-4 h-4 shrink-0" /> {currentNewsletter?.postalAddress}
                    </span>
                  ) : (
                    <Link href="/settings" className="font-semibold text-amber-400 flex items-center gap-1 underline">
                      <AlertTriangle className="w-4 h-4" /> Set in Settings
                    </Link>
                  )}
                </div>

                <div className="flex items-center justify-between p-3 rounded-lg bg-zinc-900 border border-zinc-800/80">
                  <span className="text-zinc-300">Email size &lt; 102 KB (Gmail clipping threshold)</span>
                  {isClipSafe ? (
                    <span className="font-semibold text-emerald-400 flex items-center gap-1">
                      <CheckCircle2 className="w-4 h-4" /> {htmlKb.toFixed(1)} KB
                    </span>
                  ) : (
                    <span className="font-semibold text-amber-400 flex items-center gap-1">
                      <AlertTriangle className="w-4 h-4" /> {htmlKb.toFixed(1)} KB (May Clip in Gmail)
                    </span>
                  )}
                </div>
              </div>
            </div>

            {/* Send Test Preview to Inbox */}
            <div className="rounded-2xl border border-zinc-800 bg-zinc-900/40 p-6 space-y-3">
              <div>
                <h3 className="text-base font-bold text-white flex items-center gap-2">
                  <Mail className="w-5 h-5 text-indigo-400" aria-hidden="true" />
                  Send Test Preview to Inbox
                </h3>
                <p className="text-xs text-zinc-400 mt-1">
                  Preview typography, links, and rendering in your personal email client before broadcasting.
                </p>
              </div>

              <div className="flex flex-col sm:flex-row items-center gap-2.5 pt-1">
                <input
                  type="email"
                  value={testEmail}
                  onChange={(e) => setTestEmail(e.target.value)}
                  placeholder={currentNewsletter?.fromEmail || 'your.email@example.com'}
                  className="w-full text-xs px-3.5 py-2.5 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition"
                />
                <button
                  type="button"
                  disabled={isSendingTest || (!testEmail.trim() && !currentNewsletter?.fromEmail)}
                  onClick={handleSendTest}
                  className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-semibold bg-zinc-800 hover:bg-zinc-700 text-white transition disabled:opacity-50 shrink-0"
                >
                  <Send className={`w-3.5 h-3.5 ${isSendingTest ? 'animate-pulse' : ''}`} />
                  {isSendingTest ? 'Sending Test...' : 'Send Test Email'}
                </button>
              </div>
            </div>

            <div className="flex items-center justify-between pt-4">
              <button
                type="button"
                onClick={() => setStep('write')}
                className="text-xs font-semibold text-zinc-400 hover:text-white transition"
              >
                &larr; Back to Editor
              </button>

              <button
                type="button"
                onClick={() => setStep('send')}
                className="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm shadow-indigo-600/20"
              >
                Confirm &amp; Next: Scheduling
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </button>
            </div>
          </div>
        )}

        {/* Step 4: Send & Schedule */}
        {state.step === 'send' && (
          <div className="max-w-xl mx-auto rounded-2xl border border-zinc-800 bg-zinc-900/40 p-8 space-y-6">
            <div>
              <h3 className="text-lg font-bold text-white">Audience &amp; Dispatch Strategy</h3>
              <p className="text-xs text-zinc-400 mt-1">
                Configure delivery rates, warm-up slices, and scheduling options.
              </p>
            </div>

            <div className="space-y-4">
              {/* Audience Cap */}
              <div>
                <label htmlFor="audience-cap" className="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">
                  Audience Limit (Optional)
                </label>
                <input
                  id="audience-cap"
                  type="number"
                  min={1}
                  value={state.audienceLimit}
                  onChange={(e) => setAudienceLimit(e.target.value)}
                  placeholder={`All ${currentNewsletter?.subscriberCount || 'subscribers'}`}
                  className="w-full px-4 py-2.5 rounded-xl bg-zinc-900 border border-zinc-800 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 transition text-sm"
                />
              </div>

              {/* Warm-up Checkbox */}
              <div className="flex items-start gap-3 p-4 rounded-xl bg-zinc-900 border border-zinc-800">
                <input
                  id="spread-checkbox"
                  type="checkbox"
                  checked={state.isSpreadSend}
                  onChange={(e) => setIsSpreadSend(e.target.checked)}
                  className="w-4 h-4 text-indigo-600 rounded bg-zinc-800 border-zinc-700 focus:ring-indigo-500 mt-0.5"
                />
                <div>
                  <label htmlFor="spread-checkbox" className="text-sm font-semibold text-white cursor-pointer">
                    Enable 5% Warm-up Pilot Slice First
                  </label>
                  <p className="text-xs text-zinc-400 mt-0.5 leading-relaxed">
                    Dispatches to the top 5% most engaged readers first. Evaluates bounce &amp; complaint metrics before automatically releasing the remaining audience.
                  </p>
                </div>
              </div>

              {/* Schedule Date */}
              <div>
                <label htmlFor="schedule-date" className="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">
                  Schedule for Later (Leave blank to send now)
                </label>
                <div className="relative">
                  <Calendar className="w-4 h-4 text-zinc-500 absolute left-3.5 top-3" aria-hidden="true" />
                  <input
                    id="schedule-date"
                    type="datetime-local"
                    value={state.scheduledAt}
                    onChange={(e) => setScheduledAt(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 rounded-xl bg-zinc-900 border border-zinc-800 text-white focus:outline-none focus:border-indigo-500 transition text-sm"
                  />
                </div>
              </div>
            </div>

            {/* Final Action Buttons */}
            <div className="pt-4 border-t border-zinc-800 flex flex-wrap items-center justify-between gap-3">
              <button
                type="button"
                onClick={() => setStep('review')}
                className="text-xs font-semibold text-zinc-400 hover:text-white transition"
              >
                &larr; Back to Review
              </button>

              <div className="flex items-center gap-2.5">
                <button
                  type="button"
                  disabled={isSubmitting}
                  onClick={() => submitCampaign(true)}
                  className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-xs font-semibold bg-zinc-800 hover:bg-zinc-700 text-zinc-200 transition disabled:opacity-50"
                >
                  <Save className="w-4 h-4" aria-hidden="true" />
                  Save as Draft
                </button>

                <button
                  type="button"
                  disabled={isSubmitting}
                  onClick={() => submitCampaign(false)}
                  className="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm shadow-emerald-600/25 disabled:opacity-50"
                >
                  <Send className="w-4 h-4" aria-hidden="true" />
                  {state.scheduledAt ? 'Schedule Campaign' : 'Send Campaign Now'}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
