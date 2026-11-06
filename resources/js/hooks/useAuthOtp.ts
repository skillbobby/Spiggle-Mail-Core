import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';

export interface UseAuthOtpResult {
    readonly email: string;
    readonly setEmail: (email: string) => void;
    readonly step: 'email' | 'code';
    readonly setStep: (step: 'email' | 'code') => void;
    readonly code: string;
    readonly setCode: (code: string) => void;
    readonly isSending: boolean;
    readonly isVerifying: boolean;
    readonly error: string | null;
    readonly devCode: string | null;
    readonly handleSendCode: (e: React.FormEvent) => void;
    readonly handleVerifyCode: (e: React.FormEvent) => void;
    readonly handleUseAnotherEmail: () => void;
    readonly handleResendCode: () => void;
    readonly quickFillAndSend: (targetEmail: string) => void;
}

export function useAuthOtp(
    initialDevCode: string | null = null,
    initialEmail: string = ''
): UseAuthOtpResult {
    const [email, setEmail] = useState(initialEmail);
    const [step, setStep] = useState<'email' | 'code'>(initialDevCode ? 'code' : 'email');
    const [code, setCode] = useState(initialDevCode ?? '');
    const [isSending, setIsSending] = useState(false);
    const [isVerifying, setIsVerifying] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [devCode, setDevCode] = useState<string | null>(initialDevCode);

    useEffect(() => {
        if (initialDevCode) {
            setDevCode(initialDevCode);
            setCode(initialDevCode);
            setStep('code');
        }
    }, [initialDevCode]);

    useEffect(() => {
        if (initialEmail && !email) {
            setEmail(initialEmail);
        }
    }, [initialEmail]);

    const sendOtpRequest = (targetEmail: string) => {
        if (!targetEmail || isSending) return;

        setIsSending(true);
        setError(null);

        router.post('/signin/send-otp', { email: targetEmail }, {
            preserveScroll: true,
            onSuccess: (page) => {
                setIsSending(false);
                setStep('code');
                const pageProps = page.props as {
                    devCode?: string;
                    initialDevCode?: string;
                    email?: string;
                    initialEmail?: string;
                };
                const activeCode = pageProps.devCode || pageProps.initialDevCode;
                if (activeCode) {
                    setDevCode(activeCode);
                    setCode(activeCode);
                }
                const activeEmail = pageProps.email || pageProps.initialEmail;
                if (activeEmail) {
                    setEmail(activeEmail);
                }
            },
            onError: (errs) => {
                setIsSending(false);
                setError(errs.email || errs.error || 'Failed to send verification code.');
            },
        });
    };

    const handleSendCode = (e: React.FormEvent) => {
        e.preventDefault();
        sendOtpRequest(email);
    };

    const quickFillAndSend = (targetEmail: string) => {
        setEmail(targetEmail);
        sendOtpRequest(targetEmail);
    };

    const handleVerifyCode = (e: React.FormEvent) => {
        e.preventDefault();
        if (!code || isVerifying) return;

        setIsVerifying(true);
        setError(null);

        router.post('/signin/verify-otp', { email, code }, {
            preserveScroll: true,
            onFinish: () => setIsVerifying(false),
            onError: (errs) => {
                setError(errs.code || errs.error || 'Invalid or expired code.');
            },
        });
    };

    const handleUseAnotherEmail = () => {
        setStep('email');
        setCode('');
        setDevCode(null);
        setError(null);
    };

    const handleResendCode = () => {
        sendOtpRequest(email);
    };

    return {
        email,
        setEmail,
        step,
        setStep,
        code,
        setCode,
        isSending,
        isVerifying,
        error,
        devCode,
        handleSendCode,
        handleVerifyCode,
        handleUseAnotherEmail,
        handleResendCode,
        quickFillAndSend,
    };
}
