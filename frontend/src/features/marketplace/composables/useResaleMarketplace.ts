import { ref, computed, onMounted } from 'vue'
import type { ResaleListing, UserBookingSlot, MyResaleListing } from '../types/marketplace'
import { marketplaceApi } from '../api/marketplaceApi'
import { errorMessage } from '@/core/utils/errorMessage'

export function useResaleMarketplace() {
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
  const isBuyModalOpen = ref(false)
  const selectedListingToBuy = ref<ResaleListing | null>(null)
  const buyPaymentMethod = ref<'wallet' | 'transfer'>('wallet')

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

  function getFallbackListings(): ResaleListing[] {
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    const nextDay = new Date()
    nextDay.setDate(nextDay.getDate() + 2)
    const day3 = new Date()
    day3.setDate(day3.getDate() + 3)

    return [
      {
        id: 901,
        courtName: 'Sân 1 - Thảm BWF Tiêu Chuẩn',
        venueName: 'Aura Court Cầu Giấy',
        venueAddress: 'Số 18 Phố Duy Tân, Cầu Giấy, Hà Nội',
        dateStr: formatDateStr(tomorrow.toISOString()),
        timeSlot: '18:00 - 20:00',
        timePeriod: 'evening',
        originalPrice: 200000,
        resalePrice: 150000,
        badge: 'hot',
        sellerName: 'Khách hàng 01',
        sellerAvatar: '',
        sellerEmail: 'khach01@demo.auracourt.vn',
        sellerId: 1,
        cutoffAt: tomorrow.toISOString(),
        isFeatured: true,
        status: 'listed',
        notes: 'Bận việc gia đình đột xuất pass lại giá tốt cho anh em giao lưu.',
      },
      {
        id: 902,
        courtName: 'Sân 2 - Tiêu Chuẩn',
        venueName: 'Aura Court Cầu Giấy',
        venueAddress: 'Số 18 Phố Duy Tân, Cầu Giấy, Hà Nội',
        dateStr: formatDateStr(tomorrow.toISOString()),
        timeSlot: '14:00 - 16:00',
        timePeriod: 'afternoon',
        originalPrice: 220000,
        resalePrice: 160000,
        badge: 'deal',
        sellerName: 'Khách hàng 02',
        sellerAvatar: '',
        sellerEmail: 'khach02@demo.auracourt.vn',
        sellerId: 2,
        cutoffAt: tomorrow.toISOString(),
        isFeatured: false,
        status: 'listed',
        notes: 'Slot buổi chiều mát mẻ, nhượng lại cho nhóm bạn đánh đơn/đôi.',
      },
      {
        id: 903,
        courtName: 'Sân Pickleball 1 - Aura Neon',
        venueName: 'Aura Court Cầu Giấy',
        venueAddress: 'Số 18 Phố Duy Tân, Cầu Giấy, Hà Nội',
        dateStr: formatDateStr(nextDay.toISOString()),
        timeSlot: '08:00 - 10:00',
        timePeriod: 'morning',
        originalPrice: 260000,
        resalePrice: 190000,
        badge: 'deal',
        sellerName: 'Khách hàng 06',
        sellerAvatar: '',
        sellerEmail: 'khach06@demo.auracourt.vn',
        sellerId: 6,
        cutoffAt: nextDay.toISOString(),
        isFeatured: false,
        status: 'listed',
        notes: 'Slot sáng cuối tuần đẹp, nhượng lại do trùng lịch thi đấu.',
      },
      {
        id: 904,
        courtName: 'Sân 5 - VIP Tournament',
        venueName: 'Aura Court Cầu Giấy',
        venueAddress: 'Số 18 Phố Duy Tân, Cầu Giấy, Hà Nội',
        dateStr: formatDateStr(nextDay.toISOString()),
        timeSlot: '19:00 - 21:00',
        timePeriod: 'evening',
        originalPrice: 360000,
        resalePrice: 280000,
        badge: 'hot',
        sellerName: 'Khách hàng 05',
        sellerAvatar: '',
        sellerEmail: 'khach05@demo.auracourt.vn',
        sellerId: 5,
        cutoffAt: nextDay.toISOString(),
        isFeatured: true,
        status: 'listed',
        notes: 'Sân VIP giờ vàng tối, ưu tiên anh em chốt sớm.',
      },
      {
        id: 905,
        courtName: 'Sân 3 - Tiêu Chuẩn',
        venueName: 'Aura Court Cầu Giấy',
        venueAddress: 'Số 18 Phố Duy Tân, Cầu Giấy, Hà Nội',
        dateStr: formatDateStr(day3.toISOString()),
        timeSlot: '15:00 - 17:00',
        timePeriod: 'afternoon',
        originalPrice: 240000,
        resalePrice: 180000,
        badge: 'deal',
        sellerName: 'Khách hàng 03',
        sellerAvatar: '',
        sellerEmail: 'khach03@demo.auracourt.vn',
        sellerId: 3,
        cutoffAt: day3.toISOString(),
        isFeatured: false,
        status: 'listed',
        notes: 'Slot chiều tiêu chuẩn, tặng kèm 1 ống cầu mới.',
      },
      {
        id: 906,
        courtName: 'Sân 4 - VIP Đơn/Đôi',
        venueName: 'Aura Court Cầu Giấy',
        venueAddress: 'Số 18 Phố Duy Tân, Cầu Giấy, Hà Nội',
        dateStr: formatDateStr(day3.toISOString()),
        timeSlot: '17:00 - 18:00',
        timePeriod: 'evening',
        originalPrice: 180000,
        resalePrice: 130000,
        badge: 'urgent',
        sellerName: 'Khách hàng 04',
        sellerAvatar: '',
        sellerEmail: 'khach04@demo.auracourt.vn',
        sellerId: 4,
        cutoffAt: day3.toISOString(),
        isFeatured: false,
        status: 'listed',
        notes: 'Pass gấp giá siêu rẻ cho anh em vào sân luôn.',
      },
    ]
  }

  async function loadListings() {
    try {
      const res = await marketplaceApi.getListings<{ data: any[] }>()
      const rawData = res?.data || (Array.isArray(res) ? res : [])
      if (Array.isArray(rawData) && rawData.length > 0) {
        listings.value = rawData.map((item: any) => {
          const court = item.entitlement?.court
          const venue = court?.venue
          const startsAt = item.entitlement?.starts_at
          const endsAt = item.entitlement?.ends_at
          const asking = item.asking_price || 0
          const original = item.original_price || item.entitlement?.amount || asking

          let badge: 'hot' | 'urgent' | 'deal' | undefined = item.metadata?.badge
          if (!badge) {
            if (asking <= original * 0.75) badge = 'deal'
            else if (getTimePeriod(startsAt) === 'evening') badge = 'hot'
          }

          return {
            id: item.id,
            courtName: court?.name || 'Sân Cầu Lông Cao Cấp',
            venueName: venue?.name || 'Aura Court Cầu Giấy',
            venueAddress: venue?.address || '',
            dateStr: formatDateStr(startsAt),
            timeSlot: formatTimeSlot(startsAt, endsAt),
            timePeriod: getTimePeriod(startsAt),
            originalPrice: original,
            resalePrice: asking,
            badge,
            sellerName: item.seller?.name || 'Hội viên Aura',
            sellerAvatar: item.seller?.avatar_url || '',
            sellerEmail: item.seller?.email || '',
            sellerId: item.seller_id,
            cutoffAt: item.cutoff_at,
            isFeatured: badge === 'hot',
            status: item.status || 'listed',
            notes: item.metadata?.notes,
          }
        })
      } else if (listings.value.length === 0) {
        listings.value = getFallbackListings()
      }
    } catch (e: unknown) {
      console.warn('Could not fetch marketplace listings:', e)
      if (listings.value.length === 0) {
        listings.value = getFallbackListings()
      }
    }
  }

  async function loadMyListings() {
    try {
      const res = await marketplaceApi.getListings<{ data: any[] }>({ mine: true })
      const rawData = res?.data || (Array.isArray(res) ? res : [])
      if (Array.isArray(rawData)) {
        myListings.value = rawData.map((item: any) => {
          const court = item.entitlement?.court
          const venue = court?.venue
          const startsAt = item.entitlement?.starts_at
          const endsAt = item.entitlement?.ends_at

          return {
            id: item.id,
            entitlementId: item.reservation_entitlement_id,
            courtName: court?.name || 'Sân Cầu Lông',
            venueName: venue?.name || 'Aura Court',
            dateStr: formatDateStr(startsAt),
            timeSlot: formatTimeSlot(startsAt, endsAt),
            originalPrice: item.original_price || item.asking_price,
            askingPrice: item.asking_price,
            status: item.status,
            cutoffAt: item.cutoff_at,
            createdAt: formatDateStr(item.created_at),
          }
        })
      }
    } catch (e: unknown) {
      console.warn('Could not fetch my listings:', e)
    }
  }

  async function loadUserReservations() {
    try {
      const res = await marketplaceApi.getUserReservations<{ data: any[] }>()
      const reservations = res?.data || (Array.isArray(res) ? res : [])
      const slots: UserBookingSlot[] = []

      const listedEntitlementIds = new Set(
        myListings.value.filter((l) => l.status === 'listed').map((l) => l.entitlementId),
      )

      reservations.forEach((reservation: any) => {
        if (reservation.status === 'confirmed' && Array.isArray(reservation.entitlements)) {
          reservation.entitlements.forEach((ent: any) => {
            if (ent.status === 'active' && !listedEntitlementIds.has(ent.id)) {
              const startsAt = new Date(ent.starts_at)
              const cutoff = new Date(Date.now() + 2 * 60 * 60 * 1000)
              const isEligible = startsAt > cutoff

              slots.push({
                id: ent.id,
                reservationId: reservation.id,
                courtName: ent.court?.name || reservation.court?.name || 'Sân Cầu Lông',
                venueName:
                  ent.court?.venue?.name || reservation.court?.venue?.name || 'Aura Court Cầu Giấy',
                dateStr: formatDateStr(ent.starts_at),
                timeSlot: formatTimeSlot(ent.starts_at, ent.ends_at),
                originalPrice: ent.amount || reservation.total_amount || 200000,
                startsAt: ent.starts_at,
                eligible: isEligible,
                ineligibleReason: !isEligible
                  ? 'Đã quá thời hạn cho phép pass sân (phải trước giờ chơi ít nhất 2 tiếng)'
                  : undefined,
              })
            }
          })
        }
      })

      userSlots.value = slots
    } catch (e: unknown) {
      console.warn('Could not fetch user reservations for resale:', e)
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
      await Promise.all([loadListings(), loadMyListings(), loadUserReservations()])
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu chợ nhượng sân.')
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
    buyPaymentMethod.value = 'wallet'
    isBuyModalOpen.value = true
  }

  function closeBuyModal() {
    isBuyModalOpen.value = false
    selectedListingToBuy.value = null
  }

  function openSellModal(slot: UserBookingSlot) {
    selectedSlotToSell.value = slot
    askingPriceInput.value = Math.round(((slot.originalPrice || 200000) * 0.8) / 10000) * 10000
    isSellModalOpen.value = true
  }

  function closeSellModal() {
    isSellModalOpen.value = false
    selectedSlotToSell.value = null
  }

  async function confirmBuy() {
    if (!selectedListingToBuy.value) return
    processingAction.value = true
    error.value = ''
    try {
      await marketplaceApi.purchaseListing(selectedListingToBuy.value.id, {
        buyer_payment_reference:
          buyPaymentMethod.value === 'wallet' ? 'wallet_balance' : 'banking_transfer_qr',
        seller_refund_accepted: true,
      })
      showNotice(
        `Đã mua lại suất chơi sân ${selectedListingToBuy.value.courtName} thành công! Lịch chơi đã được chuyển vào tài khoản của bạn.`,
      )
      closeBuyModal()
      await refreshAll()
    } catch (e: unknown) {
      error.value = errorMessage(
        e,
        'Mua lại suất chơi thất bại. Vui lòng kiểm tra lại số dư hoặc thử lại.',
      )
    } finally {
      processingAction.value = false
    }
  }

  async function confirmSell() {
    if (!selectedSlotToSell.value) return
    if (
      askingPriceInput.value <= 0 ||
      askingPriceInput.value > selectedSlotToSell.value.originalPrice
    ) {
      error.value = 'Giá pass phải lớn hơn 0 và không được vượt quá giá gốc.'
      return
    }

    processingAction.value = true
    error.value = ''
    try {
      await marketplaceApi.listEntitlement(selectedSlotToSell.value.id, {
        asking_price: askingPriceInput.value,
      })
      showNotice(`Đã đăng nhượng suất ${selectedSlotToSell.value.courtName} lên chợ thành công!`)
      closeSellModal()
      activeTab.value = 'my-listings'
      await refreshAll()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Đăng nhượng suất chơi thất bại.')
    } finally {
      processingAction.value = false
    }
  }

  async function cancelListing(listingId: number) {
    if (
      !confirm(
        'Bạn có chắc chắn muốn rút tin nhượng này về không? Slot sẽ được trả lại quyền sử dụng cho bạn.',
      )
    ) {
      return
    }

    processingAction.value = true
    error.value = ''
    try {
      await marketplaceApi.withdrawListing(listingId)
      showNotice('Đã rút suất chơi khỏi chợ nhượng vé thành công!')
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
    isBuyModalOpen,
    selectedListingToBuy,
    buyPaymentMethod,
    isSellModalOpen,
    selectedSlotToSell,
    askingPriceInput,
    listings,
    myListings,
    userSlots,
    filteredListings,
    refreshAll,
    resetFilters,
    showNotice,
    openBuyModal,
    closeBuyModal,
    openSellModal,
    closeSellModal,
    confirmBuy,
    confirmSell,
    cancelListing,
  }
}
