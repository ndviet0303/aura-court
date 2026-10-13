import { onScopeDispose, watch, type WatchSource } from 'vue'

/** Calls `tick` every `ms` while `active` is true, and stops when the owning component unmounts. */
export function usePolling(active: WatchSource<boolean>, ms: number, tick: () => unknown) {
  let timer: ReturnType<typeof setInterval> | undefined
  const stop = () => {
    clearInterval(timer)
    timer = undefined
  }
  watch(
    active,
    (on) => {
      stop()
      if (on) timer = setInterval(tick, ms)
    },
    { immediate: true },
  )
  onScopeDispose(stop)
}
