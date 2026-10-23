import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { adminApi } from '@/features/admin/api/adminApi'
import type { Venue } from '@/features/court/types/court'
import { customerApi } from '../api/customerApi'
import type { VenueCustomer, VenueCustomerProfile } from '../types/customer'

/** The current quarter and the three before it, in venue time, as "2026-Q4" (BR-141 keeps past quarters readable). */
export function recentQuarters(now = new Date()): string[] {
  const local = new Date(now.getTime() + 7 * 3600_000)
  let year = local.getUTCFullYear()
  let quarter = Math.floor(local.getUTCMonth() / 3) + 1
  const quarters: string[] = []
  for (let i = 0; i < 4; i++) {
    quarters.push(`${year}-Q${quarter}`)
    quarter -= 1
    if (quarter === 0) {
      quarter = 4
      year -= 1
    }
  }
  return quarters
}

/** FR-110 (UC-7.1, UC-7.2): one venue's customers for a quarter and the profile of the one picked. */
export function useVenueCustomers() {
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  const quarters = recentQuarters()
  const venues = ref<Venue[]>([])
  const venueId = ref<number | null>(Number(route.query.venue) || null)
  const quarter = ref(quarters[0]!)
  const search = ref('')
  const page = ref(1)
  const lastPage = ref(1)
  const customers = ref<VenueCustomer[]>([])
  const profile = ref<VenueCustomerProfile | null>(null)
  const loading = ref(false)
  const error = ref('')
  let latest = 0

  const venue = computed(() => venues.value.find((v) => v.id === venueId.value) ?? null)
  const organizationId = computed(() => venue.value?.organization_id ?? null)

  async function load() {
    const org = organizationId.value
    if (!venueId.value || !org) return
    const request = ++latest
    loading.value = true
    try {
      const result = await customerApi.list(
        org,
        venueId.value,
        quarter.value,
        search.value,
        page.value,
      )
      if (request !== latest) return
      customers.value = result.data
      lastPage.value = result.meta.last_page
      error.value = ''
    } catch (e) {
      if (request === latest) error.value = errorMessage(e, t('customers.loadError'))
    } finally {
      if (request === latest) loading.value = false
    }
  }

  async function open(userId: number) {
    const org = organizationId.value
    if (!venueId.value || !org) return
    try {
      profile.value = await customerApi.show(org, venueId.value, userId, quarter.value)
    } catch (e) {
      error.value = errorMessage(e, t('customers.loadError'))
    }
  }

  watch([venueId, quarter], () => {
    page.value = 1
    profile.value = null
    void router.replace({ query: { ...route.query, venue: venueId.value ?? undefined } })
    void load()
  })
  watch(page, () => void load())

  onMounted(async () => {
    try {
      venues.value = await adminApi.getVenues()
    } catch (e) {
      error.value = errorMessage(e, t('customers.loadError'))
      return
    }
    if (!venue.value) venueId.value = venues.value[0]?.id ?? null
    else void load()
  })

  function find() {
    if (page.value === 1) void load()
    else page.value = 1
  }

  return {
    quarters,
    venues,
    venueId,
    venue,
    organizationId,
    quarter,
    search,
    page,
    lastPage,
    customers,
    profile,
    loading,
    error,
    find,
    open,
    close: () => (profile.value = null),
  }
}
