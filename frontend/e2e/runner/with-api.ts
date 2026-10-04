import type { Page } from '@playwright/test'
import { createE2EApiClient, logoutE2EActor } from './api-client.ts'
import { getTestManifest } from './test-data.ts'

function apiContext(page: Page) {
  const manifest = getTestManifest()
  const api = createE2EApiClient(page, manifest)
  return {
    api,
    apiV2: createE2EApiClient(page, manifest, 'v2'),
    authApi: {
      login: async (credentials: { email: string; password: string }) => {
        await api.csrf()
        return api.post('/auth/login', credentials)
      },
      logout: () => logoutE2EActor(page, manifest),
    },
    apiUrl: (path: string) => `${manifest.backend.url}/api/v1${path.startsWith('/') ? path : `/${path}`}`,
    apiV2Url: (path: string) => `${manifest.backend.url}/api/v2${path.startsWith('/') ? path : `/${path}`}`,
  }
}

/** Runs API setup/assertions against the same authenticated HTTP session as the browser. */
export async function withE2EApi<T, A = undefined>(
  page: Page,
  operation: (clients: ReturnType<typeof apiContext>, arg: A) => Promise<T>,
  arg?: A,
): Promise<T> {
  return operation(apiContext(page), arg as A)
}
