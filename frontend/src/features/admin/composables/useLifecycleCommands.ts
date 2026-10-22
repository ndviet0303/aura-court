import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ApiError } from '@/core/types/api'
import type { OperationalStop } from '@/features/court/types/court'
import { useAuthStore } from '@/stores/auth'
import { adminApi } from '../api/adminApi'
import { useAffectedConfirm } from './useAffectedConfirm'
import type {
  LifecycleCommand,
  LifecycleTarget,
  MissingItem,
  StopCommand,
  StopPayload,
} from '../types/lifecycle'

type LifecycleError = ApiError & { missing_items?: MissingItem[] }

/**
 * Open / pause / resume / close for venues and courts. Pause and close go through the stop dialog;
 * open and resume run at once. onDone gets the backend message and should reload the list.
 */
export function useLifecycleCommands(onDone: (message: string) => void | Promise<void>) {
  const { t } = useI18n()
  const isOwnerOf = useOwnerCheck()
  // target null: the dialog picks the court (schedule view).
  const stopRequest = ref<{ command: StopCommand; target: LifecycleTarget | null } | null>(null)
  const busy = ref(false)
  const failure = ref<LifecycleError | null>(null)
  // Pause and close answer 409 with the bookings they touch until the actor acknowledges them.
  const affected = useAffectedConfirm()

  async function send(target: LifecycleTarget, command: LifecycleCommand, payload?: StopPayload) {
    busy.value = true
    try {
      let message = ''
      const saved = await affected.guard(async (acknowledge) => {
        const body =
          acknowledge && payload ? { ...payload, acknowledge_affected: true as const } : payload
        const result =
          target.type === 'venue'
            ? await adminApi.venueCommand(target.organizationId, target.id, command, body)
            : await adminApi.courtCommand(target.id, command, body)
        message = result.message
      })
      if (!saved) return false
      await onDone(message)
      return true
    } catch (cause) {
      failure.value = cause as LifecycleError
      return false
    } finally {
      busy.value = false
    }
  }

  function requestStop(command: StopCommand, target: LifecycleTarget | null = null) {
    stopRequest.value = { command, target }
  }

  async function run(target: LifecycleTarget, command: LifecycleCommand) {
    if (command === 'pause' || command === 'close') return requestStop(command, target)
    await send(target, command)
  }

  // The dialog stays open on failure so the reason and times can be corrected.
  async function submitStop(payload: StopPayload, courtId: number | null = null) {
    const request = stopRequest.value
    const target = request?.target ?? (courtId ? { type: 'court' as const, id: courtId } : null)
    if (!request || !target) return
    if (await send(target, request.command, payload)) stopRequest.value = null
  }

  const failureModal = computed(() => {
    const error = failure.value
    if (error?.missing_items?.length) {
      return {
        title: t('admin.lifecycle.missingTitle'),
        message: error.message,
        details: error.missing_items.map((item) => item.message),
      }
    }
    return { error }
  })

  return {
    stopRequest,
    busy,
    failureOpen: computed(() => failure.value !== null),
    failureModal,
    affected,
    closeFailure: () => (failure.value = null),
    closeStop: () => (stopRequest.value = null),
    isOwnerOf,
    run,
    requestStop,
    submitStop,
  }
}

/** ponytail: UI-only role check; the backend still answers 403 for the wrong role or organization.
 * An unknown organization is never owned, so no owner action shows for it. */
export function useOwnerCheck() {
  const auth = useAuthStore()
  return (organizationId?: number | null): boolean =>
    (auth.user?.organizations ?? []).some(
      (o) =>
        o.status === 'active' &&
        o.role === 'organization_owner' &&
        organizationId != null &&
        (o.organization_id ?? o.id) === organizationId,
    )
}

/** A court holds at most one open pause; staff may resume only a maintenance pause. */
export function hasOpenMaintenance(stops?: OperationalStop[]): boolean {
  return (stops ?? []).some((stop) => !stop.ended_at && stop.kind === 'maintenance')
}
