import { reactive } from 'vue'
import { errorMessage, translateMessage } from '@/core/utils/errorMessage'
import type { AffectedList } from '../types/affected'

type Save = (acknowledge: boolean) => Promise<unknown>

/** A 4xx carries a specific reason the generic code mapping would hide; field errors keep the usual path. */
export function failureMessage(cause: unknown): string {
  const failure = cause as { status?: number; message?: string; errors?: unknown } | null
  return failure?.status && failure.status < 500 && failure.message && !failure.errors
    ? translateMessage(failure.message)
    : errorMessage(cause)
}

/** The list a 409 AFFECTED_BOOKINGS_UNACKNOWLEDGED carries, or null for any other failure. */
function unacknowledgedList(cause: unknown): AffectedList | null {
  const failure = cause as { status?: number; code?: string; affected?: AffectedList } | null
  return failure?.status === 409 && failure.code === 'AFFECTED_BOOKINGS_UNACKNOWLEDGED'
    ? (failure.affected ?? { bookings: [], sessions: [] })
    : null
}

/**
 * Design D7: save without the acknowledgement; when the server answers 409 with the affected bookings, show them
 * (AffectedBookingsDialog) and resend with `acknowledge_affected` only after the actor ticks the box.
 * `guard` resolves true once saved and false when the actor backs out; any other failure is thrown.
 * The server computes the same list for its preview endpoints, so the 409 is the preview and nothing is saved.
 */
export function useAffectedConfirm() {
  let pending: Save | null = null
  let settle: ((saved: boolean) => void) | null = null

  const state = reactive({
    list: null as AffectedList | null,
    busy: false,
    error: '',
    guard,
    confirm,
    cancel: () => finish(false),
  })

  function finish(saved: boolean) {
    state.list = null
    pending = null
    settle?.(saved)
    settle = null
  }

  async function guard(save: Save): Promise<boolean> {
    try {
      await save(false)
      return true
    } catch (cause: unknown) {
      const list = unacknowledgedList(cause)
      if (!list) throw cause
      state.list = list
      state.error = ''
      pending = save
      return new Promise<boolean>((resolve) => (settle = resolve))
    }
  }

  async function confirm() {
    if (!pending || state.busy) return
    state.busy = true
    state.error = ''
    try {
      await pending(true)
      finish(true)
    } catch (cause: unknown) {
      // The dialog stays open so the actor can read the reason and retry or back out.
      state.error = failureMessage(cause)
    } finally {
      state.busy = false
    }
  }

  return state
}

export type AffectedConfirm = ReturnType<typeof useAffectedConfirm>
