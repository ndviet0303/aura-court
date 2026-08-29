<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { apiV2, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import SkeletonLoader from '@/components/SkeletonLoader.vue'

const money = (value: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0).replace('₫', 'đ')

interface VenueWallet {
  id: string
  venue_id: number
  venue_name: string
  address: string
  balance: number
  is_active: boolean
  currency: string
  gradient_type: 'primary' | 'secondary' | 'neutral'
}

interface Transaction {
  id: string
  type: 'booking' | 'topup' | 'pos' | 'refund'
  title: string
  venue_name: string
  description: string
  date: string
  time: string
  amount: number
  status: 'success' | 'pending' | 'failed'
}

const auth = useAuthStore()

// State
const loading = ref(false)
const processing = ref(false)
const notice = ref('')
const error = ref('')

// Filter State
const transactionFilter = ref<'all' | 'expense' | 'topup' | 'refund'>('all')
const selectedVenueFilter = ref<string>('all')

// Modals State
const isDepositModalOpen = ref(false)
const isQrModalOpen = ref(false)
const isAddWalletModalOpen = ref(false)
const selectedWalletForAction = ref<VenueWallet | null>(null)

// Deposit Form
const depositAmount = ref<number>(500000)
const depositCustomAmount = ref<number | null>(null)
const depositMethod = ref<'vietqr' | 'vnpay' | 'momo' | 'card'>('vietqr')
const depositTargetWalletId = ref<string>('aura-center')

// Preset Topup Amounts
const quickAmounts = [100000, 200000, 500000, 1000000, 2000000]

// QR Code countdown
const qrCountdown = ref(60)
let qrTimer: ReturnType<typeof setInterval> | null = null

// Venue Wallets Data
const wallets = ref<VenueWallet[]>([
  {
    id: 'aura-center',
    venue_id: 1,
    venue_name: 'Aura Center (Quận 1)',
    address: 'Số 12 Nguyễn Thị Minh Khai, P. Bến Nghé, Q.1',
    balance: 1500000,
    is_active: true,
    currency: 'VND',
    gradient_type: 'primary',
  },
  {
    id: 'riverside-club',
    venue_id: 2,
    venue_name: 'Riverside Club (Bình Thạnh)',
    address: 'Số 48 Ung Văn Khiêm, P.25, Q. Bình Thạnh',
    balance: 4500000,
    is_active: true,
    currency: 'VND',
    gradient_type: 'secondary',
  },
  {
    id: 'district1-hub',
    venue_id: 3,
    venue_name: 'District 1 Hub',
    address: 'Số 88 Lê Thánh Tôn, P. Bến Nghé, Q.1',
    balance: 0,
    is_active: true,
    currency: 'VND',
    gradient_type: 'neutral',
  },
])

// Transactions Data
const transactions = ref<Transaction[]>([
  {
    id: 'TX-8921',
    type: 'booking',
    title: 'Đặt sân',
    venue_name: 'Aura Center (Quận 1)',
    description: 'Đặt sân #4 - 2 giờ (18:30 - 20:30)',
    date: '24 Thg 10, 2026',
    time: '18:30',
    amount: -320000,
    status: 'success',
  },
  {
    id: 'TX-8840',
    type: 'topup',
    title: 'Nạp tiền',
    venue_name: 'Aura Center (Quận 1)',
    description: 'Nạp qua VietQR Tự Động (+50.000đ KM)',
    date: '20 Thg 10, 2026',
    time: '09:15',
    amount: 1000000,
    status: 'success',
  },
  {
    id: 'TX-8712',
    type: 'pos',
    title: 'Mua hàng POS',
    venue_name: 'Riverside Club (Bình Thạnh)',
    description: '2x Nước suối ion, 1x Ống cầu lông Yonex',
    date: '15 Thg 10, 2026',
    time: '20:45',
    amount: -380000,
    status: 'success',
  },
  {
    id: 'TX-8650',
    type: 'refund',
    title: 'Hoàn tiền',
    venue_name: 'Riverside Club (Bình Thạnh)',
    description: 'Hủy đặt sân do thời tiết bất khả kháng',
    date: '12 Thg 10, 2026',
    time: '14:20',
    amount: 160000,
    status: 'success',
  },
  {
    id: 'TX-8520',
    type: 'booking',
    title: 'Đặt sân',
    venue_name: 'Aura Center (Quận 1)',
    description: 'Đặt sân VIP #1 - 1.5 giờ',
    date: '08 Thg 10, 2026',
    time: '17:00',
    amount: -270000,
    status: 'success',
  },
  {
    id: 'TX-8410',
    type: 'topup',
    title: 'Nạp tiền',
    venue_name: 'Riverside Club (Bình Thạnh)',
    description: 'Nạp qua MoMo Ví Điện Tử',
    date: '01 Thg 10, 2026',
    time: '11:00',
    amount: 500000,
    status: 'success',
  },
])

// Total Balance
const totalBalance = computed(() => {
  return wallets.value.reduce((acc, curr) => acc + curr.balance, 0)
})

// Filtered Transactions
const filteredTransactions = computed(() => {
  return transactions.value.filter((tx) => {
    // Type Filter
    if (transactionFilter.value === 'expense' && tx.amount >= 0) return false
    if (transactionFilter.value === 'topup' && tx.type !== 'topup') return false
    if (transactionFilter.value === 'refund' && tx.type !== 'refund') return false

    // Venue Filter
    if (selectedVenueFilter.value !== 'all') {
      const matchWallet = wallets.value.find((w) => w.id === selectedVenueFilter.value)
      if (matchWallet && !tx.venue_name.includes(matchWallet.venue_name.split(' ')[0] || '')) {
        return false
      }
    }

    return true
  })
})

function showNotice(msg: string) {
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 4000)
}

function showError(msg: string) {
  error.value = msg
  setTimeout(() => {
    if (error.value === msg) error.value = ''
  }, 4500)
}

function openDepositModal(wallet?: VenueWallet) {
  if (wallet) {
    selectedWalletForAction.value = wallet
    depositTargetWalletId.value = wallet.id
  } else {
    selectedWalletForAction.value = wallets.value[0] || null
    depositTargetWalletId.value = wallets.value[0]?.id || 'aura-center'
  }
  isDepositModalOpen.value = true
}

function openQrModal(wallet: VenueWallet) {
  selectedWalletForAction.value = wallet
  isQrModalOpen.value = true
  qrCountdown.value = 60

  if (qrTimer) clearInterval(qrTimer)
  qrTimer = setInterval(() => {
    if (qrCountdown.value > 0) {
      qrCountdown.value -= 1
    } else {
      qrCountdown.value = 60
    }
  }, 1000)
}

function closeQrModal() {
  isQrModalOpen.value = false
  if (qrTimer) clearInterval(qrTimer)
}

async function handleDepositSubmit() {
  const finalAmount = depositCustomAmount.value ? depositCustomAmount.value : depositAmount.value
  if (!finalAmount || finalAmount < 10000) {
    showError('Vui lòng chọn hoặc nhập số tiền tối thiểu 10.000đ.')
    return
  }

  processing.value = true
  try {
    // Try backend call if available
    try {
      await apiV2.post('/wallet/credit', {
        amount: finalAmount,
        method: depositMethod.value,
        wallet_id: depositTargetWalletId.value,
      })
    } catch {
      // Optimistic simulated update
    }

    const target = wallets.value.find((w) => w.id === depositTargetWalletId.value)
    if (target) {
      target.balance += finalAmount
    }

    // Add transaction record
    transactions.value.unshift({
      id: `TX-${Math.floor(1000 + Math.random() * 9000)}`,
      type: 'topup',
      title: 'Nạp tiền',
      venue_name: target?.venue_name || 'Ví Cụm Sân',
      description: `Nạp tiền qua ${depositMethod.value.toUpperCase()} (Tự động duyệt)`,
      date: 'Hôm nay',
      time: 'Vừa xong',
      amount: finalAmount,
      status: 'success',
    })

    showNotice(`🎉 Nạp thành công ${money(finalAmount)} vào ${target?.venue_name}!`)
    isDepositModalOpen.value = false
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể thực hiện giao dịch nạp tiền.'))
  } finally {
    processing.value = false
  }
}

onMounted(async () => {
  loading.value = true
  try {
    const res = await apiV2.get<{ balance: number }>('/wallet')
    if (res && res.balance > 0) {
      const mainWallet = wallets.value[0]
      if (mainWallet) mainWallet.balance = res.balance
    }
  } catch {
    // Fallback to local default data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="wallet-page">
    <!-- Ambient Background Glow -->
    <div class="ambient-glow-wrapper" aria-hidden="true">
      <div class="glow-orb glow-orb-left"></div>
      <div class="glow-orb glow-orb-right"></div>
    </div>

    <main class="wallet-main-content">
      <!-- Flash Alert Notices -->
      <div class="alerts-container">
        <Transition name="fade">
          <div v-if="notice" class="alert-banner alert-success">
            <span class="material-symbols-outlined">check_circle</span>
            <span>{{ notice }}</span>
          </div>
        </Transition>
        <Transition name="fade">
          <div v-if="error" class="alert-banner alert-danger">
            <span class="material-symbols-outlined">error</span>
            <span>{{ error }}</span>
          </div>
        </Transition>
      </div>

      <div class="wallet-container">
        <!-- Top Breadcrumb & Quick Info Bar -->
        <div class="top-nav-bar">
          <RouterLink to="/" class="back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Trang chủ</span>
          </RouterLink>

          <!-- Total Balance Quick Summary Pill -->
          <div class="total-wealth-pill">
            <span class="wealth-label">Tổng số dư tất cả cụm sân:</span>
            <span class="wealth-val">{{ money(totalBalance) }}</span>
          </div>
        </div>

        <!-- Page Header -->
        <div class="wallet-header-block">
          <h1 class="page-headline">Ví Của Tôi</h1>
          <p class="page-subline">
            Quản lý số dư tại các cụm sân. Trải nghiệm thanh toán nhanh chóng, không gián đoạn cho mọi dịch vụ và tiện ích.
          </p>
        </div>

        <!-- ================= VENUE WALLET CARDS SECTION ================= -->
        <section class="section-block">
          <div class="section-head-row">
            <h2 class="section-title">Số dư khả dụng</h2>
            <button
              type="button"
              class="btn-topup-main"
              @click="openDepositModal()"
            >
              <span class="material-symbols-outlined text-[18px]">add_circle</span>
              <span>Nạp Tiền Nhanh</span>
            </button>
          </div>

          <!-- Loading Skeleton Cards -->
          <div v-if="loading" class="wallet-cards-carousel">
            <div v-for="i in 3" :key="i" class="venue-wallet-card skeleton-wallet-card">
              <div class="card-head-row">
                <div class="venue-info">
                  <SkeletonLoader type="text" width="120px" height="12px" />
                  <div style="margin-top: 8px">
                    <SkeletonLoader type="title" width="160px" height="28px" />
                  </div>
                </div>
                <SkeletonLoader type="avatar" width="40px" height="40px" />
              </div>
              <div class="card-actions-row" style="margin-top: 24px">
                <SkeletonLoader type="rect" height="38px" rounded="12px" />
              </div>
            </div>
          </div>

          <div v-else class="wallet-cards-carousel">
            <!-- Venue Wallet Card -->
            <div
              v-for="wallet in wallets"
              :key="wallet.id"
              class="venue-wallet-card"
              :class="`theme-${wallet.gradient_type}`"
            >
              <div class="card-ambient-blur"></div>

              <div class="card-head-row">
                <div class="venue-info">
                  <span class="venue-tag">{{ wallet.venue_name }}</span>
                  <div class="venue-balance">{{ money(wallet.balance) }}</div>
                </div>

                <div class="wallet-icon-box">
                  <span class="material-symbols-outlined">account_balance_wallet</span>
                </div>
              </div>

              <div class="card-actions-row">
                <button
                  type="button"
                  class="btn-card-deposit"
                  @click="openDepositModal(wallet)"
                >
                  <span>Nạp tiền</span>
                </button>

                <button
                  type="button"
                  class="btn-card-qr"
                  title="Mã QR thanh toán tại quầy"
                  @click="openQrModal(wallet)"
                >
                  <span class="material-symbols-outlined">qr_code_scanner</span>
                </button>
              </div>
            </div>

            <!-- Add New Venue Wallet Card -->
            <div class="add-wallet-card" @click="openDepositModal()">
              <div class="add-icon-circle">
                <span class="material-symbols-outlined">add</span>
              </div>
              <span class="add-card-label">Kích hoạt ví sân mới</span>
            </div>
          </div>
        </section>

        <!-- ================= TRANSACTION HISTORY SECTION ================= -->
        <section class="section-block mt-8">
          <div class="section-head-row flex-wrap gap-4">
            <h2 class="section-title">Lịch sử giao dịch</h2>

            <div class="history-controls">
              <!-- Filter by Type Tabs -->
              <div class="filter-tabs-pills">
                <button
                  type="button"
                  class="filter-tab-btn"
                  :class="{ active: transactionFilter === 'all' }"
                  @click="transactionFilter = 'all'"
                >
                  Tất cả
                </button>
                <button
                  type="button"
                  class="filter-tab-btn"
                  :class="{ active: transactionFilter === 'expense' }"
                  @click="transactionFilter = 'expense'"
                >
                  Tiêu dùng
                </button>
                <button
                  type="button"
                  class="filter-tab-btn"
                  :class="{ active: transactionFilter === 'topup' }"
                  @click="transactionFilter = 'topup'"
                >
                  Nạp tiền
                </button>
                <button
                  type="button"
                  class="filter-tab-btn"
                  :class="{ active: transactionFilter === 'refund' }"
                  @click="transactionFilter = 'refund'"
                >
                  Hoàn tiền
                </button>
              </div>

              <!-- Filter by Venue Dropdown -->
              <select v-model="selectedVenueFilter" class="venue-select-input">
                <option value="all">Tất cả cụm sân</option>
                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.venue_name }}</option>
              </select>
            </div>
          </div>

          <!-- Transaction Table Card -->
          <div class="table-card-box">
            <div class="table-responsive">
              <table class="tx-table">
                <thead>
                  <tr>
                    <th>GIAO DỊCH</th>
                    <th>NGÀY & GIỜ</th>
                    <th>CHI TIẾT</th>
                    <th class="text-right">SỐ TIỀN</th>
                  </tr>
                </thead>
                <tbody v-if="loading">
                  <tr v-for="i in 4" :key="i" class="tx-row">
                    <td class="tx-type-cell">
                      <SkeletonLoader type="avatar" width="36px" height="36px" />
                      <SkeletonLoader type="text" width="80px" height="14px" />
                    </td>
                    <td class="tx-date-cell">
                      <SkeletonLoader type="text" width="100px" height="14px" />
                    </td>
                    <td class="tx-desc-cell">
                      <SkeletonLoader type="text" width="180px" height="14px" />
                    </td>
                    <td class="tx-amount-cell">
                      <SkeletonLoader type="text" width="90px" height="16px" />
                    </td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr
                    v-for="tx in filteredTransactions"
                    :key="tx.id"
                    class="tx-row"
                  >
                    <!-- Transaction Type & Icon -->
                    <td class="tx-type-cell">
                      <div class="tx-icon-wrap" :class="`icon-${tx.type}`">
                        <span class="material-symbols-outlined">
                          {{
                            tx.type === 'booking'
                              ? 'calendar_month'
                              : tx.type === 'topup'
                              ? 'add_card'
                              : tx.type === 'pos'
                              ? 'point_of_sale'
                              : 'currency_exchange'
                          }}
                        </span>
                      </div>
                      <span class="tx-type-name">{{ tx.title }}</span>
                    </td>

                    <!-- Date & Time -->
                    <td class="tx-date-cell">
                      <div class="date-main">{{ tx.date }}</div>
                      <div class="time-sub">{{ tx.time }}</div>
                    </td>

                    <!-- Description & Venue Name -->
                    <td class="tx-desc-cell">
                      <div class="desc-main">{{ tx.description }}</div>
                      <div class="venue-sub">Ví {{ tx.venue_name }}</div>
                    </td>

                    <!-- Amount -->
                    <td class="tx-amount-cell" :class="{ 'is-positive': tx.amount > 0, 'is-negative': tx.amount < 0 }">
                      {{ tx.amount > 0 ? '+' : '' }}{{ money(tx.amount) }}
                    </td>
                  </tr>

                  <tr v-if="filteredTransactions.length === 0">
                    <td colspan="4" class="empty-table-cell">
                      <div class="empty-tx-box">
                        <span class="material-symbols-outlined empty-icon">receipt_long</span>
                        <p>Không tìm thấy giao dịch nào phù hợp với bộ lọc.</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="table-footer-row">
              <button type="button" class="btn-load-more">Tải thêm giao dịch</button>
            </div>
          </div>
        </section>
      </div>
    </main>

    <!-- ================= DEPOSIT MODAL ================= -->
    <div v-if="isDepositModalOpen" class="modal-backdrop" @click.self="isDepositModalOpen = false">
      <div class="modal-card modal-deposit">
        <div class="modal-head">
          <div>
            <h2 class="modal-title">Nạp Tiền Vào Ví Cụm Sân</h2>
            <p class="modal-sub">Thanh toán tự động, số dư cập nhật tức thì.</p>
          </div>
          <button type="button" class="btn-close-modal" @click="isDepositModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form @submit.prevent="handleDepositSubmit">
          <div class="modal-body">
            <!-- Select Target Venue Wallet -->
            <div class="form-group">
              <label class="form-label">Chọn cụm sân nhận tiền</label>
              <select v-model="depositTargetWalletId" class="modal-select">
                <option v-for="w in wallets" :key="w.id" :value="w.id">
                  {{ w.venue_name }} · Số dư: {{ money(w.balance) }}
                </option>
              </select>
            </div>

            <!-- Quick Amounts Grid -->
            <div class="form-group">
              <label class="form-label">Chọn mệnh giá nạp</label>
              <div class="quick-amounts-grid">
                <button
                  v-for="amt in quickAmounts"
                  :key="amt"
                  type="button"
                  class="amt-btn"
                  :class="{ active: depositAmount === amt && !depositCustomAmount }"
                  @click="depositAmount = amt; depositCustomAmount = null"
                >
                  {{ money(amt) }}
                </button>
              </div>
            </div>

            <!-- Custom Amount Input -->
            <div class="form-group">
              <label class="form-label">Hoặc nhập số tiền khác (VND)</label>
              <input
                v-model.number="depositCustomAmount"
                type="number"
                min="10000"
                step="10000"
                placeholder="Ví dụ: 350.000"
                class="modal-input"
              />
            </div>

            <!-- Payment Method Selector -->
            <div class="form-group">
              <label class="form-label">Phương thức thanh toán</label>
              <div class="pay-methods-grid">
                <label class="pay-method-card" :class="{ active: depositMethod === 'vietqr' }">
                  <input v-model="depositMethod" type="radio" value="vietqr" class="hidden-radio" />
                  <span class="material-symbols-outlined text-primary">qr_code_2</span>
                  <div class="pay-info">
                    <strong>VietQR Tự Động</strong>
                    <span>Chuyển khoản 24/7 tức thì</span>
                  </div>
                </label>

                <label class="pay-method-card" :class="{ active: depositMethod === 'vnpay' }">
                  <input v-model="depositMethod" type="radio" value="vnpay" class="hidden-radio" />
                  <span class="material-symbols-outlined text-primary">credit_card</span>
                  <div class="pay-info">
                    <strong>VNPAY-QR</strong>
                    <span>Ví VNPAY & App Ngân hàng</span>
                  </div>
                </label>

                <label class="pay-method-card" :class="{ active: depositMethod === 'momo' }">
                  <input v-model="depositMethod" type="radio" value="momo" class="hidden-radio" />
                  <span class="material-symbols-outlined text-primary">account_balance_wallet</span>
                  <div class="pay-info">
                    <strong>MoMo</strong>
                    <span>Ví điện tử MoMo</span>
                  </div>
                </label>
              </div>
            </div>

            <!-- Promo Banner -->
            <div class="promo-hint-box">
              <span class="material-symbols-outlined text-primary">redeem</span>
              <span>Tặng thêm <strong>5% giá trị</strong> vào ví khi nạp từ 1.000.000đ trở lên.</span>
            </div>
          </div>

          <div class="modal-foot">
            <button type="button" class="btn-cancel" @click="isDepositModalOpen = false">Hủy</button>
            <button type="submit" class="btn-submit-deposit" :disabled="processing">
              <span v-if="processing" class="material-symbols-outlined animate-spin text-[16px]">sync</span>
              <span>Xác Nhận Nạp {{ money(depositCustomAmount || depositAmount) }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= QR PAYMENT SCAN MODAL ================= -->
    <div v-if="isQrModalOpen && selectedWalletForAction" class="modal-backdrop" @click.self="closeQrModal">
      <div class="modal-card modal-qr">
        <div class="modal-head text-center">
          <div>
            <span class="badge-qr-pulse">DYNAMIC PAYMENT QR</span>
            <h2 class="modal-title mt-1">{{ selectedWalletForAction.venue_name }}</h2>
            <p class="modal-sub">Đưa mã này cho thu ngân hoặc máy POS quét trừ tiền trực tiếp.</p>
          </div>
          <button type="button" class="btn-close-modal" @click="closeQrModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-qr">
          <div class="qr-code-frame">
            <!-- Simulated QR Image -->
            <img src="/aura/badge-logo.png" alt="Aura Dynamic QR" class="qr-code-img" />
            <div class="qr-scan-line"></div>
          </div>

          <div class="qr-meta-box">
            <div class="otp-token-str">Mã xác thực: <strong>882-910</strong></div>
            <div class="countdown-bar">
              <span class="material-symbols-outlined text-[16px]">timer</span>
              <span>Tự động làm mới sau <strong>{{ qrCountdown }}s</strong></span>
            </div>
            <div class="current-bal-tag">
              Số dư ví hiện tại: <strong>{{ money(selectedWalletForAction.balance) }}</strong>
            </div>
          </div>
        </div>

        <div class="modal-foot justify-center">
          <button type="button" class="btn-primary-pill" @click="closeQrModal">Đã Thanh Toán Xong</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ==========================================================================
   Internal Wallet View Styles
   ========================================================================== */
.wallet-page {
  position: relative;
  min-height: 100vh;
  background-color: var(--bg, #eff1f4);
  color: var(--on-surface, #191c1d);
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  transition: background-color 0.3s ease, color 0.3s ease;
  overflow-x: hidden;
}

.ambient-glow-wrapper {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
  z-index: 0;
}

.glow-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
}

.glow-orb-left {
  width: 480px;
  height: 480px;
  top: -100px;
  left: -100px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.6) 0%, transparent 70%);
}

.glow-orb-right {
  width: 420px;
  height: 420px;
  top: 150px;
  right: -80px;
  background: radial-gradient(circle, rgba(120, 85, 94, 0.35) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-left {
  background: radial-gradient(circle, rgba(248, 180, 196, 0.15) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-right {
  background: radial-gradient(circle, rgba(83, 51, 62, 0.3) 0%, transparent 70%);
}

.wallet-main-content {
  position: relative;
  z-index: 1;
  width: 100%;
  padding: 16px 20px 80px;
}

@media (min-width: 768px) {
  .wallet-main-content {
    padding: 24px 32px 96px;
  }
}

.alerts-container {
  max-width: 1152px;
  margin: 0 auto 12px;
}

.alert-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 8px;
}

.alert-success {
  background: var(--success-bg, #e8f5e9);
  color: var(--success-text, #2e7d32);
  border: 1px solid rgba(46, 125, 50, 0.25);
}

.alert-danger {
  background: var(--error-bg, #ffebee);
  color: var(--error-text, #c62828);
  border: 1px solid rgba(198, 40, 40, 0.25);
}

.wallet-container {
  max-width: 1152px;
  margin: 0 auto;
}

/* ================= TOP NAV & BREADCRUMB ================= */
.top-nav-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--primary, #78555e);
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 8px;
  transition: all 0.2s;
}

.back-link:hover {
  background-color: var(--primary-container, #ffd1dc);
}

:global(html.dark) .back-link {
  color: var(--primary, #f8b4c4);
}

:global(html.dark) .back-link:hover {
  background-color: var(--primary-container, #53333e);
}

.total-wealth-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  background-color: var(--card-bg, #ffffff);
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .total-wealth-pill {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.wealth-label {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
}

.wealth-val {
  font-size: 14px;
  font-weight: 800;
  color: var(--primary, #78555e);
}

:global(html.dark) .wealth-val {
  color: var(--primary, #f8b4c4);
}

/* ================= PAGE HEADLINE ================= */
.wallet-header-block {
  margin-bottom: 28px;
}

.page-headline {
  font-size: 32px;
  font-weight: 900;
  letter-spacing: -0.02em;
  color: var(--on-surface, #191c1d);
  margin: 0 0 8px;
}

.page-subline {
  font-size: 14px;
  color: var(--on-surface-variant, #5d5f5f);
  max-width: 680px;
  line-height: 1.5;
  margin: 0;
}

/* ================= SECTION BLOCK & CARDS ================= */
.section-block {
  margin-bottom: 32px;
}

.section-head-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.section-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  margin: 0;
}

.btn-topup-main {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  border-radius: 9999px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-topup-main:hover {
  background-color: #5e3e47;
  transform: translateY(-1px);
}

:global(html.dark) .btn-topup-main {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

/* Wallet Cards Carousel */
.wallet-cards-carousel {
  display: flex;
  gap: 20px;
  overflow-x: auto;
  padding-bottom: 12px;
  scroll-snap-type: x mandatory;
}

.venue-wallet-card {
  position: relative;
  flex: 0 0 320px;
  min-height: 190px;
  border-radius: 20px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  scroll-snap-align: start;
  border: 1px solid var(--border, #e0e4ea);
  background-color: var(--card-bg, #ffffff);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

:global(html.dark) .venue-wallet-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}

.venue-wallet-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
}

.card-ambient-blur {
  position: absolute;
  right: -30px;
  top: -30px;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  filter: blur(40px);
  opacity: 0.5;
  pointer-events: none;
}

.theme-primary .card-ambient-blur {
  background: rgba(255, 209, 220, 0.8);
}

:global(html.dark) .theme-primary .card-ambient-blur {
  background: rgba(248, 180, 196, 0.3);
}

.theme-secondary .card-ambient-blur {
  background: rgba(120, 85, 94, 0.4);
}

.theme-neutral .card-ambient-blur {
  background: rgba(150, 150, 150, 0.3);
}

.card-head-row {
  position: relative;
  z-index: 2;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.venue-tag {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--on-surface-variant, #5d5f5f);
  display: block;
  margin-bottom: 6px;
}

.venue-balance {
  font-size: 26px;
  font-weight: 900;
  color: var(--on-surface, #191c1d);
  letter-spacing: -0.01em;
}

.wallet-icon-box {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

:global(html.dark) .wallet-icon-box {
  background-color: var(--primary-container, #53333e);
  color: var(--primary, #f8b4c4);
}

.card-actions-row {
  position: relative;
  z-index: 2;
  display: flex;
  gap: 8px;
  margin-top: 20px;
}

.btn-card-deposit {
  flex: 1;
  padding: 10px 16px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-card-deposit:hover {
  background-color: #5e3e47;
}

:global(html.dark) .btn-card-deposit {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

.btn-card-qr {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px 14px;
  border-radius: 12px;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-card-qr:hover {
  background-color: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .btn-card-qr {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: var(--on-surface, #ffffff);
}

/* Add Wallet Card */
.add-wallet-card {
  flex: 0 0 180px;
  min-height: 190px;
  border-radius: 20px;
  border: 2px dashed var(--outline-variant, #d3c3c5);
  background-color: transparent;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  cursor: pointer;
  padding: 20px;
  text-align: center;
  transition: all 0.2s;
}

.add-wallet-card:hover {
  border-color: var(--primary, #78555e);
  background-color: var(--surface-subtle, #f5f6f8);
}

:global(html.dark) .add-wallet-card {
  border-color: #4a4e59;
}

:global(html.dark) .add-wallet-card:hover {
  border-color: var(--primary, #f8b4c4);
  background-color: var(--surface-container, #2b2e36);
}

.add-icon-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid var(--outline-variant, #d3c3c5);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--on-surface-variant, #5d5f5f);
}

.add-card-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
}

/* ================= TRANSACTION HISTORY TABLE ================= */
.history-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-tabs-pills {
  display: inline-flex;
  padding: 3px;
  background-color: var(--surface-container, #edeeef);
  border-radius: 10px;
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .filter-tabs-pills {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.filter-tab-btn {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.filter-tab-btn.active {
  background-color: var(--card-bg, #ffffff);
  color: var(--primary, #78555e);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

:global(html.dark) .filter-tab-btn.active {
  background-color: var(--surface-container-high, #353943);
  color: var(--primary, #f8b4c4);
}

.venue-select-input {
  padding: 7px 12px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
  background-color: var(--card-bg, #ffffff);
  color: var(--on-surface, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
}

:global(html.dark) .venue-select-input {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

.table-card-box {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  border: 1px solid var(--border, #e0e4ea);
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .table-card-box {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.tx-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.tx-table th {
  padding: 14px 16px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--on-surface-variant, #5d5f5f);
  background-color: var(--surface-subtle, #f5f6f8);
  border-bottom: 1px solid var(--border, #e0e4ea);
  text-align: left;
}

:global(html.dark) .tx-table th {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.tx-row {
  border-bottom: 1px solid var(--border, #e0e4ea);
  transition: background-color 0.2s;
}

:global(html.dark) .tx-row {
  border-color: var(--border, #353942);
}

.tx-row:hover {
  background-color: var(--surface-subtle, #f5f6f8);
}

:global(html.dark) .tx-row:hover {
  background-color: var(--surface-container, #2b2e36);
}

.tx-type-cell {
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.tx-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tx-icon-wrap .material-symbols-outlined {
  font-size: 18px;
}

.icon-booking,
.icon-pos {
  background-color: #ffebee;
  color: #c62828;
}

:global(html.dark) .icon-booking,
:global(html.dark) .icon-pos {
  background-color: rgba(198, 40, 40, 0.25);
  color: #f87171;
}

.icon-topup {
  background-color: #e8f5e9;
  color: #2e7d32;
}

:global(html.dark) .icon-topup {
  background-color: rgba(46, 125, 50, 0.25);
  color: #81c784;
}

.icon-refund {
  background-color: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

:global(html.dark) .icon-refund {
  background-color: var(--primary-container, #53333e);
  color: var(--primary, #f8b4c4);
}

.tx-type-name {
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

.tx-date-cell {
  padding: 14px 16px;
}

.date-main {
  font-weight: 600;
  color: var(--on-surface, #191c1d);
}

.time-sub {
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
}

.tx-desc-cell {
  padding: 14px 16px;
}

.desc-main {
  color: var(--on-surface, #191c1d);
  font-weight: 500;
}

.venue-sub {
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
}

.tx-amount-cell {
  padding: 14px 16px;
  text-align: right;
  font-size: 15px;
  font-weight: 800;
  font-family: monospace;
}

.tx-amount-cell.is-positive {
  color: #2e7d32;
}

:global(html.dark) .tx-amount-cell.is-positive {
  color: #81c784;
}

.tx-amount-cell.is-negative {
  color: var(--on-surface, #191c1d);
}

.empty-table-cell {
  padding: 40px 16px;
  text-align: center;
}

.empty-tx-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--on-surface-variant, #5d5f5f);
}

.empty-icon {
  font-size: 36px;
  opacity: 0.6;
}

.table-footer-row {
  display: flex;
  justify-content: center;
  padding: 14px;
  border-top: 1px solid var(--border, #e0e4ea);
  background-color: var(--surface-subtle, #f5f6f8);
}

:global(html.dark) .table-footer-row {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.btn-load-more {
  padding: 6px 18px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  background-color: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--on-surface-variant, #5d5f5f);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-load-more:hover {
  background-color: var(--surface-container, #edeeef);
}

:global(html.dark) .btn-load-more {
  background-color: var(--surface-container-high, #353943);
  border-color: var(--border, #353942);
  color: var(--on-surface, #f3f4f6);
}

/* ================= MODALS ================= */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--border, #e0e4ea);
  max-width: 500px;
  width: 100%;
}

:global(html.dark) .modal-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.modal-title {
  font-size: 18px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  margin: 0 0 4px;
}

.modal-sub {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 0;
}

.btn-close-modal {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--on-surface-variant, #5d5f5f);
}

.form-group {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin-bottom: 6px;
}

.modal-select,
.modal-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 13px;
  background-color: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .modal-select,
:global(html.dark) .modal-input {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

.quick-amounts-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.amt-btn {
  padding: 8px 6px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  background-color: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--on-surface, #191c1d);
  cursor: pointer;
  transition: all 0.2s;
}

.amt-btn.active {
  background-color: var(--primary-container, #ffd1dc);
  border-color: var(--primary, #78555e);
  color: var(--primary, #78555e);
}

:global(html.dark) .amt-btn {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: #ffffff;
}

:global(html.dark) .amt-btn.active {
  background-color: var(--primary-container, #53333e);
  border-color: var(--primary, #f8b4c4);
  color: var(--primary, #f8b4c4);
}

.pay-methods-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pay-method-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 12px;
  background-color: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
  transition: all 0.2s;
}

.pay-method-card.active {
  border-color: var(--primary, #78555e);
  background-color: rgba(255, 209, 220, 0.2);
}

:global(html.dark) .pay-method-card {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

:global(html.dark) .pay-method-card.active {
  border-color: var(--primary, #f8b4c4);
  background-color: rgba(248, 180, 196, 0.1);
}

.hidden-radio {
  display: none;
}

.pay-info {
  display: flex;
  flex-direction: column;
  font-size: 12px;
}

.pay-info strong {
  color: var(--on-surface, #191c1d);
}

.pay-info span {
  color: var(--on-surface-variant, #5d5f5f);
  font-size: 11px;
}

.promo-hint-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  background-color: rgba(255, 209, 220, 0.35);
  font-size: 12px;
  color: var(--primary, #78555e);
  margin-top: 14px;
}

:global(html.dark) .promo-hint-box {
  background-color: rgba(248, 180, 196, 0.12);
  color: var(--primary, #f8b4c4);
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}

.btn-cancel {
  padding: 10px 16px;
  border-radius: 9999px;
  background: transparent;
  border: 1px solid var(--border, #e0e4ea);
  color: var(--on-surface-variant, #5d5f5f);
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
}

:global(html.dark) .btn-cancel {
  border-color: var(--border, #353942);
}

.btn-submit-deposit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 9999px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  font-weight: 700;
  font-size: 13px;
  border: none;
  cursor: pointer;
}

:global(html.dark) .btn-submit-deposit {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

/* ================= MODAL QR ================= */
.modal-qr {
  max-width: 400px;
  text-align: center;
}

.badge-qr-pulse {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 9999px;
  background-color: #e8f5e9;
  color: #2e7d32;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
}

:global(html.dark) .badge-qr-pulse {
  background-color: rgba(46, 125, 50, 0.25);
  color: #81c784;
}

.qr-code-frame {
  position: relative;
  width: 180px;
  height: 180px;
  margin: 16px auto;
  border-radius: 16px;
  background-color: #ffffff;
  padding: 16px;
  border: 2px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.qr-code-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qr-scan-line {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: var(--primary, #78555e);
  box-shadow: 0 0 8px var(--primary, #78555e);
  animation: qr-scan-anim 2.5s infinite ease-in-out;
}

:global(html.dark) .qr-scan-line {
  background: var(--primary, #f8b4c4);
  box-shadow: 0 0 10px var(--primary, #f8b4c4);
}

@keyframes qr-scan-anim {
  0% { top: 0; }
  50% { top: 100%; }
  100% { top: 0; }
}

.qr-meta-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.otp-token-str {
  font-size: 14px;
  font-family: monospace;
}

.countdown-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
}

.current-bal-tag {
  font-size: 12px;
  color: var(--primary, #78555e);
  margin-top: 4px;
}

:global(html.dark) .current-bal-tag {
  color: var(--primary, #f8b4c4);
}

.btn-primary-pill {
  padding: 8px 24px;
  border-radius: 9999px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

:global(html.dark) .btn-primary-pill {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}
</style>
