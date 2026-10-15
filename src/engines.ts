/**
 * Engine registry. Ids must match public/api/engines/registry.php.
 */
export type EngineId =
  | 'google'
  | 'youtube'
  | 'bing'
  | 'yahoo'
  | 'duckduckgo'
  | 'qwant'
  | 'yandex'
  | 'amazon'
  | 'ebay'
  | 'wikipedia'
  | 'appstore'

export type EngineGroup = 'Search' | 'Video' | 'Shopping' | 'Reference' | 'Apps'

export interface Engine {
  id: EngineId
  label: string
  group: EngineGroup
  /** Tailwind classes for the source chip. */
  chip: string
  /** Tailwind background class for the status dot / legend. */
  dot: string
  note?: string
}

export const ENGINES: Engine[] = [
  { id: 'google', label: 'Google', group: 'Search', chip: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-200', dot: 'bg-blue-500' },
  { id: 'bing', label: 'Bing', group: 'Search', chip: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-200', dot: 'bg-teal-500' },
  { id: 'yahoo', label: 'Yahoo', group: 'Search', chip: 'bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-200', dot: 'bg-purple-500' },
  { id: 'duckduckgo', label: 'DuckDuckGo', group: 'Search', chip: 'bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-200', dot: 'bg-orange-500' },
  { id: 'qwant', label: 'Qwant', group: 'Search', chip: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200', dot: 'bg-sky-500' },
  { id: 'yandex', label: 'Yandex', group: 'Search', chip: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200', dot: 'bg-red-500' },
  { id: 'youtube', label: 'YouTube', group: 'Video', chip: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200', dot: 'bg-rose-500' },
  { id: 'amazon', label: 'Amazon', group: 'Shopping', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200', dot: 'bg-amber-500' },
  { id: 'ebay', label: 'eBay', group: 'Shopping', chip: 'bg-lime-100 text-lime-800 dark:bg-lime-500/20 dark:text-lime-200', dot: 'bg-lime-500', note: 'Also returns matching categories' },
  { id: 'wikipedia', label: 'Wikipedia', group: 'Reference', chip: 'bg-slate-200 text-slate-800 dark:bg-slate-500/20 dark:text-slate-200', dot: 'bg-slate-500' },
  { id: 'appstore', label: 'App Store', group: 'Apps', chip: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-200', dot: 'bg-indigo-500' },
]

export const ENGINE_GROUPS: EngineGroup[] = ['Search', 'Video', 'Shopping', 'Reference', 'Apps']

export const ENGINE_BY_ID: Record<EngineId, Engine> = Object.fromEntries(
  ENGINES.map((e) => [e.id, e]),
) as Record<EngineId, Engine>

export const DEFAULT_ENGINES: EngineId[] = ['google', 'bing', 'youtube', 'amazon']

/** Locales supported by the proxy. Keep in sync with KS_LOCALES in public/api/lib.php. */
export const LOCALES: { code: string; label: string }[] = [
  { code: 'en-US', label: 'English (United States)' },
  { code: 'en-GB', label: 'English (United Kingdom)' },
  { code: 'en-AU', label: 'English (Australia)' },
  { code: 'en-CA', label: 'English (Canada)' },
  { code: 'en-IN', label: 'English (India)' },
  { code: 'de-DE', label: 'Deutsch (Deutschland)' },
  { code: 'fr-FR', label: 'Français (France)' },
  { code: 'es-ES', label: 'Español (España)' },
  { code: 'it-IT', label: 'Italiano (Italia)' },
  { code: 'nl-NL', label: 'Nederlands (Nederland)' },
  { code: 'pt-BR', label: 'Português (Brasil)' },
  { code: 'ja-JP', label: '日本語 (日本)' },
]

export function isEngineId(value: string): value is EngineId {
  return value in ENGINE_BY_ID
}
