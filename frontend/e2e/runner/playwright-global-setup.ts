import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import type { FullConfig } from '@playwright/test'
import { E2EIsolatedEnvironment } from './environment.ts'
import { startProductionEnvironment } from './production.ts'

declare global {
  // eslint-disable-next-line no-var
  var __E2E_ENVIRONMENT__: E2EIsolatedEnvironment | undefined
}

export default async function globalSetup(_config: FullConfig): Promise<void> {
  const targetEnv = (process.env.E2E_TARGET || process.env.TARGET_ENV || 'local').toLowerCase()
  if (targetEnv === 'prod' || targetEnv === 'production') {
    await startProductionEnvironment()
    return
  }

  const env = new E2EIsolatedEnvironment()
  const manifest = await env.start({ skipFrontend: false })
  globalThis.__E2E_ENVIRONMENT__ = env

  const manifestPath = path.join(manifest.tempDir, 'manifest.json')
  process.env.E2E_MANIFEST_PATH = manifestPath
  process.env.PLAYWRIGHT_BASE_URL = manifest.frontend.url
  process.env.E2E_RUN_ID = manifest.runId
  process.env.E2E_BACKEND_URL = manifest.backend.url

  const pointerPath = path.join(os.tmpdir(), 'aura_e2e_active_manifest.txt')
  fs.writeFileSync(pointerPath, manifestPath, { encoding: 'utf-8', mode: 0o600 })
}
