import { useState, useCallback, useMemo } from 'react';

export interface OnboardingFormValues {
  readonly name: string;
  readonly description: string;
  readonly fromName: string;
  readonly fromEmail: string;
  readonly postalAddress: string;
}

export interface UseOnboardingWizardResult {
  readonly step: number;
  readonly totalSteps: number;
  readonly values: OnboardingFormValues;
  readonly isFirstStep: boolean;
  readonly isLastStep: boolean;
  readonly canProceed: boolean;
  readonly handleFieldChange: (field: keyof OnboardingFormValues, value: string) => void;
  readonly nextStep: () => void;
  readonly previousStep: () => void;
}

const TOTAL_STEPS = 4;

export function useOnboardingWizard(initialFromName: string = ''): UseOnboardingWizardResult {
  const [step, setStep] = useState<number>(0);
  const [values, setValues] = useState<OnboardingFormValues>({
    name: '',
    description: '',
    fromName: initialFromName,
    fromEmail: '',
    postalAddress: '',
  });

  const handleFieldChange = useCallback((field: keyof OnboardingFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  }, []);

  const nextStep = useCallback(() => {
    setStep((prev) => Math.min(prev + 1, TOTAL_STEPS - 1));
  }, []);

  const previousStep = useCallback(() => {
    setStep((prev) => Math.max(prev - 1, 0));
  }, []);

  const canProceed = useMemo(() => {
    switch (step) {
      case 0:
        return values.name.trim().length > 0;
      case 1:
        return values.fromName.trim().length > 0;
      case 2:
        return values.fromEmail.includes('@');
      case 3:
        return values.postalAddress.trim().length > 5;
      default:
        return false;
    }
  }, [step, values]);

  return {
    step,
    totalSteps: TOTAL_STEPS,
    values,
    isFirstStep: step === 0,
    isLastStep: step === TOTAL_STEPS - 1,
    canProceed,
    handleFieldChange,
    nextStep,
    previousStep,
  };
}
