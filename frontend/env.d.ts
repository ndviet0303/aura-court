/// <reference types="vite/client" />
declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, unknown>
  export default component
}

import 'vue-router'
declare module 'vue-router' {
  interface RouteMeta {
    auth?: boolean
    admin?: boolean
    title?: string
    description?: string
    indexable?: boolean
  }
}
