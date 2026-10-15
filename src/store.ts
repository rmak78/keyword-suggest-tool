import type { EngineId } from './engines'

export interface Entry {
  /** Display form (first spelling seen). */
  keyword: string
  sources: Set<EngineId>
  /** Seed keyword this entry descends from. */
  seed: string
  /** 0 = direct suggestion for a seed query, 1 = suggestion of a suggestion, ... */
  depth: number
  /** Insertion order, used for the default sort. */
  order: number
  isSeed: boolean
}

/** Case- and whitespace-insensitive key. */
export function normaliseKey(keyword: string): string {
  return keyword.trim().replace(/\s+/g, ' ').toLowerCase()
}

export class ResultStore {
  private readonly map = new Map<string, Entry>()
  private readonly categories = new Set<string>()
  private counter = 0

  get size(): number {
    return this.map.size
  }

  clear(): void {
    this.map.clear()
    this.categories.clear()
    this.counter = 0
  }

  has(keyword: string): boolean {
    return this.map.has(normaliseKey(keyword))
  }

  get(keyword: string): Entry | undefined {
    return this.map.get(normaliseKey(keyword))
  }

  /**
   * Record a keyword from an engine. Returns true when the keyword is new.
   * Known keywords just gain the engine as an extra source.
   */
  add(keyword: string, engine: EngineId | null, seed: string, depth: number, isSeed = false): boolean {
    const display = keyword.trim().replace(/\s+/g, ' ')
    if (display === '') return false
    const key = normaliseKey(display)
    const existing = this.map.get(key)
    if (existing) {
      if (engine) existing.sources.add(engine)
      if (depth < existing.depth) existing.depth = depth
      return false
    }
    this.map.set(key, {
      keyword: display,
      sources: new Set(engine ? [engine] : []),
      seed,
      depth,
      order: this.counter++,
      isSeed,
    })
    return true
  }

  addCategory(category: string): void {
    const c = category.trim()
    if (c) this.categories.add(c)
  }

  getCategories(): string[] {
    return [...this.categories]
  }

  values(): Entry[] {
    return [...this.map.values()]
  }

  /** Entries that came from an engine (seeds the user typed are excluded). */
  results(): Entry[] {
    return this.values().filter((e) => !e.isSeed)
  }
}
