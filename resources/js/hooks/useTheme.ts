import { useState, useEffect, useCallback } from 'react';

export type Theme = 'dark' | 'light';

export const THEME_STORAGE_KEY = 'spiggle_theme';

export function getInitialTheme(): Theme {
  if (typeof window === 'undefined') {
    return 'light';
  }
  try {
    const saved = localStorage.getItem(THEME_STORAGE_KEY) as Theme | null;
    if (saved === 'dark') {
      return 'dark';
    }
  } catch {
    // ignore
  }
  return 'light';
}

export function applyTheme(theme: Theme): void {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  if (theme === 'light') {
    root.classList.remove('dark');
    root.classList.add('light');
  } else {
    root.classList.add('dark');
    root.classList.remove('light');
  }
  try {
    localStorage.setItem(THEME_STORAGE_KEY, theme);
  } catch {
    // ignore
  }
}

export function useTheme() {
  const [theme, setTheme] = useState<Theme>(() => {
    if (typeof document !== 'undefined') {
      if (document.documentElement.classList.contains('light')) {
        return 'light';
      }
    }
    return getInitialTheme();
  });

  useEffect(() => {
    applyTheme(theme);
  }, [theme]);

  const toggleTheme = useCallback(() => {
    setTheme((prev) => (prev === 'dark' ? 'light' : 'dark'));
  }, []);

  return { theme, setTheme, toggleTheme };
}
