import { describe, expect, it } from 'vitest'
import type { Fetcher, SuggestResponse } from '../src/api'
import { Crawler, MODIFIER_PREFIXES, expandKeyword, parseSeeds, type CrawlOptions } from '../src/crawler'
import { ResultStore } from '../src/store'

const noSleep = () => Promise.resolve()

function fakeFetcher(table: Record<string, string[]>, errors: Record<string, string> = {}): Fetcher {
  return async (engine, query, locale): Promise<SuggestResponse> => ({
    engine,
    query,
    locale,
    suggestions: table[query] ?? [],
    extra: engine === 'ebay' ? { categories: ['Cat'] } : {},
    error: errors[query] ?? null,
    ms: 1,
  })
}

function run(opts: CrawlOptions, fetcher: Fetcher) {
  const store = new ResultStore()
  return new Promise<{ store: ResultStore; reason: string }>((resolve) => {
    const c = new Crawler(opts, fetcher, store, { onDone: (reason) => resolve({ store, reason }) }, noSleep)
    c.start()
  })
}

describe('expandKeyword', () => {
  it('returns the bare keyword when nothing is enabled', () => {
    expect(expandKeyword(' shoes ', { suffixLetters: false, suffixDigits: false, prefixModifiers: false })).toEqual(['shoes'])
  })
  it('adds letters, digits and modifiers', () => {
    const out = expandKeyword('shoes', { suffixLetters: true, suffixDigits: true, prefixModifiers: true })
    expect(out).toHaveLength(1 + 26 + 10 + MODIFIER_PREFIXES.length)
    expect(out[1]).toBe('shoes a')
    expect(out[26]).toBe('shoes z')
    expect(out[27]).toBe('shoes 0')
    expect(out).toContain('how shoes')
  })
  it('returns nothing for blank input', () => {
    expect(expandKeyword('  ', { suffixLetters: true, suffixDigits: true, prefixModifiers: true })).toEqual([])
  })
})

describe('parseSeeds', () => {
  it('splits on newlines and commas, trims, dedupes', () => {
    expect(parseSeeds('shoes\n Shoes ,socks\r\n\n,hats')).toEqual(['shoes', 'socks', 'hats'])
  })
})

describe('Crawler', () => {
  const base: CrawlOptions = {
    seeds: ['shoes'],
    engines: ['google'],
    locale: 'en-US',
    depth: 0,
    suffixLetters: false,
    suffixDigits: false,
    prefixModifiers: false,
    maxResults: 500,
    delayMs: 0,
  }

  it('collects suggestions for the seed and finishes', async () => {
    const { store, reason } = await run(base, fakeFetcher({ shoes: ['shoes for men', 'shoes for women'] }))
    expect(reason).toBe('complete')
    expect(store.results().map((e) => e.keyword)).toEqual(['shoes for men', 'shoes for women'])
  })

  it('follows suggestions to the configured depth only', async () => {
    const table = { shoes: ['red shoes'], 'red shoes': ['red shoes sale'], 'red shoes sale': ['too deep'] }
    const d1 = await run({ ...base, depth: 1 }, fakeFetcher(table))
    expect(d1.store.results().map((e) => e.keyword)).toEqual(['red shoes', 'red shoes sale'])
    expect(d1.store.get('red shoes sale')!.depth).toBe(1)
    const d2 = await run({ ...base, depth: 2 }, fakeFetcher(table))
    expect(d2.store.results().map((e) => e.keyword)).toContain('too deep')
  })

  it('never queries the same engine twice with the same text', async () => {
    const calls: string[] = []
    const fetcher: Fetcher = async (engine, query, locale) => {
      calls.push(query)
      return { engine, query, locale, suggestions: ['shoes', 'SHOES', 'loop'], extra: {}, error: null, ms: 0 }
    }
    await run({ ...base, depth: 2 }, fetcher)
    expect(calls).toEqual(['shoes', 'loop'])
  })

  it('merges sources across engines and records categories', async () => {
    const { store } = await run({ ...base, engines: ['google', 'ebay'] }, fakeFetcher({ shoes: ['shoes for men'] }))
    expect([...store.get('shoes for men')!.sources]).toEqual(['google', 'ebay'])
    expect(store.getCategories()).toEqual(['Cat'])
  })

  it('stops at maxResults', async () => {
    const table: Record<string, string[]> = { shoes: Array.from({ length: 30 }, (_, i) => `shoes ${i}`) }
    for (let i = 0; i < 30; i++) table[`shoes ${i}`] = [`deep ${i}`]
    const { store, reason } = await run({ ...base, depth: 1, maxResults: 35 }, fakeFetcher(table))
    expect(reason).toBe('limit')
    expect(store.results().length).toBeGreaterThanOrEqual(35)
    expect(store.results().length).toBeLessThan(60)
  })

  it('reports errors and keeps going', async () => {
    const errors: string[] = []
    const store = new ResultStore()
    await new Promise<void>((resolve) => {
      const c = new Crawler(
        { ...base, seeds: ['a', 'b'] },
        fakeFetcher({ b: ['b1'] }, { a: 'boom' }),
        store,
        { onError: (_e, q, m) => errors.push(`${q}:${m}`), onDone: () => resolve() },
        noSleep,
      )
      c.start()
    })
    expect(errors).toEqual(['a:boom'])
    expect(store.results().map((e) => e.keyword)).toEqual(['b1'])
  })

  it('can be stopped mid-run', async () => {
    const store = new ResultStore()
    let c!: Crawler
    const fetcher: Fetcher = async (engine, query, locale) => {
      c.stop()
      return { engine, query, locale, suggestions: ['x'], extra: {}, error: null, ms: 0 }
    }
    const reason = await new Promise<string>((resolve) => {
      c = new Crawler({ ...base, seeds: ['a', 'b', 'c'] }, fetcher, store, { onDone: resolve }, noSleep)
      c.start()
    })
    expect(reason).toBe('stopped')
    expect(c.isRunning).toBe(false)
  })
})
