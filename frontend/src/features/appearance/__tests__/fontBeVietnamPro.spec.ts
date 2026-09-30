import { describe, it, expect } from 'vitest'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

describe('Be Vietnam Pro Font Configuration for V2', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const indexHtmlPath = path.resolve(currentDir, '../../../../index.html')
  const indexHtml = fs.readFileSync(indexHtmlPath, 'utf-8')

  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const variablesCssPath = path.resolve(currentDir, '../../../assets/styles/variables.css')
  const variablesCss = fs.readFileSync(variablesCssPath, 'utf-8')

  it('includes Be Vietnam Pro in index.html with display=swap and weights 400-700', () => {
    expect(indexHtml).toContain('family=Be+Vietnam+Pro')
    expect(indexHtml).toContain('display=swap')
    expect(indexHtml).toContain('400')
    expect(indexHtml).toContain('700')
  })

  it('scopes Be Vietnam Pro font-family under html[data-ui-version="v2"]', () => {
    expect(erabookCss).toContain("--font-family-base: 'Be Vietnam Pro'")
    expect(erabookCss).toContain('-apple-system, BlinkMacSystemFont,')
  })

  it('preserves Material Symbols Outlined font in V2', () => {
    expect(erabookCss).toContain("font-family: 'Material Symbols Outlined' !important;")
  })

  it('does not apply Be Vietnam Pro to V1 default root variables', () => {
    expect(variablesCss).not.toContain('Be Vietnam Pro')
  })
})
