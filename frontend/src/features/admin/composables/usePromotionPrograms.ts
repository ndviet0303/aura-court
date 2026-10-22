import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useErrorModal } from '@/core/composables/useErrorModal'
import type { ApiError } from '@/core/types/api'
import type { Court, Venue } from '@/features/court/types/court'
import { adminApi } from '../api/adminApi'
import { promotionProgramApi } from '../api/promotionProgramApi'
import type {
  CreatePromotionProgramPayload,
  PromotionProgram,
  PromotionProgramCodes,
  PromotionProgramTerms,
} from '../types/promotionProgram'

/** Owner promotion program management of one venue at a time. */
export function usePromotionPrograms() {
  const { t } = useI18n()
  const errorDialog = useErrorModal()

  const venues = ref<Venue[]>([])
  const venueId = ref<number | null>(null)
  const courts = ref<Court[]>([])
  const programs = ref<PromotionProgram[]>([])
  const detail = ref<PromotionProgram | null>(null)
  const codes = ref<PromotionProgramCodes | null>(null)
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const fieldErrors = ref<Record<string, string[]>>({})

  async function loadVenues() {
    loading.value = true
    try {
      venues.value = await adminApi.getVenues()
      venueId.value = venues.value[0]?.id ?? null
    } catch (e) {
      errorDialog.showError(e)
    } finally {
      loading.value = false
    }
  }

  async function loadVenue() {
    const id = venueId.value
    detail.value = null
    programs.value = []
    courts.value = []
    if (!id) return
    loading.value = true
    try {
      const [list, venueCourts] = await Promise.all([
        promotionProgramApi.list(id),
        adminApi.getCourts(id),
      ])
      if (id !== venueId.value) return
      programs.value = list.data
      courts.value = venueCourts
    } catch (e) {
      errorDialog.showError(e)
    } finally {
      loading.value = false
    }
  }
  watch(venueId, loadVenue)

  async function openDetail(programId: number) {
    try {
      detail.value = (await promotionProgramApi.show(programId)).data
    } catch (e) {
      errorDialog.showError(e)
    }
  }

  async function openCodes() {
    const program = detail.value
    if (!program) return
    try {
      codes.value = (await promotionProgramApi.codes(program.id)).data
    } catch (e) {
      errorDialog.showError(e)
    }
  }

  // Puts the changed program in the detail and in its list row.
  function show(program: PromotionProgram) {
    detail.value = program
    const index = programs.value.findIndex((row) => row.id === program.id)
    if (index === -1) programs.value.unshift(program)
    else programs.value[index] = program
  }

  // 422 field errors stay inline on the open form; anything else opens the dialog.
  async function mutate(request: () => Promise<PromotionProgram>, success: string) {
    saving.value = true
    fieldErrors.value = {}
    notice.value = ''
    try {
      show(await request())
      notice.value = success
      return true
    } catch (e) {
      const errors = (e as ApiError).errors
      if (errors) fieldErrors.value = errors
      else errorDialog.showError(e)
      return false
    } finally {
      saving.value = false
    }
  }

  function create(payload: CreatePromotionProgramPayload) {
    const venue = venueId.value
    if (!venue) return Promise.resolve(false)
    return mutate(
      async () => (await promotionProgramApi.create(venue, payload)).data,
      t('admin.promotions.createdNotice', { name: payload.name }),
    )
  }

  function update(payload: Partial<PromotionProgramTerms>) {
    const program = detail.value
    if (!program) return Promise.resolve(false)
    return mutate(
      async () => (await promotionProgramApi.update(program.id, payload)).data,
      t('admin.promotions.updatedNotice'),
    )
  }

  async function stop() {
    const program = detail.value
    if (!program || !window.confirm(t('admin.promotions.stopConfirm', { name: program.name })))
      return
    await mutate(
      async () => (await promotionProgramApi.stop(program.id)).data,
      t('admin.promotions.stoppedNotice'),
    )
  }

  const errorDialogProps = computed(() => ({
    error: errorDialog.currentError.value as ApiError | null,
  }))

  return {
    venues,
    venueId,
    courts,
    programs,
    detail,
    codes,
    loading,
    saving,
    notice,
    fieldErrors,
    errorOpen: errorDialog.isOpen,
    errorDialogProps,
    closeError: errorDialog.closeError,
    loadVenues,
    loadVenue,
    openDetail,
    openCodes,
    create,
    update,
    stop,
  }
}
