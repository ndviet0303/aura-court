import fs from 'node:fs'
import os from 'node:os'
import { execFileSync } from 'node:child_process'
import path from 'node:path'
import crypto from 'node:crypto'
import type { E2EFixturesPayload, RunManifest } from './types.ts'

/**
 * Reads the active RunManifest from the environment variable E2E_MANIFEST_PATH or pointer file.
 */
export function getTestManifest(): RunManifest {
  let manifestPath = process.env.E2E_MANIFEST_PATH
  if (!manifestPath || !fs.existsSync(manifestPath)) {
    const pointerPath = path.join(os.tmpdir(), 'aura_e2e_active_manifest.txt')
    if (fs.existsSync(pointerPath)) {
      manifestPath = fs.readFileSync(pointerPath, 'utf-8').trim()
    }
  }
  if (!manifestPath || !fs.existsSync(manifestPath)) {
    return getDiscoveryManifest()
  }
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8')) as RunManifest
  const isProductionTarget = ['prod', 'production'].includes(
    (process.env.E2E_TARGET || process.env.TARGET_ENV || 'local').toLowerCase(),
  )
  const isProductionManifest = /^prod_e2e_[0-9]+_[a-f0-9]{8}$/.test(manifest.runId)
  if (isProductionTarget !== isProductionManifest) {
    if (!process.env.E2E_MANIFEST_PATH) return getDiscoveryManifest()
    throw new Error(
      'E2E manifest does not belong to the selected target. Run global setup for this target.',
    )
  }
  return manifest
}

export function getDiscoveryManifest(): RunManifest {
  // Test discovery must never borrow real production identities or credentials.
  const roles = {
    admin: 'platform_admin',
    owner: 'organization_owner',
    foreignOwner: 'organization_owner',
    manager: 'venue_manager',
    cashier: 'cashier',
    staff: 'staff',
    customer: 'customer',
    customer2: 'customer',
    unverifiedCustomer: 'customer',
    restrictedCustomer: 'customer',
  }
  const users = Object.fromEntries(
    Object.entries(roles).map(([actor, role], index) => [
      actor,
      {
        id: index + 1,
        name: `Discovery ${actor}`,
        email: `${actor}@discovery.auracourt.test`,
        password: 'DiscoveryOnly123!',
        role,
      },
    ]),
  ) as {
    [Actor in keyof typeof roles]: {
      id: number
      name: string
      email: string
      password: string
      role: string
    }
  }
  return {
    runId: 'discovery',
    createdAt: new Date().toISOString(),
    tempDir: path.join(os.tmpdir(), 'aura_discovery'),
    storagePath: path.join(os.tmpdir(), 'aura_discovery', 'storage'),
    database: { name: 'booking_discovery', host: '127.0.0.1', port: 5432, user: 'postgres' },
    backend: { port: 8000, url: 'http://127.0.0.1:8000', pid: 0 },
    frontend: { port: 4180, url: 'http://127.0.0.1:4180', pid: 0 },
    credentials: users,
    fixtures: {
      run_id: 'discovery',
      users,
      organizations: {
        org1: {
          id: 1,
          name: 'Discovery Primary',
          slug: 'discovery-primary',
          venue1A: {
            id: 1,
            name: 'Discovery A',
            slug: 'discovery-a',
            routingId: 'discovery-1a',
            courts: [1, 2],
          },
          venue1B: {
            id: 2,
            name: 'Discovery B',
            slug: 'discovery-b',
            routingId: 'discovery-1b',
            courts: [3],
          },
        },
        org2: {
          id: 2,
          name: 'Discovery Foreign',
          slug: 'discovery-foreign',
          venue2: {
            id: 3,
            name: 'Discovery C',
            slug: 'discovery-c',
            routingId: 'discovery-2',
            courts: [4],
          },
        },
      },
    },
    status: 'ready',
  }
}

/**
 * Safely reads the RunManifest without throwing, useful for early discovery and config evaluation.
 */
export function getTestManifestSafe(): RunManifest | null {
  try {
    return getTestManifest()
  } catch {
    return null
  }
}

export interface FuturePlayDate {
  dateString: string
  isoString: string
  hourString: string
  weekday: number
  slotTime: string
}

export interface ArrangedCourt {
  status: string
  court_id: number
  name: string
  slug: string
  venue_id: number
  organization_id: number
  price: number
}

export interface ArrangedCoupon {
  status: string
  coupon_id: number
  code: string
  discount_type: string
  discount_value: number
  organization_id: number
}

export interface GrantedPoints {
  status: string
  user_id: number
  organization_id: number
  granted_points: number
  current_balance: number
  ledger_id: number
}

export interface ArrangedStock {
  status: string
  product_id: number
  sku: string
  name: string
  price: number
  stock_quantity: number
  organization_id: number
}

export interface ExpiredHoldsResult {
  status: string
  expired_count: number
  backdated_reservations: string[]
}

/**
 * Calculates a deterministically valid future play date in Asia/Ho_Chi_Minh.
 */
export function getFuturePlayDate(
  daysAhead: number = 2,
  hour: number = 10,
  minute: number = 0,
): FuturePlayDate {
  // Use Intl to compute local time in Asia/Ho_Chi_Minh
  const now = new Date()
  const targetUtcMs = now.getTime() + daysAhead * 24 * 60 * 60 * 1000

  // Format in Asia/Ho_Chi_Minh timezone
  const dtf = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
  const dateString = dtf.format(new Date(targetUtcMs)) // YYYY-MM-DD
  // Parse YYYY-MM-DD into target date at specified hour:minute in Asia/Ho_Chi_Minh
  const hourString = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
  const slotTime = `${dateString}T${hourString}:00+07:00`
  const targetDate = new Date(slotTime)

  // Day of week: Sunday = 7, Monday = 1 ... Saturday = 6 (ISO-8601 dayOfWeekIso computed strictly in Asia/Ho_Chi_Minh)
  const weekdayFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Ho_Chi_Minh',
    weekday: 'short',
  })
  const weekdayStr = weekdayFormatter.format(targetDate)
  const weekdayMap: Record<string, number> = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
  }
  const weekday = weekdayMap[weekdayStr] || 1
  return {
    dateString,
    isoString: targetDate.toISOString(),
    hourString,
    weekday,
    slotTime,
  }
}

/** Pilot-only features run for the two fixture organizations only, as production lists its pilots (D19). */
export function pilotOrganizationIds(fixtures: E2EFixturesPayload): string {
  return `${fixtures.organizations.org1.id},${fixtures.organizations.org2.id}`
}

export function getCanonicalBackendEnv(manifest: RunManifest): Record<string, string> {
  const cleanProcessEnv = { ...process.env }
  delete cleanProcessEnv.DB_URL
  delete cleanProcessEnv.DATABASE_URL

  const backendDir = path.resolve(import.meta.dirname, '../../../backend')
  let realAppKey = cleanProcessEnv.APP_KEY
  if (!realAppKey) {
    const envName =
      manifest.runId.startsWith('prod_e2e_') ||
      !fs.existsSync(path.join(backendDir, '.env.testing'))
        ? '.env'
        : '.env.testing'
    const envPath = path.resolve(backendDir, envName)
    if (fs.existsSync(envPath)) {
      const match = fs.readFileSync(envPath, 'utf-8').match(/^APP_KEY=(.*)$/m)
      if (match) realAppKey = match[1].trim().replace(/^(["'])(.*)\1$/, '$2')
    }
  }

  return {
    ...cleanProcessEnv,
    APP_NAME: 'Aura Court E2E',
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    BCRYPT_ROUNDS: '4',
    ...(realAppKey ? { APP_KEY: realAppKey } : {}),
    DB_CONNECTION: 'pgsql',
    DB_HOST: manifest.database.host,
    DB_PORT: String(manifest.database.port),
    DB_DATABASE: manifest.database.name,
    DB_USERNAME: manifest.database.user,
    DB_PASSWORD: manifest.database.password || '',
    // Empty, not absent: Laravel would otherwise read DB_URL from backend/.env*, which beats DB_HOST/DB_DATABASE
    DB_URL: '',
    DATABASE_URL: '',
    LARAVEL_STORAGE_PATH: manifest.storagePath,
    SESSION_DRIVER: 'file',
    SESSION_LIFETIME: '120',
    SESSION_ENCRYPT: 'false',
    CACHE_STORE: 'file',
    CACHE_PREFIX: 'aura-court-e2e-cache-',
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
    PILOT_ORGANIZATION_IDS: manifest.fixtures ? pilotOrganizationIds(manifest.fixtures) : '',
    PAYOS_BASE_URL: 'http://127.0.0.1:9',
    SEPAY_API_V2_BASE_URL: 'http://127.0.0.1:9',
    SEPAY_API_KEY: 'test_sepay_api_key_e2e',
    SEPAY_HMAC_SECRET: 'test_sepay_hmac_secret_e2e',
    SEPAY_BANK: 'TPBank',
    SEPAY_ACCOUNT: 'TEST_ACC_E2E',
    SEPAY_ACCOUNT_NAME: 'AURA COURT E2E',
    HTTP_PROXY: 'http://127.0.0.1:9',
    HTTPS_PROXY: 'http://127.0.0.1:9',
    ALL_PROXY: 'http://127.0.0.1:9',
    NO_PROXY: '127.0.0.1,localhost',
  }
}

/**
 * Executes raw PHP within the canonical backend environment and asserts connection target ownership.
 */
export function executeGuardedBackendPhp(
  manifest: RunManifest,
  script: string,
  options?: { cwd?: string },
): string {
  const backendDir = options?.cwd || path.resolve(import.meta.dirname, '../../../backend')
  const guardedScript =
    `
    require "vendor/autoload.php";
    $app = require_once "bootstrap/app.php";
    $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
    $kernel->bootstrap();

    $expectedDb = '${manifest.database.name}';
    try {
      $actualDb = \\Illuminate\\Support\\Facades\\DB::scalar('SELECT current_database()');
    } catch (\\Throwable $e) {
      fwrite(STDERR, "CRITICAL TARGET CONNECTION FAILED: " . $e->getMessage() . "\\n");
      exit(1);
    }
    if ($actualDb !== $expectedDb) {
      fwrite(STDERR, "CRITICAL TARGET MISMATCH: PHP helper expected '{$expectedDb}', connected to '{$actualDb}'\\n");
      exit(1);
    }
  ` + script

  return execFileSync('php', ['-r', guardedScript], {
    cwd: backendDir,
    env: getCanonicalBackendEnv(manifest),
    encoding: 'utf-8',
  })
}

/**
 * Creates an isolated dedicated court for a specific test scenario.
 */
export function arrangeCourt(
  manifest: RunManifest,
  options: {
    testKey: string
    venueId?: number
    price?: number
  },
): ArrangedCourt {
  const backendDir = path.resolve(import.meta.dirname, '../../../backend')
  const payload = {
    test_key: options.testKey,
    venue_id: options.venueId ?? manifest.fixtures?.organizations.org1?.venue1A?.id,
    price: options.price || 100000,
  }

  const raw = execFileSync(
    'php',
    [
      'artisan',
      'e2e:arrange-test-data',
      `--run-id=${manifest.runId}`,
      '--scenario=court',
      `--payload-json=${JSON.stringify(payload)}`,
    ],
    {
      cwd: backendDir,
      env: getCanonicalBackendEnv(manifest),
      encoding: 'utf-8',
    },
  )

  return JSON.parse(raw) as ArrangedCourt
}

/**
 * Creates an isolated dedicated coupon for a specific test scenario.
 */
export function arrangeCoupon(
  manifest: RunManifest,
  options: {
    testKey: string
    code?: string
    discountType?: 'fixed' | 'percent'
    discountValue?: number
    minSpend?: number
    usageLimit?: number
    venueId?: number
    organizationId?: number
  },
): ArrangedCoupon {
  const backendDir = path.resolve(import.meta.dirname, '../../../backend')
  const payload = {
    test_key: options.testKey,
    code: options.code,
    discount_type: options.discountType || 'fixed',
    discount_value: options.discountValue || 20000,
    min_spend: options.minSpend || 50000,
    usage_limit: options.usageLimit || 10,
    venue_id: options.venueId ?? manifest.fixtures?.organizations.org1?.venue1A?.id,
    organization_id: options.organizationId ?? manifest.fixtures?.organizations.org1?.id,
  }

  const raw = execFileSync(
    'php',
    [
      'artisan',
      'e2e:arrange-test-data',
      `--run-id=${manifest.runId}`,
      '--scenario=coupon',
      `--payload-json=${JSON.stringify(payload)}`,
    ],
    {
      cwd: backendDir,
      env: getCanonicalBackendEnv(manifest),
      encoding: 'utf-8',
    },
  )

  return JSON.parse(raw) as ArrangedCoupon
}

/**
 * Grants loyalty points to a user in an organization.
 */
export function grantLoyaltyPoints(
  manifest: RunManifest,
  options: {
    userId?: number
    organizationId?: number
    points: number
  },
): GrantedPoints {
  const backendDir = path.resolve(import.meta.dirname, '../../../backend')
  const payload = {
    user_id: options.userId ?? manifest.fixtures?.users.customer?.id,
    organization_id: options.organizationId ?? manifest.fixtures?.organizations.org1?.id,
    points: options.points,
  }

  const raw = execFileSync(
    'php',
    [
      'artisan',
      'e2e:arrange-test-data',
      `--run-id=${manifest.runId}`,
      '--scenario=loyalty_points',
      `--payload-json=${JSON.stringify(payload)}`,
    ],
    {
      cwd: backendDir,
      env: getCanonicalBackendEnv(manifest),
      encoding: 'utf-8',
    },
  )

  return JSON.parse(raw) as GrantedPoints
}

/**
 * Arranges POS stock for a specific product and test scenario.
 */
export function arrangePosStock(
  manifest: RunManifest,
  options: {
    testKey: string
    sku?: string
    name?: string
    price?: number
    cost?: number
    stockQuantity?: number
    organizationId?: number
  },
): ArrangedStock {
  const backendDir = path.resolve(import.meta.dirname, '../../../backend')
  const payload = {
    test_key: options.testKey,
    sku: options.sku,
    name: options.name,
    price: options.price || 20000,
    cost: options.cost || 10000,
    stock_quantity: options.stockQuantity || 50,
    organization_id: options.organizationId ?? manifest.fixtures?.organizations.org1?.id,
  }

  const raw = execFileSync(
    'php',
    [
      'artisan',
      'e2e:arrange-test-data',
      `--run-id=${manifest.runId}`,
      '--scenario=pos_stock',
      `--payload-json=${JSON.stringify(payload)}`,
    ],
    {
      cwd: backendDir,
      env: getCanonicalBackendEnv(manifest),
      encoding: 'utf-8',
    },
  )

  return JSON.parse(raw) as ArrangedStock
}

/**
 * Backdates hold timestamps and executes real expiry sweep action via test-only CLI.
 */
export function expireHolds(
  manifest: RunManifest,
  options: {
    reservationCode?: string
    minutes?: number
  } = {},
): ExpiredHoldsResult {
  const backendDir = path.resolve(import.meta.dirname, '../../../backend')
  const args = [
    'artisan',
    'e2e:expire-holds',
    `--run-id=${manifest.runId}`,
    `--minutes=${options.minutes || 30}`,
  ]

  if (options.reservationCode) {
    args.push(`--reservation-code=${options.reservationCode}`)
  }

  const raw = execFileSync('php', args, {
    cwd: backendDir,
    env: getCanonicalBackendEnv(manifest),
    encoding: 'utf-8',
  })

  return JSON.parse(raw) as ExpiredHoldsResult
}
export interface SyntheticWebhookOptions {
  routingId: string
  secret: string
  accountNumber: string
  amount: number
  code: string
  referenceCode?: string
  transferType?: 'in' | 'out'
  transactionDate?: string
  currency?: string
  transactionId?: string
  timestamp?: number
  tamperSignature?: boolean
  posOrderId?: number
}

export interface SyntheticWebhookResponse {
  statusCode: number
  ok: boolean
  body: unknown
}

export function formatVietnamDate(date: Date = new Date()): string {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(date)
  const map = Object.fromEntries(parts.map((p) => [p.type, p.value]))
  return `${map.year}-${map.month}-${map.day} ${map.hour}:${map.minute}:${map.second}`
}

/** Authenticates only fresh QR checkouts in the explicitly authorized production test tenants. */
function sendProductionPosWebhook(
  manifest: RunManifest,
  options: SyntheticWebhookOptions,
  rawBody: string,
): SyntheticWebhookResponse {
  const organizationIds = Object.values(manifest.fixtures?.organizations || {}).flatMap((org) =>
    org ? [org.id] : [],
  )
  const cashier = manifest.fixtures?.users.cashier
  if (
    manifest.backend.url.replace(/\/$/, '') !== 'https://court-api.ziet.dev' ||
    !/^prod_e2e_[0-9]+_[a-f0-9]{8}$/.test(manifest.runId) ||
    manifest.fixtures?.run_id !== manifest.runId ||
    organizationIds.length !== 2 ||
    new Set(organizationIds).size !== 2 ||
    organizationIds.some((id) => ![73, 74].includes(id)) ||
    !cashier?.id ||
    cashier.email !== `cashier_${manifest.runId}@auracourt.test` ||
    !Number.isFinite(Date.parse(manifest.createdAt)) ||
    !Number.isSafeInteger(options.posOrderId) ||
    Number(options.posOrderId) <= 0 ||
    !Number.isSafeInteger(options.amount) ||
    options.amount <= 0 ||
    !/^POS-[0-9]+-[A-Z0-9_-]+$/.test(options.code)
  ) {
    throw new Error(
      'Production POS signing requires a fresh checkout ID, current synthetic cashier and authorized test tenants 73/74.',
    )
  }
  const scope = Buffer.from(
    JSON.stringify({
      runId: manifest.runId,
      createdAt: manifest.createdAt,
      organizationIds,
      cashierId: cashier.id,
      cashierEmail: cashier.email,
      orderId: options.posOrderId,
      rawBody,
      tamperSignature: !!options.tamperSignature,
    }),
  ).toString('base64')
  const result = executeGuardedBackendPhp(
    manifest,
    `
    $scope = json_decode(base64_decode('${scope}'), true, 512, JSON_THROW_ON_ERROR);
    $event = json_decode($scope['rawBody'], true, 512, JSON_THROW_ON_ERROR);
    $order = \\App\\Models\\PosOrder::whereKey($scope['orderId'])
      ->whereIn('organization_id', $scope['organizationIds'])
      ->where('cashier_id', $scope['cashierId'])
      ->where('payment_reference', $event['code'])->first();
    $organization = $order ? \\App\\Models\\Organization::find($order->organization_id) : null;
    $cashier = \\App\\Models\\User::whereKey($scope['cashierId'])
      ->where('email', $scope['cashierEmail'])->first();
    if (!$order || !$cashier
      || ($organization?->settings['e2e_run_id'] ?? '') !== $scope['runId']
      || $order->created_at->lt(\\Carbon\\CarbonImmutable::parse($scope['createdAt']))
      || $order->status !== 'pending_payment' || $order->payment_method !== 'qr'
      || $event['transferType'] !== 'in' || $event['currency'] !== 'VND'
      || $event['referenceCode'] !== $order->payment_reference
      || (int) $event['transferAmount'] !== (int) $order->total_amount) {
      fwrite(STDERR, "Production POS callback is outside the current run's fresh QR checkout scope.\\n");
      exit(1);
    }
    // Read credentials only after ownership checks; keep them inside PHP and return no headers.
    try {
      $config = \\Dotenv\\Dotenv::parse(file_get_contents(base_path('.env')));
    } catch (\\Throwable $e) {
      fwrite(STDERR, "Production POS signing configuration could not be read.\\n");
      exit(1);
    }
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    $secret = (string) ($config['SEPAY_HMAC_SECRET'] ?? '');
    $apiKey = (string) ($config['SEPAY_API_KEY'] ?? '');
    if ($scope['tamperSignature']) {
      $headers[] = 'X-SePay-Signature: sha256='.str_repeat('0', 64);
    } elseif ($secret !== '') {
      $headers[] = 'X-SePay-Signature: '.hash_hmac('sha256', $scope['rawBody'], $secret);
    } elseif ($apiKey !== '') {
      // The deployed endpoint also supports its existing Apikey authentication contract.
      $headers[] = 'Authorization: Apikey '.$apiKey;
    } else {
      fwrite(STDERR, "Production POS webhook credential is not configured.\\n");
      exit(1);
    }
    $client = curl_init('https://court-api.ziet.dev/api/webhooks/sepay');
    curl_setopt_array($client, [
      CURLOPT_POST => true, CURLOPT_POSTFIELDS => $scope['rawBody'],
      CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_PROXY => '',
    ]);
    $body = curl_exec($client);
    $status = (int) curl_getinfo($client, CURLINFO_RESPONSE_CODE);
    if ($body === false) {
      fwrite(STDERR, "Production POS webhook request failed.\\n");
      exit(1);
    }
    curl_close($client);
    echo json_encode([
      'statusCode' => $status, 'ok' => $status >= 200 && $status < 300,
      'body' => json_decode($body, true) ?? $body,
    ], JSON_THROW_ON_ERROR);
  `,
  )
  return JSON.parse(result) as SyntheticWebhookResponse
}

/**
 * Sends an authenticated synthetic payment webhook through the real endpoint.
 */
export async function sendSyntheticPaymentWebhook(
  manifest: RunManifest,
  options: SyntheticWebhookOptions,
): Promise<SyntheticWebhookResponse> {
  const timestamp = String(options.timestamp || Math.floor(Date.now() / 1000))
  const rawBody = JSON.stringify({
    id: options.transactionId || `TXN-SEPAY-${Date.now()}`,
    transactionDate: options.transactionDate || formatVietnamDate(new Date(Date.now() + 5000)),
    accountNumber: options.accountNumber,
    transferType: options.transferType || 'in',
    transferAmount: options.amount,
    code: options.code,
    referenceCode: options.referenceCode || options.code,
    currency: options.currency || 'VND',
  })
  const isPos = options.code?.startsWith('POS-') || !options.routingId
  if (isPos && manifest.runId.startsWith('prod_e2e_')) {
    return sendProductionPosWebhook(manifest, options, rawBody)
  }
  const webhookUrl = isPos
    ? `${manifest.backend.url}/api/webhooks/sepay`
    : `${manifest.backend.url}/api/webhooks/sepay/connections/${options.routingId}`
  const secret = isPos ? 'test_sepay_hmac_secret_e2e' : options.secret
  const signatureHash = crypto
    .createHmac('sha256', secret)
    .update(isPos ? rawBody : `${timestamp}.${rawBody}`)
    .digest('hex')

  const signature = options.tamperSignature
    ? 'sha256=0000000000000000000000000000000000000000000000000000000000000000'
    : isPos
      ? signatureHash
      : `sha256=${signatureHash}`

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-SePay-Timestamp': timestamp,
    'X-SePay-Signature': signature,
    Authorization: 'Apikey test_sepay_api_key_e2e',
  }

  const response = await fetch(webhookUrl, {
    method: 'POST',
    headers,
    body: rawBody,
    redirect: 'manual',
  })

  const responseText = await response.text()
  let body: unknown = responseText
  try {
    body = JSON.parse(responseText)
  } catch {}

  if (!isPos && response.ok) {
    // Production acknowledges receipt ingestion before its queue allocates payment.
    // Wait for the real terminal receipt; never process it or change its status here.
    const lookup = Buffer.from(
      JSON.stringify({
        routingId: options.routingId,
        eventId: JSON.parse(rawBody).id,
        organizationIds: Object.values(manifest.fixtures?.organizations || {}).flatMap((org) =>
          org ? [org.id] : [],
        ),
      }),
    ).toString('base64')
    const deadline = Date.now() + 15000
    let processed = false
    while (Date.now() < deadline) {
      const result = JSON.parse(
        executeGuardedBackendPhp(
          manifest,
          `
        $lookup = json_decode(base64_decode('${lookup}'), true);
        $connection = \\App\\Models\\ReceivingConnection::where('routing_id', $lookup['routingId'])
          ->whereIn('organization_id', $lookup['organizationIds'])->firstOrFail();
        $receipt = \\App\\Models\\OwnerPaymentReceipt::where('receiving_connection_id', $connection->id)
          ->where('provider_event_id', (string) $lookup['eventId'])->first();
        echo json_encode(['processed' => $receipt && $receipt->processed_at !== null]);
      `,
        ),
      ) as { processed: boolean }
      if (result.processed) {
        processed = true
        break
      }
      await new Promise((resolve) => setTimeout(resolve, 300))
    }
    if (!processed)
      throw new Error(
        'Payment webhook was acknowledged but its test receipt was not processed within 15 seconds.',
      )
  }

  return {
    statusCode: response.status,
    ok: response.ok,
    body,
  }
}
