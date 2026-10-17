import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'
import { execFileSync } from 'node:child_process'

const ART = new URL('../docs', import.meta.url).pathname
const WP = new URL('../plugins/wordpress/assets', import.meta.url).pathname
const OC = new URL('../plugins/opencart/assets', import.meta.url).pathname
mkdirSync(WP, { recursive: true })
mkdirSync(OC, { recursive: true })

const browser = await chromium.launch({ headless: true })
const page = await browser.newPage({ viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 })
await page.goto('file://' + ART + '/artwork.html')
await page.waitForTimeout(1500) // fonts

async function shot(id, path, scale = 1) {
  const el = page.locator('#' + id)
  await page.setViewportSize({ width: 1600, height: 1000 })
  if (scale !== 1) {
    await page.evaluate(([i, s]) => { document.getElementById(i).style.zoom = s }, [id, scale])
  }
  await el.screenshot({ path, omitBackground: true })
  if (scale !== 1) await page.evaluate((i) => { document.getElementById(i).style.zoom = '' }, id)
}

// wordpress.org: icon 256 + 128 (via 512 downscale), banners 1544x500 + 772x250
await shot('icon', `${OC}/icon-512x512.png`)
await shot('banner', `${WP}/banner-1544x500.png`)
await shot('banner', `${WP}/banner-772x250.png`, 0.5)
await shot('feature', `${OC}/feature-1200x800.png`)
await browser.close()

// Downscale with Pillow for crisp small sizes
execFileSync('python3', ['-I', '-c', `
from PIL import Image
src = Image.open('${OC}/icon-512x512.png').convert('RGBA')
for size, path in [(256, '${WP}/icon-256x256.png'), (128, '${WP}/icon-128x128.png'), (512, '${OC}/icon-512x512.png'), (100, '${OC}/icon-100x100.png')]:
    src.resize((size, size), Image.LANCZOS).save(path, optimize=True)
for p in ['${WP}/banner-1544x500.png', '${WP}/banner-772x250.png', '${OC}/feature-1200x800.png']:
    im = Image.open(p); print(p.split('/')[-1], im.size)
`])
console.log('artwork rendered')
