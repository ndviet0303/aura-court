import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import { randomBytes } from 'node:crypto'
import { execFileSync } from 'node:child_process'
import { getCanonicalBackendEnv } from './test-data.ts'
import type { E2EFixturesPayload, RunManifest, UserCredential } from './types.ts'

function backendConfig(): Record<string, string> {
  const envPath = path.resolve(import.meta.dirname, '../../../backend/.env')
  if (!fs.existsSync(envPath)) return {}
  return Object.fromEntries(fs.readFileSync(envPath, 'utf8').split(/\r?\n/).flatMap((line) => {
    const match = line.match(/^([A-Z_][A-Z0-9_]*)=(.*)$/)
    if (!match) return []
    const raw = match[2].trim()
    return [[match[1], raw.replace(/^(["'])(.*)\1$/, '$2')]]
  }))
}

export async function startProductionEnvironment(): Promise<RunManifest> {
  const config = backendConfig()
  const frontendUrl = process.env.PLAYWRIGHT_BASE_URL || process.env.PROD_URL || 'https://court.ziet.dev'
  const backendUrl = process.env.PROD_API_URL || process.env.E2E_BACKEND_URL || 'https://court-api.ziet.dev'
  const runId = `prod_e2e_${Date.now()}_${randomBytes(4).toString('hex')}`
  const configuredPath = process.env.E2E_MANIFEST_PATH
  const tempDir = configuredPath ? path.dirname(configuredPath) : fs.mkdtempSync(path.join(os.tmpdir(), 'aura_prod_e2e_'))
  fs.mkdirSync(tempDir, { recursive: true, mode: 0o700 })
  const manifest: RunManifest = {
    runId, createdAt: new Date().toISOString(), tempDir,
    storagePath: path.join(tempDir, 'storage'),
    database: {
      host: process.env.E2E_DB_HOST || config.DB_HOST || '',
      port: Number(process.env.E2E_DB_PORT || config.DB_PORT || 5432),
      name: process.env.E2E_DB_DATABASE || config.DB_DATABASE || '',
      user: process.env.E2E_DB_USERNAME || config.DB_USERNAME || '',
      password: process.env.E2E_DB_PASSWORD ?? config.DB_PASSWORD ?? '',
    },
    backend: { port: 0, url: backendUrl, pid: 0 },
    frontend: { port: 0, url: frontendUrl, pid: 0 }, status: 'initializing',
  }
  if (process.env.E2E_DB_PROTECTED === 'true' || ['none', 'disabled'].includes(manifest.database.host)) {
    throw new Error('Full production acceptance requires its test fixture database; protected/disabled DB cannot produce a full passing suite.')
  }
  if (!manifest.database.host || !manifest.database.name || !manifest.database.user) {
    throw new Error('Production E2E database host, name and user must be configured explicitly or in backend/.env.')
  }
  const health = await fetch(`${backendUrl}/up`, { signal: AbortSignal.timeout(15000) })
  if (!health.ok) throw new Error(`Production API health check failed: HTTP ${health.status}`)
  const runtime = await fetch(`${frontendUrl}/runtime-config.json`, { signal: AbortSignal.timeout(15000) })
  if (runtime.ok) {
    const settings = await runtime.json() as { apiUrl?: string }
    if (settings.apiUrl && settings.apiUrl.replace(/\/$/, '') !== backendUrl.replace(/\/$/, '')) {
      throw new Error('Production frontend API origin does not match the selected E2E backend.')
    }
  }
  for (const dir of ['framework/cache/data', 'framework/sessions', 'framework/views', 'app/private', 'app/public', 'logs']) {
    fs.mkdirSync(path.join(manifest.storagePath, dir), { recursive: true })
  }
  const organizationIds = process.env.E2E_PROD_ORGANIZATION_IDS || config.E2E_PROD_ORGANIZATION_IDS
  const raw = execFileSync('php', [
    'artisan', 'e2e:seed-production-fixtures', `--run-id=${runId}`,
    `--expected-db=${manifest.database.name}`, `--expected-host=${manifest.database.host}`,
    ...(organizationIds ? [`--organization-ids=${organizationIds}`] : []),
  ], {
    cwd: path.resolve(import.meta.dirname, '../../../backend'),
    env: getCanonicalBackendEnv(manifest), encoding: 'utf8', timeout: 90000,
    stdio: ['ignore', 'pipe', 'pipe'],
  })
  manifest.fixtures = JSON.parse(raw) as E2EFixturesPayload
  manifest.credentials = Object.fromEntries(Object.entries(manifest.fixtures.users).map(([actor, user]) => {
    if (!user?.password) throw new Error(`Missing generated credential for ${actor}`)
    return [actor, { ...user, password: user.password, name: user.name || actor } satisfies UserCredential]
  }))
  manifest.status = 'ready'
  const manifestPath = configuredPath || path.join(tempDir, 'manifest.json')
  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), { mode: 0o600 })
  process.env.E2E_MANIFEST_PATH = manifestPath
  process.env.PLAYWRIGHT_BASE_URL = frontendUrl
  process.env.E2E_BACKEND_URL = backendUrl
  fs.writeFileSync(path.join(os.tmpdir(), 'aura_e2e_active_manifest.txt'), manifestPath, { mode: 0o600 })
  console.log(`[E2E Production] ${runId}: fresh synthetic actors and two dedicated test tenants ready.`)
  return manifest
}
