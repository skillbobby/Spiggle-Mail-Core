import { useState } from 'react';
import { router } from '@inertiajs/react';

export interface SettingsFormData {
    sesDriver: 'mock' | 'live';
    awsAccessKeyId: string;
    awsSecretAccessKey: string;
    awsRegion: string;
    sesConfigurationSet: string;
    sendRateLimit: number;
    defaultFromName: string;
    defaultFromEmail: string;
    replyToEmail: string;
    postalAddress: string;
    doubleOptIn: boolean;
    tickitdexEnabled: boolean;
    tickitdexWebhookUrl: string;
    tickitdexToken: string;
    hasTickitdexToken?: boolean;
    tickitdexCategory: string;
    tickitdexCustomerEmail: string;
    tickitdexMinPriority: string;
}

export function useSettingsForm(initialData: SettingsFormData) {
    const [formData, setFormData] = useState<SettingsFormData>(initialData);
    const [isSaving, setIsSaving] = useState(false);
    const [savedSuccess, setSavedSuccess] = useState(false);

    const updateField = <K extends keyof SettingsFormData>(field: K, value: SettingsFormData[K]) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        setSavedSuccess(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);
        router.post('/settings', formData as Record<string, unknown>, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSaving(false);
                setSavedSuccess(true);
                setTimeout(() => setSavedSuccess(false), 3000);
            },
            onError: () => {
                setIsSaving(false);
            },
        });
    };

    return {
        formData,
        updateField,
        handleSubmit,
        isSaving,
        savedSuccess,
    };
}
