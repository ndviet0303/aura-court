import fs from 'node:fs'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import type { PreflightCheckResult } from './types'

export class PreflightValidationError extends Error {
  public readonly violations: string[]

  constructor(message: string, violations: string[] = []) {
    super(message)
    this.name = 'PreflightValidationError'
    this.violations = violations
  }
}

/**
 * Validates environment variables before any action.
 */
export function validateEnvironment(env: Record<string, string | undefined>): string[] {
  const violations: string[] = []
  const appEnv = env.APP_ENV

  if (!appEnv) {
    violations.push('APP_ENV is not defined; must be explicitly set to "testing".')
  } else if (appEnv === 'production' || appEnv === 'staging') {
    violations.push(`CRITICAL: Prohibited environment APP_ENV="${appEnv}".`)
  } else if (appEnv !== 'testing') {
    violations.push(`APP_ENV must be "testing", got "${appEnv}".`)
  }

  return violations
}

/**
 * Validates database target parameters.
 */
export function validateDatabaseTarget(
  db: { host?: string; port?: number; database?: string; connection?: string; url?: string },
  options: { strict?: boolean; expectedDbName?: string } = {},
): string[] {
  const violations: string[] = []
  const host = db.host || '127.0.0.1'
  const database = db.database || ''
  const allowedHosts = ['127.0.0.1', 'localhost', '::1']

  if (!allowedHosts.includes(host)) {
    violations.push(`DB_REMOTE_TARGET: Database host "${host}" is not local loopback (127.0.0.1).`)
  }

  if (database === 'booking') {
    violations.push('CRITICAL: Target database "booking" is production/default.')
  }

  if (options.strict && database === 'booking_test') {
    violations.push(
      'DB_SHARED_PHPUNIT: "booking_test" is reserved for PHPUnit; E2E must own a dedicated database.',
    )
  }

  if (!database.endsWith('_test')) {
    violations.push(`DB_NAMING: Target database "${database}" does not end with "_test".`)
  }

  if (options.expectedDbName && database !== options.expectedDbName) {
    violations.push(
      `DB_MISMATCH: Target database "${database}" does not match expected "${options.expectedDbName}".`,
    )
  }

  if (db.url) {
    try {
      const parsed = new URL(db.url)
      if (!allowedHosts.includes(parsed.hostname)) {
        violations.push(`DB_URL_REMOTE: Database URL specifies remote host "${parsed.hostname}".`)
      }
      const urlDb = parsed.pathname.replace(/^\//, '')
      if (urlDb === 'booking') {
        violations.push('CRITICAL: Database URL targets production database "booking".')
      }
      if (options.expectedDbName && urlDb && urlDb !== options.expectedDbName) {
        violations.push(
          `DB_URL_MISMATCH: Database URL target "${urlDb}" does not match expected "${options.expectedDbName}".`,
        )
      }
    } catch {
      violations.push('DB_URL_MALFORMED: Database URL is invalid.')
    }
  }

  return violations
}

/**
 * Validates that configuration and routes are not cached in backend.
 */
export function validateNoCachedConfig(backendDir: string): string[] {
  const violations: string[] = []
  const cacheFiles = [
    path.join(backendDir, 'bootstrap', 'cache', 'config.php'),
    path.join(backendDir, 'bootstrap', 'cache', 'routes-v7.php'),
    path.join(backendDir, 'bootstrap', 'cache', 'events.php'),
  ]

  for (const file of cacheFiles) {
    if (fs.existsSync(file)) {
      violations.push(
        `CACHE_ACTIVE: Cached artifact "${path.basename(file)}" exists. Test overrides cannot be verified safely.`,
      )
    }
  }

  return violations
}

/**
 * Validates frontend runtime configuration does not override to an external API.
 */
export function validateFrontendRuntimeConfig(
  frontendDir: string,
  expectedBackendUrl: string,
): string[] {
  const violations: string[] = []
  const runtimeConfigPath = path.join(frontendDir, 'public', 'runtime-config.json')

  if (fs.existsSync(runtimeConfigPath)) {
    try {
      const content = JSON.parse(fs.readFileSync(runtimeConfigPath, 'utf-8')) as {
        apiUrl?: unknown
      }
      if (typeof content.apiUrl === 'string' && content.apiUrl.trim().length > 0) {
        const trimmed = content.apiUrl.trim().replace(/\/$/, '')
        const normalizedExpected = expectedBackendUrl.trim().replace(/\/$/, '')
        if (trimmed !== normalizedExpected) {
          violations.push(
            `FRONTEND_API_OVERRIDE: runtime-config.json specifies apiUrl="${trimmed}", conflicting with isolated runner "${normalizedExpected}".`,
          )
        }
      }
    } catch {
      violations.push('FRONTEND_CONFIG_MALFORMED: runtime-config.json is not valid JSON.')
    }
  }

  return violations
}

/**
 * Runs backend Artisan preflight inspect command.
 */
export function runBackendPreflightCommand(
  backendDir: string,
  env: Record<string, string>,
  expectedDb: string,
  runId: string,
): PreflightCheckResult {
  try {
    const output = execFileSync(
      'php',
      [
        'artisan',
        'e2e:inspect-target',
        `--expected-db=${expectedDb}`,
        `--run-id=${runId}`,
        '--strict',
      ],
      {
        cwd: backendDir,
        env: (() => {
          const clean = { ...process.env }
          delete clean.DB_URL
          delete clean.DATABASE_URL
          return {
            ...clean,
            DB_URL: '',
            DATABASE_URL: '',
            ...env,
          }
        })(),
        encoding: 'utf-8',
        stdio: ['pipe', 'pipe', 'pipe'],
      },
    )

    const parsed = JSON.parse(output) as {
      status: string
      environment: string
      config_cached: boolean
      routes_cached: boolean
      storage_path: string
      session_driver: string
      database: {
        connection: string
        host: string
        port: number
        database: string
        actual_connected_database?: string | null
      }
      violations: string[]
    }

    return {
      passed: parsed.status === 'passed',
      violations: parsed.violations || [],
      details: {
        environment: parsed.environment,
        database: {
          connection: parsed.database.connection,
          host: parsed.database.host,
          port: parsed.database.port,
          database: parsed.database.database,
          actualConnected: parsed.database.actual_connected_database,
        },
        configCached: parsed.config_cached,
        routesCached: parsed.routes_cached,
        storagePath: parsed.storage_path,
        sessionDriver: parsed.session_driver,
        backendUrl: env.APP_URL || '',
        frontendUrl: env.FRONTEND_URL || '',
      },
    }
  } catch (error: unknown) {
    const err = error as { stdout?: string; stderr?: string; message?: string }
    const out = err.stdout ? String(err.stdout) : ''
    try {
      const parsed = JSON.parse(out)
      return {
        passed: false,
        violations: parsed.violations || [err.message || 'Inspection failed'],
        details: {
          environment: parsed.environment || 'unknown',
          database: parsed.database || { connection: '', host: '', port: 0, database: '' },
          configCached: Boolean(parsed.config_cached),
          routesCached: Boolean(parsed.routes_cached),
          storagePath: parsed.storage_path || '',
          sessionDriver: parsed.session_driver || '',
          backendUrl: env.APP_URL || '',
          frontendUrl: env.FRONTEND_URL || '',
        },
      }
    } catch {
      return {
        passed: false,
        violations: [`Artisan command error: ${err.message || String(error)}`],
        details: {
          environment: 'error',
          database: { connection: '', host: '', port: 0, database: '' },
          configCached: false,
          routesCached: false,
          storagePath: '',
          sessionDriver: '',
          backendUrl: env.APP_URL || '',
          frontendUrl: env.FRONTEND_URL || '',
        },
      }
    }
  }
}
