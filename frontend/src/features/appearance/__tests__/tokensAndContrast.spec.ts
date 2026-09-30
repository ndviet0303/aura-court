import { describe, it, expect } from 'vitest'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

// WCAG relative luminance and contrast ratio computation
function hexToRgb(hex: string): { r: number; g: number; b: number } {
  const clean = hex.replace('#', '')
  const num = parseInt(clean, 16)
  return {
    r: (num >> 16) & 255,
    g: (num >> 8) & 255,
    b: num & 255,
  }
}

function getRelativeLuminance(rgb: { r: number; g: number; b: number }): number {
  const [rs, gs, bs] = [rgb.r, rgb.g, rgb.b].map((c) => {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4)
  })
  return 0.2126 * (rs ?? 0) + 0.7152 * (gs ?? 0) + 0.0722 * (bs ?? 0)
}

function getContrastRatio(hex1: string, hex2: string): number {
  const lum1 = getRelativeLuminance(hexToRgb(hex1))
  const lum2 = getRelativeLuminance(hexToRgb(hex2))
  const brightest = Math.max(lum1, lum2)
  const darkest = Math.min(lum1, lum2)
  return (brightest + 0.05) / (darkest + 0.05)
}

describe('V2 Token Stylesheet and Contrast Compliance', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const cssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const cssContent = fs.readFileSync(cssPath, 'utf-8')

  it('scopes all V2 styles strictly under html[data-ui-version="v2"]', () => {
    expect(cssContent).toContain('html[data-ui-version="v2"]')
    expect(cssContent).toContain('html[data-ui-version="v2"].dark')
    // Asserts no unscoped root styles
    const lines = cssContent.split('\n')
    const unscopedRoot = lines.some((line) => line.trim().startsWith(':root {'))
    expect(unscopedRoot).toBe(false)
  })

  it('defines core semantic tokens in V2 light and dark themes', () => {
    expect(cssContent).toContain('--primary: #F89300')
    expect(cssContent).toContain('--secondary: #FFC107')
    expect(cssContent).toContain('--bg: #FFFFFF')
    expect(cssContent).toContain('--bg: #181A20')
    expect(cssContent).toContain('--surface: #FAFAFA')
    expect(cssContent).toContain('--surface: #1F222A')
    expect(cssContent).toContain('--ink: #212121')
    expect(cssContent).toContain('--ink: #FFFFFF')
    expect(cssContent).toContain('--border: #EEEEEE')
    expect(cssContent).toContain('--border: #35383F')
    expect(cssContent).toContain('--on-primary: #212121')
    expect(cssContent).toContain('--on-primary: #181A20')
    expect(cssContent).toContain('color: var(--on-primary) !important')
  })

  it('preserves legacy CSS variable aliases mapped to V2 tokens', () => {
    expect(cssContent).toContain('--pink: #FFC107')
    expect(cssContent).toContain('--pink-dark: #F89300')
    expect(cssContent).toContain('--pink-light: #FEF4E6')
  })

  it('satisfies WCAG AA contrast threshold (>= 4.5:1) for body text and >= 3:1 for large UI text', () => {
    // V2 Light: ink #212121 on bg #FFFFFF
    const lightTextRatio = getContrastRatio('#212121', '#FFFFFF')
    expect(lightTextRatio).toBeGreaterThanOrEqual(14.0)

    // V2 Light: muted text #757575 on bg #FFFFFF
    const lightMutedRatio = getContrastRatio('#757575', '#FFFFFF')
    expect(lightMutedRatio).toBeGreaterThanOrEqual(4.5)

    // V2 Dark: ink #FFFFFF on bg #181A20
    const darkTextRatio = getContrastRatio('#FFFFFF', '#181A20')
    expect(darkTextRatio).toBeGreaterThanOrEqual(14.0)

    // V2 Dark: muted text #9E9E9E on bg #181A20
    const darkMutedRatio = getContrastRatio('#9E9E9E', '#181A20')
    expect(darkMutedRatio).toBeGreaterThanOrEqual(6.0)

    // V2 Button CTA: dark text #212121 on orange #F89300
    const ctaDarkOnOrange = getContrastRatio('#212121', '#F89300')
    expect(ctaDarkOnOrange).toBeGreaterThanOrEqual(4.5)

    // V2 Dark button CTA: dark background text #181A20 on orange #F89300
    const darkBtnTextOnOrange = getContrastRatio('#181A20', '#F89300')
    expect(darkBtnTextOnOrange).toBeGreaterThanOrEqual(5.0)
  })
})
