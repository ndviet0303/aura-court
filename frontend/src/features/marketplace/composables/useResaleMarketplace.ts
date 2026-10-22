import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  ResaleListing,
  UserBookingSlot,
  MyResaleListing,
  ResaleReceiver,
} from '../types/marketplace'
import { marketplaceApi } from '../api/marketplaceApi'
import { errorMessage } from '@/core/utils/errorMessage'

export function useResaleMarketplace() {
  const { t } = useI18n()
  const activeTab = ref<'buy' | 'sell' | 'my-listings'>('buy')
  const loading = ref(false)
  const processingAction = ref(false)
  const notice = ref('')
  const error = ref('')

  // Filter states
  const selectedDate = ref('')
  const selectedTimePeriod = ref('all')
  const selectedVenue = ref('all')
  const searchQuery = ref('')
  const sortBy = ref<'newest' | 'price_asc' | 'price_desc' | 'time_asc'>('newest')

  // Modals
  const selectedListingToBuy = ref<ResaleListing | null>(null)

  const isSellModalOpen = ref(false)
  const selectedSlotToSell = ref<UserBookingSlot | null>(null)
  const askingPriceInput = ref<number>(150000)

  // Data lists
  const listings = ref<ResaleListing[]>([])
  const myListings = ref<MyResaleListing[]>([])
  const userSlots = ref<UserBookingSlot[]>([])

  // Helper date formatting
  function formatDateStr(dateStr?: string): string {
    if (!dateStr) return ''
    try {
      const d = new Date(dateStr)
      const day = String(d.getDate()).padStart(2, '0')
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const year = d.getFullYear()
      const daysOfWeek = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7']
      return `${daysOfWeek[d.getDay()]}, ${day}/${month}/${year}`
    } catch {
      return dateStr
    }
  }

  function formatTimeSlot(start?: string, end?: string): string {
    if (!start) return ''
    try {
      const s = new Date(start)
      const e = end ? new Date(end) : null
      const startH =
        String(s.getHours()).padStart(2, '0') + ':' + String(s.getMinutes()).padStart(2, '0')
      if (!e) return startH
      const endH =
        String(e.getHours()).padStart(2, '0') + ':' + String(e.getMinutes()).padStart(2, '0')
      return `${startH} - ${endH}`
    } catch {
      return ''
    }
  }

  function getTimePeriod(start?: string): 'morning' | 'afternoon' | 'evening' {
    if (!start) return 'evening'
    try {
      const h = new Date(start).getHours()
      if (h < 12) return 'morning'
      if (h < 17) return 'afternoon'
      return 'evening'
    } catch {
      return 'evening'
    }
  }

  // Filtered & Sorted listings for the Marketplace (Buy tab)
  const filteredListings = computed(() => {
    let result = listings.value.filter((item) => {
      if (
        selectedVenue.value !== 'all' &&
        !item.venueName?.toLowerCase().includes(selectedVenue.value.toLowerCase())
      ) {
        return false
      }
      if (selectedTimePeriod.value !== 'all' && item.timePeriod !== selectedTimePeriod.value) {
        return false
      }
      if (selectedDate.value && !item.dateStr?.includes(selectedDate.value)) {
        return false
      }
      if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        return (
          item.courtName?.toLowerCase().includes(q) ||
          item.venueName?.toLowerCase().includes(q) ||
          item.sellerName?.toLowerCase().includes(q)
        )
      }
      return true
    })

    // Sorting
    result = [...result].sort((a, b) => {
      if (sortBy.value === 'price_asc') return a.resalePrice - b.resalePrice
      if (sortBy.value === 'price_desc') return b.resalePrice - a.resalePrice
      if (sortBy.value === 'time_asc') return a.timeSlot.localeCompare(b.timeSlot)
      return b.id - a.id
    })

    return result
  })

  // Unique venues list for filter dropdown
  const availableVenues = computed(() => {
    const set = new Set<string>()
    listings.value.forEach((l) => {
      if (l.venueName) set.add(l.venueName)
    })
    return Array.from(set)
  })

  async function loadListings() {
    try {
      const res = await marketplaceApi.getListings()
      listings.value = (res?.data ?? []).map((item) => {
        const court = item.entitlement?.court
        const startsAt = item.entitlement?.starts_at
        const asking = item.asking_price
        const original = item.original_price
        let badge = item.metadata?.badge
        if (!badge) {
          if (asking <= original * 0.75) badge = 'deal'
          else if (getTimePeriod(startsAt) === 'evening') badge = 'hot'
        }
        return {
          id: item.id,
          courtName: court?.name ?? '',
          venueName: court?.venue?.name ?? '',
          venueAddress: court?.venue?.address ?? '',
          dateStr: formatDateStr(startsAt),
          timeSlot: formatTimeSlot(startsAt, item.entitlement?.ends_at),
          timePeriod: getTimePeriod(startsAt),
          originalPrice: original,
          resalePrice: asking,
          badge,
          sellerName: item.seller?.name ?? '',
          sellerAvatar: item.seller?.avatar_url ?? '',
          sellerId: item.seller_id,
          cutoffAt: item.cutoff_at ?? undefined,
          isFeatured: badge === 'hot',
          status: item.effective_status,
          heldByMe: item.effective_status === 'checkout',
          notes: item.metadata?.notes,
        }
      })
    } catch (e: unknown) {
      listings.value = []
      error.value = errorMessage(e, 'Không thể tải chợ nhượng sân.')
    }
  }

  async function loadMyListings() {
    try {
      const res = await marketplaceApi.getListings({ mine: true })
      myListings.value = (res?.data ?? []).map((item) => {
        const court = item.entitlement?.court
        const startsAt = item.entitlement?.starts_at
        const checkout = item.effective_status === 'checkout'
        return {
          id: item.id,
          entitlementId: item.reservation_entitlement_id,
          courtName: court?.name ?? '',
          venueName: court?.venue?.name ?? '',
          dateStr: formatDateStr(startsAt),
          timeSlot: formatTimeSlot(startsAt, item.entitlement?.ends_at),
          originalPrice: item.original_price,
          askingPrice: item.asking_price,
          status: item.effective_status,
          lockedUntil: checkout ? item.metadata?.locked_until : undefined,
          paymentReportedAt: checkout ? item.metadata?.payment_reported_at : undefined,
          cutoffAt: item.cutoff_at ?? undefined,
          createdAt: formatDateStr(item.created_at ?? undefined),
        }
      })
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải tin đăng của bạn.')
    }
  }

  async function loadUserReservations() {
    try {
      const res = await marketplaceApi.getUserReservations()
      const slots: UserBookingSlot[] = []
      const listedEntitlementIds = new Set(
        myListings.value
          .filter((l) => l.status === 'listed' || l.status === 'checkout')
          .map((l) => l.entitlementId),
      )
      const cutoff = Date.now() + 2 * 60 * 60 * 1000

      for (const reservation of res?.data ?? []) {
        if (reservation.status !== 'confirmed') continue
        for (const ent of reservation.entitlements ?? []) {
          if (ent.status !== 'active' || listedEntitlementIds.has(ent.id)) continue
          const court = ent.court ?? reservation.court
          const priced = typeof ent.amount === 'number' && ent.amount > 0
          const early = new Date(ent.starts_at).getTime() > cutoff
          slots.push({
            id: ent.id,
            reservationId: reservation.id,
            courtName: court?.name ?? '',
            venueName: court?.venue?.name ?? '',
            dateStr: formatDateStr(ent.starts_at),
            timeSlot: formatTimeSlot(ent.starts_at, ent.ends_at),
            maxResalePrice: ent.max_resale_price ?? 0,
            startsAt: ent.starts_at,
            eligible: priced && early,
            ineligibleReason: !priced
              ? 'Giờ chơi này chưa có giá gốc nên chưa thể pass lại.'
              : !early
                ? 'Đã quá thời hạn cho phép pass sân (phải trước giờ chơi ít nhất 2 tiếng)'
                : undefined,
          })
        }
      }
      userSlots.value = slots
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải lịch đặt sân của bạn.')
    }
  }

  function resetFilters() {
    searchQuery.value = ''
    selectedTimePeriod.value = 'all'
    selectedVenue.value = 'all'
    selectedDate.value = ''
    sortBy.value = 'newest'
  }

  async function refreshAll() {
    loading.value = true
    error.value = ''
    try {
      await Promise.all([loadListings(), loadMyListings()])
      // The sell tab hides hours already listed, so it needs my listings first.
      await loadUserReservations()
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function openBuyModal(listing: ResaleListing) {
    selectedListingToBuy.value = listing
  }

  function closeBuyModal() {
    selectedListingToBuy.value = null
  }

  function openSellModal(slot: UserBookingSlot) {
    selectedSlotToSell.value = slot
    // Rounded down so the suggestion never passes the cap.
    askingPriceInput.value = Math.floor((slot.maxResalePrice * 8) / 100000) * 10000
    isSellModalOpen.value = true
  }

  function closeSellModal() {
    isSellModalOpen.value = false
    selectedSlotToSell.value = null
  }

  /** A paid listing carries the seller's own receiving account; a free one carries none. */
  async function confirmSell(receiver: ResaleReceiver | null) {
    const slot = selectedSlotToSell.value
    if (!slot) return
    const asking = askingPriceInput.value
    if (!Number.isInteger(asking) || asking < 0 || asking > slot.maxResalePrice) {
      error.value = t('resale.p2p.priceAboveMax', {
        amount: new Intl.NumberFormat('vi-VN').format(slot.maxResalePrice),
      })
      return
    }
    if (asking > 0 && !receiver) {
      error.value = 'Nhập tài khoản nhận tiền của bạn.'
      return
    }

    processingAction.value = true
    error.value = ''
    try {
      await marketplaceApi.listEntitlement(slot.id, {
        asking_price: asking,
        ...(asking > 0 && receiver ? { seller_receiver: receiver } : {}),
      })
      showNotice(`Đã đăng pass đơn ${slot.timeSlot} tại ${slot.courtName} lên chợ.`)
      closeSellModal()
      activeTab.value = 'my-listings'
      await refreshAll()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Đăng pass giờ chơi thất bại.')
    } finally {
      processingAction.value = false
    }
  }

  async function cancelListing(listingId: number) {
    if (
      !confirm(
        'Bạn có chắc chắn muốn rút tin nhượng này về không? Giờ chơi này sẽ trở lại thành lịch của bạn.',
      )
    ) {
      return
    }

    processingAction.value = true
    error.value = ''
    try {
      await marketplaceApi.withdrawListing(listingId)
      showNotice('Đã rút giờ chơi khỏi chợ nhượng sân.')
      await refreshAll()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể rút tin nhượng.')
    } finally {
      processingAction.value = false
    }
  }

  onMounted(() => {
    refreshAll()
  })

  return {
    activeTab,
    loading,
    processingAction,
    notice,
    error,
    selectedDate,
    selectedTimePeriod,
    selectedVenue,
    searchQuery,
    sortBy,
    availableVenues,
    selectedListingToBuy,
    isSellModalOpen,
    selectedSlotToSell,
    askingPriceInput,
    listings,
    myListings,
    userSlots,
    filteredListings,
    refreshAll,
    loadMyListings,
    resetFilters,
    showNotice,
    openBuyModal,
    closeBuyModal,
    openSellModal,
    closeSellModal,
    confirmSell,
    cancelListing,
  }
}
