import { ref, computed, onMounted } from 'vue'
import type { ResaleListing, UserBookingSlot, TransactionLog } from '../types/marketplace'
import { marketplaceApi } from '../api/marketplaceApi'
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

  const listings = ref<ResaleListing[]>([])
  const mySlots = ref<UserBookingSlot[]>([])
  const transactions = ref<TransactionLog[]>([])

  const filteredListings = computed(() => {
    return listings.value.filter((item) => {
      if (selectedVenue.value !== 'all' && !item.venueName?.includes(selectedVenue.value)) return false
      if (selectedTimePeriod.value !== 'all' && item.timePeriod !== selectedTimePeriod.value) return false
      if (selectedDate.value && !item.dateStr?.includes(selectedDate.value)) return false

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
  })

  async function loadListings() {
    loading.value = true
    error.value = ''
    try {
      const res = await marketplaceApi.getListings<{ data: ResaleListing[] }>()
      if (res?.data) {
        listings.value = res.data
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách nhượng sân.')
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
    isBuyModalOpen.value = true
  }

  function closeBuyModal() {
    isBuyModalOpen.value = false
    selectedListingToBuy.value = null
  }

  function openSellModal(slot: UserBookingSlot) {
    selectedSlotToSell.value = slot
    askingPriceInput.value = Math.round((slot.originalPrice || 200000) * 0.8)
    isSellModalOpen.value = true
  }

  function closeSellModal() {
    isSellModalOpen.value = false
    selectedSlotToSell.value = null
  }

  async function confirmBuy() {
    if (!selectedListingToBuy.value) return
    processingAction.value = true
    try {
      await marketplaceApi.purchaseListing(selectedListingToBuy.value.id, {
        buyer_payment_reference: buyPaymentMethod.value,
      })
      showNotice(`Đã mua lại suất chơi sân ${selectedListingToBuy.value.courtName} thành công!`)
      await loadListings()
      closeBuyModal()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Mua lại suất chơi thất bại.')
    } finally {
      processingAction.value = false
    }
  }

  async function confirmSell() {
    if (!selectedSlotToSell.value) return
    processingAction.value = true
    try {
      await marketplaceApi.listEntitlement(selectedSlotToSell.value.id, {
        asking_price: askingPriceInput.value,
      })
      showNotice(`Đã đăng nhượng suất ${selectedSlotToSell.value.courtName} lên sàn giao dịch!`)
      await loadListings()
      closeSellModal()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Đăng nhượng suất chơi thất bại.')
    } finally {
      processingAction.value = false
    }
  }

  async function cancelListing(listingId: number) {
    processingAction.value = true
    try {
      await marketplaceApi.withdrawListing(listingId)
      showNotice('Đã rút suất chơi khỏi sàn nhượng vé thành công!')
      await loadListings()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể rút tin nhượng.')
    } finally {
      processingAction.value = false
    }
  }

  onMounted(() => {
    loadListings()
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
    isBuyModalOpen,
    selectedListingToBuy,
    buyPaymentMethod,
    isSellModalOpen,
    selectedSlotToSell,
    askingPriceInput,
    listings,
    mySlots,
    userSlots: mySlots,
    transactions,
    filteredListings,
    loadListings,
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
