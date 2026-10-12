import { ref, computed } from 'vue'
import { apiV2 } from '@/core/api/client'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'

export interface TenantCapabilities {
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

const defaultCapabilities: TenantCapabilities = {
  organization_tenancy_enforced: true,
}

const capabilities = ref<TenantCapabilities>({ ...defaultCapabilities })
const currentOrgId = ref<number | null>(null)
const currentAccountId = ref<number | null>(null)
const currentRole = ref<string | null>(null)
const isPlatformAdmin = ref(false)
const loading = ref(false)
const loaded = ref(false)
const fetchFailed = ref(false)
const error = ref<string | null>(null)

let latestRequestId = 0

export function useTenantCapabilities() {
  const auth = useAuthStore()

  function resetCapabilities() {
    latestRequestId++
    capabilities.value = { ...defaultCapabilities }
    currentOrgId.value = null
    currentAccountId.value = null
    currentRole.value = null
    isPlatformAdmin.value = false
    loading.value = false
    loaded.value = false
    fetchFailed.value = false
    error.value = null
  }
  async function fetchCapabilities(
    orgId?: number,
    accountId?: number,
  ): Promise<CapabilitiesResponse | null> {
    const requestUser = auth.user
    const resolvedAccountId = accountId ?? auth.user?.id ?? null

    if (
      currentAccountId.value !== null &&
      resolvedAccountId !== null &&
      currentAccountId.value !== resolvedAccountId
    ) {
      resetCapabilities()
    } else if (currentOrgId.value !== null && orgId !== undefined && currentOrgId.value !== orgId) {
      // If organization context changed, reset loaded flags so stale capabilities are not reused
      loaded.value = false
      fetchFailed.value = false
    }

    const requestId = ++latestRequestId
    loading.value = true
    fetchFailed.value = false
    error.value = null

    try {
      const url = orgId ? `/organizations/${orgId}/capabilities` : '/capabilities'
      const res = await apiV2.get<CapabilitiesResponse>(url)

      // Stale response discard: if a newer request was dispatched, logged out, or account/org switched, drop this result
      if (requestId !== latestRequestId || (requestUser && !auth.user)) {
        return null
      }
      if (resolvedAccountId !== null && auth.user && auth.user.id !== resolvedAccountId) {
        return null
      }
      if (res) {
        capabilities.value = res.capabilities || {}
        currentOrgId.value = res.organization_id
        currentRole.value = res.role
        isPlatformAdmin.value = res.is_platform_admin
        currentAccountId.value = resolvedAccountId
        loaded.value = true
        fetchFailed.value = false
        error.value = null
        return res
      }
      return null
    } catch (err: unknown) {
      // Only apply error if this is still the active in-flight request and not logged out
      if (requestId === latestRequestId && (!requestUser || auth.user)) {
        fetchFailed.value = true
        loaded.value = false // Note: failed capability resolution is NOT loaded/disabled
        error.value = errorMessage(err, 'Không thể tải quyền hạn tổ chức.')
      }
      return null
    } finally {
      if (requestId === latestRequestId) {
        loading.value = false
      }
    }
  }

  const resolutionStatus = computed<'idle' | 'loading' | 'resolved' | 'failed'>(() => {
    if (loading.value) return 'loading'
    if (fetchFailed.value) return 'failed'
    if (loaded.value) return 'resolved'
    return 'idle'
  })

  function isEnabled(flag: string): boolean {
    if (!loaded.value || fetchFailed.value) return false
    return !!capabilities.value[flag]
  }

  const isPosEnabled = computed(() => {
    return loaded.value && !fetchFailed.value && !!capabilities.value['tenant_pos_and_wallet']
  })

  const isPosDisabled = computed(() => {
    return loaded.value && !fetchFailed.value && !capabilities.value['tenant_pos_and_wallet']
  })

  const isTournamentsEnabled = computed(() => isEnabled('social_matchmaking_tournaments'))
  const isPayoutsEnabled = computed(() => isEnabled('marketplace_payouts'))
  const isSplitEnabled = computed(() => isEnabled('marketplace_split_payments'))
  const isResaleEnabled = computed(() => isEnabled('marketplace_slot_resale'))

  return {
    capabilities,
    currentOrgId,
    currentAccountId,
    currentRole,
    isPlatformAdmin,
    loading,
    loaded,
    fetchFailed,
    error,
    resolutionStatus,
    resetCapabilities,
    fetchCapabilities,
    isEnabled,
    isPosEnabled,
    isPosDisabled,
    isTournamentsEnabled,
    isPayoutsEnabled,
    isSplitEnabled,
    isResaleEnabled,
  }
}
