import { ref, computed } from 'vue'
import type { ResaleListing, UserBookingSlot, TransactionLog } from '../types/marketplace'
import { errorMessage } from '@/core/utils/errorMessage'

export function useResaleMarketplace() {
  const activeTab = ref<'buy' | 'sell'>('buy')
  const loading = ref(false)
  const processingAction = ref(false)
  const notice = ref('')
  const error = ref('')

  // Filter states
  const selectedDate = ref('')
  const selectedTimePeriod = ref('all')
  const selectedVenue = ref('all')
  const searchQuery = ref('')

  // Modals
  const isBuyModalOpen = ref(false)
  const selectedListingToBuy = ref<ResaleListing | null>(null)
  const buyPaymentMethod = ref<'wallet' | 'transfer'>('wallet')

  const isSellModalOpen = ref(false)
  const selectedSlotToSell = ref<UserBookingSlot | null>(null)
  const askingPriceInput = ref<number>(150000)

  const listings = ref<ResaleListing[]>([
    {
      id: 101,
      courtName: 'Sân VIP 1 (Thảm Taraflex Đỏ)',
      venueName: 'Aura Center - Quận 1',
      dateStr: 'Hôm nay, 28/08/2026',
      timeSlot: '19:00 - 21:00 (2 tiếng)',
      timePeriod: 'evening',
      originalPrice: 400000,
      resalePrice: 250000,
      badge: 'hot',
      sellerName: 'Minh Hoàng',
      isFeatured: true,
    },
    {
      id: 102,
      courtName: 'Sân Tiêu Chuẩn 3',
      venueName: 'Aura East - Quận 2',
      dateStr: 'Thứ Sáu, 29/08/2026',
      timeSlot: '17:00 - 19:00 (2 tiếng)',
      timePeriod: 'afternoon',
      originalPrice: 220000,
      resalePrice: 150000,
      badge: 'urgent',
      sellerName: 'Trần Tuấn',
    },
    {
      id: 103,
      courtName: 'Sân Thảm Xanh 2',
      venueName: 'Aura North - Phú Nhuận',
      dateStr: 'Thứ Bảy, 30/08/2026',
      timeSlot: '08:00 - 10:00 (2 tiếng)',
      timePeriod: 'morning',
      originalPrice: 180000,
      resalePrice: 135000,
      badge: 'deal',
      sellerName: 'Ngọc Mai',
    },
    {
      id: 104,
      courtName: 'Sân VIP 2',
      venueName: 'Aura Center - Quận 1',
      dateStr: 'Chủ Nhật, 31/08/2026',
      timeSlot: '20:00 - 22:00 (2 tiếng)',
      timePeriod: 'evening',
      originalPrice: 350000,
      resalePrice: 270000,
      badge: 'deal',
      sellerName: 'Đức Huy',
    },
  ])

  const userSlots = ref<UserBookingSlot[]>([
    {
      id: 201,
      entitlementId: 501,
      courtName: 'Sân 04 (Thảm Tiêu Chuẩn)',
      venueName: 'Aura Center - Quận 1',
      dateStr: 'Chủ Nhật, 07/09/2026',
      timeSlot: '18:00 - 20:00',
      originalPrice: 240000,
      eligible: true,
      dateBadge: {
        dayOfWeek: 'CN',
        dayNum: '07',
      },
    },
  ])

  const filteredListings = computed(() => {
    return listings.value.filter((l) => {
      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        l.courtName.toLowerCase().includes(q) ||
        l.venueName.toLowerCase().includes(q) ||
        l.sellerName.toLowerCase().includes(q)

      const matchPeriod =
        selectedTimePeriod.value === 'all' || l.timePeriod === selectedTimePeriod.value

      const matchVenue =
        selectedVenue.value === 'all' || l.venueName.includes(selectedVenue.value)

      return matchSearch && matchPeriod && matchVenue
    })
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function openBuyModal(listing: ResaleListing) {
    selectedListingToBuy.value = listing
    isBuyModalOpen.value = true
  }

  function openSellModal(slot: UserBookingSlot) {
    selectedSlotToSell.value = slot
    askingPriceInput.value = Math.round(slot.originalPrice * 0.8)
    isSellModalOpen.value = true
  }

  function confirmBuy() {
    if (!selectedListingToBuy.value) return
    processingAction.value = true
    setTimeout(() => {
      const idx = listings.value.findIndex((l) => l.id === selectedListingToBuy.value?.id)
      if (idx >= 0) listings.value.splice(idx, 1)
      isBuyModalOpen.value = false
      processingAction.value = false
      showNotice('Giao dịch chuyển nhượng thành công! Lịch sân đã được cập nhật vào ví của bạn.')
    }, 800)
  }

  function confirmSell() {
    if (!selectedSlotToSell.value) return
    processingAction.value = true
    setTimeout(() => {
      listings.value.unshift({
        id: Date.now(),
        courtName: selectedSlotToSell.value!.courtName,
        venueName: selectedSlotToSell.value!.venueName,
        dateStr: selectedSlotToSell.value!.dateStr,
        timeSlot: selectedSlotToSell.value!.timeSlot,
        timePeriod: 'evening',
        originalPrice: selectedSlotToSell.value!.originalPrice,
        resalePrice: askingPriceInput.value,
        badge: 'deal',
        sellerName: 'Bạn (Chủ đơn)',
      })
      isSellModalOpen.value = false
      processingAction.value = false
      showNotice('Đã niêm yết lịch sân lên Chợ Chuyển Nhượng thành công!')
    }, 800)
  }

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
    isBuyModalOpen,
    selectedListingToBuy,
    buyPaymentMethod,
    isSellModalOpen,
    selectedSlotToSell,
    askingPriceInput,
    listings,
    userSlots,
    filteredListings,
    showNotice,
    openBuyModal,
    openSellModal,
    confirmBuy,
    confirmSell,
  }
}
