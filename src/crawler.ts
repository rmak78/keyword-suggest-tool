import type { Fetcher, SuggestResponse } from './api'
import type { EngineId } from './engines'
import { ResultStore } from './store'

export interface CrawlOptions {
  seeds: string[]
  engines: EngineId[]
  locale: string
  /** How many levels of suggestions-of-suggestions to follow (0 = seeds only). */
  depth: number
  suffixLetters: boolean
  suffixDigits: boolean
  prefixModifiers: boolean
  /** Stop once this many unique keywords have been collected. */
  maxResults: number
  /** Minimum gap between two requests to the same engine. */
  delayMs: number
}

export const DEFAULT_OPTIONS: Omit<CrawlOptions, 'seeds' | 'engines'> = {
  locale: 'en-US',
  depth: 1,
  suffixLetters: true,
  suffixDigits: false,
  prefixModifiers: false,
  maxResults: 500,
  delayMs: 300,
}

export const MODIFIER_PREFIXES = ['how', 'what', 'why', 'when', 'where', 'which', 'who', 'best', 'top', 'cheap', 'free', 'vs', 'near me', 'is', 'can']

export interface Task {
  query: string
  seed: string
  depth: number
}

/**
 * Build the list of queries to run for one keyword.
 * Letters/digits are appended ("shoes a", "shoes b"...), modifiers are prepended ("how shoes").
 */
export function expandKeyword(keyword: string, opts: Pick<CrawlOptions, 'suffixLetters' | 'suffixDigits' | 'prefixModifiers'>): string[] {
  const base = keyword.trim().replace(/\s+/g, ' ')
  if (!base) return []
  const out = [base]
  if (opts.suffixLetters) {
    for (let i = 0; i < 26; i++) out.push(`${base} ${String.fromCharCode(97 + i)}`)
  }
  if (opts.suffixDigits) {
    for (let i = 0; i <= 9; i++) out.push(`${base} ${i}`)
  }
  if (opts.prefixModifiers) {
    for (const m of MODIFIER_PREFIXES) out.push(`${m} ${base}`)
  }
  return out
}

/** Parse the seed textarea: one keyword per line, commas also accepted. */
export function parseSeeds(text: string): string[] {
  const seen = new Set<string>()
  const out: string[] = []
  for (const raw of text.split(/[\r\n,]+/)) {
    const s = raw.trim().replace(/\s+/g, ' ')
    const key = s.toLowerCase()
    if (s && !seen.has(key)) {
      seen.add(key)
      out.push(s)
    }
  }
  return out
}

export interface EngineStats {
  requests: number
  found: number
  errors: number
  lastError: string | null
  queued: number
}

export interface Progress {
  running: boolean
  done: number
  queued: number
  total: number
  results: number
  currentQuery: Record<string, string>
  engines: Record<string, EngineStats>
}

export interface CrawlerEvents {
  onProgress?: (p: Progress) => void
  onBatch?: (added: number, response: SuggestResponse) => void
  onError?: (engine: EngineId, query: string, message: string) => void
  onDone?: (reason: 'complete' | 'stopped' | 'limit') => void
}

export class Crawler {
  private readonly queues = new Map<EngineId, Task[]>()
  private readonly seenQueries = new Map<EngineId, Set<string>>()
  private readonly stats = new Map<EngineId, EngineStats>()
  private readonly current = new Map<EngineId, string>()
  private controller: AbortController | null = null
  private running = false
  private done = 0
  private enqueued = 0
  private finishedReason: 'complete' | 'stopped' | 'limit' | null = null
  private activeWorkers = 0

  constructor(
    private readonly opts: CrawlOptions,
    private readonly fetcher: Fetcher,
    private readonly store: ResultStore,
    private readonly events: CrawlerEvents = {},
    private readonly sleep: (ms: number, signal: AbortSignal) => Promise<void> = defaultSleep,
  ) {}

  get isRunning(): boolean {
    return this.running
  }

  start(): void {
    if (this.running) return
    this.running = true
    this.finishedReason = null
    this.controller = new AbortController()
    this.done = 0
    this.enqueued = 0

    for (const engine of this.opts.engines) {
      this.queues.set(engine, [])
      this.seenQueries.set(engine, new Set())
      this.stats.set(engine, { requests: 0, found: 0, errors: 0, lastError: null, queued: 0 })
    }
    for (const seed of this.opts.seeds) {
      this.store.add(seed, null, seed, 0, true)
      for (const engine of this.opts.engines) this.enqueue(engine, seed, seed, 0)
    }

    this.emitProgress()
    this.activeWorkers = this.opts.engines.length
    for (const engine of this.opts.engines) {
      void this.worker(engine).finally(() => {
        this.activeWorkers--
        if (this.activeWorkers === 0) this.finish(this.finishedReason ?? 'complete')
      })
    }
    if (this.opts.engines.length === 0) this.finish('complete')
  }

  stop(): void {
    if (!this.running) return
    this.finishedReason = 'stopped'
    this.controller?.abort()
  }

  snapshot(): Progress {
    let queued = 0
    const engines: Record<string, EngineStats> = {}
    for (const [id, q] of this.queues) {
      const s = this.stats.get(id)!
      s.queued = q.length
      queued += q.length
      engines[id] = { ...s }
    }
    return {
      running: this.running,
      done: this.done,
      queued,
      total: this.enqueued,
      results: this.store.results().length,
      currentQuery: Object.fromEntries(this.current),
      engines,
    }
  }

  private enqueue(engine: EngineId, keyword: string, seed: string, depth: number): void {
    const seen = this.seenQueries.get(engine)!
    const queue = this.queues.get(engine)!
    for (const query of expandKeyword(keyword, this.opts)) {
      const key = query.toLowerCase()
      if (seen.has(key)) continue
      seen.add(key)
      queue.push({ query, seed, depth })
      this.enqueued++
    }
  }

  private async worker(engine: EngineId): Promise<void> {
    const queue = this.queues.get(engine)!
    const stats = this.stats.get(engine)!
    const signal = this.controller!.signal

    while (!signal.aborted) {
      const task = queue.shift()
      if (!task) break
      if (this.store.results().length >= this.opts.maxResults) {
        this.finishedReason = this.finishedReason ?? 'limit'
        this.controller?.abort()
        break
      }

      this.current.set(engine, task.query)
      this.emitProgress()
      stats.requests++

      let response: SuggestResponse
      try {
        response = await this.fetcher(engine, task.query, this.opts.locale, signal)
      } catch (err) {
        if (signal.aborted) break
        response = { engine, query: task.query, locale: this.opts.locale, suggestions: [], extra: {}, error: (err as Error).message, ms: 0 }
      }
      if (signal.aborted) break

      this.done++
      if (response.error) {
        stats.errors++
        stats.lastError = response.error
        this.events.onError?.(engine, task.query, response.error)
      }

      let added = 0
      for (const suggestion of response.suggestions) {
        const isNew = this.store.add(suggestion, engine, task.seed, task.depth)
        if (isNew) {
          added++
          stats.found++
          if (task.depth < this.opts.depth) this.enqueue(engine, suggestion, task.seed, task.depth + 1)
        }
      }
      for (const c of response.extra.categories ?? []) this.store.addCategory(c)

      this.events.onBatch?.(added, response)
      this.emitProgress()

      if (queue.length > 0 && this.opts.delayMs > 0) {
        try {
          await this.sleep(this.opts.delayMs, signal)
        } catch {
          break
        }
      }
    }
    this.current.delete(engine)
  }

  private finish(reason: 'complete' | 'stopped' | 'limit'): void {
    if (!this.running) return
    this.running = false
    this.current.clear()
    this.emitProgress()
    this.events.onDone?.(reason)
  }

  private emitProgress(): void {
    this.events.onProgress?.(this.snapshot())
  }
}

function defaultSleep(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve, reject) => {
    if (signal.aborted) return reject(new Error('aborted'))
    const t = setTimeout(() => {
      signal.removeEventListener('abort', onAbort)
      resolve()
    }, ms)
    const onAbort = () => {
      clearTimeout(t)
      reject(new Error('aborted'))
    }
    signal.addEventListener('abort', onAbort, { once: true })
  })
}
