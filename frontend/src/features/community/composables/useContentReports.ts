import { computed, reactive, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { errorMessage } from '@/core/utils/errorMessage'
import { moderationApi } from '../api/moderationApi'
import type { ContentReportDetail, ModerationCommand, ReportFilters } from '../types/moderation'

/** FR-132: the platform admin's report queue, pending first by default. */
export function useContentReports() {
  const { t } = useI18n()
  const filters = reactive<ReportFilters>({ status: 'pending', type: '' })
  const { data, loading, error, execute } = useApiCall(moderationApi.list, {
    initialLoading: true,
    defaultErrorMessage: t('moderation.loadError'),
  })
  // useApiCall keeps the message in `error`.
  const load = (page = 1) => execute({ ...filters }, page).catch(() => null)
  watch(filters, () => load(), { immediate: true })

  return {
    filters,
    rows: computed(() => data.value?.data ?? []),
    page: computed(() => data.value?.meta.current_page ?? 1),
    lastPage: computed(() => data.value?.meta.last_page ?? 1),
    loading,
    error,
    load,
  }
}

/** One report with its content and context; hide, restore and keep each need a note (BR-160). */
export function useContentReportDetail(id: Ref<number>) {
  const { t } = useI18n()
  const detail = ref<ContentReportDetail | null>(null)
  const loading = ref(false)
  const acting = ref(false)
  const error = ref('')

  async function load() {
    loading.value = true
    error.value = ''
    try {
      detail.value = await moderationApi.show(id.value)
    } catch (e) {
      error.value = errorMessage(e, t('moderation.loadError'))
    } finally {
      loading.value = false
    }
  }
  watch(id, load, { immediate: true })

  async function act(command: ModerationCommand, note: string): Promise<boolean> {
    acting.value = true
    error.value = ''
    try {
      await moderationApi.act(id.value, command, note.trim())
      await load()
      return true
    } catch (e) {
      error.value = errorMessage(e, t('moderation.actError'))
      return false
    } finally {
      acting.value = false
    }
  }

  return { detail, loading, acting, error, act }
}
