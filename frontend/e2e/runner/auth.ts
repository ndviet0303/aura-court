import fs from 'node:fs'
import path from 'node:path'
import type { BrowserContext, Page } from '@playwright/test'
import type { RunManifest } from './types.ts'
export { getTestManifest } from './test-data.ts'
export interface ActorCredential {
  email: string
  password: string
  name?: string
  role?: string
  id?: number
}

export type ActorRole =
  | 'admin'
  | 'owner1'
  | 'owner2'
  | 'manager1'
  | 'cashier1'
  | 'staff1'
  | 'customer'
  | 'customer2'
  | 'unverifiedCustomer'
  | 'restrictedCustomer'
export interface ActorSession {
  actor: ActorRole
  user: ActorCredential
  authStatePath: string
  reused: boolean
}

/**
 * Resolves credential object for named actor role from manifest fixtures or credentials.
 */
export function getActorCredentials(manifest: RunManifest, actor: ActorRole): ActorCredential {
  const credKeyMap: Record<ActorRole, string> = {
    admin: 'admin',
    owner1: 'owner',
    owner2: 'foreignOwner',
    manager1: 'manager',
    cashier1: 'cashier',
    staff1: 'staff',
    customer: 'customer',
    customer2: 'customer2',
    unverifiedCustomer: 'unverifiedCustomer',
    restrictedCustomer: 'restrictedCustomer',
  }

  const key = credKeyMap[actor]

  if (manifest.credentials && manifest.credentials[key]) {
    const cred = manifest.credentials[key]
    if (cred) {
      return {
        email: cred.email,
        password: cred.password,
        name: cred.name,
        role: cred.role,
        id: manifest.fixtures?.users[key]?.id,
      }
    }
  }

  const fixtureUser = manifest.fixtures?.users[key]
  if (fixtureUser) {
    return {
      email: fixtureUser.email,
      password: fixtureUser.password || `Password123!@#${manifest.runId}`,
      role: fixtureUser.role,
      id: fixtureUser.id,
    }
  }

  throw new Error(`Credential for actor role "${actor}" not found in manifest`)
}

/**
 * Returns path to actor's cached storageState JSON file.
 * Stored under manifest.storagePath/auth-states, completely outside git and artifact directories.
 */
export function getActorAuthStatePath(manifest: RunManifest, actor: ActorRole): string {
  const dir = path.join(manifest.storagePath, 'auth-states')
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true, mode: 0o700 })
  }
  return path.join(dir, `${actor}.json`)
}

/**
 * Asserts that the auth state file and storage directories are strictly outside repository root.
 */
export function assertAuthStateSafeLocation(targetPath: string, repoRoot: string): void {
  const resolvedTarget = path.resolve(targetPath)
  const resolvedRepo = path.resolve(repoRoot)

  // Disallow any storage inside repository working directory or common artifact paths
  if (resolvedTarget.startsWith(resolvedRepo)) {
    throw new Error(
      `Security violation: Auth state/manifest path "${resolvedTarget}" must not be located inside repository "${resolvedRepo}"`,
    )
  }

  const forbiddenSegments = ['.git', 'artifacts', 'test-results', 'playwright-report']
  for (const segment of forbiddenSegments) {
    if (resolvedTarget.split(path.sep).includes(segment)) {
      throw new Error(
        `Security violation: Path "${resolvedTarget}" contains sensitive/committed segment "${segment}"`,
      )
    }
  }
}

/**
 * Asserts that a mutation response is NOT demo-simulated.
 * Demo mutations are rolled back by SimulateDemoMutations middleware and must never count as persistence.
 */
export function assertNonDemoPersistence(
  headers: Headers | Record<string, string | string[] | undefined>,
): void {
  let demoHeader: string | undefined

  if (headers instanceof Headers) {
    demoHeader = headers.get('x-demo-simulated') || undefined
  } else {
    const val = headers['x-demo-simulated'] || headers['X-Demo-Simulated']
    demoHeader = Array.isArray(val) ? val[0] : val
  }

  if (demoHeader === 'true' || demoHeader === '1') {
    throw new Error(
      'Persistence violation: Received X-Demo-Simulated response header. Demo mutations roll back and must not count as browser acceptance persistence.',
    )
  }
}

/**
 * Authenticates an actor and saves or reuses their session storageState JSON file.
 * If cached auth state already exists for this actor in current run, reuses it without re-logging in.
 */
export async function authenticateActorSession(
  manifest: RunManifest,
  actor: ActorRole,
  page: Page,
  repoRoot?: string,
): Promise<ActorSession> {
  const authStatePath = getActorAuthStatePath(manifest, actor)

  if (repoRoot) {
    assertAuthStateSafeLocation(authStatePath, repoRoot)
  }

  const credential = getActorCredentials(manifest, actor)

  // Reuse existing auth state if already generated during this run
  if (fs.existsSync(authStatePath)) {
    return {
      actor,
      user: credential,
      authStatePath,
      reused: true,
    }
  }

  const frontendUrl = manifest.frontend.url

  // 1. Visit frontend to initialize CSRF and stateful cookies
  await page.goto(`${frontendUrl}/login`)

  // 2. Perform login via frontend form or API within page context
  const loginRes = await page.request.post(`${manifest.backend.url}/api/v1/auth/login`, {
    data: {
      email: credential.email,
      password: credential.password,
    },
    headers: {
      Accept: 'application/json',
      Referer: frontendUrl,
      Origin: frontendUrl,
    },
  })

  if (!loginRes.ok()) {
    const errText = await loginRes.text()
    throw new Error(
      `Actor login failed for "${actor}" (${credential.email}) with status ${loginRes.status()}: ${errText}`,
    )
  }

  // Verify non-demo persistence
  assertNonDemoPersistence(loginRes.headers())

  // Verify authenticated identity via /auth/me
  const meRes = await page.request.get(`${manifest.backend.url}/api/v1/auth/me`, {
    headers: {
      Accept: 'application/json',
      Referer: frontendUrl,
    },
  })
  if (!meRes.ok()) {
    throw new Error(`Failed to verify authenticated session for "${actor}": HTTP ${meRes.status()}`)
  }

  const meData = (await meRes.json()) as { user?: { email?: string; status?: string } }
  if (meData.user?.email !== credential.email) {
    throw new Error(
      `Session user mismatch: expected ${credential.email}, got ${meData.user?.email}`,
    )
  }

  // 3. Save storageState to dedicated outside-git ephemeral file
  await page.context().storageState({ path: authStatePath })

  return {
    actor,
    user: credential,
    authStatePath,
    reused: false,
  }
}

/**
 * Creates an isolated browser context pre-authenticated as the specified actor.
 */
export async function createAuthenticatedContext(
  manifest: RunManifest,
  actor: ActorRole,
  browser: { newContext: (options?: unknown) => Promise<BrowserContext> },
  helperPage?: Page,
  repoRoot?: string,
): Promise<BrowserContext> {
  const authStatePath = getActorAuthStatePath(manifest, actor)

  if (repoRoot) {
    assertAuthStateSafeLocation(authStatePath, repoRoot)
  }

  // Ensure auth state exists
  if (!fs.existsSync(authStatePath)) {
    if (!helperPage) {
      throw new Error(`Cannot initialize new auth state for "${actor}" without helper page`)
    }
    await authenticateActorSession(manifest, actor, helperPage, repoRoot)
  }

  // Create new context with stored auth state
  return await browser.newContext({
    storageState: authStatePath,
  })
}
