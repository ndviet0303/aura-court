import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

// public/fonts/material-symbols-outlined.woff2 holds only the icons in scripts/material-symbols-icons.txt;
// an icon missing from it renders as its ligature text. Google ignores subsets past ~400 names.
const SRC_DIR = path.resolve(__dirname, '..')
const subset = fs
  .readFileSync(path.resolve(SRC_DIR, '../scripts/material-symbols-icons.txt'), 'utf8')
  .split('\n')
  .filter(Boolean)

function sourceFiles(dir: string): string[] {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) return entry.name === '__tests__' ? [] : sourceFiles(full)
    return /\.(vue|ts)$/.test(entry.name) && !entry.name.endsWith('.spec.ts') ? [full] : []
  })
}

// Static <span class="material-symbols-outlined">name</span> and `icon: 'name'` / icon="name" literals.
function usedIcons(): Set<string> {
  const used = new Set<string>()
  for (const file of sourceFiles(SRC_DIR)) {
    const text = fs.readFileSync(file, 'utf8')
    for (const [, name] of text.matchAll(/material-symbols-outlined[^>]*>\s*([a-z0-9_]+)\s*</g))
      used.add(name!)
    for (const [, name] of text.matchAll(/\b[iI]con['"]?\s*[:=]\s*['"]([a-z][a-z0-9_]*)['"]/g))
      used.add(name!)
  }
  return used
}

describe('Material Symbols icon_names subset', () => {
  it('stays sorted, unique and under the size Google honours', () => {
    expect(subset.length).toBeGreaterThan(0)
    expect(subset.length).toBeLessThanOrEqual(400)
    expect(subset).toEqual([...new Set(subset)].sort())
  })

  it('lists every icon the app renders', () => {
    const missing = [...usedIcons()].filter((name) => !subset.includes(name)).sort()
    expect(
      missing,
      'add these to scripts/material-symbols-icons.txt, then run scripts/update-icon-font.sh',
    ).toEqual([])
  })
})
