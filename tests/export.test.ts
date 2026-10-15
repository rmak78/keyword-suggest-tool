import { describe, expect, it } from 'vitest'
import { csvField, exportFilename, toCSV, toTXT } from '../src/export'
import type { Entry } from '../src/store'

const entry = (keyword: string, sources: Entry['sources'], depth = 0, seed = 'shoes'): Entry => ({
  keyword,
  sources,
  depth,
  seed,
  order: 0,
  isSeed: false,
})

describe('csvField', () => {
  it('quotes fields with commas, quotes or newlines', () => {
    expect(csvField('plain')).toBe('plain')
    expect(csvField('a, b')).toBe('"a, b"')
    expect(csvField('say "hi"')).toBe('"say ""hi"""')
    expect(csvField('line\nbreak')).toBe('"line\nbreak"')
    expect(csvField(3)).toBe('3')
  })
})

describe('toCSV', () => {
  it('starts with a BOM and uses engine labels', () => {
    const csv = toCSV([entry('shoes, red', new Set(['google', 'ebay']), 1)])
    expect(csv.charCodeAt(0)).toBe(0xfeff)
    expect(csv.slice(1)).toBe('keyword,sources,depth,seed\r\n"shoes, red",Google; eBay,1,shoes\r\n')
  })
})

describe('toTXT', () => {
  it('joins keywords with newlines', () => {
    expect(toTXT([entry('a', new Set()), entry('b', new Set())])).toBe('a\nb')
  })
})

describe('exportFilename', () => {
  it('slugifies the first seed and adds a date', () => {
    expect(exportFilename(['Running Shoes!'], 'csv')).toMatch(/^running-shoes-keywords-\d{4}-\d{2}-\d{2}\.csv$/)
    expect(exportFilename([], 'txt')).toMatch(/^keywords-keywords-/)
  })
})
