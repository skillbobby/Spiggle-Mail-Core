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
  ShieldCheck,
  Plus,
  AlertTriangle,
  X,
  Sun,
  Moon,
  Bell,
  HelpCircle,
  Menu,
  Edit3,
  Check,
  Activity,
  CheckCircle2,
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
  readonly getBadge?: (currentNewsletter?: Newsletter | null) => string | number | null;
}

const NEWSLETTER_NAV: readonly NavItem[] = [
  { href: '/dashboard', label: 'Overview', icon: LayoutGrid },
  { href: '/campaigns', label: 'Campaigns', icon: Send },
  {
    href: '/contacts',
    label: 'Contacts',
    icon: Users,
    getBadge: (nl) => (nl?.subscriberCount !== undefined ? String(nl.subscriberCount) : '0'),
  },
  { href: '/widgets', label: 'Embed Widgets', icon: Layers },
  { href: '/settings', label: 'Settings', icon: Settings },
];

const ACCOUNT_NAV: readonly NavItem[] = [
  { href: '/domains', label: 'Domains', icon: Globe },
  { href: '/api-keys', label: 'API keys', icon: KeyRound },
  { href: '/deliverability', label: 'Deliverability & Health', icon: ShieldAlert },
  { href: '/account', label: 'Account & Security', icon: Settings },
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
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);
  const [isPubSwitcherOpen, setIsPubSwitcherOpen] = useState(false);
  const [isFoldersOpen, setIsFoldersOpen] = useState(true);
  const [isStatusOpen, setIsStatusOpen] = useState(true);

  const notifRef = useRef<HTMLDivElement>(null);
  const pubSwitcherRef = useRef<HTMLDivElement>(null);
  const { theme, toggleTheme } = useTheme();

  const availableNewsletters = props.newsletters && props.newsletters.length > 0
    ? props.newsletters
    : newsletters;

  const activeNewsletter = props.currentNewsletter || currentNewsletter || availableNewsletters[0] || null;

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (notifRef.current && !notifRef.current.contains(e.target as Node)) {
        setIsNotificationsOpen(false);
      }
      if (pubSwitcherRef.current && !pubSwitcherRef.current.contains(e.target as Node)) {
        setIsPubSwitcherOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSelectNewsletter = (id: string) => {
    router.post('/newsletters/select', { newsletter_id: id }, { preserveScroll: true });
  };

  return (
    <div className="min-h-screen bg-[#242730] text-zinc-100 flex items-center justify-center p-0 sm:p-2 lg:p-4 select-none overflow-x-hidden font-sans">
      {/* Main Outer Device/Application Window Frame */}
      <div className="w-full max-w-[1680px] h-screen sm:h-[calc(100vh-1.5rem)] bg-[#121318] rounded-none sm:rounded-[36px] p-2 sm:p-3 shadow-2xl flex overflow-hidden border border-white/5 relative">

        {/* ====================================================================
            1. EXTREME LEFT NARROW DOCK / STRIP (72px)
            ==================================================================== */}
        <aside
          aria-label="Application Dock"
          className="hidden lg:flex w-[72px] shrink-0 flex-col items-center py-4 justify-between border-r border-[#20242e]/60"
        >
          <div className="flex flex-col items-center gap-4 w-full">
            {/* Logo Yellow Brand Square */}
            <Link
              href="/dashboard"
              title="SpiggleMail Home"
              className="w-12 h-12 rounded-2xl bg-[#fee358] hover:brightness-105 active:scale-95 transition-all flex items-center justify-center text-slate-900 shadow-lg shadow-yellow-500/10 shrink-0"
            >
              <Mail className="w-6 h-6 stroke-[2.5]" aria-hidden="true" />
            </Link>

            {/* Notification Bell Quick Action */}
            <div className="relative" ref={notifRef}>
              <button
                type="button"
                onClick={() => setIsNotificationsOpen(!isNotificationsOpen)}
                title="System Notifications & SES Health"
                className={`w-11 h-11 rounded-xl flex items-center justify-center transition-all relative shrink-0 ${
                  isNotificationsOpen
                    ? 'bg-white/10 text-white'
                    : 'text-zinc-400 hover:text-white hover:bg-white/5'
                }`}
              >
                <Bell className="w-5 h-5" aria-hidden="true" />
                <span className="absolute top-2.5 right-2.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-[#121318]" />
              </button>

              {/* Notification Popover Dropdown */}
              {isNotificationsOpen && (
                <div className="absolute top-0 left-full ml-3 w-80 rounded-2xl bg-[#171a22] border border-[#262c3b] p-4 shadow-2xl z-50 text-xs animate-in fade-in zoom-in-95 duration-100">
                  <div className="flex items-center justify-between pb-3 mb-3 border-b border-zinc-800">
                    <div className="flex items-center gap-2">
                      <Activity className="w-4 h-4 text-emerald-400" />
                      <span className="font-bold text-white text-sm">System & SES Health</span>
                    </div>
                    <span className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-400 font-semibold border border-emerald-800/60">
                      Healthy
                    </span>
                  </div>
                  <div className="space-y-2.5">
                    <div className="p-2.5 rounded-xl bg-zinc-900/80 border border-zinc-800">
                      <div className="flex items-center justify-between text-zinc-200 font-semibold">
                        <span>Mail Dispatch Engine</span>
                        <span className="text-emerald-400 flex items-center gap-1 font-mono text-[11px]">
                          <CheckCircle2 className="w-3.5 h-3.5" /> Ready
                        </span>
                      </div>
                      <p className="text-[11px] text-zinc-400 mt-1 leading-relaxed">
                        Enforces RFC 8058 one-click list unsubscribes and auto-pauses on bounce threshold.
                      </p>
                    </div>
                    <div className="p-2.5 rounded-xl bg-zinc-900/80 border border-zinc-800">
                      <div className="flex items-center justify-between text-zinc-200 font-semibold">
                        <span>DNS Deliverability</span>
                        <span className="text-sky-400 font-mono text-[11px]">SPF / DKIM OK</span>
                      </div>
                      <p className="text-[11px] text-zinc-400 mt-1 leading-relaxed">
                        Automatic DNS alignment checks prevent domain blacklisting and spam filtering.
                      </p>
                    </div>
                  </div>
                </div>
              )}
            </div>

            <div className="w-8 h-[1px] bg-zinc-800/80 my-1" />

            {/* Publication Quick-Switcher Avatar Stack */}
            <div className="flex flex-col items-center gap-3 w-full">
              {availableNewsletters.map((nl) => {
                const isSelected = nl.id === activeNewsletter?.id;
                const initial = (nl.name || 'P').charAt(0).toUpperCase();
                return (
                  <button
                    key={nl.id}
                    type="button"
                    onClick={() => handleSelectNewsletter(nl.id)}
                    title={`Switch to: ${nl.name}`}
                    className={`relative group shrink-0 w-11 h-11 rounded-full flex items-center justify-center font-bold text-xs transition-all ${
                      isSelected
                        ? 'bg-indigo-600 text-white ring-2 ring-[#fee358] shadow-md shadow-yellow-500/20'
                        : 'bg-zinc-800/90 text-zinc-400 hover:text-white hover:bg-zinc-700 ring-2 ring-transparent'
                    }`}
                  >
                    <span>{initial}</span>
                    {isSelected && (
                      <span className="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 rounded-full ring-2 ring-[#121318]" />
                    )}
                  </button>
                );
              })}

              <Link
                href="/settings"
                title="Manage Publications"
                className="w-11 h-11 rounded-full border border-dashed border-zinc-700 hover:border-zinc-400 text-zinc-400 hover:text-white flex items-center justify-center transition-all shrink-0"
              >
                <Plus className="w-4 h-4 font-semibold" aria-hidden="true" />
              </Link>
            </div>
          </div>

          {/* Bottom Dock Utilities */}
          <div className="flex flex-col items-center gap-3">
            <Link
              href="/docs"
              title="Documentation & Guides"
              className="w-11 h-11 rounded-xl text-zinc-400 hover:text-white hover:bg-white/5 flex items-center justify-center transition-all shrink-0"
            >
              <HelpCircle className="w-5 h-5" aria-hidden="true" />
            </Link>

            <button
              type="button"
              onClick={toggleTheme}
              aria-label={theme === 'dark' ? 'Switch to light workspace' : 'Switch to dark workspace'}
              title={theme === 'dark' ? 'Switch to light workspace' : 'Switch to dark workspace'}
              className="w-11 h-11 rounded-xl text-zinc-400 hover:text-white hover:bg-white/5 flex items-center justify-center transition-all shrink-0"
            >
              {theme === 'dark' ? (
                <Sun className="w-5 h-5 text-amber-400" aria-hidden="true" />
              ) : (
                <Moon className="w-5 h-5 text-indigo-400" aria-hidden="true" />
              )}
            </button>

            <Link
              href="/settings"
              title="Publication & Engine Settings"
              className="w-11 h-11 rounded-xl text-zinc-400 hover:text-white hover:bg-white/5 flex items-center justify-center transition-all shrink-0"
            >
              <Settings className="w-5 h-5" aria-hidden="true" />
            </Link>

            <Link
              href="/logout"
              method="post"
              as="button"
              title="Sign Out"
              className="w-11 h-11 rounded-xl text-zinc-400 hover:text-rose-400 hover:bg-white/5 flex items-center justify-center transition-all shrink-0"
            >
              <LogOut className="w-5 h-5" aria-hidden="true" />
            </Link>
          </div>
        </aside>

        {/* ====================================================================
            2. SECONDARY DARK SIDEBAR (FOLDERS & LABELS) (240px)
            ==================================================================== */}
        <aside
          aria-label="Navigation Sidebar"
          className={`${
            isMobileMenuOpen ? 'fixed inset-y-0 left-0 z-50 flex shadow-2xl' : 'hidden lg:flex'
          } w-[240px] shrink-0 bg-[#14161d] p-4 flex-col justify-between overflow-y-auto dark-scrollbar border-r border-[#1f242e]/60`}
        >
          <div className="flex flex-col gap-6">
            {/* User Profile Card */}
            <div className="flex items-center justify-between">
              <Link
                href="/account"
                onClick={() => setIsMobileMenuOpen(false)}
                className="flex items-center gap-3 px-1 py-1 rounded-2xl hover:bg-white/5 transition group cursor-pointer flex-1 min-w-0 mr-1"
                title="Manage Account Profile"
              >
                <div className="relative shrink-0">
                  <div className="w-11 h-11 rounded-full bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-bold text-sm ring-2 ring-white/10 group-hover:ring-[#fee358] transition-all">
                    {user?.name?.charAt(0) ?? 'A'}
                  </div>
                  <span className="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 rounded-full ring-2 ring-[#14161d]" />
                </div>
                <div className="flex flex-col min-w-0">
                  <div className="flex items-center gap-1.5">
                    <span className="text-[14px] font-semibold text-white tracking-tight truncate whitespace-nowrap">
                      {user?.name ?? 'Admin Operator'}
                    </span>
                    {isSuperAdmin && (
                      <span className="inline-flex items-center rounded-sm bg-amber-950/80 px-1 py-0.2 text-[9px] font-bold text-amber-400 uppercase tracking-wider border border-amber-800/50">
                        Super
                      </span>
                    )}
                  </div>
                  <span className="text-xs text-zinc-400 truncate whitespace-nowrap">
                    {user?.email ?? 'admin@example.com'}
                  </span>
                </div>
              </Link>

              {/* Close Button on Mobile Drawer */}
              {isMobileMenuOpen && (
                <button
                  type="button"
                  onClick={() => setIsMobileMenuOpen(false)}
                  className="lg:hidden p-1.5 text-zinc-400 hover:text-white rounded-lg hover:bg-white/10"
                >
                  <X className="w-4 h-4" />
                </button>
              )}
            </div>

            {/* Main Navigation Links */}
            <nav className="flex flex-col gap-1.5" aria-label="Main Menu">
              {NEWSLETTER_NAV.map(({ href, label, icon: Icon, getBadge }) => {
                const isActive = url === href || (href !== '/dashboard' && url.startsWith(`${href}/`));
                const badge = getBadge ? getBadge(activeNewsletter) : null;
                return (
                  <Link
                    key={href}
                    href={href}
                    onClick={() => setIsMobileMenuOpen(false)}
                    className={`flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-sm font-medium transition-colors whitespace-nowrap ${
                      isActive
                        ? 'bg-[#aab8f8] text-slate-900 font-semibold shadow-sm'
                        : 'text-zinc-400 hover:text-zinc-200 hover:bg-white/5'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <Icon className="w-4 h-4 shrink-0" aria-hidden="true" />
                      <span className="whitespace-nowrap">{label}</span>
                    </div>
                    {badge && (
                      <span
                        className={`text-xs px-2 py-0.5 rounded-lg font-bold whitespace-nowrap ${
                          isActive
                            ? 'bg-slate-900/10 text-slate-900'
                            : 'bg-zinc-800/80 text-zinc-400'
                        }`}
                      >
                        {badge}
                      </span>
                    )}
                  </Link>
                );
              })}

              {isSuperAdmin && (
                <Link
                  href="/admin"
                  onClick={() => setIsMobileMenuOpen(false)}
                  className={`flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-sm font-medium transition-colors whitespace-nowrap ${
                    url.startsWith('/admin')
                      ? 'bg-amber-400 text-slate-900 font-semibold shadow-sm'
                      : 'text-amber-400/90 hover:text-amber-200 hover:bg-white/5'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <ShieldCheck className="w-4 h-4 shrink-0" aria-hidden="true" />
                    <span className="whitespace-nowrap">Super Admin</span>
                  </div>
                  <span className="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-amber-950/60 text-amber-300 border border-amber-800/40">
                    Portal
                  </span>
                </Link>
              )}
            </nav>

            {/* Folders Section (Infrastructure) - Fully Interactive Collapsible */}
            <div>
              <button
                type="button"
                onClick={() => setIsFoldersOpen(!isFoldersOpen)}
                className="w-full flex items-center justify-between px-2 text-zinc-400 hover:text-white transition-colors py-1 group mb-1.5"
              >
                <div className="flex items-center gap-1.5 whitespace-nowrap">
                  <ChevronDown className={`w-3.5 h-3.5 transition-transform duration-200 ${isFoldersOpen ? '' : '-rotate-90'}`} />
                  <span className="text-xs font-bold uppercase tracking-wider text-zinc-300 group-hover:text-white">
                    Infrastructure
                  </span>
                </div>
                <span className="text-[10px] text-zinc-500 font-mono font-medium group-hover:text-zinc-300">
                  {ACCOUNT_NAV.length}
                </span>
              </button>

              {isFoldersOpen && (
                <div className="flex flex-col gap-1 animate-in fade-in duration-150">
                  {ACCOUNT_NAV.map(({ href, label, icon: Icon }) => {
                    const isActive = url.startsWith(href);
                    return (
                      <Link
                        key={href}
                        href={href}
                        onClick={() => setIsMobileMenuOpen(false)}
                        className={`flex items-center justify-between px-3 py-2 rounded-xl text-[13px] transition-colors whitespace-nowrap ${
                          isActive
                            ? 'bg-zinc-800 text-white font-semibold'
                            : 'text-zinc-400 hover:text-zinc-200 hover:bg-white/5'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <Icon className="w-4 h-4 shrink-0" aria-hidden="true" />
                          <span className="whitespace-nowrap">{label}</span>
                        </div>
                      </Link>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Labels Section (System Status Indicators) - Fully Interactive Collapsible */}
            <div>
              <button
                type="button"
                onClick={() => setIsStatusOpen(!isStatusOpen)}
                className="w-full flex items-center justify-between px-2 text-zinc-400 hover:text-white transition-colors py-1 group mb-1.5"
              >
                <div className="flex items-center gap-1.5 whitespace-nowrap">
                  <ChevronDown className={`w-3.5 h-3.5 transition-transform duration-200 ${isStatusOpen ? '' : '-rotate-90'}`} />
                  <span className="text-xs font-bold uppercase tracking-wider text-zinc-300 group-hover:text-white">
                    Deliverability
                  </span>
                </div>
                <span className="text-[10px] text-emerald-400 font-mono font-bold">
                  Live
                </span>
              </button>

              {isStatusOpen && (
                <div className="flex flex-col gap-1 text-[13px] animate-in fade-in duration-150">
                  <div className="flex items-center justify-between px-3 py-1.5 text-zinc-400 hover:text-zinc-200 rounded-lg hover:bg-white/5 transition">
                    <div className="flex items-center gap-2.5">
                      <span className="w-2.5 h-2.5 rounded-full border-2 border-emerald-500 shrink-0" />
                      <span>SES Engine</span>
                    </div>
                    <span className="text-[11px] text-emerald-400 font-semibold font-mono">Connected</span>
                  </div>
                  <div className="flex items-center justify-between px-3 py-1.5 text-zinc-400 hover:text-zinc-200 rounded-lg hover:bg-white/5 transition">
                    <div className="flex items-center gap-2.5">
                      <span className="w-2.5 h-2.5 rounded-full border-2 border-sky-400 shrink-0" />
                      <span>SPF &amp; DKIM</span>
                    </div>
                    <span className="text-[11px] text-sky-400 font-semibold font-mono">Protected</span>
                  </div>
                  <div className="flex items-center justify-between px-3 py-1.5 text-zinc-400 hover:text-zinc-200 rounded-lg hover:bg-white/5 transition">
                    <div className="flex items-center gap-2.5">
                      <span className="w-2.5 h-2.5 rounded-full border-2 border-amber-500 shrink-0" />
                      <span>Bounce Guard</span>
                    </div>
                    <span className="text-[11px] text-amber-400 font-semibold font-mono">0.0%</span>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Compose Floating Yellow Action Button */}
          <div className="pt-4 border-t border-[#1f242e]/60">
            <Link
              href="/campaigns/new"
              onClick={() => setIsMobileMenuOpen(false)}
              className="w-full py-3 bg-[#faea78] hover:bg-[#fae459] active:scale-[0.99] text-slate-900 font-bold rounded-2xl flex items-center justify-center gap-2 shadow-sm transition-all whitespace-nowrap"
            >
              <Edit3 className="w-4 h-4 font-bold" aria-hidden="true" />
              <span className="text-sm tracking-tight whitespace-nowrap">New Newsletter</span>
            </Link>
          </div>
        </aside>

        {/* Mobile menu backdrop */}
        {isMobileMenuOpen && (
          <div
            className="fixed inset-0 bg-black/60 z-40 lg:hidden"
            onClick={() => setIsMobileMenuOpen(false)}
          />
        )}

        {/* ====================================================================
            3 & 4. UNIFIED SINGLE ROUNDED CONTAINER CARD (MAIN WORKSPACE)
            ==================================================================== */}
        <div className="flex-1 app-workspace-frame bg-white dark:bg-[#161820] rounded-[24px] sm:rounded-[30px] flex flex-col overflow-hidden shadow-xl relative text-slate-900 dark:text-zinc-100">

          {/* Card Top Header / Action Toolbar */}
          <header className="app-workspace-topbar px-5 sm:px-8 py-3.5 border-b border-[#edf0f5] dark:border-zinc-800/80 flex items-center justify-between shrink-0 bg-white dark:bg-[#161820] z-20">
            <div className="flex items-center gap-3">
              {/* Mobile Hamburger Menu Toggle */}
              <button
                type="button"
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                aria-label="Toggle Navigation Drawer"
                className="lg:hidden p-2 text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-zinc-800 transition"
              >
                <Menu className="w-5 h-5" />
              </button>

              {/* Interactive Active Publication Switcher Dropdown in Header */}
              <div className="relative" ref={pubSwitcherRef}>
                <button
                  type="button"
                  onClick={() => setIsPubSwitcherOpen(!isPubSwitcherOpen)}
                  className="px-3.5 py-1.5 rounded-full bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800/50 text-xs font-semibold flex items-center gap-2 hover:bg-purple-100 dark:hover:bg-purple-900/40 transition shrink-0"
                  title="Click to Switch Publication"
                >
                  <span className="w-2 h-2 rounded-full border-2 border-purple-500 shrink-0" />
                  <span className="truncate max-w-[140px] sm:max-w-[220px]">
                    {activeNewsletter?.name || 'Primary Publication'}
                  </span>
                  <ChevronDown className={`w-3.5 h-3.5 transition-transform duration-150 ${isPubSwitcherOpen ? 'rotate-180' : ''}`} />
                </button>

                {isPubSwitcherOpen && (
                  <div className="absolute top-full left-0 mt-2 w-72 rounded-2xl bg-white dark:bg-[#171a22] border border-slate-200 dark:border-zinc-800 shadow-2xl p-2 z-50 text-xs animate-in fade-in zoom-in-95 duration-100">
                    <div className="px-3 py-1.5 text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 tracking-wider">
                      Switch Publication
                    </div>
                    <div className="space-y-0.5 max-h-48 overflow-y-auto custom-scrollbar">
                      {availableNewsletters.map((nl) => {
                        const isSelected = nl.id === activeNewsletter?.id;
                        return (
                          <button
                            key={nl.id}
                            type="button"
                            onClick={() => {
                              handleSelectNewsletter(nl.id);
                              setIsPubSwitcherOpen(false);
                            }}
                            className={`w-full flex items-center justify-between px-3 py-2 rounded-xl text-left transition ${
                              isSelected
                                ? 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-semibold'
                                : 'text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800'
                            }`}
                          >
                            <span className="truncate">{nl.name}</span>
                            {isSelected && <Check className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400 shrink-0" />}
                          </button>
                        );
                      })}
                    </div>
                    <div className="mt-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                      <Link
                        href="/settings"
                        onClick={() => setIsPubSwitcherOpen(false)}
                        className="flex items-center gap-2 px-3 py-1.5 rounded-xl text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition"
                      >
                        <Plus className="w-3.5 h-3.5 text-indigo-500" />
                        <span>Manage publications</span>
                      </Link>
                    </div>
                  </div>
                )}
              </div>

              {/* Subscriber Badge */}
              <span className="hidden sm:inline-flex px-3 py-1 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 text-xs font-semibold items-center gap-1.5 whitespace-nowrap shrink-0">
                <Users className="w-3.5 h-3.5" />
                {activeNewsletter?.subscriberCount ?? 0} Subscribers
              </span>
            </div>

            {/* Quick Header Utilities */}
            <div className="flex items-center gap-2">
              <Link
                href="/campaigns/new"
                className="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#faea78] hover:bg-[#fae459] text-slate-900 text-xs font-bold transition shadow-xs"
              >
                <Edit3 className="w-3.5 h-3.5" />
                <span>New Newsletter</span>
              </Link>
            </div>
          </header>

          {/* Account Alert Banner (if any) */}
          {accountAlert && !isAlertDismissed && (
            <div
              className={`border-b px-6 py-3 flex items-center justify-between gap-4 transition-all shrink-0 ${
                accountAlert.type === 'danger'
                  ? 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-950/80 dark:border-rose-800/80 dark:text-rose-200'
                  : 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-950/80 dark:border-amber-800/80 dark:text-amber-200'
              }`}
            >
              <div className="flex items-center gap-3 min-w-0">
                <AlertTriangle
                  className={`w-4 h-4 shrink-0 ${
                    accountAlert.type === 'danger' ? 'text-rose-500' : 'text-amber-500'
                  }`}
                />
                <div className="text-xs flex flex-wrap items-center gap-2">
                  <span className="font-bold tracking-wider uppercase text-[10px] px-1.5 py-0.5 rounded bg-black/10 dark:bg-black/40 border border-current/20">
                    {accountAlert.title}
                  </span>
                  <span className="opacity-95">{accountAlert.message}</span>
                </div>
              </div>
              <div className="flex items-center gap-2 shrink-0">
                {accountAlert.actionUrl && (
                  <Link
                    href={accountAlert.actionUrl}
                    className="text-xs font-semibold px-2.5 py-1 rounded-md bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-xs"
                  >
                    {accountAlert.actionLabel || 'Inspect'}
                  </Link>
                )}
                <button
                  type="button"
                  onClick={() => setIsAlertDismissed(true)}
                  className="p-1 rounded hover:bg-black/10 dark:hover:bg-white/10 text-current opacity-70 hover:opacity-100 transition"
                  title="Dismiss banner"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          )}

          {/* Main Scrollable Workspace Canvas */}
          <main className="flex-1 overflow-y-auto custom-scrollbar p-5 sm:p-7 lg:p-8 app-workspace-card">
            <div className="max-w-7xl mx-auto w-full">{children}</div>
          </main>

          {/* Floating Get Started Onboarding Checklist */}
          <GetStartedPanel data={props.getStarted} />
        </div>
      </div>
    </div>
  );
};

export default DashboardLayout;
