import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import net from 'node:net'
import crypto from 'node:crypto'
import { spawn, execFileSync, type ChildProcess } from 'node:child_process'
import type { RunManifest, E2EFixturesPayload } from './types.ts'
import {
  validateEnvironment,
  validateDatabaseTarget,
  validateNoCachedConfig,
  validateFrontendRuntimeConfig,
  runBackendPreflightCommand,
} from './preflight.ts'
import {
  teardownEnvironment,
  registerExitHandlers,
} from './teardown.ts'

export interface EnvironmentOptions {
  runId?: string
  dbHost?: string
  dbPort?: number
  dbUser?: string
  backendPort?: number
  frontendPort?: number
  preserveOnFailure?: boolean
}

/**
 * Finds an available port on loopback address.
 */
export async function getAvailablePort(preferredPort?: number): Promise<number> {
  if (preferredPort && preferredPort > 0) {
    const isFree = await new Promise<boolean>((resolve) => {
      const server = net.createServer()
      server.once('error', () => resolve(false))
      server.once('listening', () => {
        server.close(() => resolve(true))
      })
      server.listen(preferredPort, '127.0.0.1')
    })
    if (isFree) return preferredPort
  }

  const { promise, resolve, reject } = Promise.withResolvers<number>()
  const server = net.createServer()
  server.once('error', reject)
  server.once('listening', () => {
    const address = server.address()
    const port = typeof address === 'object' && address ? address.port : 0
    server.close(() => resolve(port))
  })
  server.listen(0, '127.0.0.1')
  return promise
}

/**
 * Polls an HTTP URL until it returns 200 or timeout expires.
 */
export async function waitForHttp(url: string, timeoutMs: number = 20000): Promise<boolean> {
  const deadline = Date.now() + timeoutMs
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url)
      if (response.ok || response.status === 200) {
        return true
      }
    } catch {
      // Server not ready yet
    }
    const { promise, resolve } = Promise.withResolvers<void>()
    setTimeout(resolve, 200)
    await promise
  }
  return false
}

/**
 * Main E2E isolated environment manager.
 */
export class E2EIsolatedEnvironment {
  public readonly options: EnvironmentOptions
  public readonly runId: string
  public readonly rootDir: string
  public readonly backendDir: string
  public readonly frontendDir: string
  public readonly tempDir: string
  public readonly storageDir: string
  public readonly dbName: string
  public readonly dbHost: string
  public readonly dbPort: number
  public readonly dbUser: string

  public manifest: RunManifest | null = null
  private unregisterExitHandlers: (() => void) | null = null

  public backendProc?: ChildProcess
  public frontendProc?: ChildProcess
  private backendLogFd: number | null = null
  private frontendLogFd: number | null = null

  constructor(options: EnvironmentOptions = {}) {
    this.options = options
    const randomHex = crypto.randomBytes(4).toString('hex')
    this.runId = options.runId || `${Date.now()}_${randomHex}`
    this.rootDir = path.resolve(import.meta.dirname, '../../..')
    this.backendDir = path.join(this.rootDir, 'backend')
    this.frontendDir = path.join(this.rootDir, 'frontend')

    this.tempDir = path.join(os.tmpdir(), 'booking-e2e', this.runId)
    this.storageDir = path.join(this.tempDir, 'storage')

    this.dbHost = options.dbHost || '127.0.0.1'
    this.dbPort = options.dbPort || 5432
    this.dbUser = options.dbUser || process.env.USER || os.userInfo().username
    this.dbName = `booking_e2e_${this.runId}_test`
  }

  /**
   * Persists the current manifest state to disk.
   */
  public persistManifest(): void {
    if (this.manifest) {
      fs.writeFileSync(
        path.join(this.tempDir, 'manifest.json'),
        JSON.stringify(this.manifest, null, 2),
        'utf-8'
      )
    }
  }

  /**
   * Prepares and starts the completely isolated E2E environment.
   */
  async start(options: { skipFrontend?: boolean } = {}): Promise<RunManifest> {
    // 1. Validate environment & targets before creating anything
    const envViolations = validateEnvironment({ APP_ENV: 'testing' })
    if (envViolations.length > 0) {
      throw new Error(`Environment validation failed: ${envViolations.join(', ')}`)
    }

    const dbViolations = validateDatabaseTarget(
      { host: this.dbHost, port: this.dbPort, database: this.dbName },
      { strict: true, expectedDbName: this.dbName }
    )
    if (dbViolations.length > 0) {
      throw new Error(`Database validation failed: ${dbViolations.join(', ')}`)
    }

    const cacheViolations = validateNoCachedConfig(this.backendDir)
    if (cacheViolations.length > 0) {
      throw new Error(`Cache validation failed: ${cacheViolations.join(', ')}`)
    }

    // 2. Prepare ephemeral filesystem structure
    fs.mkdirSync(path.join(this.storageDir, 'app', 'public'), { recursive: true })
    fs.mkdirSync(path.join(this.storageDir, 'framework', 'cache', 'data'), { recursive: true })
    fs.mkdirSync(path.join(this.storageDir, 'framework', 'sessions'), { recursive: true })
    fs.mkdirSync(path.join(this.storageDir, 'framework', 'views'), { recursive: true })
    fs.mkdirSync(path.join(this.storageDir, 'logs'), { recursive: true })

    // 3. Allocate free ports
    const backendPort = this.options.backendPort || (await getAvailablePort(8010))
    const frontendPort = this.options.frontendPort || (await getAvailablePort(Number(process.env.PLAYWRIGHT_FRONTEND_PORT) || 4180))
    const backendUrl = `http://127.0.0.1:${backendPort}`
    const frontendUrl = `http://127.0.0.1:${frontendPort}`

    // 4. Validate frontend runtime-config does not conflict with isolated backend
    const runtimeViolations = validateFrontendRuntimeConfig(this.frontendDir, backendUrl)
    if (runtimeViolations.length > 0) {
      throw new Error(`Frontend runtime validation failed: ${runtimeViolations.join(', ')}`)
    }

    // 5. Create isolated PostgreSQL database
    try {
      execFileSync(
        'createdb',
        ['-h', this.dbHost, '-p', String(this.dbPort), '-U', this.dbUser, this.dbName],
        { stdio: ['ignore', 'pipe', 'pipe'], encoding: 'utf-8' }
      )
    } catch (err) {
      throw new Error(`Failed to create isolated database "${this.dbName}": ${(err as Error).message}`)
    }

    // 6. Generate non-demo test credentials
    const runSecret = crypto.randomBytes(8).toString('hex')
    const credentials = {
      customer: {
        email: `customer_${this.runId}@auracourt.test`,
        password: `CustPass_${runSecret}!`,
        name: `Test Customer ${this.runId}`,
        role: 'customer',
      },
      owner: {
        email: `owner_${this.runId}@auracourt.test`,
        password: `OwnerPass_${runSecret}!`,
        name: `Test Owner ${this.runId}`,
        role: 'organization_owner',
      },
      manager: {
        email: `manager_${this.runId}@auracourt.test`,
        password: `MgrPass_${runSecret}!`,
        name: `Test Manager ${this.runId}`,
        role: 'venue_manager',
      },
      cashier: {
        email: `cashier_${this.runId}@auracourt.test`,
        password: `CashierPass_${runSecret}!`,
        name: `Test Cashier ${this.runId}`,
        role: 'cashier',
      },
      staff: {
        email: `staff_${this.runId}@auracourt.test`,
        password: `StaffPass_${runSecret}!`,
        name: `Test Staff ${this.runId}`,
        role: 'staff',
      },
      admin: {
        email: `admin_${this.runId}@auracourt.test`,
        password: `AdminPass_${runSecret}!`,
        name: `Test Admin ${this.runId}`,
        role: 'platform_admin',
      },
    }

    // 7. Initialize manifest immediately to claim ownership
    this.manifest = {
      runId: this.runId,
      createdAt: new Date().toISOString(),
      tempDir: this.tempDir,
      storagePath: this.storageDir,
      database: {
        name: this.dbName,
        host: this.dbHost,
        port: this.dbPort,
        user: this.dbUser,
      },
      backend: {
        port: backendPort,
        url: backendUrl,
      },
      frontend: {
        port: frontendPort,
        url: frontendUrl,
      },
      credentials,
      status: 'initializing',
    }

    // Persist manifest immediately and register exit handlers
    this.persistManifest()
    this.unregisterExitHandlers = registerExitHandlers(() => this.manifest)

    // 8. Wrap all subsequent setup steps in try/catch to ensure complete failure teardown
    try {
      const backendEnv: Record<string, string> = {
        APP_NAME: 'Aura Court E2E',
        CACHE_PREFIX: 'aura-court-e2e-cache-',
        APP_ENV: 'testing',
        APP_DEBUG: 'true',
        APP_URL: backendUrl,
        CORS_ALLOWED_ORIGINS: frontendUrl,
        SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${frontendPort},localhost:${frontendPort}`,
        PHP_CLI_SERVER_WORKERS: '4',
        DB_CONNECTION: 'pgsql',
        DB_HOST: this.dbHost,
        DB_PORT: String(this.dbPort),
        DB_DATABASE: this.dbName,
        DB_USERNAME: this.dbUser,
        DB_PASSWORD: '',
        DB_URL: '',
        DATABASE_URL: '',
        LARAVEL_STORAGE_PATH: this.storageDir,
        SESSION_DRIVER: 'file',
        SESSION_LIFETIME: '120',
        SESSION_ENCRYPT: 'false',
        CACHE_STORE: 'file',
        QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'array',
        BROADCAST_CONNECTION: 'null',
        FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
        FEATURE_TENANT_POS_WALLET: 'true',
        FEATURE_SOCIAL_TOURNAMENTS: 'true',
        FEATURE_MARKETPLACE_SPLIT: 'true',
        FEATURE_MARKETPLACE_SPLIT_PAYMENTS: 'true',
        FEATURE_MARKETPLACE_RESALE: 'true',
        FEATURE_MARKETPLACE_SLOT_RESALE: 'true',
        PILOT_ORGANIZATION_IDS: '*',
        SEPAY_API_KEY: 'test_sepay_api_key_e2e',
        SEPAY_HMAC_SECRET: 'test_sepay_hmac_secret_e2e',
        SEPAY_BANK: 'TPBank',
        SEPAY_ACCOUNT: 'TEST_ACC_E2E',
        SEPAY_ACCOUNT_NAME: 'AURA COURT E2E',
        PAYOS_BASE_URL: 'http://127.0.0.1:9',
        SEPAY_API_V2_BASE_URL: 'http://127.0.0.1:9',
        HTTP_PROXY: 'http://127.0.0.1:9',
        HTTPS_PROXY: 'http://127.0.0.1:9',
        ALL_PROXY: 'http://127.0.0.1:9',
        NO_PROXY: '127.0.0.1,localhost',
      }

      // Execute preflight inspection command against the new database
      const preflight = runBackendPreflightCommand(
        this.backendDir,
        backendEnv,
        this.dbName,
        this.runId
      )
      if (!preflight.passed) {
        throw new Error(`Preflight check rejected target: ${preflight.violations.join(', ')}`)
      }

      // Run migrations on dedicated database
      try {
        execFileSync('php', ['artisan', 'migrate', '--force'], {
          cwd: this.backendDir,
          env: { ...process.env, ...backendEnv },
          stdio: ['ignore', 'pipe', 'pipe'],
          encoding: 'utf-8',
        })
      } catch (err) {
        throw new Error(`Database migration failed: ${(err as Error).message}`)
      }

      // Seed non-demo multi-organization fixtures satisfying real business rules
      try {
        const seedOutput = execFileSync(
          'php',
          [
            'artisan',
            'e2e:seed-fixtures',
            `--run-id=${this.runId}`,
            `--credentials-json=${JSON.stringify(credentials)}`,
          ],
          {
            cwd: this.backendDir,
            env: { ...process.env, ...backendEnv },
            stdio: ['ignore', 'pipe', 'pipe'],
            encoding: 'utf-8',
          }
        )
        const fixtures = JSON.parse(seedOutput) as E2EFixturesPayload
        this.manifest.fixtures = fixtures
        this.persistManifest()
      } catch (err: any) {
        const detail = (err?.stderr || '') + (err?.stdout || '') + (err?.message || '')
        throw new Error(`Database fixture seeding failed: ${detail}`)
      }
      // Spawn owned backend process
      this.backendLogFd = fs.openSync(path.join(this.storageDir, 'logs', 'backend-serve.log'), 'a')
      this.backendProc = spawn(
        'php',
        ['artisan', 'serve', '--host=127.0.0.1', `--port=${backendPort}`, '--no-reload'],
        {
          cwd: this.backendDir,
          env: { ...process.env, ...backendEnv },
          stdio: ['ignore', this.backendLogFd, this.backendLogFd],
          detached: false,
        }
      )

      this.manifest.backend.pid = this.backendProc.pid
      this.persistManifest()

      // Wait for backend healthcheck
      const backendReady = await waitForHttp(`${backendUrl}/up`, 15000)
      if (!backendReady) {
        throw new Error(`Backend failed to respond at ${backendUrl}/up within 15 seconds.`)
      }

      // Spawn owned frontend process if not skipped
      if (!options.skipFrontend) {
        this.frontendLogFd = fs.openSync(path.join(this.storageDir, 'logs', 'frontend-vite.log'), 'a')
        this.frontendProc = spawn(
          'npx',
          ['vite', '--host', '127.0.0.1', '--port', String(frontendPort), '--strictPort'],
          {
            cwd: this.frontendDir,
            env: {
              ...process.env,
              VITE_API_URL: backendUrl,
            },
            stdio: ['ignore', this.frontendLogFd, this.frontendLogFd],
            detached: false,
          }
        )

        this.manifest.frontend.pid = this.frontendProc.pid
        this.persistManifest()

        const frontendReady = await waitForHttp(frontendUrl, 20000)
        if (!frontendReady) {
          throw new Error(`Frontend failed to respond at ${frontendUrl} within 20 seconds.`)
        }
      }

      this.manifest.status = 'ready'
      this.persistManifest()

      return this.manifest
    } catch (error) {
      // Complete teardown on any startup failure
      await this.stop()
      throw error
    }
  }

  /**
   * Shuts down and cleans up the isolated environment.
   */
  async stop(options: { preserveOnFailure?: boolean } = {}): Promise<void> {
    if (this.unregisterExitHandlers) {
      this.unregisterExitHandlers()
      this.unregisterExitHandlers = null
    }

    // Close log file descriptors if open
    if (this.backendLogFd !== null) {
      try {
        fs.closeSync(this.backendLogFd)
      } catch {
        // Ignore
      }
      this.backendLogFd = null
    }

    if (this.frontendLogFd !== null) {
      try {
        fs.closeSync(this.frontendLogFd)
      } catch {
        // Ignore
      }
      this.frontendLogFd = null
    }

    // Kill in-memory process handles directly as first line of defense
    if (this.backendProc) {
      try {
        this.backendProc.kill('SIGTERM')
      } catch {
        // Ignore
      }
      this.backendProc = undefined
    }

    if (this.frontendProc) {
      try {
        this.frontendProc.kill('SIGTERM')
      } catch {
        // Ignore
      }
      this.frontendProc = undefined
    }

    if (this.manifest) {
      await teardownEnvironment(this.manifest, options)
      this.manifest = null
    } else {
      if (fs.existsSync(this.tempDir)) {
        try {
          fs.rmSync(this.tempDir, { recursive: true, force: true })
        } catch {
          // Ignore
        }
      }
    }
  }
}
