import { computed, onUnmounted, ref } from 'vue'

/** mm:ss left until an ISO deadline, ticking every second; empty when there is no deadline. */
export function useDeadlineCountdown(deadline: () => string | null | undefined) {
  const now = ref(Date.now())
  const timer = setInterval(() => (now.value = Date.now()), 1000)
  onUnmounted(() => clearInterval(timer))

  return computed(() => {
    const until = Date.parse(deadline() ?? '')
    if (!Number.isFinite(until)) return ''
    const left = Math.max(0, Math.floor((until - now.value) / 1000))
    return `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`
  })
}
