import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { suspensionApi } from '../api/suspensionApi'
import type { RemediationStatus } from '../types/suspension'

/** Task 5.10: the platform admin's remediation queue, one row per decision (its latest submission). */
export function useRemediationQueue() {
  const { t } = useI18n()
  const status = ref<RemediationStatus | ''>('pending')
  const { data, loading, error, execute } = useApiCall(suspensionApi.listRemediations, {
    initialLoading: true,
    defaultErrorMessage: t('admin.remediation.loadError'),
  })
  const rows = computed(() => data.value?.data ?? [])
  const page = computed(() => data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => data.value?.meta.last_page ?? 1)

  // useApiCall keeps the message in `error`.
  const load = (target = 1) => execute(status.value, target).catch(() => null)
  watch(status, () => load(), { immediate: true })

  return { status, rows, page, lastPage, loading, error, load }
}
