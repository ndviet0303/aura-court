import fs from 'node:fs'
import path from 'node:path'
import { execFileSync } from 'node:child_process'

const SECRET_PATTERNS: Array<[RegExp, string | ((match: string) => string)]> = [
  // Passwords in JSON/query/form
  [/("password"\s*:\s*")[^"]+(")/gi, '$1[REDACTED]$2'],
  [/('password'\s*:\s*')[^']+(')/gi, '$1[REDACTED]$2'],
  [/(password=[^&"'\s\\]+)/gi, 'password=[REDACTED]'],
  [/(Password123![^"'\s\\]*)/g, '[REDACTED_PASSWORD]'],

  // Authorization headers and tokens
  [/("authorization"\s*:\s*")[^"]+(")/gi, '$1[REDACTED]$2'],
  [/(Bearer\s+)[A-Za-z0-9_\-.]{10,}/gi, '$1[REDACTED_TOKEN]'],
  [/(Apikey\s+)[A-Za-z0-9_\-.]{10,}/gi, '$1[REDACTED_KEY]'],

  // Cookies and sessions
  [/("cookie"\s*:\s*")[^"]+(")/gi, '$1[REDACTED]$2'],
  [/("set-cookie"\s*:\s*")[^"]+(")/gi, '$1[REDACTED]$2'],
  [/(aura_session=[A-Za-z0-9_%-]+)/gi, 'aura_session=[REDACTED]'],
  [/(XSRF-TOKEN=[A-Za-z0-9_%-]+)/gi, 'XSRF-TOKEN=[REDACTED]'],

  // Webhook and provider secrets
  [/(whsec_[a-zA-Z0-9_-]{6,})/g, '[REDACTED_WEBHOOK_SECRET]'],
  [/(test_sepay_[a-zA-Z0-9_-]{4,})/g, '[REDACTED_SECRET]'],
  [/(test_payos_[a-zA-Z0-9_-]{4,})/g, '[REDACTED_SECRET]'],
  [/([a-f0-9]{32,64})/g, (match: string) => {
    // Only redact long hex tokens that look like signatures/hashes in credential contexts
    return match.length === 64 ? match.substring(0, 8) + '...[REDACTED_HEX]' : match
  }],
]

export function sanitizeText(content: string): string {
  let sanitized = content
  for (const [pattern, replacement] of SECRET_PATTERNS) {
    if (typeof replacement === 'string') {
      sanitized = sanitized.replace(pattern, replacement)
    } else {
      sanitized = sanitized.replace(pattern, replacement as any)
    }
  }
  return sanitized
}

export function sanitizeZipArchive(zipPath: string): boolean {
  if (!fs.existsSync(zipPath)) return false

  const scriptPath = path.resolve(import.meta.dirname, 'sanitize_zip.py')
  try {
    execFileSync('python3', [scriptPath, zipPath], { encoding: 'utf-8' })
    return true
  } catch (err) {
    console.warn(`[SanitizeTraces] Failed to sanitize zip ${zipPath}:`, err)
    return false
  }
}

export function sanitizeDirectory(dirPath: string): { filesScanned: number; filesSanitized: number } {
  let scanned = 0
  let sanitized = 0

  if (!fs.existsSync(dirPath)) {
    return { filesScanned: 0, filesSanitized: 0 }
  }

  function walk(current: string) {
    const entries = fs.readdirSync(current, { withFileTypes: true })
    for (const entry of entries) {
      const fullPath = path.join(current, entry.name)
      if (entry.isDirectory()) {
        walk(fullPath)
      } else if (entry.isFile()) {
        scanned++
        if (entry.name.endsWith('.zip')) {
          if (sanitizeZipArchive(fullPath)) sanitized++
        } else if (['.html', '.xml', '.json', '.txt', '.log'].some((ext) => entry.name.endsWith(ext))) {
          try {
            const content = fs.readFileSync(fullPath, 'utf-8')
            const cleaned = sanitizeText(content)
            if (cleaned !== content) {
              fs.writeFileSync(fullPath, cleaned, 'utf-8')
              sanitized++
            }
          } catch {}
        }
      }
    }
  }

  walk(dirPath)
  return { filesScanned: scanned, filesSanitized: sanitized }
}

export function sanitizeAllTestArtifacts(rootDir: string = process.cwd()): void {
  const targetDirs = [
    path.join(rootDir, 'test-results'),
    path.join(rootDir, 'playwright-report'),
    path.join(rootDir, 'frontend', 'test-results'),
    path.join(rootDir, 'frontend', 'playwright-report'),
  ]

  for (const dir of targetDirs) {
    if (fs.existsSync(dir)) {
      const result = sanitizeDirectory(dir)
      if (result.filesSanitized > 0) {
        console.log(`[SanitizeArtifacts] Sanitized ${result.filesSanitized}/${result.filesScanned} files in ${dir}`)
      }
    }
  }
}

if (process.argv[1] && import.meta.url.endsWith(path.basename(process.argv[1]))) {
  sanitizeAllTestArtifacts()
}
