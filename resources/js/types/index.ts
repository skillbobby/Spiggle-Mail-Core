export type ContactStatus = 
  | 'subscribed'
  | 'unsubscribed'
  | 'bounced'
  | 'complained'
  | 'dormant'
  | 'pending'
  | 'unconfirmed';

export type CampaignStatus = 
  | 'draft'
  | 'scheduled'
  | 'sending'
  | 'paused'
  | 'sent'
  | 'canceled';

export type CampaignFormat = 'rich' | 'html' | 'markdown';

export type DomainStatus = 'pending' | 'verified' | 'failed';

export interface User {
  readonly id: number;
  readonly name: string;
  readonly email: string;
  readonly role?: 'admin' | 'owner' | 'member';
  readonly isSuperAdmin?: boolean;
}

export interface AccountInfo {
  readonly id: string;
  readonly name: string;
  readonly status: 'active' | 'paused';
  readonly pauseReason?: string;
  readonly pausedAt?: string;
  readonly plan: string;
  readonly emailProvider: string;
  readonly subscriberCount: number;
  readonly campaignsCount: number;
  readonly createdAt: string;
}

export interface AdminAccountItem {
  readonly id: string;
  readonly name: string;
  readonly ownerEmail: string;
  readonly ownerName: string;
  readonly status: 'active' | 'paused';
  readonly pauseReason?: string;
  readonly subscriberCount: number;
  readonly campaignsCount: number;
  readonly bounceRate: number;
  readonly complaintRate: number;
  readonly createdAt: string;
}

export interface Newsletter {
  readonly id: string;
  readonly name: string;
  readonly slug: string;
  readonly fromName: string;
  readonly fromEmail: string;
  readonly postalAddress: string;
  readonly description?: string;
  readonly subscriberCount: number;
}

export interface Domain {
  readonly id: string;
  readonly domain: string;
  readonly status: DomainStatus;
  readonly dkimVerified: boolean;
  readonly spfVerified: boolean;
  readonly dmarcVerified: boolean;
  readonly verifiedAt?: string;
}

export interface Campaign {
  readonly id: string;
  readonly newsletterId: string;
  readonly subject: string;
  readonly previewText: string;
  readonly format: CampaignFormat;
  readonly status: CampaignStatus;
  readonly sentCount: number;
  readonly openRate: number;
  readonly clickRate: number;
  readonly bounceRate: number;
  readonly scheduledAt?: string;
  readonly sentAt?: string;
}

export interface Contact {
  readonly id: string;
  readonly email: string;
  readonly status: ContactStatus;
  readonly openCount: number;
  readonly clickCount: number;
  readonly lastSentAt?: string;
  readonly lastOpenedAt?: string;
  readonly createdAt: string;
}

export interface EmailTemplate {
  readonly id: string;
  readonly title: string;
  readonly category: string;
  readonly subjectDefault: string;
  readonly previewDefault?: string;
  readonly htmlContent: string;
  readonly isSystem: boolean;
}

export interface Shortcode {
  readonly tag: string;
  readonly label: string;
  readonly example: string;
}

export interface ProviderStat {
  readonly provider: string;
  readonly label: string;
  readonly domain?: string;
  readonly sent: number;
  readonly delivered: number;
  readonly opened: number;
  readonly clicked: number;
  readonly bounced: number;
  readonly complaints: number;
}

export interface CampaignStats {
  readonly totalRecipients: number;
  readonly sent: number;
  readonly delivered: number;
  readonly opened: number;
  readonly clicked: number;
  readonly bounced: number;
  readonly complaints: number;
  readonly unsubscribed: number;
  readonly deliveryRate: number;
  readonly openRate: number;
  readonly clickRate: number;
  readonly bounceRate: number;
  readonly complaintRate: number;
  readonly unsubscribeRate: number;
}

export interface CampaignDetail {
  readonly id: string;
  readonly newsletterId: string;
  readonly subject: string;
  readonly subjectB?: string;
  readonly abTestEnabled?: boolean;
  readonly abWinner?: string;
  readonly variantAOpens?: number;
  readonly variantBOpens?: number;
  readonly previewText?: string;
  readonly format: CampaignFormat;
  readonly status: CampaignStatus;
  readonly pauseReason?: string;
  readonly archiveSlug?: string;
  readonly archivePinned: boolean;
  readonly archiveListed: boolean;
  readonly webViews: number;
  readonly html?: string;
  readonly scheduledAt?: string;
  readonly sentAt?: string;
  readonly createdAt: string;
}

export interface ActivityLogItem {
  readonly id: string;
  readonly action: string;
  readonly description: string;
  readonly ipAddress?: string;
  readonly properties?: Record<string, unknown>;
  readonly createdAt: string;
}

export interface ArchiveIssue {
  readonly id: string;
  readonly subject: string;
  readonly previewText?: string;
  readonly archiveSlug: string;
  readonly archivePinned: boolean;
  readonly webViews: number;
  readonly sentAt?: string;
  readonly createdAt: string;
}

export interface ClickedLink {
  readonly url: string;
  readonly text: string;
  readonly clicks: number;
  readonly uniqueClicks?: number;
  readonly percentage: number;
}

export interface AccountAlert {
  readonly type: 'warning' | 'danger' | 'info';
  readonly title: string;
  readonly message: string;
  readonly pausedBy?: string;
  readonly pausedAt?: string;
  readonly actionUrl?: string;
  readonly actionLabel?: string;
}



