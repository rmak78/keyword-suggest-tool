import type { HealthReport } from './api'
import type { Progress } from './crawler'
import { ENGINES, ENGINE_BY_ID, ENGINE_GROUPS, LOCALES, type EngineId } from './engines'
import type { Entry } from './store'

export type SortKey = 'order' | 'keyword' | 'sources' | 'depth'

export interface ViewFilter {
  text: string
  sort: SortKey
  engine: EngineId | 'all'
}

export function $<T extends HTMLElement = HTMLElement>(id: string): T {
  const el = document.getElementById(id)
  if (!el) throw new Error(`Missing element #${id}`)
  return el as T
}

export function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!)
}

// --- Engine picker ---------------------------------------------------------

export function renderEnginePicker(container: HTMLElement, selected: Set<EngineId>): void {
  container.innerHTML = ENGINE_GROUPS.map((group) => {
    const items = ENGINES.filter((e) => e.group === group)
    if (!items.length) return ''
    return `
      <div>
        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">${group}</div>
        <div class="flex flex-wrap gap-1.5">
          ${items
            .map(
              (e) => `
            <label class="engine-pill group" data-engine="${e.id}" title="${e.note ? escapeHtml(e.note) : e.label}">
              <input type="checkbox" class="peer sr-only" value="${e.id}" ${selected.has(e.id) ? 'checked' : ''} />
              <span class="engine-pill-inner">
                <span class="status-dot bg-slate-300 dark:bg-slate-600" data-status="${e.id}" title="Not checked yet"></span>
                ${e.label}
              </span>
            </label>`,
            )
            .join('')}
        </div>
      </div>`
  }).join('')
}

export function applyHealth(container: HTMLElement, report: HealthReport | null, error?: string): void {
  for (const e of ENGINES) {
    const dot = container.querySelector<HTMLElement>(`[data-status="${e.id}"]`)
    const pill = container.querySelector<HTMLElement>(`[data-engine="${e.id}"]`)
    if (!dot || !pill) continue
    dot.classList.remove('bg-slate-300', 'dark:bg-slate-600', 'bg-emerald-500', 'bg-red-500', 'bg-amber-400', 'animate-pulse')
    if (!report) {
      dot.classList.add('bg-amber-400')
      pill.title = error ? `Status check failed: ${error}` : 'Checking…'
      continue
    }
    const h = report.engines[e.id]
    if (!h) {
      dot.classList.add('bg-slate-300', 'dark:bg-slate-600')
      pill.title = 'Not available on this server'
      continue
    }
    if (h.ok) {
      dot.classList.add('bg-emerald-500')
      pill.title = `${e.label}: OK (${h.count} suggestions in ${h.ms} ms)`
    } else {
      dot.classList.add('bg-red-500')
      pill.title = `${e.label}: ${h.error ?? 'No results'} — you can still try it`
    }
  }
}

export function setPending(container: HTMLElement): void {
  container.querySelectorAll<HTMLElement>('[data-status]').forEach((d) => {
    d.classList.add('animate-pulse', 'bg-amber-400')
    d.classList.remove('bg-slate-300', 'dark:bg-slate-600')
  })
}

export function selectedEngines(container: HTMLElement): EngineId[] {
  return [...container.querySelectorAll<HTMLInputElement>('input[type=checkbox]:checked')].map((i) => i.value as EngineId)
}

export function renderLocaleSelect(select: HTMLSelectElement, current: string): void {
  select.innerHTML = LOCALES.map((l) => `<option value="${l.code}" ${l.code === current ? 'selected' : ''}>${escapeHtml(l.label)}</option>`).join('')
}

// --- Progress --------------------------------------------------------------

export function renderProgress(p: Progress, els: { bar: HTMLElement; text: HTMLElement; current: HTMLElement; engines: HTMLElement }): void {
  const pct = p.total > 0 ? Math.min(100, Math.round((p.done / p.total) * 100)) : 0
  els.bar.style.width = `${pct}%`
  els.bar.classList.toggle('animate-pulse', p.running)
  els.text.textContent = p.running
    ? `${p.done} / ${p.total} queries · ${p.queued} queued`
    : p.total > 0
      ? `${p.done} queries done`
      : 'Idle'
  const active = Object.values(p.currentQuery)
  els.current.textContent = active.length ? active[active.length - 1]! : ''
  els.engines.innerHTML = Object.entries(p.engines)
    .map(([id, s]) => {
      const e = ENGINE_BY_ID[id as EngineId]
      const err = s.lastError ? ` title="Last error: ${escapeHtml(s.lastError)}"` : ''
      return `<span class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs ${e.chip}"${err}>
        <span class="inline-block h-1.5 w-1.5 rounded-full ${e.dot}"></span>${e.label}
        <span class="font-semibold tabular-nums">${s.found}</span>
        ${s.errors ? `<span class="text-red-600 dark:text-red-300">⚠ ${s.errors}</span>` : ''}
      </span>`
    })
    .join('')
}

// --- Results ---------------------------------------------------------------

export function filterAndSort(entries: Entry[], f: ViewFilter): Entry[] {
  const q = f.text.trim().toLowerCase()
  let out = entries
  if (q) out = out.filter((e) => e.keyword.toLowerCase().includes(q))
  if (f.engine !== 'all') out = out.filter((e) => e.sources.has(f.engine as EngineId))
  const sorted = [...out]
  switch (f.sort) {
    case 'keyword':
      sorted.sort((a, b) => a.keyword.localeCompare(b.keyword))
      break
    case 'sources':
      sorted.sort((a, b) => b.sources.size - a.sources.size || a.order - b.order)
      break
    case 'depth':
      sorted.sort((a, b) => a.depth - b.depth || a.order - b.order)
      break
    default:
      sorted.sort((a, b) => a.order - b.order)
  }
  return sorted
}

const MAX_RENDER = 2000

export function renderResults(tbody: HTMLElement, entries: Entry[], filterText: string): void {
  if (!entries.length) {
    tbody.innerHTML = ''
    return
  }
  const q = filterText.trim()
  const rows = entries.slice(0, MAX_RENDER).map((e, i) => {
    const kw = q ? highlight(e.keyword, q) : escapeHtml(e.keyword)
    const chips = [...e.sources]
      .map((id) => {
        const en = ENGINE_BY_ID[id]
        return `<span class="chip ${en.chip}" title="${en.label}">${en.label}</span>`
      })
      .join('')
    return `<tr class="group border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60">
      <td class="w-10 px-3 py-1.5 text-right text-xs tabular-nums text-slate-400">${i + 1}</td>
      <td class="px-3 py-1.5 font-medium text-slate-800 dark:text-slate-100">${kw}</td>
      <td class="px-3 py-1.5"><div class="flex flex-wrap gap-1">${chips}</div></td>
      <td class="hidden w-16 px-3 py-1.5 text-center text-xs tabular-nums text-slate-500 sm:table-cell">${e.depth}</td>
      <td class="hidden px-3 py-1.5 text-xs text-slate-500 md:table-cell">${escapeHtml(e.seed)}</td>
      <td class="w-10 px-2 py-1.5 text-right">
        <button type="button" class="copy-one rounded p-1 text-slate-400 opacity-0 transition hover:bg-slate-200 hover:text-slate-700 group-hover:opacity-100 focus:opacity-100 dark:hover:bg-slate-700 dark:hover:text-slate-100" data-copy="${escapeHtml(e.keyword)}" title="Copy">
          <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H7Zm0 1.5h7a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5H7a.5.5 0 0 1-.5-.5V5a.5.5 0 0 1 .5-.5Z"/><path d="M3.5 7a.75.75 0 0 0-1.5 0v8A3.5 3.5 0 0 0 5.5 18.5h6a.75.75 0 0 0 0-1.5h-6a2 2 0 0 1-2-2V7Z"/></svg>
        </button>
      </td>
    </tr>`
  })
  if (entries.length > MAX_RENDER) {
    rows.push(`<tr><td colspan="6" class="px-3 py-2 text-center text-xs text-slate-500">Showing first ${MAX_RENDER} of ${entries.length}. Use the filter or export to see everything.</td></tr>`)
  }
  tbody.innerHTML = rows.join('')
}

function highlight(text: string, q: string): string {
  const idx = text.toLowerCase().indexOf(q.toLowerCase())
  if (idx < 0) return escapeHtml(text)
  return `${escapeHtml(text.slice(0, idx))}<mark class="rounded bg-yellow-200 px-0.5 dark:bg-yellow-500/40 dark:text-inherit">${escapeHtml(text.slice(idx, idx + q.length))}</mark>${escapeHtml(text.slice(idx + q.length))}`
}

export function renderCategories(container: HTMLElement, categories: string[]): void {
  if (!categories.length) {
    container.classList.add('hidden')
    container.innerHTML = ''
    return
  }
  container.classList.remove('hidden')
  container.innerHTML = `<span class="mr-1 text-xs font-semibold text-slate-500 dark:text-slate-400">eBay categories:</span>${categories
    .map((c) => `<span class="chip bg-lime-100 text-lime-800 dark:bg-lime-500/20 dark:text-lime-200">${escapeHtml(c)}</span>`)
    .join('')}`
}

// --- Toasts ----------------------------------------------------------------

let toastTimer: number | undefined

export function toast(message: string, kind: 'info' | 'success' | 'error' = 'info'): void {
  const el = $('toast')
  el.textContent = message
  el.className = `pointer-events-none fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-lg px-4 py-2 text-sm font-medium shadow-lg transition-opacity ${
    kind === 'error'
      ? 'bg-red-600 text-white'
      : kind === 'success'
        ? 'bg-emerald-600 text-white'
        : 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900'
  }`
  el.style.opacity = '1'
  window.clearTimeout(toastTimer)
  toastTimer = window.setTimeout(() => {
    el.style.opacity = '0'
  }, 2500)
}
