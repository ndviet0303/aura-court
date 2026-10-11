import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import type { FullConfig } from '@playwright/test'
import type { RunManifest } from './types.ts'
import { teardownEnvironment } from './teardown.ts'
import { sanitizeAllTestArtifacts, sanitizeDirectory, sanitizeText } from './sanitize-traces.ts'
export default async function globalTeardown(_config: FullConfig): Promise<void> {
  const targetEnv = (process.env.E2E_TARGET || process.env.TARGET_ENV || 'local').toLowerCase()
  if (targetEnv === 'prod' || targetEnv === 'production') {
    sanitizeConfiguredArtifacts(_config)
    return
  }
  const pointerPath = path.join(os.tmpdir(), 'aura_e2e_active_manifest.txt')
  const env = globalThis.__E2E_ENVIRONMENT__
  if (env) {
    await env.stop()
    globalThis.__E2E_ENVIRONMENT__ = undefined
  } else {
    // Fallback: read run manifest from E2E_MANIFEST_PATH or pointer file if global state lost across processes
    let manifestPath = process.env.E2E_MANIFEST_PATH
    if ((!manifestPath || !fs.existsSync(manifestPath)) && fs.existsSync(pointerPath)) {
      manifestPath = fs.readFileSync(pointerPath, 'utf-8').trim()
    }
    if (manifestPath && fs.existsSync(manifestPath)) {
      try {
        const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8')) as RunManifest
        await teardownEnvironment(manifest)
      } catch (err) {
        console.error('[Playwright Global Teardown] Error tearing down from manifest:', err)
      }
    }
  }
  if (fs.existsSync(pointerPath)) {
    try {
      fs.unlinkSync(pointerPath)
    } catch {}
  }

  // R18: Sanitize credentials and secrets from all generated trace and report artifacts
  try {
    sanitizeConfiguredArtifacts(_config)
  } catch (err) {
    console.warn('[Playwright Global Teardown] Trace sanitization warning:', err)
  }
}

function sanitizeConfiguredArtifacts(config: FullConfig): void {
  sanitizeAllTestArtifacts()
  const directories = new Set(config.projects.map((project) => project.outputDir))
  const files = new Set<string>()
  for (const [name, options] of config.reporter) {
    if (name === 'html' && options?.outputFolder)
      directories.add(path.resolve(options.outputFolder))
    if (name === 'junit' && options?.outputFile) files.add(path.resolve(options.outputFile))
  }
  if (process.env.PLAYWRIGHT_HTML_OUTPUT_DIR)
    directories.add(path.resolve(process.env.PLAYWRIGHT_HTML_OUTPUT_DIR))
  if (process.env.PLAYWRIGHT_JUNIT_OUTPUT_FILE)
    files.add(path.resolve(process.env.PLAYWRIGHT_JUNIT_OUTPUT_FILE))
  for (const directory of directories) sanitizeDirectory(directory)
  for (const file of files) {
    if (fs.existsSync(file)) fs.writeFileSync(file, sanitizeText(fs.readFileSync(file, 'utf8')))
  }
}
