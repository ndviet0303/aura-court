import { computed, ref, watch, type Ref } from 'vue'
import { errorMessage } from '@/core/utils/errorMessage'
import { promotionProgramApi } from '../api/promotionProgramApi'
import type { PromotionProgram, VoucherUse } from '../types/promotionProgram'

/** Usage tracking of one program (FR-68): its held bookings, and its used bookings a page at a time. */
export function usePromotionProgramUsage(program: Ref<PromotionProgram>) {
  const used = ref<VoucherUse[]>([])
  const held = ref<VoucherUse[]>([])
  const page = ref(0)
  const lastPage = ref(1)
  const total = ref(0)
  const loading = ref(false)
  const error = ref('')
  let latest = 0

  async function load(next: number) {
    const request = ++latest
    loading.value = true
    error.value = ''
    try {
      const res = await promotionProgramApi.usage(program.value.id, next)
      if (request !== latest) return
      // Pages are by offset: a use confirmed meanwhile pushes a row already shown onto the next page.
      const shown = new Set(used.value.map((use) => use.id))
      used.value =
        next === 1 ? res.data : [...used.value, ...res.data.filter((use) => !shown.has(use.id))]
      held.value = res.held
      page.value = res.meta.current_page
      lastPage.value = res.meta.last_page
      total.value = res.meta.total
    } catch (e) {
      if (request === latest) error.value = errorMessage(e)
    } finally {
      if (request === latest) loading.value = false
    }
  }

  // Every new program object reloads: its stop or update may have released held uses (D19).
  watch(
    program,
    (next, prev) => {
      if (next.id !== prev?.id) {
        used.value = []
        held.value = []
        page.value = 0
        lastPage.value = 1
        total.value = 0
      }
      load(1)
    },
    { immediate: true },
  )

  return {
    used,
    held,
    total,
    loading,
    error,
    loaded: computed(() => page.value > 0),
    hasMore: computed(() => page.value < lastPage.value),
    loadMore: () => load(page.value + 1),
  }
}
