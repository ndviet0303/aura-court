import { reactive } from 'vue'
import { suspensionApi } from '../api/suspensionApi'
import type { SuspensionPreview } from '../types/suspension'
import { failureMessage, useAffectedConfirm } from './useAffectedConfirm'

/**
 * Task 5.10 / D13: the platform admin suspends a venue. The preview shows the court count and the impact; the
 * save goes through the D7 acknowledgement, so a 409 lists the affected bookings before anything is saved.
 */
export function useSuspendVenue(onDone: (message: string) => unknown) {
  const affected = useAffectedConfirm()
  const state = reactive({
    venueId: null as number | null,
    preview: null as SuspensionPreview | null,
    previewing: false,
    busy: false,
    error: '',
    fieldErrors: {} as Record<string, string[]>,
    affected,
    open,
    close: () => (state.venueId = null),
    submit,
  })

  async function open(venueId: number) {
    Object.assign(state, { venueId, preview: null, error: '', fieldErrors: {}, previewing: true })
    try {
      state.preview = (await suspensionApi.previewSuspension(venueId)).data
    } catch (cause: unknown) {
      state.error = failureMessage(cause)
    } finally {
      state.previewing = false
    }
  }

  async function submit(reason: string) {
    const venueId = state.venueId
    if (venueId === null || state.busy) return
    state.busy = true
    state.error = ''
    state.fieldErrors = {}
    try {
      let message = ''
      const saved = await affected.guard(async (acknowledge) => {
        const response = await suspensionApi.suspendVenue(venueId, {
          reason,
          acknowledge_affected: acknowledge,
        })
        message = response.message
      })
      if (!saved) return
      state.venueId = null
      await onDone(message)
    } catch (cause: unknown) {
      state.fieldErrors = (cause as { errors?: Record<string, string[]> } | null)?.errors ?? {}
      state.error = failureMessage(cause)
    } finally {
      state.busy = false
    }
  }

  return state
}

export type SuspendVenueState = ReturnType<typeof useSuspendVenue>
