import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const SRC_DIR = path.resolve(__dirname, '../src')
const SCOPED_FEATURES = ['partner-registration', 'platform-subscriptions']
const BASELINE_LEGACY_STORES = ['auth.ts', 'booking.ts', 'counter.ts', 'navigation.ts', 'theme.ts']

const errors = []

function getFiles(dir, extension = '') {
  if (!fs.existsSync(dir)) return []
  const entries = fs.readdirSync(dir, { withFileTypes: true })
  let files = []
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

// 1. Check legacy stores directory
const storesDir = path.join(SRC_DIR, 'stores')
if (fs.existsSync(storesDir)) {
  const storeFiles = fs
    .readdirSync(storesDir)
    .filter((f) => f.endsWith('.ts') || f.endsWith('.js'))
    .sort()

  const unexpectedStores = storeFiles.filter((f) => !BASELINE_LEGACY_STORES.includes(f))
  if (unexpectedStores.length > 0) {
    errors.push(
      `New store(s) [${unexpectedStores.join(', ')}] detected in legacy @/stores. Place domain stores in features/{domain}/stores/ instead.`
    )
  }
}

// 2. Check views and components
for (const feature of SCOPED_FEATURES) {
  const viewsDir = path.join(SRC_DIR, 'features', feature, 'views')
  if (fs.existsSync(viewsDir)) {
    const views = getFiles(viewsDir, '.vue')
    for (const file of views) {
      const content = fs.readFileSync(file, 'utf-8')
      const lines = content.split('\n')
      if (lines.length > 250) {
        errors.push(`View [${path.relative(SRC_DIR, file)}] exceeds 250 lines (${lines.length}).`)
      }
      const scriptMatch = content.match(/<script[\s\S]*?>([\s\S]*?)<\/script>/)
      if (scriptMatch && scriptMatch[1]) {
        const scriptLines = scriptMatch[1].split('\n').length
        if (scriptLines > 200) {
          errors.push(`View script in [${path.relative(SRC_DIR, file)}] exceeds 200 lines (${scriptLines}).`)
        }
      }
      if (content.includes('@/core/api/client') || content.includes('core/api/client')) {
        errors.push(`View [${path.relative(SRC_DIR, file)}] imports raw API client.`)
      }
      if (content.includes('@/stores/')) {
        errors.push(`View [${path.relative(SRC_DIR, file)}] imports legacy store from @/stores/.`)
      }
    }
  }

  const componentsDir = path.join(SRC_DIR, 'features', feature, 'components')
  if (fs.existsSync(componentsDir)) {
    const components = getFiles(componentsDir, '.vue')
    for (const file of components) {
      const content = fs.readFileSync(file, 'utf-8')
      const lines = content.split('\n')
      if (lines.length > 300) {
        errors.push(`Component [${path.relative(SRC_DIR, file)}] exceeds 300 lines (${lines.length}).`)
      }
      if (content.includes('@/core/api/client') || content.includes('core/api/client')) {
        errors.push(`Component [${path.relative(SRC_DIR, file)}] imports raw API client.`)
      }
      if (content.includes('@/stores/')) {
        errors.push(`Component [${path.relative(SRC_DIR, file)}] imports legacy store from @/stores/.`)
      }
    }
  }
}

// 3. Check lazy routes in router
const routerFile = path.join(SRC_DIR, 'router', 'index.ts')
if (fs.existsSync(routerFile)) {
  const content = fs.readFileSync(routerFile, 'utf-8')
  const lines = content.split('\n')
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i] || ''
    if (line.includes("path: 'partner") || line.includes("path: '/partner") || line.includes('subscription')) {
      const chunk = lines.slice(i, i + 5).join('\n')
      if (chunk.includes('component:') && !chunk.includes('() => import(')) {
        errors.push(`Route near line ${i + 1} is not using lazy import () => import(...).`)
      }
    }
  }
}

if (errors.length > 0) {
  console.error('Frontend Architecture Violations Found:')
  for (const err of errors) {
    console.error(`  - ${err}`)
  }
  process.exit(1)
}

console.log('All scoped frontend architecture checks passed successfully.')
process.exit(0)
