import { ref } from 'vue'
import type { VenueCreditAccount } from '@/core/types/venueCredit'
import { profileApi } from '@/features/profile/api/profileApi'

/**
 * Venue credit a booking spends (venue-credit spec): part or all of the amount payable, only at the
 * organization that issued it. expected_total stays the amount payable before credit.
 */
export function useVenueCreditChoice() {
  const accounts = ref<VenueCreditAccount[] | null>(null)
  // 0 means the booking pays no credit.
  const amount = ref(0)
  let request: Promise<unknown> | null = null

  // Loaded once while the form is open; a failed load offers no credit.
  function load() {
    request ??= profileApi.getVenueCredits().then(
      (res) => (accounts.value = res.data),
      () => (accounts.value = []),
    )
  }

  const balanceAt = (organizationId: number) =>
    accounts.value?.find((a) => a.organization.id === organizationId)?.balance ?? 0

  const payload = () => (amount.value > 0 ? { venue_credit_amount: amount.value } : {})

  function clear() {
    amount.value = 0
    accounts.value = null
    request = null
  }

  return { amount, load, balanceAt, payload, clear }
}
