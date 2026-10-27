/// <reference types="@cloudflare/workers-types" />

interface Env {
  ASSETS: Fetcher
  /** Public API origin configured in Cloudflare Workers Variables. */
  PUBLIC_API_URL?: string
}

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), {
    headers: {
      'Cache-Control': 'no-store',
      'Content-Type': 'application/json; charset=utf-8',
    },
  })
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url)

    // Keep public runtime configuration separate from the compiled Vite bundle.
    // Never expose secrets from the Worker environment here.
    if (url.pathname === '/runtime-config.json') {
      return jsonResponse({ apiUrl: env.PUBLIC_API_URL ?? null })
    }

    // All other requests are served by the uploaded Vite assets. The Worker
    // entrypoint is intentionally kept small so static delivery remains fast.
    const response = await env.ASSETS.fetch(request)
    // The SPA fallback answers every path with index.html; a missing file (/llms.txt,
    // /.well-known/*.json, a chunk from an old deploy) must be a 404, not HTML.
    const missingFile =
      /\.[a-z0-9]+$/i.test(url.pathname) &&
      !url.pathname.endsWith('.html') &&
      response.headers.get('Content-Type')?.startsWith('text/html')
    return missingFile ? new Response('Not found', { status: 404 }) : response
  },
}
