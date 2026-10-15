export type ThemeMode = 'light' | 'dark' | 'system'

const KEY = 'kst.theme'

export function getTheme(): ThemeMode {
  try {
    const v = localStorage.getItem(KEY)
    if (v === 'light' || v === 'dark' || v === 'system') return v
  } catch {
    /* storage unavailable */
  }
  return 'system'
}

export function applyTheme(mode: ThemeMode): void {
  const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
  const dark = mode === 'dark' || (mode === 'system' && prefersDark)
  document.documentElement.classList.toggle('dark', dark)
  document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
}

export function setTheme(mode: ThemeMode): void {
  try {
    localStorage.setItem(KEY, mode)
  } catch {
    /* ignore */
  }
  applyTheme(mode)
}

export function cycleTheme(): ThemeMode {
  const order: ThemeMode[] = ['system', 'light', 'dark']
  const next = order[(order.indexOf(getTheme()) + 1) % order.length]
  setTheme(next)
  return next
}

export function initTheme(): void {
  applyTheme(getTheme())
  window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (getTheme() === 'system') applyTheme('system')
  })
}
