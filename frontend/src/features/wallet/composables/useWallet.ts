import { ref, computed, onMounted } from 'vue'
import type { VenueWallet, Transaction } from '../types/wallet'
export type { VenueWallet, Transaction }
import { walletApi } from '../api/walletApi'
import { errorMessage } from '@/core/utils/errorMessage'

export function useWallet() {
  const loading = ref(false)
  const processing = ref(false)
  const notice = ref('')
  const error = ref('')

  const transactionFilter = ref<'all' | 'expense' | 'topup' | 'refund'>('all')
  const selectedVenueFilter = ref<string>('all')

  const isDepositModalOpen = ref(false)
  const isQrModalOpen = ref(false)
  const selectedWalletForAction = ref<VenueWallet | null>(null)

  const depositAmount = ref<number>(500000)
  const depositMethod = ref<'vietqr' | 'vnpay' | 'momo' | 'card'>('vietqr')
  const depositTargetWalletId = ref<string>('')

  const quickAmounts = [100000, 200000, 500000, 1000000, 2000000]

  const wallets = ref<VenueWallet[]>([])
  const transactions = ref<Transaction[]>([])

  const totalBalance = computed(() => {
    return wallets.value.reduce((sum, w) => sum + (w.balance || 0), 0)
  })

  const filteredTransactions = computed(() => {
    return transactions.value.filter((t) => {
      if (transactionFilter.value === 'expense' && t.amount > 0) return false
      if (transactionFilter.value === 'topup' && t.type !== 'topup') return false
      if (transactionFilter.value === 'refund' && t.type !== 'refund') return false
      if (selectedVenueFilter.value !== 'all' && !t.venue_name.includes(selectedVenueFilter.value)) return false
      return true
    })
  })

  async function loadWalletData() {
    loading.value = true
    error.value = ''
    try {
      const res = await walletApi.getWallet()
      if (res) {
        wallets.value = [
          {
            id: 'main-wallet',
            venue_id: 1,
            venue_name: 'Ví Aura Tổng Hợp',
            address: 'Hệ thống Sân Aura Toàn Quốc',
            balance: res.balance || 0,
            is_active: true,
            currency: 'VND',
            gradient_type: 'primary',
          },
        ]
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải thông tin ví.')
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function openDepositModal(wallet?: VenueWallet) {
    if (wallet) {
      depositTargetWalletId.value = wallet.id
      selectedWalletForAction.value = wallet
    }
    isDepositModalOpen.value = true
  }

  function openQrModal(wallet: VenueWallet) {
    selectedWalletForAction.value = wallet
    isQrModalOpen.value = true
  }

  async function confirmDeposit() {
    processing.value = true
    error.value = ''
    try {
      await walletApi.credit({
        amount: depositAmount.value,
        method: depositMethod.value,
      })
      await loadWalletData()
      isDepositModalOpen.value = false
      showNotice(`Đã nạp thành công ${depositAmount.value.toLocaleString('vi-VN')} ₫ vào ví!`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể thực hiện nạp tiền vào ví.')
    } finally {
      processing.value = false
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
    isDepositModalOpen,
    isQrModalOpen,
    selectedWalletForAction,
    depositAmount,
    depositMethod,
    depositTargetWalletId,
    quickAmounts,
    wallets,
    transactions,
    totalBalance,
    filteredTransactions,
    loadWalletData,
    showNotice,
    openDepositModal,
    openQrModal,
    confirmDeposit,
  }
}
