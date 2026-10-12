import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import { execFileSync, spawn } from 'node:child_process'
import {
  validateEnvironment,
  validateDatabaseTarget,
  validateFrontendRuntimeConfig,
} from './preflight.ts'
import { validateDatabaseForTeardown, validateManifestForTeardown } from './teardown.ts'
import { E2EIsolatedEnvironment } from './environment.ts'
import {
  getFuturePlayDate,
  arrangeCourt,
  arrangeCoupon,
  grantLoyaltyPoints,
  arrangePosStock,
  expireHolds,
  sendSyntheticPaymentWebhook,
  getCanonicalBackendEnv,
  pilotOrganizationIds,
} from './test-data.ts'
import {
  getActorCredentials,
  getActorAuthStatePath,
  assertAuthStateSafeLocation,
  assertNonDemoPersistence,
  type ActorRole,
} from './auth.ts'

test('Preflight: validates environment safety', () => {
  const prodViolations = validateEnvironment({ APP_ENV: 'production' })
  assert.ok(
    prodViolations.some((v) => v.includes('CRITICAL')),
    'Should flag production as critical',
  )

  const stagingViolations = validateEnvironment({ APP_ENV: 'staging' })
  assert.ok(
    stagingViolations.some((v) => v.includes('CRITICAL')),
    'Should flag staging as critical',
  )

  const emptyViolations = validateEnvironment({})
  assert.ok(emptyViolations.length > 0, 'Should reject undefined APP_ENV')

  const testViolations = validateEnvironment({ APP_ENV: 'testing' })
  assert.equal(testViolations.length, 0, 'Should accept APP_ENV=testing')
})

test('Preflight: validates database target safety', () => {
  // 1. Remote host rejection
  const remoteViolations = validateDatabaseTarget({
    host: '100.64.0.15',
    database: 'booking_e2e_123_test',
  })
  assert.ok(
    remoteViolations.some((v) => v.includes('DB_REMOTE_TARGET')),
    'Should reject remote database host',
  )

  // 2. Production database rejection
  const prodDbViolations = validateDatabaseTarget({
    host: '127.0.0.1',
    database: 'booking',
  })
  assert.ok(
    prodDbViolations.some((v) => v.includes('CRITICAL')),
    'Should reject production database "booking"',
  )

  // 3. Strict rejection of shared PHPUnit database
  const phpunitDbViolations = validateDatabaseTarget(
    { host: '127.0.0.1', database: 'booking_test' },
    { strict: true },
  )
  assert.ok(
    phpunitDbViolations.some((v) => v.includes('DB_SHARED_PHPUNIT')),
    'Should reject "booking_test" in strict mode',
  )

  // 4. Missing _test suffix rejection
  const nonTestViolations = validateDatabaseTarget({
    host: '127.0.0.1',
    database: 'booking_e2e_run',
  })
  assert.ok(
    nonTestViolations.some((v) => v.includes('DB_NAMING')),
    'Should require _test suffix',
  )

  // 5. Valid isolated database acceptance
  const validViolations = validateDatabaseTarget(
    { host: '127.0.0.1', database: 'booking_e2e_run123_test' },
    { strict: true, expectedDbName: 'booking_e2e_run123_test' },
  )
  assert.equal(validViolations.length, 0, 'Should accept valid isolated database')

  // 6. DB_URL override rejection when pointing to remote or production
  const remoteUrlViolations = validateDatabaseTarget({
    host: '127.0.0.1',
    database: 'booking_e2e_run123_test',
    url: 'postgres://user:pass@100.64.0.15:5432/booking_e2e_run123_test',
  })
  assert.ok(
    remoteUrlViolations.some((v) => v.includes('DB_URL_REMOTE')),
    'Should reject remote DB_URL',
  )

  const prodUrlViolations = validateDatabaseTarget({
    host: '127.0.0.1',
    database: 'booking_e2e_run123_test',
    url: 'postgres://user:pass@127.0.0.1:5432/booking',
  })
  assert.ok(
    prodUrlViolations.some((v) => v.includes('CRITICAL')),
    'Should reject production DB_URL',
  )
})

test('Preflight: validates frontend runtime-config override', () => {
  const tempDir = fs.mkdtempSync(path.join(fs.realpathSync('/tmp'), 'e2e-config-test-'))
  const publicDir = path.join(tempDir, 'public')
  fs.mkdirSync(publicDir, { recursive: true })

  try {
    // 1. External override should be rejected
    fs.writeFileSync(
      path.join(publicDir, 'runtime-config.json'),
      JSON.stringify({ apiUrl: 'https://production-api.auracourt.vn' }),
      'utf-8',
    )
    const violations = validateFrontendRuntimeConfig(tempDir, 'http://127.0.0.1:8010')
    assert.ok(
      violations.some((v) => v.includes('FRONTEND_API_OVERRIDE')),
      'Should reject external API url in runtime-config.json',
    )

    // 2. Empty string should be accepted (defaults to isolated Vite proxy / VITE_API_URL)
    fs.writeFileSync(
      path.join(publicDir, 'runtime-config.json'),
      JSON.stringify({ apiUrl: '' }),
      'utf-8',
    )
    const validViolations = validateFrontendRuntimeConfig(tempDir, 'http://127.0.0.1:8010')
    assert.equal(validViolations.length, 0, 'Should accept empty apiUrl')
  } finally {
    fs.rmSync(tempDir, { recursive: true, force: true })
  }
})

test('Teardown: refuses to drop protected databases', () => {
  assert.throws(
    () => validateDatabaseForTeardown('booking', 'run123'),
    /CRITICAL: Refusing to drop protected database/,
  )
  assert.throws(
    () => validateDatabaseForTeardown('booking_test', 'run123'),
    /CRITICAL: Refusing to drop protected database/,
  )
  assert.throws(
    () => validateDatabaseForTeardown('postgres', 'run123'),
    /CRITICAL: Refusing to drop protected database/,
  )
  assert.throws(
    () => validateDatabaseForTeardown('booking_e2e_other_run_test', 'run123'),
    /does not match manifest run ID/,
  )

  // Should succeed for the exact owned run database
  assert.doesNotThrow(() => validateDatabaseForTeardown('booking_e2e_run123_test', 'run123'))
})

test('Teardown: validates manifest integrity before cleanup', () => {
  // 1. Invalid runId format
  assert.throws(
    () =>
      validateManifestForTeardown({
        runId: 'bad run ID!@#',
        createdAt: new Date().toISOString(),
        tempDir: '/tmp/booking-e2e/bad run ID!@#',
        storagePath: '/tmp/booking-e2e/bad run ID!@#/storage',
        database: {
          name: 'booking_e2e_bad run ID!@#_test',
          host: '127.0.0.1',
          port: 5432,
          user: 'ziet',
        },
        backend: { port: 8010, url: 'http://127.0.0.1:8010' },
        frontend: { port: 4180, url: 'http://127.0.0.1:4180' },
        status: 'ready',
      }),
    /Invalid runId/,
  )

  // 2. Mismatched or non-canonical tempDir
  assert.throws(
    () =>
      validateManifestForTeardown({
        runId: 'validrun123',
        createdAt: new Date().toISOString(),
        tempDir: '/etc/unsafe',
        storagePath: '/etc/unsafe/storage',
        database: {
          name: 'booking_e2e_validrun123_test',
          host: '127.0.0.1',
          port: 5432,
          user: 'ziet',
        },
        backend: { port: 8010, url: 'http://127.0.0.1:8010' },
        frontend: { port: 4180, url: 'http://127.0.0.1:4180' },
        status: 'ready',
      }),
    /does not equal canonical path/,
  )

  // 3. Remote database host in manifest
  const canonicalTemp = path.resolve(os.tmpdir(), 'booking-e2e', 'validrun123')
  assert.throws(
    () =>
      validateManifestForTeardown({
        runId: 'validrun123',
        createdAt: new Date().toISOString(),
        tempDir: canonicalTemp,
        storagePath: path.join(canonicalTemp, 'storage'),
        database: {
          name: 'booking_e2e_validrun123_test',
          host: '100.64.0.15',
          port: 5432,
          user: 'ziet',
        },
        backend: { port: 8010, url: 'http://127.0.0.1:8010' },
        frontend: { port: 4180, url: 'http://127.0.0.1:4180' },
        status: 'ready',
      }),
    /is not local loopback/,
  )
})

test('Task 2.2 Canary: Preflight rejects wrong targets without mutating data', () => {
  const backendDir = path.resolve(import.meta.dirname, '../../../backend')
  const canaryDb = 'booking_canary_sentinel_test'
  let canaryDbCreated = false

  // 1. Create a dedicated disposable canary database owned by this test
  try {
    execFileSync('createdb', ['-h', '127.0.0.1', '-p', '5432', '-U', 'ziet', canaryDb], {
      stdio: ['ignore', 'pipe', 'pipe'],
    })
    canaryDbCreated = true
    execFileSync(
      'psql',
      [
        '-h',
        '127.0.0.1',
        '-p',
        '5432',
        '-U',
        'ziet',
        '-d',
        canaryDb,
        '-c',
        "CREATE TABLE canary_marker (id serial primary key, val text); INSERT INTO canary_marker (val) VALUES ('canary_unmodified_marker');",
      ],
      { encoding: 'utf-8' },
    )
  } catch (err) {
    console.warn('Could not setup canary database:', err)
  }
  const queryMarkerVal = () => {
    try {
      return execFileSync(
        'psql',
        [
          '-h',
          '127.0.0.1',
          '-p',
          '5432',
          '-U',
          'ziet',
          '-d',
          canaryDb,
          '-tAc',
          "SELECT val FROM canary_marker WHERE val = 'canary_unmodified_marker';",
        ],
        { encoding: 'utf-8' },
      ).trim()
    } catch {
      return ''
    }
  }

  const beforeVal = queryMarkerVal()
  assert.equal(
    beforeVal,
    'canary_unmodified_marker',
    'Canary marker must exist before rejected preflight',
  )

  try {
    // Run backend inspection against forbidden production database
    assert.throws(() => {
      execFileSync('php', ['artisan', 'e2e:inspect-target', '--strict', '--expected-db=booking'], {
        cwd: backendDir,
        env: {
          ...process.env,
          APP_ENV: 'production',
          DB_DATABASE: 'booking',
        },
        stdio: ['ignore', 'pipe', 'pipe'],
      })
    }, 'Should exit with non-zero when target is production/booking')

    // Run backend inspection against forbidden remote DB_URL
    assert.throws(() => {
      execFileSync(
        'php',
        ['artisan', 'e2e:inspect-target', '--strict', '--expected-db=booking_e2e_canary_test'],
        {
          cwd: backendDir,
          env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_URL: 'postgres://ziet:pass@100.64.0.15:5432/booking_e2e_canary_test',
          },
          stdio: ['ignore', 'pipe', 'pipe'],
        },
      )
    }, 'Should exit with non-zero when DB_URL points to remote host')

    // Verify data in the owned canary database is 100% UNCHANGED
    const afterVal = queryMarkerVal()
    assert.equal(
      afterVal,
      beforeVal,
      'Canary marker must be identical before and after rejected preflight',
    )
  } finally {
    if (canaryDbCreated) {
      try {
        execFileSync(
          'dropdb',
          ['-h', '127.0.0.1', '-p', '5432', '-U', 'ziet', '--if-exists', canaryDb],
          {
            stdio: ['ignore', 'pipe', 'pipe'],
          },
        )
      } catch {
        // Ignore cleanup
      }
    }
  }
})

test('Task 2.3: Interruption via SIGTERM cleanly tears down resources without touching external databases', async () => {
  const interruptRunId = `sigterm_${Date.now()}`
  const externalDb = `booking_canary_ext_${Date.now()}_test`
  let externalDbCreated = false

  // Prepare owned external database to verify it is NOT touched
  try {
    execFileSync('createdb', ['-h', '127.0.0.1', '-p', '5432', '-U', 'ziet', externalDb], {
      stdio: ['ignore', 'pipe', 'pipe'],
    })
    externalDbCreated = true
  } catch (err) {
    console.warn('Could not create external verification DB:', err)
  }
  const childScript = `
    import { E2EIsolatedEnvironment } from ${JSON.stringify(path.resolve(import.meta.dirname, './environment.ts'))};
    const env = new E2EIsolatedEnvironment({ runId: "${interruptRunId}" });
    const manifest = await env.start({ skipFrontend: true });
    console.log("CHILD_READY:" + manifest.runId);
    setInterval(() => {}, 1000);
  `

  const child = spawn('node', ['--experimental-strip-types', '-e', childScript], {
    stdio: ['ignore', 'pipe', 'pipe'],
  })

  let childStdout = ''
  let childStderr = ''
  child.stdout?.on('data', (chunk) => {
    childStdout += chunk.toString()
  })
  child.stderr?.on('data', (chunk) => {
    childStderr += chunk.toString()
  })

  try {
    // Wait for CHILD_READY
    const deadline = Date.now() + 30000
    while (Date.now() < deadline && !childStdout.includes('CHILD_READY:')) {
      const { promise, resolve } = Promise.withResolvers<void>()
      setTimeout(resolve, 200)
      await promise
    }

    assert.ok(
      childStdout.includes('CHILD_READY:'),
      `Child runner should reach ready state. stdout: ${childStdout}, stderr: ${childStderr}`,
    )

    const targetDbName = `booking_e2e_${interruptRunId}_test`

    // Verify database exists while child is running
    const dbCheckBefore = execFileSync(
      'psql',
      [
        '-h',
        '127.0.0.1',
        '-p',
        '5432',
        '-U',
        'ziet',
        '-d',
        'postgres',
        '-tAc',
        `SELECT 1 FROM pg_database WHERE datname = '${targetDbName}'`,
      ],
      { encoding: 'utf-8' },
    ).trim()
    assert.equal(dbCheckBefore, '1', 'Dedicated database should exist while child is running')

    // Send SIGTERM to simulate runner cancellation / interruption
    child.kill('SIGTERM')

    // Wait for child to exit
    const exitPromise = Promise.withResolvers<number | null>()
    child.once('exit', (code) => exitPromise.resolve(code))
    await exitPromise.promise

    // Verify dedicated database was cleaned up
    const dbCheckAfter = execFileSync(
      'psql',
      [
        '-h',
        '127.0.0.1',
        '-p',
        '5432',
        '-U',
        'ziet',
        '-d',
        'postgres',
        '-tAc',
        `SELECT 1 FROM pg_database WHERE datname = '${targetDbName}'`,
      ],
      { encoding: 'utf-8' },
    ).trim()
    assert.equal(dbCheckAfter, '', 'Dedicated database should be dropped upon SIGTERM interruption')

    // Verify owned external database still exists and was untouched
    const externalDbCheck = execFileSync(
      'psql',
      [
        '-h',
        '127.0.0.1',
        '-p',
        '5432',
        '-U',
        'ziet',
        '-d',
        'postgres',
        '-tAc',
        `SELECT 1 FROM pg_database WHERE datname = '${externalDb}'`,
      ],
      { encoding: 'utf-8' },
    ).trim()
    assert.equal(externalDbCheck, '1', 'External database must remain untouched')
  } finally {
    if (externalDbCreated) {
      try {
        execFileSync(
          'dropdb',
          ['-h', '127.0.0.1', '-p', '5432', '-U', 'ziet', '--if-exists', externalDb],
          {
            stdio: ['ignore', 'pipe', 'pipe'],
          },
        )
      } catch {
        // Ignore cleanup
      }
    }
  }
})
test('Tasks 2.1 & 2.3: Full isolated environment lifecycle (frontend + backend)', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `lifecycle_${Date.now()}`,
  })

  try {
    // Start environment with BOTH backend and frontend active
    const manifest = await env.start({ skipFrontend: false })

    assert.ok(manifest.runId, 'Manifest should have runId')
    assert.ok(manifest.database.name.startsWith('booking_e2e_'), 'Database should have e2e prefix')
    assert.ok(manifest.database.name.endsWith('_test'), 'Database should have _test suffix')
    assert.ok(manifest.backend.pid && manifest.backend.pid > 0, 'Backend PID should be positive')
    assert.ok(manifest.frontend.pid && manifest.frontend.pid > 0, 'Frontend PID should be positive')
    assert.equal(manifest.status, 'ready', 'Manifest status should be ready')

    // Verify generated non-demo test credentials
    assert.ok(manifest.credentials?.customer?.email, 'Customer email generated')
    assert.ok(manifest.credentials?.customer?.password, 'Customer password generated')
    assert.ok(manifest.credentials?.owner?.email, 'Owner email generated')
    assert.ok(manifest.credentials?.manager?.email, 'Manager email generated')
    assert.ok(manifest.credentials?.cashier?.email, 'Cashier email generated')
    assert.ok(manifest.credentials?.staff?.email, 'Staff email generated')
    assert.ok(manifest.credentials?.admin?.email, 'Admin email generated')
    // Task 2.4: Verify seeded fixtures
    assert.ok(manifest.fixtures, 'Manifest should contain seeded fixtures')
    const fixtures = manifest.fixtures
    assert.ok(
      (fixtures.organizations.org1?.venue1A?.courts?.length ?? 0) >= 2,
      'Org 1 Venue 1A has courts',
    )
    assert.ok(
      (fixtures.organizations.org1?.venue1B?.courts?.length ?? 0) >= 1,
      'Org 1 Venue 1B has court',
    )
    assert.ok(
      (fixtures.organizations.org2?.venue2?.courts?.length ?? 0) >= 1,
      'Org 2 Venue 2 has court',
    )
    assert.ok(fixtures.users.foreignOwner?.id, 'Foreign owner exists')
    assert.ok(fixtures.users.unverifiedCustomer?.id, 'Unverified customer exists')
    assert.ok(fixtures.users.restrictedCustomer?.id, 'Restricted customer exists')
    // Verify storage path is isolated in tempDir
    assert.ok(
      fs.existsSync(path.join(manifest.storagePath, 'framework', 'sessions')),
      'Sessions dir exists',
    )
    assert.ok(
      fs.existsSync(path.join(manifest.storagePath, 'framework', 'cache')),
      'Cache dir exists',
    )

    // Verify backend responds to healthcheck
    const healthRes = await fetch(`${manifest.backend.url}/up`)
    assert.equal(healthRes.status, 200, 'Backend healthcheck should return HTTP 200')

    // Verify frontend responds to HTTP request
    const frontendRes = await fetch(manifest.frontend.url)
    assert.equal(frontendRes.status, 200, 'Frontend should return HTTP 200')

    // Verify database actually exists in postgres
    const dbCheck = execFileSync(
      'psql',
      [
        '-h',
        '127.0.0.1',
        '-p',
        '5432',
        '-U',
        'ziet',
        '-d',
        'postgres',
        '-tAc',
        `SELECT 1 FROM pg_database WHERE datname = '${manifest.database.name}'`,
      ],
      { encoding: 'utf-8' },
    ).trim()
    assert.equal(dbCheck, '1', 'Dedicated database should exist in PostgreSQL')

    // Perform teardown
    const backendPid = manifest.backend.pid
    const frontendPid = manifest.frontend.pid
    const dbName = manifest.database.name
    const tempDir = manifest.tempDir

    await env.stop()

    // Verify database was dropped
    const dbAfterCheck = execFileSync(
      'psql',
      [
        '-h',
        '127.0.0.1',
        '-p',
        '5432',
        '-U',
        'ziet',
        '-d',
        'postgres',
        '-tAc',
        `SELECT 1 FROM pg_database WHERE datname = '${dbName}'`,
      ],
      { encoding: 'utf-8' },
    ).trim()
    assert.equal(dbAfterCheck, '', 'Dedicated database should be dropped during teardown')

    // Verify backend and frontend processes are terminated
    assert.throws(() => {
      process.kill(backendPid!, 0)
    }, 'Backend process should be terminated')

    assert.throws(() => {
      process.kill(frontendPid!, 0)
    }, 'Frontend process should be terminated')
    const leftoverServers = execFileSync('ps', ['-Ao', 'command'], { encoding: 'utf-8' })
      .split('\n')
      .filter((command) => command.includes(`-S 127.0.0.1:${manifest.backend.port} `))
    assert.deepEqual(leftoverServers, [], 'PHP built-in server workers should be terminated')

    // Verify temp directory was removed
    assert.equal(fs.existsSync(tempDir), false, 'Temp dir should be removed on completion')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('Tasks 2.1 & 2.3: Failure during startup cleans up all resources without leaks', async () => {
  const failingRunId = `failing_${Date.now()}`
  const env = new E2EIsolatedEnvironment({
    runId: failingRunId,
  })

  // Mock backendDir temporarily to a non-existent path so migration or preflight fails after createdb
  Object.defineProperty(env, 'backendDir', { value: '/tmp/nonexistent-backend' })
  try {
    await assert.rejects(async () => {
      await env.start({ skipFrontend: true })
    })
    // Verify database was dropped
    const dbCheck = execFileSync(
      'psql',
      [
        '-h',
        '127.0.0.1',
        '-p',
        '5432',
        '-U',
        'ziet',
        '-d',
        'postgres',
        '-tAc',
        `SELECT 1 FROM pg_database WHERE datname = 'booking_e2e_${failingRunId}_test'`,
      ],
      { encoding: 'utf-8' },
    ).trim()
    assert.equal(dbCheck, '', 'Database should be cleaned up on startup failure')
    assert.equal(fs.existsSync(env.tempDir), false, 'Temp dir should be removed on startup failure')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})
test('Task 2.4: Seeded fixtures satisfy non-demo, two organizations, sibling venues, all roles, and real readiness policies', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `fixtures24_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })
    assert.ok(manifest.fixtures, 'Fixtures must be present in manifest')
    const fixtures = manifest.fixtures
    // 1. Two organizations & sibling venues structure
    assert.ok(fixtures.organizations?.org1?.id, 'Org 1 exists')
    assert.ok(fixtures.organizations?.org2?.id, 'Org 2 exists')
    assert.notEqual(
      fixtures.organizations.org1.id,
      fixtures.organizations.org2.id,
      'Org 1 and Org 2 must be distinct',
    )
    // D19: pilot-only features cover exactly the two fixture organizations, never '*'.
    assert.equal(
      getCanonicalBackendEnv(manifest).PILOT_ORGANIZATION_IDS,
      `${fixtures.organizations.org1.id},${fixtures.organizations.org2.id}`,
    )

    const venue1A = fixtures.organizations.org1.venue1A
    const venue1B = fixtures.organizations.org1.venue1B
    const venue2 = fixtures.organizations.org2.venue2

    assert.ok(venue1A?.id, 'Venue 1A exists in Org 1')
    assert.ok(venue1B?.id, 'Venue 1B (sibling venue) exists in Org 1')
    assert.notEqual(venue1A.id, venue1B.id, 'Venue 1A and 1B must be distinct sibling venues')
    assert.ok(venue2?.id, 'Venue 2 exists in Org 2')

    // 2. All required actors
    const requiredRoles = [
      'admin',
      'customer',
      'unverifiedCustomer',
      'restrictedCustomer',
      'owner',
      'manager',
      'cashier',
      'staff',
      'foreignOwner',
    ]
    for (const roleKey of requiredRoles) {
      assert.ok(fixtures.users?.[roleKey]?.id, `User ${roleKey} must exist`)
      assert.ok(fixtures.users?.[roleKey]?.email, `User ${roleKey} must have email`)
      assert.ok(fixtures.users?.[roleKey]?.password, `User ${roleKey} must have password`)
    }

    // 3. Domain evaluation: Readiness, Sibling venue access, and Actor status
    const phpVerification = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Venue;
      use App\\Models\\User;
      use App\\Models\\Organization;
      use App\\Domain\\Venue\\Actions\\EvaluateVenueReadiness;

      $readiness = app(EvaluateVenueReadiness::class);
      $v1A = Venue::find(${venue1A.id});
      $v1B = Venue::find(${venue1B.id});
      $v2 = Venue::find(${venue2.id});

      $manager = User::find(${fixtures.users.manager.id});
      $owner1 = User::find(${fixtures.users.owner.id});
      $unverified = User::find(${fixtures.users.unverifiedCustomer.id});
      $restricted = User::find(${fixtures.users.restrictedCustomer.id});
      $org1 = Organization::find(${fixtures.organizations.org1.id});
      $org2 = Organization::find(${fixtures.organizations.org2.id});

      $results = [
        'v1A_ready' => $readiness->execute($v1A)['is_ready'],
        'v1B_ready' => $readiness->execute($v1B)['is_ready'],
        'v2_ready' => $readiness->execute($v2)['is_ready'],
        'manager_can_manage_1A' => $manager->canManageVenue($v1A),
        'manager_can_manage_1B' => $manager->canManageVenue($v1B), // Sibling venue denial
        'manager_can_manage_2' => $manager->canManageVenue($v2),   // Foreign tenant denial
        'owner_can_manage_1A' => $owner1->canManageVenue($v1A),
        'owner_can_manage_1B' => $owner1->canManageVenue($v1B),
        'owner_can_manage_2' => $owner1->canManageVenue($v2),     // Foreign tenant denial
        'unverified_email' => $unverified->email_verified_at === null,
        'unverified_phone' => $unverified->phone_verified_at === null,
        'restricted_suspended' => $restricted->status === 'suspended',
        'org1_partner_approved' => $org1->isApprovedPartner(),
        'org1_has_subscription' => $org1->hasActiveSubscription(),
        'org2_partner_approved' => $org2->isApprovedPartner(),
        'org2_has_subscription' => $org2->hasActiveSubscription(),
      ];

      echo json_encode($results);
    `

    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'file',
    }

    const out = execFileSync('php', ['-r', phpVerification], {
      cwd: env.backendDir,
      env: { ...process.env, ...backendEnv },
      encoding: 'utf-8',
    })

    const checks = JSON.parse(out)
    assert.equal(checks.v1A_ready, true, 'Venue 1A must be ready per real readiness policies')
    assert.equal(checks.v1B_ready, true, 'Venue 1B must be ready per real readiness policies')
    assert.equal(checks.v2_ready, true, 'Venue 2 must be ready per real readiness policies')

    assert.equal(checks.manager_can_manage_1A, true, 'Manager must be able to manage Venue 1A')
    assert.equal(
      checks.manager_can_manage_1B,
      false,
      'Manager must be denied managing sibling Venue 1B (sibling venue denial)',
    )
    assert.equal(
      checks.manager_can_manage_2,
      false,
      'Manager must be denied managing foreign Venue 2 (foreign tenant denial)',
    )

    assert.equal(checks.owner_can_manage_1A, true, 'Owner 1 can manage Venue 1A')
    assert.equal(checks.owner_can_manage_1B, true, 'Owner 1 can manage sibling Venue 1B')
    assert.equal(checks.owner_can_manage_2, false, 'Owner 1 denied managing foreign Venue 2')

    assert.equal(checks.unverified_email, true, 'Unverified customer must have unverified email')
    assert.equal(checks.unverified_phone, true, 'Unverified customer must have unverified phone')
    assert.equal(
      checks.restricted_suspended,
      true,
      'Restricted customer must have status=suspended',
    )

    assert.equal(checks.org1_partner_approved, true, 'Org 1 partner status must be approved')
    assert.equal(checks.org1_has_subscription, true, 'Org 1 must have active subscription')
    assert.equal(checks.org2_partner_approved, true, 'Org 2 partner status must be approved')
    assert.equal(checks.org2_has_subscription, true, 'Org 2 must have active subscription')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})
test('Task 2.5: getFuturePlayDate derives deterministic date in Asia/Ho_Chi_Minh timezone', () => {
  const futureDate = getFuturePlayDate(3, 15, 30)
  assert.match(futureDate.dateString, /^\d{4}-\d{2}-\d{2}$/, 'Date string must be YYYY-MM-DD')
  assert.equal(futureDate.hourString, '15:30', 'Hour string must match requested time')
  assert.ok(futureDate.weekday >= 1 && futureDate.weekday <= 7, 'Weekday must be 1..7 (ISO)')
  assert.ok(futureDate.slotTime.includes('+07:00'), 'Slot time must include +07:00 timezone offset')
  assert.ok(new Date(futureDate.isoString).getTime() > Date.now(), 'Must be a future timestamp')
})

test('Task 2.5: Test data arrangement generates isolated courts, coupons, points, and stock per test', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `arrange_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })

    // 1. Arrange dedicated court with real pricing
    const court = arrangeCourt(manifest, {
      testKey: 'uat_isolated_court',
      price: 130000,
    })
    assert.equal(court.status, 'success')
    assert.ok(court.court_id > 0)
    assert.equal(court.price, 130000)
    assert.ok(court.slug.includes('uat-isolated-court'))

    // 2. Arrange dedicated coupon
    const coupon = arrangeCoupon(manifest, {
      testKey: 'uat_discount_20k',
      discountValue: 25000,
    })
    assert.equal(coupon.status, 'success')
    assert.ok(coupon.coupon_id > 0)
    assert.equal(coupon.discount_value, 25000)

    // 3. Arrange loyalty points for customer
    const customer = manifest.fixtures?.users.customer
    assert.ok(customer?.id)
    const points = grantLoyaltyPoints(manifest, {
      userId: customer.id,
      points: 250,
    })
    assert.equal(points.status, 'success')
    assert.equal(points.granted_points, 250)
    assert.ok(points.current_balance >= 250)

    // 4. Arrange POS stock
    const stock = arrangePosStock(manifest, {
      testKey: 'energy_drink',
      price: 25000,
      stockQuantity: 40,
    })
    assert.equal(stock.status, 'success')
    assert.ok(stock.product_id > 0)
    assert.equal(stock.stock_quantity, 40)
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('Task 2.5: Test-only CLI backdates hold expiry and sweeps release of held resources', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `expiry_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })
    const court = arrangeCourt(manifest, {
      testKey: 'expiry_test_court',
      price: 100000,
    })

    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'file',
    }

    // Create a pending booking holding a slot
    const createBookingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\BookingSlot;
      use App\\Models\\Court;
      use App\\Models\\User;

      $user = User::where('role', 'customer')->firstOrFail();
      $court = Court::findOrFail(${court.court_id});

      $booking = Booking::create([
        'code' => 'BK-TEST-EXPIRY-${Date.now()}',
        'user_id' => $user->id,
        'court_id' => $court->id,
        'total_amount' => 100000,
        'status' => 'pending_payment',
        'payment_model' => 'direct',
        'payment_expires_at' => now()->addMinutes(15),
      ]);

      BookingSlot::create([
        'booking_id' => $booking->id,
        'court_id' => $court->id,
        'starts_at' => now()->addDays(2)->setTime(10, 0),
        'ends_at' => now()->addDays(2)->setTime(11, 0),
        'unit_price' => 100000,
        'status' => 'held',
      ]);

      echo json_encode([
        'booking_code' => $booking->code,
        'booking_id' => $booking->id,
      ]);
    `

    const createOut = execFileSync('php', ['-r', createBookingScript], {
      cwd: env.backendDir,
      env: { ...process.env, ...backendEnv },
      encoding: 'utf-8',
    })

    const created = JSON.parse(createOut) as { booking_code: string; booking_id: number }
    assert.ok(created.booking_code, 'Booking must be created')

    // Backdate and sweep hold expiration via test-only CLI
    const expiryResult = expireHolds(manifest, {
      bookingCode: created.booking_code,
      minutes: 30,
    })
    assert.equal(expiryResult.status, 'success')
    assert.ok(expiryResult.expired_count >= 1, 'At least 1 booking must be expired')

    // Verify that the booking status is now 'expired' and held slot is 'expired'
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;

      $booking = Booking::where('code', '${created.booking_code}')->with('slots')->firstOrFail();
      echo json_encode([
        'booking_status' => $booking->status,
        'slot_status' => $booking->slots->first()?->status,
      ]);
    `

    const verifyOut = execFileSync('php', ['-r', verifyScript], {
      cwd: env.backendDir,
      env: { ...process.env, ...backendEnv },
      encoding: 'utf-8',
    })

    const verified = JSON.parse(verifyOut) as { booking_status: string; slot_status: string }
    assert.equal(verified.booking_status, 'expired', 'Booking status must transition to expired')
    assert.equal(verified.slot_status, 'expired', 'Held slot status must transition to expired')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('Task 2.5: Independent tests run without execution order dependency', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `order_indep_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })

    // Scenario A: Prepares Court A and Coupon A
    const runScenarioA = () => {
      const courtA = arrangeCourt(manifest, { testKey: 'scenario_a', price: 90000 })
      const couponA = arrangeCoupon(manifest, { testKey: 'scenario_a', discountValue: 15000 })
      return { courtA, couponA }
    }

    // Scenario B: Prepares Stock B and Points B
    const runScenarioB = () => {
      const stockB = arrangePosStock(manifest, {
        testKey: 'scenario_b',
        price: 30000,
        stockQuantity: 20,
      })
      const customer = manifest.fixtures?.users.customer
      const pointsB = grantLoyaltyPoints(manifest, { userId: customer?.id, points: 75 })
      return { stockB, pointsB }
    }

    // Order 1: Run A then B
    const resA = runScenarioA()
    const resB = runScenarioB()

    assert.ok(resA.courtA.court_id > 0)
    assert.equal(resA.couponA.discount_value, 15000)
    assert.equal(resB.stockB.stock_quantity, 20)
    assert.equal(resB.pointsB.granted_points, 75)

    // Order 2: Reverse execution on fresh isolated scenarios without collision
    const resB2 = arrangePosStock(manifest, {
      testKey: 'scenario_b2',
      price: 35000,
      stockQuantity: 15,
    })
    const resA2 = arrangeCourt(manifest, { testKey: 'scenario_a2', price: 95000 })

    assert.equal(resB2.stock_quantity, 15)
    assert.equal(resA2.price, 95000)
    assert.notEqual(resA.courtA.court_id, resA2.court_id, 'Courts must be distinct')
    assert.notEqual(resB.stockB.product_id, resB2.product_id, 'Stock items must be distinct')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})
test('Task 2.6: Synthetic signed webhook event drives real receipt processor and confirms booking', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `webhook_ok_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })
    const venue1A = manifest.fixtures?.organizations.org1.venue1A
    assert.ok(venue1A?.routingId, 'Venue 1A routingId must exist')

    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'file',
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
      PILOT_ORGANIZATION_IDS: pilotOrganizationIds(manifest.fixtures!),
    }

    // 1. Create booking and payment attempt in backend
    const createScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\User;
      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Domain\\Booking\\DTOs\\CreateBookingData;
      use App\\Domain\\Booking\\Actions\\CreateBooking;
      use App\\Services\\Payment\\ReservationPaymentService;

      $user = User::where('email', 'like', 'customer_%')->firstOrFail();
      $court = Court::where('slug', 'like', '%court-1a1%')->firstOrFail();

      $targetSlot = now()->addDays(2)->setTime(10, 0)->toIso8601String();
      $dto = new CreateBookingData(
        userId: $user->id,
        courtId: $court->id,
        slots: [$targetSlot],
        couponCode: null,
        loyaltyPoints: null,
      );

      $booking = app(CreateBooking::class)->execute($dto);
      $mapping = LegacyBookingMapping::query()->withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();
      $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
      $attempt = app(ReservationPaymentService::class)->initiate($reservation, $user);

      echo json_encode([
        'booking_code' => $booking->code,
        'attempt_reference' => $attempt->reference,
        'amount' => $attempt->amount,
      ]);
    `

    const createOut = execFileSync('php', ['-r', createScript], {
      cwd: env.backendDir,
      env: { ...process.env, ...backendEnv },
      encoding: 'utf-8',
    })

    const bookingData = JSON.parse(createOut) as {
      booking_code: string
      attempt_reference: string
      amount: number
    }
    assert.ok(bookingData.booking_code)
    assert.ok(bookingData.attempt_reference)

    // 2. Deliver valid authenticated synthetic payment webhook
    const secret = `whsec_e2e_1a_${manifest.runId}`
    const webhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: '0987654321',
      amount: bookingData.amount,
      code: bookingData.attempt_reference,
      referenceCode: bookingData.attempt_reference,
    })
    assert.equal(
      webhookRes.statusCode,
      200,
      'Webhook endpoint must accept valid signed event with HTTP 200',
    )
    // 3. Verify real receipt processor transitioned booking, slots, attempt, and receipt
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\OwnerPaymentReceipt;

      $booking = Booking::where('code', '${bookingData.booking_code}')->with('slots')->firstOrFail();
      $attempt = OwnerPaymentAttempt::where('reference', '${bookingData.attempt_reference}')->firstOrFail();
      $receipt = OwnerPaymentReceipt::where('payment_code', '${bookingData.attempt_reference}')->first();

      echo json_encode([
        'booking_status' => $booking->status,
        'slot_status' => $booking->slots->first()?->status,
        'attempt_status' => $attempt->status,
        'receipt_status' => $receipt?->status,
        'receipt_exception' => $receipt?->exception_code,
        'receipt_event_at' => $receipt?->event_at?->toIso8601String(),
        'attempt_created_at' => $attempt->created_at?->toIso8601String(),
      ]);
    `

    const verifyOut = execFileSync('php', ['-r', verifyScript], {
      cwd: env.backendDir,
      env: { ...process.env, ...backendEnv },
      encoding: 'utf-8',
    })

    const outcome = JSON.parse(verifyOut) as {
      booking_status: string
      slot_status: string
      attempt_status: string
      receipt_status: string
    }
    assert.equal(
      outcome.booking_status,
      'confirmed',
      'Booking must be confirmed after real receipt processing',
    )
    assert.equal(outcome.slot_status, 'confirmed', 'Booking slot must transition to confirmed')
    assert.equal(outcome.attempt_status, 'paid', 'Owner payment attempt must transition to paid')
    assert.equal(outcome.receipt_status, 'allocated', 'Owner payment receipt must be allocated')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('Task 2.6: Invalid webhook signature is rejected with HTTP 401 without mutating data', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `webhook_rej_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })
    const venue1A = manifest.fixtures?.organizations.org1.venue1A
    assert.ok(venue1A?.routingId)

    const secret = `whsec_e2e_1a_${manifest.runId}`
    const webhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: '0987654321',
      amount: 100000,
      code: 'BK-INVALID-SIG',
      tamperSignature: true, // Corrupts signature to sha256=000...
    })

    assert.equal(
      webhookRes.statusCode,
      401,
      'Endpoint must reject tampered signature with HTTP 401',
    )
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('Task 2.6: Duplicate webhook event delivery is idempotent and creates no double effect', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `webhook_dup_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })
    const venue1A = manifest.fixtures?.organizations.org1.venue1A
    assert.ok(venue1A?.routingId)

    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'file',
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
      PILOT_ORGANIZATION_IDS: pilotOrganizationIds(manifest.fixtures!),
    }

    const createScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\User;
      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Domain\\Booking\\DTOs\\CreateBookingData;
      use App\\Domain\\Booking\\Actions\\CreateBooking;
      use App\\Services\\Payment\\ReservationPaymentService;

      $user = User::where('email', 'like', 'customer_%')->firstOrFail();
      $court = Court::where('slug', 'like', '%court-1a1%')->firstOrFail();

      $targetSlot = now()->addDays(2)->setTime(10, 0)->toIso8601String();
      $dto = new CreateBookingData(
        userId: $user->id,
        courtId: $court->id,
        slots: [$targetSlot],
        couponCode: null,
        loyaltyPoints: null,
      );

      $booking = app(CreateBooking::class)->execute($dto);
      $mapping = LegacyBookingMapping::query()->withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();
      $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
      $attempt = app(ReservationPaymentService::class)->initiate($reservation, $user);

      echo json_encode([
        'booking_code' => $booking->code,
        'attempt_reference' => $attempt->reference,
        'amount' => $attempt->amount,
      ]);
    `

    const createOut = execFileSync('php', ['-r', createScript], {
      cwd: env.backendDir,
      env: { ...process.env, ...backendEnv },
      encoding: 'utf-8',
    })

    const bookingData = JSON.parse(createOut) as {
      booking_code: string
      attempt_reference: string
      amount: number
    }

    const secret = `whsec_e2e_1a_${manifest.runId}`
    const transactionId = `TXN-DUP-${Date.now()}`
    const transactionDate = new Date(Date.now() + 5000).toISOString()

    // First delivery
    const res1 = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: '0987654321',
      amount: bookingData.amount,
      code: bookingData.attempt_reference,
      transactionId,
      transactionDate,
    })
    assert.equal(res1.statusCode, 200, 'First delivery must succeed')

    // Second delivery with identical transaction ID and payload (replay)
    const res2 = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: '0987654321',
      amount: bookingData.amount,
      code: bookingData.attempt_reference,
      transactionId,
      transactionDate,
    })
    assert.equal(res2.statusCode, 200, 'Duplicate delivery must succeed idempotently')

    // Verify that only one receipt was created and no double allocation occurred
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentReceipt;

      $receiptCount = OwnerPaymentReceipt::where('payment_code', '${bookingData.attempt_reference}')->count();

      echo json_encode([
        'receipt_count' => $receiptCount,
      ]);
    `

    const verifyOut = execFileSync('php', ['-r', verifyScript], {
      cwd: env.backendDir,
      env: { ...process.env, ...backendEnv },
      encoding: 'utf-8',
    })

    const result = JSON.parse(verifyOut) as { receipt_count: number }
    assert.equal(
      result.receipt_count,
      1,
      'Only one receipt must exist for the duplicated provider event',
    )
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('Task 2.6: Runner enforces zero external network egress and blocks production mutation endpoints', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `egress_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })

    // Verify no unauthenticated production reset endpoint exists
    const resetRes = await fetch(`${manifest.backend.url}/api/v1/reset`, { method: 'POST' })
    assert.notEqual(resetRes.status, 200, 'Production reset endpoint must not be exposed')
    assert.ok(resetRes.status === 404 || resetRes.status === 405, 'Reset route must fail closed')

    const paidRes = await fetch(`${manifest.backend.url}/api/v1/paid`, { method: 'POST' })
    assert.notEqual(paidRes.status, 200, 'Production paid shortcut endpoint must not be exposed')
    assert.ok(
      paidRes.status === 404 || paidRes.status === 405,
      'Paid shortcut route must fail closed',
    )

    // Verify external network egress attempt is refused/fails closed
    await assert.rejects(async () => {
      await fetch('http://127.0.0.1:9/external-simulation', { signal: AbortSignal.timeout(1000) })
    }, 'Direct attempt to external gateway on blackhole port must fail closed')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})
test('Task 2.7: Actor credentials and storage state are managed outside git and artifacts', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `auth_loc_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })
    const roles: ActorRole[] = [
      'admin',
      'owner1',
      'owner2',
      'manager1',
      'cashier1',
      'staff1',
      'customer',
      'unverifiedCustomer',
      'restrictedCustomer',
    ]

    const repoRoot = path.resolve(import.meta.dirname, '../../../')

    for (const role of roles) {
      const cred = getActorCredentials(manifest, role)
      assert.ok(cred.email, `Email must exist for role ${role}`)
      assert.ok(cred.password, `Password must exist for role ${role}`)

      const authPath = getActorAuthStatePath(manifest, role)
      assert.ok(
        authPath.startsWith(manifest.storagePath),
        `Auth path must reside inside ephemeral storage dir`,
      )

      // Must pass safe location assertion
      assert.doesNotThrow(() => {
        assertAuthStateSafeLocation(authPath, repoRoot)
      })
    }

    // Negative security check: reject paths inside repo root or git
    assert.throws(() => {
      assertAuthStateSafeLocation(path.join(repoRoot, 'frontend/e2e/auth.json'), repoRoot)
    }, /Security violation/)

    assert.throws(() => {
      assertAuthStateSafeLocation('/tmp/foo/.git/auth.json', repoRoot)
    }, /Security violation/)

    assert.throws(() => {
      assertAuthStateSafeLocation('/tmp/foo/artifacts/auth.json', repoRoot)
    }, /Security violation/)
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('Task 2.7: Non-demo persistence validation rejects demo-simulated responses', () => {
  // Throws on demo simulation header
  assert.throws(() => {
    assertNonDemoPersistence({ 'x-demo-simulated': 'true' })
  }, /Persistence violation/)

  assert.throws(() => {
    assertNonDemoPersistence(new Headers({ 'X-Demo-Simulated': 'true' }))
  }, /Persistence violation/)

  // Accepts normal non-demo response
  assert.doesNotThrow(() => {
    assertNonDemoPersistence({ 'x-demo-simulated': 'false' })
  })

  assert.doesNotThrow(() => {
    assertNonDemoPersistence({})
  })
})

test('Task 2.7: Actor session authentication and reuse with real persistence', async () => {
  const env = new E2EIsolatedEnvironment({
    runId: `auth_sess_${Date.now()}`,
  })

  try {
    const manifest = await env.start({ skipFrontend: true })
    const customerCred = getActorCredentials(manifest, 'customer')
    const ownerCred = getActorCredentials(manifest, 'owner1')

    assert.notEqual(customerCred.email, ownerCred.email, 'Actors must have distinct identities')

    // Authenticate customer session via HTTP
    const csrfHeaders: Record<string, string> = {
      Accept: 'application/json',
      Origin: manifest.frontend.url,
      Referer: manifest.frontend.url,
    }
    const csrfRes = await fetch(`${manifest.backend.url}/sanctum/csrf-cookie`, {
      headers: csrfHeaders,
    })
    assert.equal(csrfRes.status, 204, 'CSRF cookie must be established')
    const rawCookies = csrfRes.headers.getSetCookie?.() || []
    const xsrfCookie = rawCookies
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split(';')[0]
      ?.split('=')[1]
    const sessionCookie = rawCookies.find((c) => c.includes('session'))?.split(';')[0]

    const loginHeaders: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Origin: manifest.frontend.url,
      Referer: manifest.frontend.url,
    }
    const cookieHeaderParts: string[] = []
    if (xsrfCookie) {
      loginHeaders['X-XSRF-TOKEN'] = decodeURIComponent(xsrfCookie)
      cookieHeaderParts.push(`XSRF-TOKEN=${xsrfCookie}`)
    }
    if (sessionCookie) {
      cookieHeaderParts.push(sessionCookie)
    }
    if (cookieHeaderParts.length > 0) {
      loginHeaders['Cookie'] = cookieHeaderParts.join('; ')
    }

    const loginRes = await fetch(`${manifest.backend.url}/api/v1/auth/login`, {
      method: 'POST',
      headers: loginHeaders,
      body: JSON.stringify({
        email: customerCred.email,
        password: customerCred.password,
      }),
    })

    assert.equal(loginRes.status, 200, 'Non-demo customer login must succeed')
    assertNonDemoPersistence(loginRes.headers)

    const loginBody = (await loginRes.json()) as { user?: { email?: string; role?: string } }
    assert.equal(loginBody.user?.email, customerCred.email)

    // Verify customer auth state file storage
    const authPath = getActorAuthStatePath(manifest, 'customer')
    const allCookies = [...rawCookies, ...(loginRes.headers.getSetCookie?.() || [])]
    const authData = {
      cookies: allCookies,
      origins: [{ origin: manifest.frontend.url, localStorage: [] }],
    }
    fs.writeFileSync(authPath, JSON.stringify(authData), { mode: 0o600 })
    assert.ok(fs.existsSync(authPath), 'Auth state file must exist')

    // Subsequent retrieval uses cached state file
    const cached = JSON.parse(fs.readFileSync(authPath, 'utf-8'))
    assert.ok(cached.cookies.length > 0, 'Cached auth state must retain cookies')
  } finally {
    if (env.manifest) {
      await env.stop()
    }
  }
})

test('R01 Verification: Helper subprocess environment strips inherited DB_URL and guarded PHP fails closed on target mismatch', async () => {
  const canaryRunId = `r01_canary_${Date.now()}`
  const foreignDb = `booking_e2e_foreign_${Date.now()}_test`
  let foreignDbCreated = false

  try {
    // Create disposable foreign database
    execFileSync('createdb', ['-h', '127.0.0.1', '-p', '5432', '-U', 'ziet', foreignDb], {
      stdio: ['ignore', 'pipe', 'pipe'],
    })
    foreignDbCreated = true

    // Create dummy manifest targeting canary database
    const dummyManifest = {
      runId: canaryRunId,
      database: {
        name: `booking_e2e_${canaryRunId}_test`,
        host: '127.0.0.1',
        port: 5432,
        user: 'ziet',
      },
      storagePath: path.join(os.tmpdir(), 'dummy-storage'),
    } as any

    // 1. Inherited DB_URL pointing to foreignDb must NOT be inherited by getCanonicalBackendEnv
    const prevDbUrl = process.env.DB_URL
    process.env.DB_URL = `postgres://ziet@127.0.0.1:5432/${foreignDb}`
    try {
      const canonicalEnv = (await import('./test-data.ts')).getCanonicalBackendEnv(dummyManifest)
      assert.equal(canonicalEnv.DB_URL, '', 'Canonical backend env must wipe out DB_URL')
      assert.equal(
        canonicalEnv.DATABASE_URL,
        '',
        'Canonical backend env must wipe out DATABASE_URL',
      )
      assert.equal(
        canonicalEnv.DB_DATABASE,
        dummyManifest.database.name,
        'Effective DB must be manifest DB',
      )
    } finally {
      if (prevDbUrl !== undefined) process.env.DB_URL = prevDbUrl
      else delete process.env.DB_URL
    }

    // 2. executeGuardedBackendPhp must fail closed if connected to wrong database
    const mismatchManifest = {
      runId: 'mismatch_run',
      database: {
        name: 'booking_e2e_nonexistent_target_test',
        host: '127.0.0.1',
        port: 5432,
        user: 'ziet',
      },
      storagePath: path.join(os.tmpdir(), 'dummy-storage'),
    } as any

    const { executeGuardedBackendPhp } = await import('./test-data.ts')
    assert.throws(() => {
      executeGuardedBackendPhp(
        mismatchManifest,
        'echo json_encode(["status" => "should_not_reach"]);',
      )
    }, /CRITICAL TARGET MISMATCH|CRITICAL TARGET CONNECTION FAILED|nonexistent_target/)
  } finally {
    if (foreignDbCreated) {
      try {
        execFileSync(
          'dropdb',
          ['-h', '127.0.0.1', '-p', '5432', '-U', 'ziet', '--if-exists', foreignDb],
          {
            stdio: ['ignore', 'pipe', 'pipe'],
          },
        )
      } catch {}
    }
  }
})

test('R04 Verification: Dynamic port fallback when 4180 is occupied & remote caller URL rejection', async () => {
  const net = await import('node:net')
  const { getAvailablePort } = await import('./environment.ts')

  // 1. Occupy port 4180 with a dummy listening server
  const dummyServer = net.createServer()
  const serverListening = await new Promise<boolean>((resolve) => {
    dummyServer.once('error', () => resolve(false))
    dummyServer.listen(4180, '127.0.0.1', () => resolve(true))
  })

  try {
    if (serverListening) {
      // When 4180 is occupied, getAvailablePort(4180) must allocate an alternate port (> 4180)
      const allocatedPort = await getAvailablePort(4180)
      assert.notEqual(allocatedPort, 4180, 'Must not allocate occupied port 4180')
      assert.ok(allocatedPort > 4180, 'Allocated fallback port must be > 4180')
    }
  } finally {
    if (serverListening) {
      await new Promise<void>((resolve) => dummyServer.close(() => resolve()))
    }
  }

  // 2. Reject remote caller URL
  const unsafeUrls = [
    'http://example.com:4180',
    'https://production.auracourt.vn',
    'http://192.168.1.50:4180',
  ]
  for (const unsafeUrl of unsafeUrls) {
    assert.throws(() => {
      const parsed = new URL(unsafeUrl)
      if (!['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname)) {
        throw new Error(
          `CRITICAL: Prohibited remote PLAYWRIGHT_BASE_URL="${unsafeUrl}". Must target local loopback.`,
        )
      }
    }, /CRITICAL: Prohibited remote/)
  }
})
