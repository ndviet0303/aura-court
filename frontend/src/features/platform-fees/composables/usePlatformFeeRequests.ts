import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { platformFeeApi } from '../api/platformFeeApi'
import type { PlatformFeeRequestFilters } from '../types/platformFee'

/** Package payment requests of every organization, page by page, and the one opened with its receipts (D15). */
export function usePlatformFeeRequests() {
  const { t } = useI18n()
  const list = useApiCall(platformFeeApi.getRequests, {
    initialLoading: true,
    defaultErrorMessage: t('admin.platformFees.requests.loadError'),
  })
  const detail = useApiCall(platformFeeApi.getRequest, {
    defaultErrorMessage: t('admin.platformFees.requestDetail.loadError'),
  })

  // Every change replaces the object, so the list reloads once per change.
  const filters = ref<PlatformFeeRequestFilters>({
    status: '',
    venueId: 0,
    term: 0,
    from: '',
    to: '',
  })
  const requests = computed(() => list.data.value?.data ?? [])
  const page = computed(() => list.data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => list.data.value?.meta.last_page ?? 1)
  const total = computed(() => list.data.value?.meta.total ?? 0)
  const openCode = ref('')
  const opened = computed(() => detail.data.value?.data ?? null)

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function query() {
    const { status, venueId, term, from, to } = filters.value
    return {
      status: status || undefined,
      venue_id: venueId || undefined,
      term: term || undefined,
      // The API needs both ends of the period, so a half-picked period filters nothing yet.
      ...(from && to ? { from, to } : {}),
    }
  }

  function load(target = 1) {
    return list.execute({ ...query(), page: target }).catch(() => null)
  }

  // D14: every row matching the filters, not just the page on screen.
  const exportFile = () => platformFeeApi.exportRequests(query())

  function open(code: string) {
    openCode.value = code
    detail.reset()
    return detail.execute(code).catch(() => null)
  }

  function close() {
    openCode.value = ''
    detail.reset()
  }

  watch(filters, () => load())
  onMounted(() => load())

  return {
    filters,
    requests,
    page,
    lastPage,
    total,
    loading: list.loading,
    error: list.error,
    load,
    exportFile,
    openCode,
    opened,
    detailLoading: detail.loading,
    detailError: detail.error,
    open,
    close,
  }
}
