import type { EngineId } from './engines'
import { ENGINES } from './engines'

export interface SuggestResponse {
  engine: EngineId
  query: string
  locale: string
  suggestions: string[]
  extra: { categories?: string[] }
  error: string | null
  ms: number
}

export interface EngineHealth {
  ok: boolean
  count: number
  sample: string[]
  error: string | null
  ms: number
}

export interface HealthReport {
  php: string
  curl: boolean
  cache: boolean
  query: string
  engines: Record<string, EngineHealth>
}

export type Fetcher = (
  engine: EngineId,
  query: string,
  locale: string,
  signal?: AbortSignal,
) => Promise<SuggestResponse>

const API_BASE = 'api/'
const MOCK = import.meta.env.VITE_MOCK === '1'

export const fetchSuggestions: Fetcher = async (engine, query, locale, signal) => {
  if (MOCK) return mockSuggestions(engine, query, locale)

  const url = `${API_BASE}suggest.php?${new URLSearchParams({ engine, q: query, locale })}`
  const res = await fetch(url, { signal, headers: { Accept: 'application/json' } })
  let data: Partial<SuggestResponse> | null = null
  try {
    data = (await res.json()) as Partial<SuggestResponse>
  } catch {
    data = null
  }
  if (!data || typeof data !== 'object') {
    return {
      engine,
      query,
      locale,
      suggestions: [],
      extra: {},
      error: `Proxy returned HTTP ${res.status} (is PHP running?)`,
      ms: 0,
    }
  }
  return {
    engine,
    query,
    locale,
    suggestions: Array.isArray(data.suggestions) ? data.suggestions.filter((s) => typeof s === 'string') : [],
    extra: normaliseExtra(data.extra),
    error: typeof data.error === 'string' ? data.error : null,
    ms: typeof data.ms === 'number' ? data.ms : 0,
  }
}

export async function fetchHealth(signal?: AbortSignal): Promise<HealthReport> {
  if (MOCK) {
    return {
      php: 'mock',
      curl: true,
      cache: true,
      query: 'shoes',
      engines: Object.fromEntries(
        ENGINES.map((e) => [e.id, { ok: e.id !== 'yandex', count: 5, sample: ['a', 'b', 'c'], error: e.id === 'yandex' ? 'Mock failure' : null, ms: 100 }]),
      ),
    }
  }
  const res = await fetch(`${API_BASE}health.php`, { signal, headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`Health check failed: HTTP ${res.status}`)
  return (await res.json()) as HealthReport
}

/** Accepts either an object or PHP's empty-array `[]` for extra. */
export function normaliseExtra(extra: unknown): { categories?: string[] } {
  if (!extra || typeof extra !== 'object' || Array.isArray(extra)) return {}
  const cats = (extra as { categories?: unknown }).categories
  if (Array.isArray(cats)) {
    return { categories: cats.filter((c): c is string => typeof c === 'string') }
  }
  return {}
}

// --- Mock mode (VITE_MOCK=1) for UI work without PHP -----------------------

const MOCK_SUFFIXES = ['for men', 'for women', 'near me', 'review', 'price', 'vs', 'best', '2025', 'cheap', 'online']

async function mockSuggestions(engine: EngineId, query: string, locale: string): Promise<SuggestResponse> {
  await new Promise((r) => setTimeout(r, 120 + Math.random() * 200))
  const base = query.replace(/\s[a-z0-9]$/i, '')
  const n = 4 + Math.floor(Math.random() * 5)
  const picks = [...MOCK_SUFFIXES].sort(() => Math.random() - 0.5).slice(0, n)
  return {
    engine,
    query,
    locale,
    suggestions: picks.map((s) => `${base} ${s}`),
    extra: engine === 'ebay' ? { categories: ['Clothing, Shoes & Accessories', 'Sporting Goods'] } : {},
    error: null,
    ms: 150,
  }
}
