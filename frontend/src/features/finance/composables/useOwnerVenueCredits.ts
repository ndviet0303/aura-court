import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { financeApi } from '../api/financeApi'

/** The owner's outstanding venue credit and its paginated entries, read-only (design D8). */
export function useOwnerVenueCredits() {
  const { t } = useI18n()
  const { data, loading, error, execute } = useApiCall(financeApi.getOwnerVenueCredits, {
    initialLoading: true,
    defaultErrorMessage: t('admin.venueCredits.loadError'),
  })

  const entries = computed(() => data.value?.data ?? [])
  const outstanding = computed(() => data.value?.outstanding ?? 0)
  const page = computed(() => data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => data.value?.meta.last_page ?? 1)

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function load(target = 1) {
    return execute(target).catch(() => null)
  }

  onMounted(() => load())

  return { entries, outstanding, page, lastPage, loading, error, load }
}
