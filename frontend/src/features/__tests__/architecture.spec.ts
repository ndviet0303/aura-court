import { describe, it, expect } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const SRC_DIR = path.resolve(__dirname, '../../')
const SCOPED_FEATURES = ['partner-registration', 'platform-subscriptions']
const BASELINE_LEGACY_STORES = ['auth.ts', 'booking.ts', 'counter.ts', 'navigation.ts', 'theme.ts']

function getFiles(dir: string, extension: string = ''): string[] {
  if (!fs.existsSync(dir)) return []
  const entries = fs.readdirSync(dir, { withFileTypes: true })
  let files: string[] = []
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name)
    if (entry.isDirectory()) {
      files = files.concat(getFiles(fullPath, extension))
    } else if (!extension || entry.name.endsWith(extension)) {
      files.push(fullPath)
    }
  }
  return files
}

describe('Scoped Frontend Architecture Rules', () => {
  it('prevents new stores in deprecated legacy src/stores directory', () => {
    const storesDir = path.join(SRC_DIR, 'stores')
    if (fs.existsSync(storesDir)) {
      const storeFiles = fs
        .readdirSync(storesDir)
        .filter((f) => f.endsWith('.ts') || f.endsWith('.js'))
        .sort()

      const unexpectedStores = storeFiles.filter((f) => !BASELINE_LEGACY_STORES.includes(f))
      expect(
        unexpectedStores,
        `New store(s) [${unexpectedStores.join(', ')}] detected in legacy @/stores. Place domain stores in features/{domain}/stores/ instead.`
      ).toEqual([])
    }
  })

  it('enforces view size limits and rules for scoped features', () => {
    for (const feature of SCOPED_FEATURES) {
      const viewsDir = path.join(SRC_DIR, 'features', feature, 'views')
      if (!fs.existsSync(viewsDir)) continue

      const views = getFiles(viewsDir, '.vue')
      for (const viewFile of views) {
        const content = fs.readFileSync(viewFile, 'utf-8')
        const lines = content.split('\n')
        const relPath = path.relative(SRC_DIR, viewFile)

        // View max lines: 250
        expect(
          lines.length,
          `View [${relPath}] has ${lines.length} lines. Views must not exceed 250 total lines.`
        ).toBeLessThanOrEqual(250)

        // Script block max lines: 200
        const scriptMatch = content.match(/<script[\s\S]*?>([\s\S]*?)<\/script>/)
        if (scriptMatch && scriptMatch[1]) {
          const scriptLines = scriptMatch[1].split('\n').length
          expect(
            scriptLines,
            `Script block in [${relPath}] has ${scriptLines} lines. View script must not exceed 200 lines.`
          ).toBeLessThanOrEqual(200)
        }

        // Views must not import raw API client
        expect(
          content.includes('@/core/api/client') || content.includes('core/api/client'),
          `View [${relPath}] imports raw API client. Use feature API module or composable instead.`
        ).toBe(false)

        // Views must not import legacy stores
        expect(
          content.includes('@/stores/'),
          `View [${relPath}] imports legacy store from @/stores/. Use feature store or local state.`
        ).toBe(false)
      }
    }
  })

  it('enforces component size limits and rules for scoped features', () => {
    for (const feature of SCOPED_FEATURES) {
      const componentsDir = path.join(SRC_DIR, 'features', feature, 'components')
      if (!fs.existsSync(componentsDir)) continue

      const components = getFiles(componentsDir, '.vue')
      for (const compFile of components) {
        const content = fs.readFileSync(compFile, 'utf-8')
        const lines = content.split('\n')
        const relPath = path.relative(SRC_DIR, compFile)

        // Component max lines: 300
        expect(
          lines.length,
          `Component [${relPath}] has ${lines.length} lines. Components must not exceed 300 total lines.`
        ).toBeLessThanOrEqual(300)

        // Components must not import raw API client
        expect(
          content.includes('@/core/api/client') || content.includes('core/api/client'),
          `Component [${relPath}] imports raw API client. Use feature API module or composable instead.`
        ).toBe(false)

        // Components must not import legacy stores
        expect(
          content.includes('@/stores/'),
          `Component [${relPath}] imports legacy store from @/stores/. Use feature store or local state.`
        ).toBe(false)
      }
    }
  })

  it('verifies router uses lazy routes for scoped partner and subscription paths', () => {
    const routerFile = path.join(SRC_DIR, 'router', 'index.ts')
    if (fs.existsSync(routerFile)) {
      const content = fs.readFileSync(routerFile, 'utf-8')
      const lines = content.split('\n')

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i] ?? ''
        if (line.includes("path: 'partner") || line.includes("path: '/partner") || line.includes('subscription')) {
          // Check following lines for dynamic import
          const chunk = lines.slice(i, i + 5).join('\n')
          if (chunk.includes('component:')) {
            expect(
              chunk.includes('() => import('),
              `Route definition near line ${i + 1} for partner/subscription must use lazy dynamic import () => import(...).`
            ).toBe(true)
          }
        }
      }
    }
  })
})
