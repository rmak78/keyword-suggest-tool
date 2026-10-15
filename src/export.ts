import type { Entry } from './store'
import { ENGINE_BY_ID } from './engines'

export function csvField(value: string | number): string {
  const s = String(value)
  return /[",\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
}

/** CSV with a UTF-8 BOM so Excel opens it with the right encoding. */
export function toCSV(entries: Entry[]): string {
  const header = ['keyword', 'sources', 'depth', 'seed'].join(',')
  const rows = entries.map((e) =>
    [
      csvField(e.keyword),
      csvField([...e.sources].map((id) => ENGINE_BY_ID[id].label).join('; ')),
      csvField(e.depth),
      csvField(e.seed),
    ].join(','),
  )
  return '﻿' + [header, ...rows].join('\r\n') + '\r\n'
}

export function toTXT(entries: Entry[]): string {
  return entries.map((e) => e.keyword).join('\n')
}

export function download(filename: string, content: string, mime: string): void {
  const blob = new Blob([content], { type: mime })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    const ok = document.execCommand('copy')
    ta.remove()
    return ok
  }
}

export function exportFilename(seeds: string[], ext: string): string {
  const slug = (seeds[0] ?? 'keywords').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40) || 'keywords'
  const date = new Date().toISOString().slice(0, 10)
  return `${slug}-keywords-${date}.${ext}`
}
