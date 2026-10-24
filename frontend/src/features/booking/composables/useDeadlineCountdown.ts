import { computed, onUnmounted, ref } from 'vue'

/** mm:ss (h:mm:ss from an hour) left until an ISO deadline, ticking every second; empty without one. */
export function useDeadlineCountdown(deadline: () => string | null | undefined) {
  const now = ref(Date.now())
  const timer = setInterval(() => (now.value = Date.now()), 1000)
  onUnmounted(() => clearInterval(timer))

  return computed(() => {
    const until = Date.parse(deadline() ?? '')
    if (!Number.isFinite(until)) return ''
    const left = Math.max(0, Math.floor((until - now.value) / 1000))
    const pad = (n: number) => String(n).padStart(2, '0')
    const clock = `${pad(Math.floor(left / 60) % 60)}:${pad(left % 60)}`
    // Tournament fees share one deadline days away (D23), so hours are spelled out.
    return left >= 3600 ? `${Math.floor(left / 3600)}:${clock}` : clock
  })
}
