import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import type { FullConfig } from '@playwright/test'
import { E2EIsolatedEnvironment, getAvailablePort } from './environment.ts'
import { startProductionEnvironment } from './production.ts'

declare global {
  var __E2E_ENVIRONMENTS__: E2EIsolatedEnvironment[] | undefined
}

export default async function globalSetup(config: FullConfig): Promise<void> {
  const targetEnv = (process.env.E2E_TARGET || process.env.TARGET_ENV || 'local').toLowerCase()
  if (targetEnv === 'prod' || targetEnv === 'production') {
    await startProductionEnvironment()
    return
  }

  // One isolated environment per worker; playwright.config.ts maps each worker's parallel index to
  // its lane. Ports are reserved up front because lanes start concurrently.
  const usedPorts = new Set<number>()
  const reservePort = async (preferred?: number): Promise<number> => {
    let port = await getAvailablePort(preferred)
    while (usedPorts.has(port)) port = await getAvailablePort()
    usedPorts.add(port)
    return port
  }
  const environments: E2EIsolatedEnvironment[] = []
  for (let lane = 0; lane < config.workers; lane++) {
    const backendPort = await reservePort(lane === 0 ? 8010 : undefined)
    const frontendPort = await reservePort(
      lane === 0 ? Number(process.env.PLAYWRIGHT_FRONTEND_PORT) || 4180 : undefined,
    )
    environments.push(new E2EIsolatedEnvironment({ backendPort, frontendPort }))
  }
  globalThis.__E2E_ENVIRONMENTS__ = environments

  const started = await Promise.allSettled(
    environments.map((env) => env.start({ skipFrontend: false })),
  )
  const failure = started.find((result) => result.status === 'rejected')
  if (failure) {
    await Promise.allSettled(environments.map((env) => env.stop()))
    globalThis.__E2E_ENVIRONMENTS__ = undefined
    throw failure.reason
  }

  const manifestPaths = environments.map((env) => path.join(env.tempDir, 'manifest.json'))
  const manifest = environments[0].manifest!
  process.env.E2E_MANIFEST_PATHS = manifestPaths.join(path.delimiter)
  process.env.E2E_MANIFEST_PATH = manifestPaths[0]
  process.env.PLAYWRIGHT_BASE_URL = manifest.frontend.url
  process.env.E2E_RUN_ID = manifest.runId
  process.env.E2E_BACKEND_URL = manifest.backend.url

  const pointerPath = path.join(os.tmpdir(), 'aura_e2e_active_manifest.txt')
  fs.writeFileSync(pointerPath, manifestPaths[0], { encoding: 'utf-8', mode: 0o600 })
}
