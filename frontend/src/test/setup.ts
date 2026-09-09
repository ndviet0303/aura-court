import type { JSDOM } from 'jsdom'

declare const jsdom: JSDOM

// Node 24 exposes its own Web Storage getters. Use this test environment's
// browser storage, not Node's optional process-wide file-backed storage.
for (const name of ['localStorage', 'sessionStorage'] as const) {
  Object.defineProperty(globalThis, name, {
    configurable: true,
    get: () => jsdom.window[name],
  })
}
