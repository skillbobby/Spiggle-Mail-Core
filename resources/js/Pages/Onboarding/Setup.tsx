import React from 'react';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Mail } from 'lucide-react';
import { useOnboardingWizard } from '@/hooks/useOnboardingWizard';
import { LiveNewsletterPreview } from '@/Components/Onboarding/LiveNewsletterPreview';

interface StepDescriptor {
  readonly title: string;
  readonly description: string;
  readonly field: 'name' | 'fromName' | 'fromEmail' | 'postalAddress';
  readonly placeholder: string;
}

const STEPS: readonly StepDescriptor[] = [
  {
    title: 'What is your newsletter called?',
    description: 'This is the publication name your subscribers see on your archive website and in their inbox.',
    field: 'name',
    placeholder: 'e.g., The Engineering Dispatch',
  },
  {
    title: 'Who is sending these emails?',
    description: 'Your personal or brand name displayed in the reader’s email client.',
    field: 'fromName',
    placeholder: 'e.g., Alex from Spiggle',
  },
  {
    title: 'What from address will you send from?',
    description: 'You can connect a custom sending domain right after this quick setup.',
    field: 'fromEmail',
    placeholder: 'alex@yourdomain.com',
  },
  {
    title: 'What is your physical mailing address?',
    description: 'Required by global anti-spam laws (CAN-SPAM, GDPR) to be included in all newsletter footers.',
    field: 'postalAddress',
    placeholder: '100 Main St, Suite 400, Austin, TX 78701',
  },
];

export default function OnboardingSetup(): React.JSX.Element {
  const {
    step,
    totalSteps,
    values,
    isFirstStep,
    isLastStep,
    canProceed,
    handleFieldChange,
    nextStep,
    previousStep,
  } = useOnboardingWizard();

  const currentStep = STEPS[step];

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!canProceed) return;

    if (!isLastStep) {
      nextStep();
      return;
    }

    // Submit final wizard data to Laravel backend
    router.post('/onboarding', values);
  };

  return (
    <div className="min-h-screen bg-zinc-950 text-zinc-100 flex flex-col lg:flex-row">
      <Head title="Newsletter Setup Wizard" />

      {/* Main Wizard Form Area */}
      <main className="flex-1 flex flex-col justify-between p-8 sm:p-14 max-w-2xl mx-auto w-full">
        {/* Header & Step Dots */}
        <div>
          <div className="flex items-center justify-between pb-8 border-b border-zinc-800/80">
            <div className="flex items-center gap-2">
              <div className="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">
                <Mail className="w-4 h-4" aria-hidden="true" />
              </div>
              <span className="font-semibold text-sm tracking-tight">SpiggleMail Onboarding</span>
            </div>
            <span className="text-xs font-mono text-zinc-400">
              Step {step + 1} of {totalSteps}
            </span>
          </div>

          {/* Progress Indicator */}
          <div className="flex gap-2 mt-4" aria-hidden="true">
            {STEPS.map((s, index) => (
              <div
                key={s.field}
                className={`h-1 flex-1 rounded-full transition-all duration-300 ${
                  index <= step ? 'bg-indigo-500' : 'bg-zinc-800'
                }`}
              />
            ))}
          </div>

          {/* Current Step Content */}
          <form onSubmit={handleSubmit} className="mt-14">
            <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-white leading-tight">
              {currentStep.title}
            </h1>
            <p className="mt-3 text-base text-zinc-400 leading-relaxed">
              {currentStep.description}
            </p>

            <div className="mt-8">
              <label htmlFor="wizard-input" className="sr-only">
                {currentStep.title}
              </label>
              <input
                id="wizard-input"
                type="text"
                autoFocus
                required
                value={values[currentStep.field]}
                onChange={(e) => handleFieldChange(currentStep.field, e.target.value)}
                placeholder={currentStep.placeholder}
                className="w-full text-lg px-4 py-3.5 rounded-xl bg-zinc-900 border border-zinc-700/80 text-white placeholder-zinc-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
              />
            </div>

            {/* Navigation Buttons */}
            <div className="mt-10 flex items-center justify-between">
              <button
                type="button"
                onClick={previousStep}
                disabled={isFirstStep}
                className={`inline-flex items-center gap-2 text-sm font-medium px-4 py-2.5 rounded-lg border border-zinc-800 transition ${
                  isFirstStep
                    ? 'opacity-0 pointer-events-none'
                    : 'text-zinc-300 hover:text-white hover:bg-zinc-900'
                }`}
              >
                <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                Back
              </button>

              <button
                type="submit"
                disabled={!canProceed}
                className={`inline-flex items-center gap-2 text-sm font-semibold px-6 py-2.5 rounded-lg text-white transition shadow-sm ${
                  canProceed
                    ? 'bg-indigo-600 hover:bg-indigo-500 shadow-indigo-600/20'
                    : 'bg-zinc-800 text-zinc-500 cursor-not-allowed'
                }`}
              >
                {isLastStep ? (
                  <>
                    Complete Setup
                    <Check className="w-4 h-4" aria-hidden="true" />
                  </>
                ) : (
                  <>
                    Continue
                    <ArrowRight className="w-4 h-4" aria-hidden="true" />
                  </>
                )}
              </button>
            </div>
          </form>
        </div>

        {/* Footer note */}
        <p className="text-xs text-zinc-500 mt-12">
          You can change all of these settings at any time in your newsletter settings panel.
        </p>
      </main>

      {/* Live Sidecar Preview */}
      <LiveNewsletterPreview values={values} />
    </div>
  );
}
