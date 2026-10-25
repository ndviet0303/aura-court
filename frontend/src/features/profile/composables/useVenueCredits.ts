import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { profileApi } from '../api/profileApi'

/** The customer's venue credit per organization, newest entries first. */
export function useVenueCredits() {
  const { t } = useI18n()
  const call = useApiCall(() => profileApi.getVenueCredits().then((r) => r.data), {
    initialLoading: true,
    defaultErrorMessage: t('profile.credit.loadFailed'),
  })
  const load = () => call.execute().catch(() => null)
  onMounted(load)

  return { accounts: call.data, loading: call.loading, error: call.error, load }
}

/** A one-time counter cash-out code; a new one replaces the live code at that organization. */
export function useVenueCreditCashOut() {
  const { t } = useI18n()
  const call = useApiCall(profileApi.createCashOutCode, {
    defaultErrorMessage: t('profile.credit.cashOutFailed'),
  })
  const create = (organizationId: number, amount: number) =>
    call.execute(organizationId, amount).catch(() => null)

  return { code: call.data, creating: call.loading, error: call.error, create, reset: call.reset }
}
