export interface UserCredential {
  email: string
  password: string
  name: string
  role?: string
  id?: number
}

export interface RunManifest {
  runId: string
  createdAt: string
  completedAt?: string
  tempDir: string
  storagePath: string
  database: {
    name: string
    host: string
    port: number
    user: string
    password?: string
  }
  backend: {
    port: number
    url: string
    pid?: number
  }
  frontend: {
    port: number
    url: string
    pid?: number
  }
  credentials?: {
    customer?: UserCredential
    owner?: UserCredential
    manager?: UserCredential
    cashier?: UserCredential
    staff?: UserCredential
    admin?: UserCredential
    foreignOwner?: UserCredential
    unverifiedCustomer?: UserCredential
    restrictedCustomer?: UserCredential
    [key: string]: UserCredential | undefined
  }
  fixtures?: E2EFixturesPayload
  status: 'initializing' | 'ready' | 'running' | 'completed' | 'failed' | 'torn_down'
  error?: string
}

export interface FixtureVenue {
  id: number
  name: string
  slug: string
  receivingConnectionId?: number
  routingId?: string
  courts: number[]
}

export interface FixtureProduct {
  id: number
  sku: string
  name: string
  price: number
  stock: number
}

export interface FixtureOrganization {
  id: number
  name: string
  slug: string
  venue1A?: FixtureVenue
  venue1B?: FixtureVenue
  venue2?: FixtureVenue
  products?: {
    drink?: FixtureProduct
    gear?: FixtureProduct
    service?: FixtureProduct
    [key: string]: FixtureProduct | undefined
  }
}

export interface FixtureUser {
  name?: string
  id: number
  email: string
  role: string
  password?: string
}

export interface E2EFixturesPayload {
  platform_plan?: { id: number; code: string }
  run_id: string
  users: {
    admin: FixtureUser
    customer: FixtureUser
    customer2?: FixtureUser
    unverifiedCustomer: FixtureUser
    restrictedCustomer: FixtureUser
    owner: FixtureUser
    manager: FixtureUser
    cashier: FixtureUser
    staff: FixtureUser
    foreignOwner: FixtureUser
    [key: string]: FixtureUser | undefined
  }
  organizations: {
    org1: FixtureOrganization
    org2: FixtureOrganization
    [key: string]: FixtureOrganization | undefined
  }
}

export interface PreflightCheckResult {
  passed: boolean
  violations: string[]
  details: {
    environment: string
    database: {
      connection: string
      host: string
      port: number
      database: string
      actualConnected?: string | null
    }
    configCached: boolean
    routesCached: boolean
    storagePath: string
    sessionDriver: string
    backendUrl: string
    frontendUrl: string
  }
}
