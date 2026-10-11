import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

// Repo-wide rules from .agents/rules/frontend-architecture.md. Files that broke a rule when it was
// introduced are listed in architecture-allowlist.json as [rule, file, ceiling]; ceilings only go down.
const SRC_DIR = path.resolve(__dirname, '..')
const ALLOWLIST_FILE = path.join(__dirname, 'architecture-allowlist.json')

const LIMITS = {
  'view-lines': 250,
  'view-script-lines': 200,
  'component-lines': 400,
  'raw-api-import': 0,
  'legacy-store-import': 0,
  'legacy-store': 0,
  'eager-route': 0,
}
type Rule = keyof typeof LIMITS

const toPosix = (p: string) => p.split(path.sep).join('/')
const lineCount = (text: string) =>
  text === '' ? 0 : text.split('\n').length - (text.endsWith('\n') ? 1 : 0)
const scriptLines = (sfc: string) =>
  [...sfc.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)].reduce(
    (total, [, body = '']) => total + lineCount(body.replace(/^\n/, '')),
    0,
  )

// Import targets as src-relative paths without extension, e.g. 'stores/auth' or 'core/api/client'.
function importTargets(file: string, text: string): string[] {
  return [...text.matchAll(/(?:from|import)\s*\(?\s*['"]([^'"]+)['"]/g)].map(([, spec = '']) => {
    if (!spec.startsWith('@/') && !spec.startsWith('.')) return spec
    const target = spec.startsWith('@/')
      ? path.join(SRC_DIR, spec.slice(2))
      : path.resolve(path.dirname(path.join(SRC_DIR, file)), spec)
    return toPosix(path.relative(SRC_DIR, target)).replace(/\.(ts|js)$/, '')
  })
}

function measure(): Map<string, number> {
  const values = new Map<string, number>()
  const record = (rule: Rule, file: string, value: number) => values.set(`${rule} ${file}`, value)
  const files = fs
    .readdirSync(SRC_DIR, { recursive: true, encoding: 'utf8' })
    .map(toPosix)
    .filter((file) => !file.split('/').includes('__tests__'))

  for (const file of files.filter((f) => f.endsWith('.vue'))) {
    const text = fs.readFileSync(path.join(SRC_DIR, file), 'utf8')
    if (file.split('/').includes('views')) {
      record('view-lines', file, lineCount(text))
      record('view-script-lines', file, scriptLines(text))
    } else {
      record('component-lines', file, lineCount(text))
    }
    const targets = importTargets(file, text)
    const rawApi = ['core/api/client', 'lib/api']
    record('raw-api-import', file, targets.filter((t) => rawApi.includes(t)).length)
    record('legacy-store-import', file, targets.filter((t) => t.startsWith('stores/')).length)
  }

  for (const file of files.filter((f) => /^stores\/[^/]+\.(ts|js)$/.test(f))) {
    record('legacy-store', file, 1)
  }

  for (const file of files.filter((f) => /^router\/.+\.ts$/.test(f))) {
    const text = fs.readFileSync(path.join(SRC_DIR, file), 'utf8')
    const eager = new Set(
      [...text.matchAll(/^import\s+(\w+)\s+from\s+['"][^'"]+\.vue['"]/gm)].map(([, name]) => name),
    )
    const eagerRoutes = [...text.matchAll(/\bcomponent:\s*(\w+)/g)].filter(([, name]) =>
      eager.has(name),
    )
    record('eager-route', file, eagerRoutes.length)
  }
  return values
}

describe('Frontend architecture', () => {
  it('keeps every file within its rule limit or allowlist ceiling', () => {
    const values = measure()
    const allowlist: [Rule, string, number][] = JSON.parse(fs.readFileSync(ALLOWLIST_FILE, 'utf8'))
    const ceilings = new Map(allowlist.map(([rule, file, ceiling]) => [`${rule} ${file}`, ceiling]))
    const problems: string[] = []

    for (const [key, value] of values) {
      const [rule, file] = key.split(' ') as [Rule, string]
      const limit = LIMITS[rule]
      const ceiling = ceilings.get(key)
      if (value <= limit) continue
      if (ceiling === undefined) {
        problems.push(
          `${file}: ${rule} is ${value}, limit ${limit}. Fix it, or allowlist it:\n  ["${rule}", "${file}", ${value}],`,
        )
      } else if (value > ceiling) {
        problems.push(
          `${file}: ${rule} is ${value}, above its ceiling ${ceiling} (limit ${limit}).`,
        )
      }
    }

    for (const [rule, file] of allowlist) {
      if (!(rule in LIMITS)) {
        problems.push(`${file}: unknown rule "${rule}" in the allowlist.`)
      } else if ((values.get(`${rule} ${file}`) ?? 0) <= LIMITS[rule]) {
        problems.push(`${file}: ${rule} is within its limit now. Remove its allowlist entry.`)
      }
    }

    expect(problems.join('\n')).toBe('')
  })
})
