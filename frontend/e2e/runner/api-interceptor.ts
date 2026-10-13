import type { Page } from '@playwright/test'
import type { RunManifest } from './types.ts'

/**
 * Intercepts browser requests to '/src/core/api/client.ts' and serves a lightweight
 * ES module that wraps window.fetch with XSRF token handling and direct backend routing.
 * This allows all E2E tests using `import('/src/core/api/client.ts')` inside `page.evaluate()`
 * to work seamlessly on production builds where source TS files do not exist on the web server.
 */
export async function setupApiClientRoute(page: Page, manifest: RunManifest): Promise<void> {
  const backendUrl = manifest.backend.url.replace(/\/$/, '')

  await page.route('**/src/core/api/client.ts', async (route) => {
    const jsModule = `
      function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^|;\\\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : '';
      }

      async function request(method, path, data, opt) {
        const options = typeof opt === 'string' ? { idempotencyKey: opt } : opt || {};
        let cleanPath = String(path || '');
        if (!cleanPath.startsWith('/api/')) {
          cleanPath = '/api' + (cleanPath.startsWith('/') ? cleanPath : '/' + cleanPath);
        }
        const apiOrigin = window.__E2E_API_ORIGIN__ || '${backendUrl}';
        let url = apiOrigin + cleanPath;
        if (options.params) {
          const sp = new URLSearchParams();
          for (const [k, v] of Object.entries(options.params)) {
            if (v !== undefined && v !== null) sp.append(k, String(v));
          }
          url += (url.includes('?') ? '&' : '?') + sp.toString();
        }

        const headers = {
          'Accept': 'application/json',
          'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
          ...(options.headers || {})
        };
        if (options.idempotencyKey) headers['Idempotency-Key'] = options.idempotencyKey;
        else if (method !== 'GET' && !headers['Idempotency-Key']) headers['Idempotency-Key'] = crypto.randomUUID();

        let body = undefined;
        if (data instanceof FormData) {
          body = data;
        } else if (data !== undefined && data !== null) {
          headers['Content-Type'] = 'application/json';
          body = JSON.stringify(data);
        }

        const res = await fetch(url, {
          method,
          headers,
          body,
          credentials: 'include'
        });

        if (!res.ok) {
          const errText = await res.text();
          let json = {};
          try { json = JSON.parse(errText); } catch {}
          const err = new Error(json.message || 'Request failed with status ' + res.status);
          err.status = res.status;
          err.errors = json.errors;
          err.response = { status: res.status, data: json };
          throw err;
        }

        return await res.json();
      }

      export const api = {
        get: (p, opt) => request('GET', p, undefined, opt),
        post: (p, d, opt) => request('POST', p, d, opt),
        put: (p, d, opt) => request('PUT', p, d, opt),
        patch: (p, d, opt) => request('PATCH', p, d, opt),
        delete: (p, opt) => request('DELETE', p, undefined, opt)
      };

      export const apiUrl = (p, params) => {
        let u = '${backendUrl}/api' + (p.startsWith('/') ? p : '/' + p);
        if (params) {
          const sp = new URLSearchParams();
          for (const [k, v] of Object.entries(params)) {
            if (v !== undefined && v !== null) sp.append(k, String(v));
          }
          u += (u.includes('?') ? '&' : '?') + sp.toString();
        }
        return u;
      };

      export const errorMessage = (e) => e?.response?.data?.message || e?.message || 'Lỗi không xác định';
    `

    await route.fulfill({
      status: 200,
      contentType: 'application/javascript',
      body: jsModule,
    })
  })

  await page.route('**/src/features/auth/api/authApi.ts', async (route) => {
    const jsModule = `
      function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^|;\\\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : '';
      }
      export const authApi = {
        logout: async () => {
          try {
            await fetch('${backendUrl}/api/auth/logout', {
              method: 'POST',
              headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN')
              },
              credentials: 'include'
            });
          } catch {}
          localStorage.clear();
          sessionStorage.clear();
        }
      };
    `
    await route.fulfill({
      status: 200,
      contentType: 'application/javascript',
      body: jsModule,
    })
  })
}
