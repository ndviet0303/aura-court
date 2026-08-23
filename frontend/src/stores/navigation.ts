import { ref } from 'vue'

export const isRouteNavigating = ref(false)

let navTimer: ReturnType<typeof setTimeout> | null = null

export function startRouteNav() {
  isRouteNavigating.value = true
  if (navTimer) clearTimeout(navTimer)
}

export function endRouteNav() {
  // Small minimum duration so rapid clicks still give visible smooth feedback
  navTimer = setTimeout(() => {
    isRouteNavigating.value = false
  }, 150)
}
