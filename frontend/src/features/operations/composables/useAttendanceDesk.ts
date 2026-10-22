import { computed, ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { useOwnerCheck } from '@/features/admin/composables/useLifecycleCommands'
import { operationsApi } from '../api/operationsApi'
import type {
  AdjustmentForm,
  AttendanceAdjustment,
  AttendanceCommand,
  AttendancePayment,
  AttendanceResult,
  OperationalReservation,
} from '../types/operations'

/**
 * FR-102, FR-103, FR-106..FR-109: the booking an operator opened, the check-in / check-out / no-show commands and
 * the owner's adjustment with its history. The server checks the venue, the time window and the role; this only
 * reports its answer and reloads the board after a change.
 */
export function useAttendanceDesk(
  organizationId: Ref<number | null>,
  onChanged: () => Promise<void> | void,
) {
  const { t } = useI18n()
  const isOwnerOf = useOwnerCheck()
  const owner = computed(() => isOwnerOf(organizationId.value))

  const selected = ref<OperationalReservation | null>(null)
  const payment = ref<AttendancePayment | null>(null)
  const history = ref<AttendanceAdjustment[]>([])
  const busy = ref(false)
  const notice = ref('')
  const failure = ref('')

  async function open(code: string) {
    const org = organizationId.value
    if (!org) return
    failure.value = ''
    try {
      const [detail, adjustments] = await Promise.all([
        operationsApi.show(org, code),
        owner.value ? operationsApi.adjustments(org, code) : Promise.resolve({ data: [] }),
      ])
      selected.value = detail.reservation
      payment.value = detail.payment
      history.value = adjustments.data
    } catch (e) {
      failure.value = errorMessage(e, t('operations.loadError'))
    }
  }

  function close() {
    selected.value = null
    payment.value = null
    history.value = []
  }

  async function settle(work: () => Promise<AttendanceResult>, done: string) {
    if (busy.value) return false
    busy.value = true
    notice.value = ''
    failure.value = ''
    try {
      const { reservation, duplicate } = await work()
      notice.value = duplicate ? t('operations.duplicate') : done
      if (selected.value?.code === reservation.code) await open(reservation.code)
      await onChanged()
      return true
    } catch (e) {
      failure.value = errorMessage(e, t('operations.commandError'))
      return false
    } finally {
      busy.value = false
    }
  }

  const run = (command: AttendanceCommand, code: string) => {
    const org = organizationId.value
    return org
      ? settle(
          () => operationsApi.command(org, command, code.trim()),
          t(`operations.done.${command}`),
        )
      : Promise.resolve(false)
  }

  const adjust = (form: AdjustmentForm) => {
    const org = organizationId.value
    return org
      ? settle(() => operationsApi.adjust(org, form), t('operations.adjust.done'))
      : Promise.resolve(false)
  }

  return { owner, selected, payment, history, busy, notice, failure, open, close, run, adjust }
}
