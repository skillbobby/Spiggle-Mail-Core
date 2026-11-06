import React, { useState, useRef, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import {
  LayoutGrid,
  Send,
  Users,
  Settings,
  Globe,
  KeyRound,
  ShieldAlert,
  Layers,
  Mail,
  ChevronDown,
  LogOut,
  User as UserIcon,
  ShieldCheck,
  Plus,
  Check,
  AlertTriangle,
  X,
  Sun,
  Moon,
} from 'lucide-react';
import type { Newsletter, User, AccountAlert } from '@/types';
import { GetStartedPanel, type GetStartedData } from '@/Components/GetStartedPanel';
import { useTheme } from '@/hooks/useTheme';

interface DashboardLayoutProps {
  readonly children: React.ReactNode;
  readonly newsletters?: readonly Newsletter[];
  readonly currentNewsletter?: Newsletter | null;
}

interface NavItem {
  readonly href: string;
  readonly label: string;
  readonly icon: React.FC<{ className?: string }>;
}

const NEWSLETTER_NAV: readonly NavItem[] = [
  { href: '/dashboard', label: 'Overview', icon: LayoutGrid },
  { href: '/campaigns', label: 'Campaigns', icon: Send },
  { href: '/contacts', label: 'Contacts', icon: Users },
  { href: '/widgets', label: 'Embed Widgets', icon: Layers },
  { href: '/settings', label: 'Settings', icon: Settings },
];

const ACCOUNT_NAV: readonly NavItem[] = [
  { href: '/domains', label: 'Domains', icon: Globe },
  { href: '/api-keys', label: 'API keys', icon: KeyRound },
  { href: '/deliverability', label: 'Deliverability & Health', icon: ShieldAlert },
  { href: '/account', label: 'Account & Security', icon: UserIcon },
];

export const DashboardLayout: React.FC<DashboardLayoutProps> = ({
  children,
  newsletters = [],
  currentNewsletter,
}) => {
  const { url, props } = usePage<{
    auth?: { user?: User | null };
    newsletters?: Newsletter[];
    currentNewsletter?: Newsletter | null;
    getStarted?: GetStartedData | null;
    accountAlert?: AccountAlert | null;
  }>();

  const user = props.auth?.user;
  const isSuperAdmin = Boolean(user?.isSuperAdmin);
  const accountAlert = props.accountAlert;
  const [isAlertDismissed, setIsAlertDismissed] = useState(false);
  const { theme, toggleTheme } = useTheme();

  const availableNewsletters = props.newsletters && props.newsletters.length > 0
    ? props.newsletters
    : newsletters;

  const activeNewsletter = props.currentNewsletter || currentNewsletter || availableNewsletters[0] || null;

  const [isSwitcherOpen, setIsSwitcherOpen] = useState(false);
  const switcherRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (switcherRef.current && !switcherRef.current.contains(e.target as Node)) {
        setIsSwitcherOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSelectNewsletter = (id: string) => {
    setIsSwitcherOpen(false);
    router.post('/newsletters/select', { newsletter_id: id }, { preserveScroll: true });
  };

  return (
    <div className="flex min-h-screen bg-zinc-950 text-zinc-100 antialiased font-sans">
      {/* Sidebar */}
      <aside
        aria-label="Sidebar Navigation"
        className="w-64 border-r border-zinc-800/80 bg-zinc-950 flex flex-col justify-between p-4 shrink-0"
      >
        <div>
          {/* Brand Logo */}
          <Link
            href="/dashboard"
            className="flex items-center gap-2.5 px-3 py-2 mb-4 rounded-lg hover:bg-zinc-900 transition"
          >
            <div className="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-500/20">
              <Mail className="w-4 h-4" aria-hidden="true" />
            </div>
            <span className="font-semibold text-base tracking-tight text-white">
              Spiggle<span className="text-indigo-400">Mail</span>
            </span>
          </Link>

          {/* Active Newsletter Dropdown Trigger */}
          <div className="mb-6 px-1 relative" ref={switcherRef}>
            <button
              type="button"
              onClick={() => setIsSwitcherOpen(!isSwitcherOpen)}
              className="w-full flex items-center justify-between px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 text-left transition"
            >
              <div className="truncate pr-2">
                <p className="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">Active Publication</p>
                <p className="text-sm font-semibold text-white truncate">
                  {activeNewsletter?.name || 'All Publications'}
                </p>
              </div>
              <ChevronDown className={`w-4 h-4 text-zinc-400 shrink-0 transition-transform ${isSwitcherOpen ? 'rotate-180' : ''}`} aria-hidden="true" />
            </button>

            {/* Switcher Dropdown Menu */}
            {isSwitcherOpen && (
              <div className="absolute top-full left-1 right-1 mt-1.5 rounded-xl bg-zinc-900 border border-zinc-800 shadow-2xl p-1.5 z-50 text-xs animate-in fade-in zoom-in-95 duration-100">
                <div className="px-2 py-1 text-[10px] uppercase font-semibold text-zinc-500 tracking-wider">
                  Switch Publication
                </div>
                <div className="space-y-0.5 max-h-48 overflow-y-auto">
                  {availableNewsletters.map((nl) => {
                    const isSelected = nl.id === activeNewsletter?.id;
                    return (
                      <button
                        key={nl.id}
                        type="button"
                        onClick={() => handleSelectNewsletter(nl.id)}
                        className={`w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-left transition ${
                          isSelected
                            ? 'bg-indigo-600/20 text-indigo-300 font-semibold border border-indigo-500/30'
                            : 'text-zinc-300 hover:bg-zinc-800 hover:text-white'
                        }`}
                      >
                        <span className="truncate">{nl.name}</span>
                        {isSelected && <Check className="w-3.5 h-3.5 text-indigo-400 shrink-0 ml-1.5" aria-hidden="true" />}
                      </button>
                    );
                  })}
                </div>

                <div className="mt-1 pt-1 border-t border-zinc-800/80">
                  <Link
                    href="/settings"
                    onClick={() => setIsSwitcherOpen(false)}
                    className="flex items-center gap-1.5 w-full px-2.5 py-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 transition text-[11px]"
                  >
                    <Plus className="w-3.5 h-3.5 text-indigo-400" aria-hidden="true" />
                    <span>Manage publications</span>
                  </Link>
                </div>
              </div>
            )}
          </div>

          {/* Navigation Groups */}
          <nav className="space-y-6">
            <div>
              <p className="px-3 text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">
                Newsletter
              </p>
              <ul className="space-y-0.5">
                {NEWSLETTER_NAV.map(({ href, label, icon: Icon }) => {
                  const isActive = url === href || (href !== '/dashboard' && url.startsWith(`${href}/`));
                  return (
                    <li key={href}>
                      <Link
                        href={href}
                        className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition ${
                          isActive
                            ? 'bg-zinc-800 text-white font-semibold'
                            : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900'
                        }`}
                      >
                        <Icon className="w-4 h-4" aria-hidden="true" />
                        <span>{label}</span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>

            <div>
              <p className="px-3 text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">
                Account & Infrastructure
              </p>
              <ul className="space-y-0.5">
                {ACCOUNT_NAV.map(({ href, label, icon: Icon }) => {
                  const isActive = url.startsWith(href);
                  return (
                    <li key={href}>
                      <Link
                        href={href}
                        className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition ${
                          isActive
                            ? 'bg-zinc-800 text-white font-semibold'
                            : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900'
                        }`}
                      >
                        <Icon className="w-4 h-4" aria-hidden="true" />
                        <span>{label}</span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          </nav>
        </div>

        {/* Footer User Profile & System Status */}
        <div className="border-t border-zinc-800/80 pt-4 px-2">
          <div className="flex items-center justify-between">
            <Link
              href="/account"
              className="flex items-center gap-2.5 truncate hover:opacity-80 transition group flex-1 mr-2"
              title="Manage Account Profile"
            >
              <div className="w-8 h-8 rounded-full bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-xs shrink-0 group-hover:ring-2 group-hover:ring-indigo-500/40 transition">
                {user?.name?.charAt(0) ?? 'O'}
              </div>
              <div className="truncate">
                <div className="flex items-center gap-1.5">
                  <p className="text-xs font-semibold text-white truncate">
                    {user?.name ?? 'Operator'}
                  </p>
                </div>
                <p className="text-[10px] text-zinc-400 truncate">
                  {user?.email ?? ''}
                </p>
              </div>
            </Link>

            <div className="flex items-center gap-1 shrink-0">
              <button
                type="button"
                onClick={toggleTheme}
                aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
                title={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
                className="text-zinc-400 hover:text-white p-1.5 rounded-lg hover:bg-zinc-900 transition shrink-0"
              >
                {theme === 'dark' ? (
                  <Sun className="w-4 h-4 text-amber-400" aria-hidden="true" />
                ) : (
                  <Moon className="w-4 h-4 text-indigo-400" aria-hidden="true" />
                )}
              </button>

              <Link
                href="/logout"
                method="post"
                as="button"
                aria-label="Sign out"
                className="text-zinc-400 hover:text-white p-1.5 rounded-lg hover:bg-zinc-900 transition shrink-0"
                title="Sign out"
              >
                <LogOut className="w-4 h-4" aria-hidden="true" />
              </Link>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content Area */}
      <main className="flex-1 flex flex-col min-w-0 overflow-y-auto relative">
        {/* Account Deliverability / Circuit Breaker Warning Banner */}
        {accountAlert && !isAlertDismissed && (
          <div
            className={`border-b px-6 py-3 flex items-center justify-between gap-4 transition-all ${
              accountAlert.type === 'danger'
                ? 'bg-rose-950/80 border-rose-800/80 text-rose-200'
                : 'bg-amber-950/80 border-amber-800/80 text-amber-200'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <AlertTriangle
                className={`w-4 h-4 shrink-0 ${
                  accountAlert.type === 'danger' ? 'text-rose-400' : 'text-amber-400'
                }`}
              />
              <div className="text-xs flex flex-wrap items-center gap-2">
                <span className="font-bold tracking-wider uppercase text-[10px] px-1.5 py-0.5 rounded bg-black/40 border border-current/20">
                  {accountAlert.title}
                </span>
                <span className="opacity-95">{accountAlert.message}</span>
              </div>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              {accountAlert.actionUrl && (
                <Link
                  href={accountAlert.actionUrl}
                  className={`text-xs font-semibold px-2.5 py-1 rounded-md transition shadow-xs ${
                    accountAlert.type === 'danger'
                      ? 'bg-rose-600 hover:bg-rose-500 text-white'
                      : 'bg-amber-500 hover:bg-amber-400 text-zinc-950'
                  }`}
                >
                  {accountAlert.actionLabel || 'Inspect'}
                </Link>
              )}
              <button
                type="button"
                onClick={() => setIsAlertDismissed(true)}
                className="p-1 rounded hover:bg-white/10 text-current opacity-70 hover:opacity-100 transition"
                title="Dismiss banner"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        )}

        <div className="p-8 max-w-7xl mx-auto w-full">{children}</div>

        {/* Floating Get Started Onboarding Checklist */}
        <GetStartedPanel data={props.getStarted} />
      </main>
    </div>
  );
};

export default DashboardLayout;
