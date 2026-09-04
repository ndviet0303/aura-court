import { ref, computed, onMounted } from 'vue'
import type { VenueWallet, Transaction, TransactionType, PaymentMethodType, CardDetails } from '../types/wallet'
export type { VenueWallet, Transaction, TransactionType, PaymentMethodType, CardDetails }
import { walletApi } from '../api/walletApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useBookingStore } from '@/stores/booking'

const DEFAULT_WALLETS: VenueWallet[] = [
  {
    id: 'wallet-main',
    venue_id: 1,
    venue_name: 'Ví Hội Viên Toàn Hệ Thống Aura',
    card_number: 'AUR-9902-8142',
    address: 'Toàn bộ hệ thống cơ sở Aura Court',
    balance: 0,
    points: 0,
    is_active: true,
    currency: 'VND',
    member_tier: 'gold',
    tier_label: 'Gold Athlete',
    accent_theme: 'burgundy',
    card_holder: 'AURA MEMBER',
    expiry_date: '12/2028',
  },
  {
    id: 'wallet-thaodien',
    venue_id: 2,
    venue_name: 'Aura Court Thảo Điền (TP. Thủ Đức)',
    card_number: 'AUR-7721-6601',
    address: '215 Nguyễn Văn Hưởng, P. Thảo Điền, TP. Thủ Đức',
    balance: 0,
    points: 0,
    is_active: true,
    currency: 'VND',
    member_tier: 'silver',
    tier_label: 'Silver Athlete',
    accent_theme: 'gold',
    card_holder: 'AURA MEMBER',
    expiry_date: '08/2027',
  },
  {
    id: 'wallet-caugiay',
    venue_id: 3,
    venue_name: 'Aura Court Cầu Giấy (Hà Nội)',
    card_number: 'AUR-5510-4498',
    address: '18 Duy Tân, Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
    balance: 0,
    points: 0,
    is_active: true,
    currency: 'VND',
    member_tier: 'standard',
    tier_label: 'Standard Athlete',
    accent_theme: 'navy',
    card_holder: 'AURA MEMBER',
    expiry_date: '06/2027',
  },
]

export function useWallet() {
  const auth = useAuthStore()
  const bookingStore = useBookingStore()

  const loading = ref(false)
  const processing = ref(false)
  const notice = ref('')
  const error = ref('')

  // Filters & Search
  const transactionFilter = ref<TransactionType | 'all'>('all')
  const selectedVenueFilter = ref<string>('all')
  const searchQuery = ref<string>('')

  // Modals state
  const isDepositModalOpen = ref(false)
  const depositStep = ref<'select' | 'sepay_pay' | 'stripe_pay'>('select')
  const isQrModalOpen = ref(false)
  const selectedWalletForAction = ref<VenueWallet | null>(null)

  // Deposit Form State
  const depositAmount = ref<number>(200000)
  const depositMethod = ref<PaymentMethodType>('sepay')
  const depositTargetWalletId = ref<string>('wallet-main')

  // Stripe card state
  const cardNumber = ref('')
  const cardExpiry = ref('')
  const cardCvc = ref('')
  const cardName = ref('')

  // Quick Amount Presets
  const quickAmounts = [100000, 200000, 500000, 1000000, 2000000, 5000000]

  // Data
  const wallets = ref<VenueWallet[]>(DEFAULT_WALLETS)
  const transactions = ref<Transaction[]>([])

  const totalBalance = computed(() => {
    // Total system available balance is the main wallet balance
    const mainWallet = wallets.value.find((w) => w.id === 'wallet-main')
    return mainWallet ? mainWallet.balance : wallets.value.reduce((sum, w) => sum + (w.balance || 0), 0)
  })

  const totalPoints = computed(() => {
    return wallets.value.reduce((sum, w) => sum + (w.points || 0), 0)
  })

  const filteredTransactions = computed(() => {
    return transactions.value.filter((t) => {
      if (transactionFilter.value !== 'all' && t.type !== transactionFilter.value) return false
      if (selectedVenueFilter.value !== 'all' && !t.venue_name.toLowerCase().includes(selectedVenueFilter.value.toLowerCase())) return false
      if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        const matchesTitle = t.title.toLowerCase().includes(q)
        const matchesRef = t.reference.toLowerCase().includes(q)
        const matchesDesc = t.description.toLowerCase().includes(q)
        if (!matchesTitle && !matchesRef && !matchesDesc) return false
      }
      return true
    })
  })

  // SePay VietQR Transfer Info
  const transferInfo = computed(() => {
    const bank = 'TPBank'
    const accountNo = '83403032005'
    const accountName = 'AURA COURT'
    const userIdentifier = auth.user?.phone || auth.user?.id || 'MEMBER'
    const memo = `ORD NAP ${userIdentifier}`
    const qrUrl = `https://img.vietqr.io/image/${bank}-${accountNo}-compact2.png?amount=${depositAmount.value}&addInfo=${encodeURIComponent(
      memo
    )}&accountName=${encodeURIComponent(accountName)}`

    return {
      bank,
      accountNo,
      accountName,
      memo,
      qrUrl,
      amount: depositAmount.value,
    }
  })

  async function loadWalletData() {
    loading.value = true
    error.value = ''
    try {
      const res = await walletApi.getWallet()
      const userName = auth.user?.name || 'AURA MEMBER'
      const realBal = typeof res?.balance === 'number' ? res.balance : 0
      const realPts = typeof res?.points === 'number' ? res.points : Math.floor(realBal / 1000)

      wallets.value = [
        {
          id: 'wallet-main',
          venue_id: 1,
          venue_name: 'Ví Hội Viên Toàn Hệ Thống Aura',
          card_number: 'AUR-9902-8142',
          address: 'Toàn bộ hệ thống cơ sở Aura Court',
          balance: realBal,
          points: realPts,
          is_active: true,
          currency: 'VND',
          member_tier: 'gold',
          tier_label: 'Gold Athlete',
          accent_theme: 'burgundy',
          card_holder: userName,
          expiry_date: '12/2028',
        },
        {
          id: 'wallet-thaodien',
          venue_id: 2,
          venue_name: 'Aura Court Thảo Điền (TP. Thủ Đức)',
          card_number: 'AUR-7721-6601',
          address: '215 Nguyễn Văn Hưởng, P. Thảo Điền, TP. Thủ Đức',
          balance: realBal,
          points: realPts,
          is_active: true,
          currency: 'VND',
          member_tier: 'silver',
          tier_label: 'Silver Athlete',
          accent_theme: 'gold',
          card_holder: userName,
          expiry_date: '08/2027',
        },
        {
          id: 'wallet-caugiay',
          venue_id: 3,
          venue_name: 'Aura Court Cầu Giấy (Hà Nội)',
          card_number: 'AUR-5510-4498',
          address: '18 Duy Tân, Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
          balance: realBal,
          points: realPts,
          is_active: true,
          currency: 'VND',
          member_tier: 'standard',
          tier_label: 'Standard Athlete',
          accent_theme: 'navy',
          card_holder: userName,
          expiry_date: '06/2027',
        },
      ]

      if (Array.isArray(res?.transactions)) {
        transactions.value = res.transactions.map((t: any) => ({
          id: `txn-${t.id}`,
          type: t.type === 'credit' ? 'topup' : 'booking',
          title: t.type === 'credit' ? 'Nạp tiền ví Aura' : 'Thanh toán đặt sân',
          venue_name: 'Hệ Thống Aura Court',
          description: t.description || (t.type === 'credit' ? 'Nạp tiền vào ví' : 'Trừ số dư đặt sân'),
          date: t.created_at ? new Date(t.created_at).toLocaleDateString('vi-VN') : '',
          time: t.created_at ? new Date(t.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '',
          amount: t.type === 'credit' ? t.amount : -t.amount,
          balance_after: t.balance_after || realBal,
          status: 'success',
          reference: t.reference || `TXN-${t.id}`,
          method_label: 'Ví Aura',
        }))
      }

      bookingStore.walletBalance = realBal
    } catch {
      const userName = auth.user?.name || 'AURA MEMBER'
      wallets.value = DEFAULT_WALLETS.map((w) => ({
        ...w,
        card_holder: userName,
      }))
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

  function openDepositModal(wallet?: VenueWallet) {
    if (wallet) {
      depositTargetWalletId.value = wallet.id
      selectedWalletForAction.value = wallet
    } else {
      depositTargetWalletId.value = wallets.value[0]?.id || 'wallet-main'
      selectedWalletForAction.value = wallets.value[0] || null
    }
    depositStep.value = 'select'
    isDepositModalOpen.value = true
  }

  function openQrModal(wallet?: VenueWallet) {
    selectedWalletForAction.value = wallet || wallets.value[0] || null
    isQrModalOpen.value = true
  }

  async function proceedToPaymentStep() {
    if (depositMethod.value === 'sepay') {
      depositStep.value = 'sepay_pay'
    } else {
      error.value = 'Nạp tiền qua Stripe chưa được cấu hình. Vui lòng sử dụng chuyển khoản VietQR.'
    }
  }

  async function confirmDeposit() {
    processing.value = true
    error.value = ''
    try {
      if (depositMethod.value !== 'sepay') {
        throw new Error('Nạp tiền qua Stripe chưa được cấu hình. Vui lòng sử dụng chuyển khoản VietQR.')
      }

      await loadWalletData()

      isDepositModalOpen.value = false
      depositStep.value = 'select'
      showNotice('Giao dịch sẽ được cộng vào ví sau khi SePay xác nhận chuyển khoản.')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể thực hiện nạp tiền vào ví.')
    } finally {
      processing.value = false
    }
  }

  async function copyToClipboard(text: string, successMsg = 'Đã sao chép vào bộ nhớ tạm') {
    try {
      if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(text)
        showNotice(successMsg)
      }
    } catch {
      // Fallback
    }
  }

  onMounted(() => {
    loadWalletData()
  })

  return {
    loading,
    processing,
    notice,
    error,
    transactionFilter,
    selectedVenueFilter,
    searchQuery,
    isDepositModalOpen,
    depositStep,
    isQrModalOpen,
    selectedWalletForAction,
    depositAmount,
    depositMethod,
    depositTargetWalletId,
    cardNumber,
    cardExpiry,
    cardCvc,
    cardName,
    quickAmounts,
    wallets,
    transactions,
    totalBalance,
    totalPoints,
    filteredTransactions,
    transferInfo,
    loadWalletData,
    showNotice,
    openDepositModal,
    openQrModal,
    proceedToPaymentStep,
    confirmDeposit,
    copyToClipboard,
  }
}
