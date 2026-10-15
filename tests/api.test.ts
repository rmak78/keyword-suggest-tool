import { describe, expect, it } from 'vitest'
import { normaliseExtra } from '../src/api'

describe('normaliseExtra', () => {
  it('accepts PHP empty array and missing values', () => {
    expect(normaliseExtra([])).toEqual({})
    expect(normaliseExtra(null)).toEqual({})
    expect(normaliseExtra(undefined)).toEqual({})
  })
  it('keeps only string categories', () => {
    expect(normaliseExtra({ categories: ['a', 1, 'b'] })).toEqual({ categories: ['a', 'b'] })
  })
})
