import { describe, expect, it } from 'vitest'
import { ResultStore, normaliseKey } from '../src/store'

describe('normaliseKey', () => {
  it('ignores case and whitespace', () => {
    expect(normaliseKey('  Running   Shoes ')).toBe('running shoes')
  })
})

describe('ResultStore', () => {
  it('dedupes case-insensitively and merges sources', () => {
    const s = new ResultStore()
    expect(s.add('Running Shoes', 'google', 'shoes', 0)).toBe(true)
    expect(s.add('running  shoes', 'bing', 'shoes', 1)).toBe(false)
    expect(s.size).toBe(1)
    const e = s.get('running shoes')!
    expect(e.keyword).toBe('Running Shoes')
    expect([...e.sources]).toEqual(['google', 'bing'])
    expect(e.depth).toBe(0)
  })

  it('keeps the shallowest depth', () => {
    const s = new ResultStore()
    s.add('a', 'google', 'a', 2)
    s.add('a', 'google', 'a', 1)
    expect(s.get('a')!.depth).toBe(1)
  })

  it('excludes seeds from results() and ignores blanks', () => {
    const s = new ResultStore()
    s.add('shoes', null, 'shoes', 0, true)
    s.add('   ', 'google', 'shoes', 0)
    s.add('shoes for men', 'google', 'shoes', 0)
    expect(s.size).toBe(2)
    expect(s.results().map((e) => e.keyword)).toEqual(['shoes for men'])
  })

  it('tracks categories and clears', () => {
    const s = new ResultStore()
    s.addCategory('Shoes')
    s.addCategory(' ')
    expect(s.getCategories()).toEqual(['Shoes'])
    s.clear()
    expect(s.getCategories()).toEqual([])
    expect(s.size).toBe(0)
  })
})
