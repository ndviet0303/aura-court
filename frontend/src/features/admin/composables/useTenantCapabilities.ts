import { ref, computed } from 'vue'
import { apiV2 } from '@/core/api/client'

export interface TenantCapabilities {
  v2_reservations_enabled?: boolean
  organization_tenancy_enforced?: boolean
  marketplace_payouts?: boolean
  marketplace_split_payments?: boolean
  marketplace_slot_resale?: boolean
  social_matchmaking_tournaments?: boolean
  tenant_pos_and_wallet?: boolean
  [key: string]: boolean | undefined
}

export interface CapabilitiesResponse {
  organization_id: number | null
  capabilities: TenantCapabilities
  role: string | null
  is_platform_admin: boolean
}

const capabilities = ref<TenantCapabilities>({
  v2_reservations_enabled: true,
  organization_tenancy_enforced: true,
})
const currentOrgId = ref<number | null>(null)
const currentRole = ref<string | null>(null)
const isPlatformAdmin = ref(false)
const loading = ref(false)
const loaded = ref(false)

export function useTenantCapabilities() {
  async function fetchCapabilities(orgId?: number): Promise<CapabilitiesResponse | null> {
    loading.value = true
    try {
      const url = orgId ? `/organizations/${orgId}/capabilities` : '/capabilities'
      const res = await apiV2.get<CapabilitiesResponse>(url)

      if (res) {
        capabilities.value = res.capabilities || {}
        currentOrgId.value = res.organization_id
        currentRole.value = res.role
        isPlatformAdmin.value = res.is_platform_admin
        return res
      }
      return null
    } catch {
      return null
    } finally {
      loading.value = false
      loaded.value = true
    }
  }

  function isEnabled(flag: string): boolean {
    return !!capabilities.value[flag]
  }

  const isPosEnabled = computed(() => isEnabled('tenant_pos_and_wallet'))
  const isTournamentsEnabled = computed(() => isEnabled('social_matchmaking_tournaments'))
  const isPayoutsEnabled = computed(() => isEnabled('marketplace_payouts'))
  const isSplitEnabled = computed(() => isEnabled('marketplace_split_payments'))
  const isResaleEnabled = computed(() => isEnabled('marketplace_slot_resale'))

  return {
    capabilities,
    currentOrgId,
    currentRole,
    isPlatformAdmin,
    loading,
    loaded,
    fetchCapabilities,
    isEnabled,
    isPosEnabled,
    isTournamentsEnabled,
    isPayoutsEnabled,
    isSplitEnabled,
    isResaleEnabled,
  }
}
