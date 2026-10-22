import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/features/auth/stores/authStore'
import type { PackageTermDays } from '@/features/platform-fees/types/platformFee'
import { platformSubscriptionApi } from '../api/platformSubscriptionApi'
import type { PackagePaymentRequest, PackageRequestError } from '../types'

/** The organization's venues with their packages, and buying, renewing or terminating a venue package (D4, D8). */
export function useVenuePackages() {
  const { t } = useI18n()
  const auth = useAuthStore()
  const { data, loading, error, execute } = useApiCall(platformSubscriptionApi.getPackages, {
    initialLoading: true,
    defaultErrorMessage: t('admin.packages.loadError'),
  })
  const venues = computed(() => data.value?.data.venues ?? [])
  const terms = computed(() => data.value?.data.terms ?? [])
  /** The request whose payment details are open. */
  const payment = ref<PackagePaymentRequest | null>(null)
  const busy = ref(false)
  const notice = ref('')
  const actionError = ref('')

  // The server checks owner rights on the path organization: the active one, else the first one owned.
  function organizationId(): number | undefined {
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
  }

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function load() {
    const orgId = organizationId()
    if (orgId === undefined) {
      loading.value = false
      error.value = t('admin.packages.noOrganization')
      return Promise.resolve(null)
    }
    return execute(orgId).catch(() => null)
  }

  async function act(task: (orgId: number) => Promise<void>, fallbackKey: string) {
    const orgId = organizationId()
    if (orgId === undefined) return
    busy.value = true
    notice.value = ''
    actionError.value = ''
    try {
      await task(orgId)
    } catch (e: unknown) {
      actionError.value = errorMessage(e, t(fallbackKey))
      // D4: the 409 carries the venue's pending request, so its payment opens instead.
      const pending = (e as PackageRequestError | null)?.data
      if ((e as PackageRequestError | null)?.code === 'PACKAGE_REQUEST_PENDING' && pending) {
        payment.value = pending
      }
    } finally {
      busy.value = false
    }
  }

  const requestPayment = (venueId: number, termDays: PackageTermDays) =>
    act(async (orgId) => {
      payment.value = (await platformSubscriptionApi.createRequest(orgId, venueId, termDays)).data
      await load()
    }, 'admin.packages.payment.createError')

  // Reopening reads the request again, so a payment that landed meanwhile shows as paid.
  const openPayment = (code: string) =>
    act(async (orgId) => {
      payment.value = (await platformSubscriptionApi.getRequest(orgId, code)).data
    }, 'admin.packages.payment.loadError')

  function closePayment() {
    payment.value = null
    actionError.value = ''
    return load()
  }

  const terminate = (venueId: number) =>
    act(async (orgId) => {
      await platformSubscriptionApi.terminate(orgId, venueId)
      notice.value = t('admin.packages.terminate.done')
      await load()
    }, 'admin.packages.terminate.error')

  onMounted(() => load())

  return {
    venues,
    terms,
    loading,
    error,
    payment,
    busy,
    notice,
    actionError,
    load,
    requestPayment,
    openPayment,
    closePayment,
    terminate,
  }
}
