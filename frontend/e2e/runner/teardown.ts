import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import { execFileSync } from 'node:child_process'
import type { RunManifest } from './types.ts'

export class TeardownSafetyError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'TeardownSafetyError'
  }
}

/**
 * Validates that the database name in the manifest is strictly owned by this E2E run.
 * Rejects production, shared, or PHPUnit databases.
 */
export function validateDatabaseForTeardown(dbName: string, expectedRunId: string): void {
  if (!dbName) {
    throw new TeardownSafetyError('Cannot drop empty or undefined database.')
  }

  // Must not be production or default or postgres
  const forbidden = ['booking', 'booking_test', 'postgres', 'template0', 'template1']
  if (forbidden.includes(dbName)) {
    throw new TeardownSafetyError(`CRITICAL: Refusing to drop protected database "${dbName}".`)
  }

  // Must match the exact run prefix pattern
  const expectedPrefix = `booking_e2e_${expectedRunId}_test`
  if (dbName !== expectedPrefix) {
    throw new TeardownSafetyError(
      `CRITICAL: Target database "${dbName}" does not match manifest run ID "${expectedPrefix}".`,
    )
  }

  if (!dbName.endsWith('_test')) {
    throw new TeardownSafetyError(`CRITICAL: Database "${dbName}" does not end with "_test".`)
  }
}

/**
 * Validates manifest integrity before allowing teardown to touch any processes, databases, or directories.
 */
export function validateManifestForTeardown(manifest: RunManifest): void {
  if (!manifest) {
    throw new TeardownSafetyError('Manifest is undefined or null.')
  }

  // 1. Run ID format check
  if (!manifest.runId || !/^[a-zA-Z0-9_-]+$/.test(manifest.runId)) {
    throw new TeardownSafetyError(`CRITICAL: Invalid runId "${manifest.runId}" in manifest.`)
  }
  // 2. Canonical temp directory validation: MUST strictly equal the expected path for this runId
  const expectedCanonicalTempDir = path.resolve(os.tmpdir(), 'booking-e2e', manifest.runId)
  const resolvedTempDir = path.resolve(manifest.tempDir || '')
  if (resolvedTempDir !== expectedCanonicalTempDir) {
    throw new TeardownSafetyError(
      `CRITICAL: Manifest tempDir "${manifest.tempDir}" does not equal canonical path "${expectedCanonicalTempDir}".`,
    )
  }

  // 3. Database connection safety
  const allowedHosts = ['127.0.0.1', 'localhost', '::1']
  if (!allowedHosts.includes(manifest.database?.host)) {
    throw new TeardownSafetyError(
      `CRITICAL: Manifest database host "${manifest.database?.host}" is not local loopback.`,
    )
  }

  validateDatabaseForTeardown(manifest.database.name, manifest.runId)
}

/**
 * Signals the process group led by `pid`: the backend runs in its own group so its PHP server
 * workers, which outlive their parent, are stopped with it.
 */
function signalProcessGroup(pid: number, signal: NodeJS.Signals): void {
  try {
    process.kill(-pid, signal)
  } catch {
    // Not a group leader, or the group already exited
  }
}

/**
 * Safely terminates a process by PID, escalating from SIGTERM to SIGKILL if necessary.
 * If expectedKeywords are provided, verifies the process command line matches to prevent PID reuse killing unrelated processes.
 */
export async function terminateProcess(
  pid: number | undefined,
  expectedKeywords: string[] = [],
): Promise<boolean> {
  if (!pid || pid <= 0) return false

  try {
    process.kill(pid, 0)
  } catch {
    // Process already dead
    return false
  }

  // Verify process identity if keywords provided: ALL keywords must match
  if (expectedKeywords.length > 0) {
    try {
      const cmd = execFileSync('ps', ['-p', String(pid), '-o', 'command='], {
        encoding: 'utf-8',
        stdio: ['ignore', 'pipe', 'ignore'],
      }).toLowerCase()

      if (cmd.includes('<defunct>') || cmd.includes('zombie')) {
        return true
      }

      const matches = expectedKeywords.every((keyword) => cmd.includes(keyword.toLowerCase()))
      if (!matches) {
        console.warn(
          `[Teardown Safety] Refusing to kill PID ${pid}: command "${cmd.trim()}" does not match all required keywords [${expectedKeywords.join(', ')}]. Possible PID reuse.`,
        )
        return false
      }
    } catch {
      return false
    }
  }
  // Find child processes before terminating parent
  let childPids: number[] = []
  try {
    const pgrepOut = execFileSync('pgrep', ['-P', String(pid)], {
      encoding: 'utf-8',
      stdio: ['ignore', 'pipe', 'ignore'],
    })
    childPids = pgrepOut
      .trim()
      .split(/\s+/)
      .map(Number)
      .filter((p) => p > 0)
  } catch {
    // No child processes found
  }

  try {
    process.kill(pid, 'SIGTERM')
  } catch {
    return false
  }
  signalProcessGroup(pid, 'SIGTERM')

  for (const cPid of childPids) {
    try {
      process.kill(cPid, 'SIGTERM')
    } catch {
      // Child already terminated
    }
  }

  // Poll for up to 2 seconds for process to exit
  const deadline = Date.now() + 2000
  while (Date.now() < deadline) {
    try {
      process.kill(pid, 0)
      const { promise, resolve } = Promise.withResolvers<void>()
      setTimeout(resolve, 100)
      await promise
    } catch {
      // Process has terminated
      signalProcessGroup(pid, 'SIGKILL')
      for (const cPid of childPids) {
        try {
          process.kill(cPid, 'SIGKILL')
        } catch {
          // Ignore
        }
      }
      return true
    }
  }

  // Escalate to SIGKILL if still running
  try {
    process.kill(pid, 'SIGKILL')
  } catch {
    // Process already terminated
  }
  signalProcessGroup(pid, 'SIGKILL')
  for (const cPid of childPids) {
    try {
      process.kill(cPid, 'SIGKILL')
    } catch {
      // Ignore
    }
  }
  return true
}

/**
 * Drops the dedicated test database.
 */
export function dropDedicatedDatabase(
  dbName: string,
  host: string = '127.0.0.1',
  port: number = 5432,
  user: string = 'ziet',
): boolean {
  try {
    execFileSync('dropdb', ['-h', host, '-p', String(port), '-U', user, '--if-exists', dbName], {
      stdio: ['ignore', 'pipe', 'pipe'],
      encoding: 'utf-8',
    })
    return true
  } catch {
    // If dropdb failed, try psql DROP DATABASE WITH (FORCE)
    try {
      execFileSync(
        'psql',
        [
          '-h',
          host,
          '-p',
          String(port),
          '-U',
          user,
          '-d',
          'postgres',
          '-c',
          `DROP DATABASE IF EXISTS "${dbName}" WITH (FORCE);`,
        ],
        {
          stdio: ['ignore', 'pipe', 'pipe'],
          encoding: 'utf-8',
        },
      )
      return true
    } catch (secondError) {
      console.error(`Failed to drop database ${dbName}:`, secondError)
      return false
    }
  }
}

/**
 * Full teardown of an isolated E2E environment based on its manifest.
 */
export async function teardownEnvironment(
  manifest: RunManifest,
  options: { preserveOnFailure?: boolean } = {},
): Promise<void> {
  if (manifest.status === 'torn_down') {
    return
  }

  // Validate manifest integrity before attempting any teardown action
  validateManifestForTeardown(manifest)

  // 1. Terminate owned backend process (verifying command contains both 'artisan' and port)
  if (manifest.backend?.pid) {
    const backendKilled = await terminateProcess(manifest.backend.pid, [
      'artisan',
      String(manifest.backend.port),
    ])
    if (!backendKilled) {
      try {
        process.kill(manifest.backend.pid, 0)
        const cmd = execFileSync('ps', ['-p', String(manifest.backend.pid), '-o', 'command='], {
          encoding: 'utf-8',
          stdio: ['ignore', 'pipe', 'ignore'],
        }).toLowerCase()
        if (!cmd.includes('<defunct>') && !cmd.includes('zombie')) {
          throw new TeardownSafetyError(
            `CRITICAL: Backend process (PID ${manifest.backend.pid}) failed to terminate or PID does not match ownership.`,
          )
        }
      } catch (e) {
        if (e instanceof TeardownSafetyError) throw e
      }
    }
  }

  // 2. Terminate owned frontend process (verifying command contains both 'vite' and port)
  if (manifest.frontend?.pid) {
    const frontendKilled = await terminateProcess(manifest.frontend.pid, [
      'vite',
      String(manifest.frontend.port),
    ])
    if (!frontendKilled) {
      try {
        process.kill(manifest.frontend.pid, 0)
        const cmd = execFileSync('ps', ['-p', String(manifest.frontend.pid), '-o', 'command='], {
          encoding: 'utf-8',
          stdio: ['ignore', 'pipe', 'ignore'],
        }).toLowerCase()
        if (!cmd.includes('<defunct>') && !cmd.includes('zombie')) {
          throw new TeardownSafetyError(
            `CRITICAL: Frontend process (PID ${manifest.frontend.pid}) failed to terminate or PID does not match ownership.`,
          )
        }
      } catch (e) {
        if (e instanceof TeardownSafetyError) throw e
      }
    }
  }

  // 3. Drop dedicated database
  if (manifest.database?.name) {
    const dropped = dropDedicatedDatabase(
      manifest.database.name,
      manifest.database.host,
      manifest.database.port,
      manifest.database.user,
    )
    if (!dropped) {
      throw new TeardownSafetyError(
        `CRITICAL: Failed to drop dedicated database "${manifest.database.name}".`,
      )
    }
  }

  // 4. Clean up temporary directory unless preserveOnFailure requested
  if (manifest.tempDir && fs.existsSync(manifest.tempDir)) {
    if (!options.preserveOnFailure || manifest.status === 'completed') {
      try {
        fs.rmSync(manifest.tempDir, { recursive: true, force: true })
      } catch (err) {
        console.warn(`[Teardown] Could not remove temp dir ${manifest.tempDir}:`, err)
      }
    }
  }

  manifest.status = 'torn_down'
  manifest.completedAt = new Date().toISOString()
}

const pendingExitCleanups = new Set<Promise<void>>()

/**
 * Registers process lifecycle exit handlers to guarantee teardown on unexpected exit.
 */
export function registerExitHandlers(manifestGetter: () => RunManifest | null): () => void {
  let cleaningUp = false

  const handler = async (signal?: string) => {
    if (cleaningUp) return
    cleaningUp = true

    const manifest = manifestGetter()
    if (manifest && manifest.status !== 'torn_down') {
      console.log(
        `\n[E2E Runner] Received ${signal || 'exit'}, running manifest teardown for run ${manifest.runId}...`,
      )
      try {
        await teardownEnvironment(manifest)
      } catch (err) {
        console.error('[E2E Runner] Teardown error during exit:', err)
      }
    }
  }

  // Each parallel environment registers a listener for the same signal: exit once all have torn down.
  const exitAfterCleanups = (cleanup: Promise<void>, code: number) => {
    pendingExitCleanups.add(cleanup)
    setImmediate(() => Promise.allSettled(pendingExitCleanups).finally(() => process.exit(code)))
  }
  const sigintListener = () => exitAfterCleanups(handler('SIGINT'), 130)
  const sigtermListener = () => exitAfterCleanups(handler('SIGTERM'), 143)

  process.once('SIGINT', sigintListener)
  process.once('SIGTERM', sigtermListener)

  return () => {
    process.removeListener('SIGINT', sigintListener)
    process.removeListener('SIGTERM', sigtermListener)
  }
}
