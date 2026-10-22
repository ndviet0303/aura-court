import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { platformFeeApi } from '../api/platformFeeApi'
import type { PlatformFeeVenueFilters } from '../types/platformFee'

/** Every venue with its package status at the time of viewing, the 7/3/1-day expiring totals, and the venue opened (D15). */
export function usePlatformFeeVenues() {
  const { t } = useI18n()
  const list = useApiCall(platformFeeApi.getVenues, {
    initialLoading: true,
    defaultErrorMessage: t('admin.platformFees.venues.loadError'),
  })
  const detail = useApiCall(platformFeeApi.getVenue, {
    defaultErrorMessage: t('admin.platformFees.venueDetail.loadError'),
  })

  // Every change replaces the object, so the list reloads once per change.
  const filters = ref<PlatformFeeVenueFilters>({ status: '', expiringWithin: 0, search: '' })
  const venues = computed(() => list.data.value?.data ?? [])
  const page = computed(() => list.data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => list.data.value?.meta.last_page ?? 1)
  const total = computed(() => list.data.value?.meta.total ?? 0)
  const expiringCounts = computed(() => list.data.value?.expiring_counts ?? null)
  const openId = ref(0)
  const opened = computed(() => detail.data.value?.data ?? null)

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function load(target = 1) {
    const { status, expiringWithin, search } = filters.value
    return list
      .execute({
        status: status || undefined,
        expiring_within: expiringWithin || undefined,
        search: search.trim() || undefined,
        page: target,
      })
      .catch(() => null)
  }

  function open(venueId: number) {
    openId.value = venueId
    detail.reset()
    return detail.execute(venueId).catch(() => null)
  }

  function close() {
    openId.value = 0
    detail.reset()
  }

  watch(filters, () => load())
  onMounted(() => load())

  return {
    filters,
    venues,
    page,
    lastPage,
    total,
    expiringCounts,
    loading: list.loading,
    error: list.error,
    load,
    openId,
    opened,
    detailLoading: detail.loading,
    detailError: detail.error,
    open,
    close,
  }
}
