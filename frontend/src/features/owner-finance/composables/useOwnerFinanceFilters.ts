import { computed, onMounted, ref, watch } from 'vue'
import { venueDate } from '@/core/utils/venueTime'
import { useAuthStore } from '@/features/auth/stores/authStore'
import type { Court, Venue } from '@/features/court/types/court'
import { ownerFinanceApi } from '../api/ownerFinanceApi'
import type { OwnerFinanceFilters } from '../types/ownerFinance'
import { periodRange } from '../utils/financePeriod'

/** The organization the owner's finance screen reads, its venues and courts, and the filters both tabs share (D12). */
export function useOwnerFinanceFilters() {
  const auth = useAuthStore()

  // The server checks owner rights on the path organization: the active one, else the first one owned.
  const organizationId = computed<number | undefined>(() => {
    const user = auth.user
    const capabilities = user?.capabilities
    if (capabilities && !Array.isArray(capabilities)) {
      const activeOrgId = capabilities.active_organization_id
      if (typeof activeOrgId === 'number') return activeOrgId
    }
    const owned = user?.organizations?.find(
      (org) => org.role === 'organization_owner' && org.status === 'active',
    )
    const ownedId = owned?.organization_id ?? owned?.id
    return typeof ownedId === 'number' ? ownedId : undefined
  })

  const venues = ref<Venue[]>([])
  const courts = ref<Court[]>([])
  const filters = ref<OwnerFinanceFilters>({
    venueId: 0,
    courtId: 0,
    kind: 'month',
    ...periodRange('month', venueDate(new Date().toISOString())),
  })

  // Courts of every status, since inactive ones still have bookings; a late answer for another venue is dropped.
  watch(
    () => filters.value.venueId,
    async (venueId) => {
      courts.value = []
      if (!venueId) return
      const list = await ownerFinanceApi.getCourts(venueId).catch(() => [])
      if (filters.value.venueId === venueId) courts.value = list
    },
  )

  // The lists only feed the filters: if they fail, the screen still reads every venue of the organization.
  onMounted(async () => {
    const list = await ownerFinanceApi.getVenues().catch(() => [])
    venues.value = list.filter((venue) => venue.organization_id === organizationId.value)
  })

  return { organizationId, venues, courts, filters }
}
