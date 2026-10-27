import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // Messages compile at build time, so the browser skips the message compiler (it cost first paint on mobile).
    VueI18nPlugin({ include: fileURLToPath(new URL('./src/i18n/locales/**', import.meta.url)) }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: true,
    proxy: { '/api': 'http://localhost:8000', '/sanctum': 'http://localhost:8000' },
  },
})
