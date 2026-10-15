import './style.css'
import { fetchHealth, fetchSuggestions, type HealthReport } from './api'
import { Crawler, DEFAULT_OPTIONS, parseSeeds, type CrawlOptions, type Progress } from './crawler'
import { DEFAULT_ENGINES, ENGINES, isEngineId, type EngineId } from './engines'
import { copyText, download, exportFilename, toCSV, toTXT } from './export'
import { ResultStore } from './store'
import { cycleTheme, getTheme, initTheme } from './theme'
import {
  $,
  applyHealth,
  filterAndSort,
  renderCategories,
  renderEnginePicker,
  renderLocaleSelect,
  renderProgress,
  renderResults,
  selectedEngines,
  setPending,
  toast,
  type SortKey,
  type ViewFilter,
} from './ui'

const PREFS_KEY = 'kst.prefs'
const HEALTH_KEY = 'kst.health'
const HEALTH_TTL = 10 * 60 * 1000

interface Prefs {
  engines: EngineId[]
  locale: string
  depth: number
  suffixLetters: boolean
  suffixDigits: boolean
  prefixModifiers: boolean
  maxResults: number
  delayMs: number
}

function loadPrefs(): Prefs {
  const base: Prefs = { engines: DEFAULT_ENGINES, ...DEFAULT_OPTIONS }
  try {
    const raw = localStorage.getItem(PREFS_KEY)
    if (!raw) return base
    const p = JSON.parse(raw) as Partial<Prefs>
    return {
      ...base,
      ...p,
      engines: Array.isArray(p.engines) ? p.engines.filter(isEngineId) : base.engines,
    }
  } catch {
    return base
  }
}

function savePrefs(p: Prefs): void {
  try {
    localStorage.setItem(PREFS_KEY, JSON.stringify(p))
  } catch {
    /* ignore */
  }
}

// --- Bootstrap -------------------------------------------------------------

initTheme()

const store = new ResultStore()
let crawler: Crawler | null = null
let lastSeeds: string[] = []
let renderQueued = false

const els = {
  seeds: $<HTMLTextAreaElement>('seeds'),
  engines: $('engines'),
  locale: $<HTMLSelectElement>('locale'),
  depth: $<HTMLSelectElement>('depth'),
  suffixLetters: $<HTMLInputElement>('opt-letters'),
  suffixDigits: $<HTMLInputElement>('opt-digits'),
  prefixModifiers: $<HTMLInputElement>('opt-modifiers'),
  maxResults: $<HTMLInputElement>('opt-max'),
  delayMs: $<HTMLInputElement>('opt-delay'),
  start: $<HTMLButtonElement>('start'),
  clear: $<HTMLButtonElement>('clear'),
  recheck: $<HTMLButtonElement>('recheck'),
  selectAll: $<HTMLButtonElement>('select-all'),
  selectNone: $<HTMLButtonElement>('select-none'),
  progressBar: $('progress-bar'),
  progressText: $('progress-text'),
  currentQuery: $('current-query'),
  engineStats: $('engine-stats'),
  filter: $<HTMLInputElement>('filter'),
  sort: $<HTMLSelectElement>('sort'),
  sourceFilter: $<HTMLSelectElement>('source-filter'),
  count: $('count'),
  tbody: $('results'),
  empty: $('empty'),
  categories: $('categories'),
  copyAll: $<HTMLButtonElement>('copy-all'),
  exportCsv: $<HTMLButtonElement>('export-csv'),
  exportTxt: $<HTMLButtonElement>('export-txt'),
  theme: $<HTMLButtonElement>('theme-toggle'),
  themeLabel: $('theme-label'),
  serverInfo: $('server-info'),
}

const prefs = loadPrefs()
renderEnginePicker(els.engines, new Set(prefs.engines))
renderLocaleSelect(els.locale, prefs.locale)
els.depth.value = String(prefs.depth)
els.suffixLetters.checked = prefs.suffixLetters
els.suffixDigits.checked = prefs.suffixDigits
els.prefixModifiers.checked = prefs.prefixModifiers
els.maxResults.value = String(prefs.maxResults)
els.delayMs.value = String(prefs.delayMs)
els.sourceFilter.innerHTML = `<option value="all">All sources</option>${ENGINES.map((e) => `<option value="${e.id}">${e.label}</option>`).join('')}`
updateThemeLabel()

// Pre-fill seeds from ?keyword= (kept from v1) or ?q=
const params = new URLSearchParams(location.search)
const initial = params.get('keyword') ?? params.get('q')
if (initial) els.seeds.value = parseSeeds(initial).join('\n')

// --- Engine health ---------------------------------------------------------

async function runHealth(force = false): Promise<void> {
  if (!force) {
    try {
      const cached = localStorage.getItem(HEALTH_KEY)
      if (cached) {
        const { at, report } = JSON.parse(cached) as { at: number; report: HealthReport }
        if (Date.now() - at < HEALTH_TTL) {
          applyHealth(els.engines, report)
          showServerInfo(report)
          return
        }
      }
    } catch {
      /* ignore */
    }
  }
  setPending(els.engines)
  els.recheck.disabled = true
  try {
    const report = await fetchHealth()
    applyHealth(els.engines, report)
    showServerInfo(report)
    try {
      localStorage.setItem(HEALTH_KEY, JSON.stringify({ at: Date.now(), report }))
    } catch {
      /* ignore */
    }
  } catch (err) {
    applyHealth(els.engines, null, (err as Error).message)
    els.serverInfo.textContent = `Status check failed: ${(err as Error).message}`
  } finally {
    els.recheck.disabled = false
  }
}

function showServerInfo(report: HealthReport): void {
  const ok = Object.values(report.engines).filter((e) => e.ok).length
  const total = Object.keys(report.engines).length
  els.serverInfo.textContent = `${ok}/${total} engines responding · PHP ${report.php}${report.curl ? '' : ' · curl missing!'}`
}

void runHealth()

// --- Crawl control ---------------------------------------------------------

function currentOptions(): CrawlOptions {
  const engines = selectedEngines(els.engines)
  const opts: CrawlOptions = {
    seeds: parseSeeds(els.seeds.value),
    engines,
    locale: els.locale.value,
    depth: Number(els.depth.value) || 0,
    suffixLetters: els.suffixLetters.checked,
    suffixDigits: els.suffixDigits.checked,
    prefixModifiers: els.prefixModifiers.checked,
    maxResults: Math.max(10, Number(els.maxResults.value) || DEFAULT_OPTIONS.maxResults),
    delayMs: Math.max(0, Number(els.delayMs.value) || 0),
  }
  const { seeds: _seeds, ...rest } = opts
  savePrefs(rest)
  return opts
}

function setRunning(running: boolean): void {
  els.start.textContent = running ? 'Stop' : 'Start searching'
  els.start.classList.toggle('btn-danger', running)
  els.start.classList.toggle('btn-primary', !running)
  els.seeds.disabled = running
  els.engines.classList.toggle('pointer-events-none', running)
  els.engines.classList.toggle('opacity-60', running)
}

function start(): void {
  if (crawler?.isRunning) {
    crawler.stop()
    return
  }
  const opts = currentOptions()
  if (!opts.seeds.length) {
    toast('Enter at least one seed keyword', 'error')
    els.seeds.focus()
    return
  }
  if (!opts.engines.length) {
    toast('Select at least one engine', 'error')
    return
  }
  store.clear()
  lastSeeds = opts.seeds
  scheduleRender()
  setRunning(true)

  crawler = new Crawler(opts, fetchSuggestions, store, {
    onProgress: onProgress,
    onBatch: () => scheduleRender(),
    onDone: (reason) => {
      setRunning(false)
      scheduleRender()
      const n = store.results().length
      toast(
        reason === 'stopped'
          ? `Stopped with ${n} keywords`
          : reason === 'limit'
            ? `Reached the ${opts.maxResults} keyword limit`
            : `Done: ${n} keywords found`,
        reason === 'complete' ? 'success' : 'info',
      )
    },
  })
  crawler.start()
}

function onProgress(p: Progress): void {
  renderProgress(p, { bar: els.progressBar, text: els.progressText, current: els.currentQuery, engines: els.engineStats })
}

// --- Results view ----------------------------------------------------------

function viewFilter(): ViewFilter {
  return { text: els.filter.value, sort: els.sort.value as SortKey, engine: els.sourceFilter.value as ViewFilter['engine'] }
}

function scheduleRender(): void {
  if (renderQueued) return
  renderQueued = true
  requestAnimationFrame(() => {
    renderQueued = false
    render()
  })
}

function render(): void {
  const all = store.results()
  const visible = filterAndSort(all, viewFilter())
  renderResults(els.tbody, visible, els.filter.value)
  renderCategories(els.categories, store.getCategories())
  els.empty.classList.toggle('hidden', all.length > 0)
  els.count.textContent = visible.length === all.length ? `${all.length} keywords` : `${visible.length} of ${all.length} keywords`
  const hasResults = all.length > 0
  els.copyAll.disabled = !hasResults
  els.exportCsv.disabled = !hasResults
  els.exportTxt.disabled = !hasResults
}

function visibleEntries() {
  return filterAndSort(store.results(), viewFilter())
}

// --- Wiring ----------------------------------------------------------------

els.start.addEventListener('click', start)
els.clear.addEventListener('click', () => {
  if (crawler?.isRunning) crawler.stop()
  store.clear()
  els.seeds.value = ''
  onProgress({ running: false, done: 0, queued: 0, total: 0, results: 0, currentQuery: {}, engines: {} })
  scheduleRender()
  els.seeds.focus()
})
els.recheck.addEventListener('click', () => void runHealth(true))
els.selectAll.addEventListener('click', () => els.engines.querySelectorAll<HTMLInputElement>('input').forEach((i) => (i.checked = true)))
els.selectNone.addEventListener('click', () => els.engines.querySelectorAll<HTMLInputElement>('input').forEach((i) => (i.checked = false)))
els.engines.addEventListener('change', () => currentOptions())
for (const el of [els.locale, els.depth, els.suffixLetters, els.suffixDigits, els.prefixModifiers, els.maxResults, els.delayMs]) {
  el.addEventListener('change', () => currentOptions())
}

els.filter.addEventListener('input', scheduleRender)
els.sort.addEventListener('change', scheduleRender)
els.sourceFilter.addEventListener('change', scheduleRender)

els.copyAll.addEventListener('click', async () => {
  const ok = await copyText(toTXT(visibleEntries()))
  toast(ok ? 'Copied to clipboard' : 'Copy failed', ok ? 'success' : 'error')
})
els.exportCsv.addEventListener('click', () => {
  download(exportFilename(lastSeeds, 'csv'), toCSV(visibleEntries()), 'text/csv;charset=utf-8')
})
els.exportTxt.addEventListener('click', () => {
  download(exportFilename(lastSeeds, 'txt'), toTXT(visibleEntries()), 'text/plain;charset=utf-8')
})
els.tbody.addEventListener('click', async (ev) => {
  const btn = (ev.target as HTMLElement).closest<HTMLElement>('.copy-one')
  if (!btn) return
  const ok = await copyText(btn.dataset.copy ?? '')
  toast(ok ? `Copied “${btn.dataset.copy}”` : 'Copy failed', ok ? 'success' : 'error')
})

els.theme.addEventListener('click', () => {
  cycleTheme()
  updateThemeLabel()
})
function updateThemeLabel(): void {
  const t = getTheme()
  els.themeLabel.textContent = t === 'system' ? 'Auto' : t === 'dark' ? 'Dark' : 'Light'
}

document.addEventListener('keydown', (ev) => {
  if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') {
    ev.preventDefault()
    start()
  }
  if (ev.key === 'Escape' && crawler?.isRunning) crawler.stop()
})

render()
