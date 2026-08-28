<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { formatCurrency, formatTime } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'

interface Member {
  id: number
  name: string
  phone: string
  role: 'host' | 'member'
  amount: number
  paid: boolean
  method?: string
  paidAt?: string
  avatar: string
  timeMinutes: number
  customPercent?: number
}

interface ItemDetail {
  id: string
  name: string
  category: 'court' | 'service' | 'drink'
  qty: number
  unitPrice: number
  total: number
}

interface TransactionLog {
  id: string
  time: string
  memberName: string
  amount: number
  method: string
  avatar: string
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const bookingStore = useBookingStore()

// --- State & Booking Details ---
const bookingCode = ref(String(route.params.code || 'AC-88924'))
const courtName = ref('Sân 3 - VIP BWF Taraflex')
const venueName = ref('Aura Court Central - Quận 1')
const bookingDate = ref('Thứ Năm, 28/08/2026')
const bookingTime = ref('19:00 — 21:00 (2 tiếng)')
const bankName = ref('TPBank')
const accountNumber = ref('83403032005')
const accountName = ref('AURA COURT')

const items = ref<ItemDetail[]>([
  { id: '1', name: 'Thuê sân 3 (VIP Taraflex - 2 giờ)', category: 'court', qty: 2, unitPrice: 220000, total: 440000 },
  { id: '2', name: '2 Hộp cầu lông Yonex Aerosensa 50', category: 'service', qty: 2, unitPrice: 60000, total: 120000 },
  { id: '3', name: '4 Chai nước ion Pocari Sweat 500ml', category: 'drink', qty: 4, unitPrice: 10000, total: 40000 },
])

const totalBill = computed(() => items.value.reduce((sum, item) => sum + item.total, 0))

// --- Split Mode ---
type SplitMode = 'equal' | 'itemized' | 'custom' | 'time'
import { getAvatarUrl } from '@/lib/avatar'

const splitMode = ref<SplitMode>('equal')

const defaultAvatarUrl = computed(() => {
  return getAvatarUrl(auth.user?.name, auth.user?.avatar_url)
})

// --- Members State ---
const members = ref<Member[]>([
  {
    id: 1,
    name: auth.user?.name || 'Aura Administrator',
    phone: auth.user?.phone || '0988 888 888',
    role: 'host',
    amount: 150000,
    paid: true,
    method: 'Aura Pay',
    paidAt: '10:30:15',
    avatar: defaultAvatarUrl.value,
    timeMinutes: 120,
    customPercent: 25,
  },
  {
    id: 2,
    name: 'Trần Quốc Bảo',
    phone: '0912 345 678',
    role: 'member',
    amount: 150000,
    paid: true,
    method: 'VietQR',
    paidAt: '10:38:42',
    avatar: getAvatarUrl('Trần Quốc Bảo'),
    timeMinutes: 120,
    customPercent: 25,
  },
  {
    id: 3,
    name: 'Lê Minh Triết',
    phone: '0933 789 012',
    role: 'member',
    amount: 150000,
    paid: true,
    method: 'MoMo',
    paidAt: '10:41:09',
    avatar: getAvatarUrl('Lê Minh Triết'),
    timeMinutes: 120,
    customPercent: 25,
  },
  {
    id: 4,
    name: 'Phạm Thu Hà',
    phone: '0977 456 123',
    role: 'member',
    amount: 150000,
    paid: false,
    method: '',
    avatar: getAvatarUrl('Phạm Thu Hà'),
    timeMinutes: 120,
    customPercent: 25,
  },
])

// --- Realtime Live Feed ---
const liveTransactions = ref<TransactionLog[]>([
  {
    id: 'tx-1',
    time: '10:41',
    memberName: 'Lê Minh Triết',
    amount: 150000,
    method: 'MoMo',
    avatar: getAvatarUrl('Lê Minh Triết'),
  },
  {
    id: 'tx-2',
    time: '10:38',
    memberName: 'Trần Quốc Bảo',
    amount: 150000,
    method: 'VietQR (MB Bank)',
    avatar: getAvatarUrl('Trần Quốc Bảo'),
  },
  {
    id: 'tx-3',
    time: '10:30',
    memberName: auth.user?.name ? `${auth.user.name} (Host)` : 'Aura Administrator (Host)',
    amount: 150000,
    method: 'Aura Pay Ví',
    avatar: defaultAvatarUrl.value,
  },
])

// --- Countdown Timer (14 mins 28 secs) ---
const remainingSeconds = ref(868)
let timerInterval: number | undefined

const formattedCountdown = computed(() => {
  const mins = Math.floor(remainingSeconds.value / 60)
  const secs = remainingSeconds.value % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const countdownPercent = computed(() => {
  const total = 900 // 15 mins base
  return Math.max(0, Math.min(100, (remainingSeconds.value / total) * 100))
})

// --- Host Guarantee Toggle ---
const hostGuarantee = ref(true)

// --- Recalculate Split Amounts based on Mode ---
function recalculateAmounts() {
  const total = totalBill.value
  const count = members.value.length
  if (count === 0) return

  if (splitMode.value === 'equal') {
    const baseShare = Math.floor(total / count)
    const remainder = total - baseShare * count
    members.value.forEach((m, idx) => {
      m.amount = baseShare + (idx === 0 ? remainder : 0)
    })
  } else if (splitMode.value === 'itemized') {
    // Host covers court fee (440,000), items (160,000) split equally among all
    const courtFee = items.value.find((i) => i.category === 'court')?.total || 0
    const servicesFee = total - courtFee
    const perMemberService = Math.floor(servicesFee / count)
    const serviceRemainder = servicesFee - perMemberService * count

    members.value.forEach((m, idx) => {
      if (m.role === 'host') {
        m.amount = courtFee + perMemberService + serviceRemainder
      } else {
        m.amount = perMemberService
      }
    })
  } else if (splitMode.value === 'time') {
    const totalMinutes = members.value.reduce((sum, m) => sum + (m.timeMinutes || 60), 0)
    if (totalMinutes > 0) {
      let allocated = 0
      members.value.forEach((m, idx) => {
        if (idx === count - 1) {
          m.amount = total - allocated
        } else {
          const share = Math.round((m.timeMinutes / totalMinutes) * total)
          m.amount = share
          allocated += share
        }
      })
    }
  } else if (splitMode.value === 'custom') {
    const currentSum = members.value.reduce((s, m) => s + (m.amount || 0), 0)
    if (currentSum === 0) {
      const baseShare = Math.floor(total / count)
      members.value.forEach((m) => (m.amount = baseShare))
    }
  }
}

watch(splitMode, () => {
  recalculateAmounts()
})

// --- Progress & Totals ---
const totalCollected = computed(() => {
  return members.value.filter((m) => m.paid).reduce((sum, m) => sum + m.amount, 0)
})

const paidCount = computed(() => members.value.filter((m) => m.paid).length)
const totalCount = computed(() => members.value.length)
const percentCollected = computed(() => {
  if (totalBill.value === 0) return 0
  return Math.min(100, Math.round((totalCollected.value / totalBill.value) * 100))
})
const remainingAmount = computed(() => Math.max(0, totalBill.value - totalCollected.value))
const isFullyPaid = computed(() => totalCollected.value >= totalBill.value)

watch([splitMode, remainingAmount], () => {
  if (!selectedMemberForQr.value) {
    generateQr()
  }
})

// --- QR Generation ---
const qrCodeDataUrl = ref('')
const selectedMemberForQr = ref<Member | null>(null)
const showQrModal = ref(false)

async function generateQr(member?: Member) {
  const amountToPay = member ? member.amount : (remainingAmount.value > 0 ? remainingAmount.value : totalBill.value)
  const memberSuffix = member ? ` M${member.id}` : ''
  const memo = `AURAPAY ${bookingCode.value}${memberSuffix}`

  // Standard VietQR URL via SePay / Napas 247 Gateway from backend .env
  const bank = bankName.value || 'TPBank'
  const acc = accountNumber.value || '83403032005'
  const name = accountName.value || 'AURA COURT'
  const vietQrUrl = `https://img.vietqr.io/image/${bank}-${acc}-compact2.png?amount=${amountToPay}&addInfo=${encodeURIComponent(memo)}&accountName=${encodeURIComponent(name)}`

  qrCodeDataUrl.value = vietQrUrl
}

function openMemberQr(member: Member) {
  selectedMemberForQr.value = member
  generateQr(member)
  showQrModal.value = true
}

function openGroupQr() {
  selectedMemberForQr.value = null
  generateQr()
  showQrModal.value = true
}

// --- Modals & Toasts ---
const toastMessage = ref('')
const showToast = ref(false)
let toastTimer: number | undefined

function triggerToast(msg: string) {
  toastMessage.value = msg
  showToast.value = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = window.setTimeout(() => {
    showToast.value = false
  }, 3500)
}

const dynamicShareUrl = computed(() => {
  if (typeof window !== 'undefined' && window.location?.origin) {
    return `${window.location.origin}/split-bill/${bookingCode.value}`
  }
  return `https://auracourt.vn/split-bill/${bookingCode.value}`
})

function copyShareLink() {
  const link = dynamicShareUrl.value
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(link)
  }
  triggerToast('✅ Đã sao chép liên kết chia tiền nhóm!')
}

function copyMemberLink(member: Member) {
  const link = `${dynamicShareUrl.value}?member=${member.id}`
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(link)
  }
  triggerToast(`📋 Đã sao chép link thanh toán riêng cho ${member.name}!`)
}

function copyMemoCode() {
  const memo = `AURAPAY ${bookingCode.value}`
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(memo)
  }
  triggerToast('✅ Đã sao chép nội dung chuyển khoản!')
}

function sendReminder(member: Member) {
  triggerToast(`🔔 Đã gửi thông báo nhắc thanh toán đến ${member.name} (${member.phone}) qua Zalo & SMS!`)
}

// --- Member Management ---
const showAddMemberModal = ref(false)
const newMemberName = ref('')
const newMemberPhone = ref('')
const newMemberMinutes = ref(120)

function addMember() {
  if (!newMemberName.value.trim()) return
  const newId = members.value.length ? Math.max(...members.value.map((m) => m.id)) + 1 : 1
  const assignedAvatar = getAvatarUrl(newMemberName.value.trim())
  members.value.push({
    id: newId,
    name: newMemberName.value.trim(),
    phone: newMemberPhone.value.trim() || '0900 000 000',
    role: 'member',
    amount: 0,
    paid: false,
    avatar: assignedAvatar,
    timeMinutes: newMemberMinutes.value || 120,
  })
  newMemberName.value = ''
  newMemberPhone.value = ''
  showAddMemberModal.value = false
  recalculateAmounts()
  triggerToast('🎉 Đã thêm thành viên mới vào danh sách chia tiền!')
}

function removeMember(id: number) {
  if (members.value.length <= 1) return
  members.value = members.value.filter((m) => m.id !== id)
  recalculateAmounts()
  triggerToast('🗑️ Đã xóa thành viên khỏi nhóm.')
}

function markAsPaid(member: Member, method: string = 'Tiền mặt') {
  member.paid = true
  member.method = method
  const now = new Date()
  const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  member.paidAt = timeStr

  liveTransactions.value.unshift({
    id: `tx-${Date.now()}`,
    time: timeStr,
    memberName: member.name,
    amount: member.amount,
    method: method,
    avatar: member.avatar,
  })

  triggerToast(`✨ Đã ghi nhận thanh toán ${formatCurrency(member.amount)} từ ${member.name}!`)

  if (isFullyPaid.value) {
    showCelebrationModal.value = true
  }
}

// --- Celebration & Receipt Modals ---
const showCelebrationModal = ref(false)
const showReceiptModal = ref(false)

function handleFinishBooking() {
  if (!isFullyPaid.value && !hostGuarantee.value) {
    triggerToast('⚠️ Nhóm chưa thu đủ tiền. Vui lòng hoàn tất hoặc bật bảo lãnh ứng trước.')
    return
  }
  showCelebrationModal.value = true
}

function closeCelebration() {
  showCelebrationModal.value = false
  router.push('/booking')
}

// --- Lifecycle ---
onMounted(async () => {
  if (route.params.code) {
    try {
      const res = await bookingStore.loadPayment(String(route.params.code))
      if (res?.booking) {
        const b = res.booking
        bookingCode.value = b.code
        if (b.court?.name) courtName.value = b.court.name
        if (b.court?.venue?.name) venueName.value = b.court.venue.name

        if (b.slots && b.slots.length > 0) {
          const firstSlot = b.slots[0]
          const lastSlot = b.slots[b.slots.length - 1]
          if (firstSlot?.starts_at) {
            const d = new Date(firstSlot.starts_at)
            bookingDate.value = d.toLocaleDateString('vi-VN', {
              weekday: 'long',
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
            })
            bookingTime.value = `${formatTime(firstSlot.starts_at)} — ${formatTime(lastSlot?.ends_at || firstSlot.starts_at)} (${b.slots.length} tiếng)`
          }

          const courtTotal = b.slots.reduce((sum, s) => sum + (s.unit_price || 0), 0)
          items.value = [
            {
              id: 'court-fee',
              name: `Thuê ${b.court?.name || 'sân'} (${b.slots.length} giờ)`,
              category: 'court',
              qty: b.slots.length,
              unitPrice: Math.round((courtTotal || b.total_amount) / (b.slots.length || 1)),
              total: courtTotal || b.total_amount,
            },
          ]
        }

        if (b.payment_expires_at) {
          const expTime = new Date(b.payment_expires_at).getTime()
          const diffSec = Math.floor((expTime - Date.now()) / 1000)
          if (diffSec > 0) {
            remainingSeconds.value = diffSec
          }
        }
      }

      if (res?.payment) {
        if (res.payment.bank) bankName.value = res.payment.bank
        if (res.payment.accountNumber) accountNumber.value = res.payment.accountNumber
      }
    } catch {
      // Keep fallback demo values
    }
  }

  recalculateAmounts()
  generateQr()

  timerInterval = window.setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--
    } else {
      if (timerInterval) clearInterval(timerInterval)
    }
  }, 1000)
})

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (toastTimer) clearTimeout(toastTimer)
})
</script>

<template>
  <div class="split-page">
    <!-- Top Breadcrumb & Actions Bar -->
    <div class="split-topbar">
      <div class="split-container">
        <div class="breadcrumb-row">
          <RouterLink to="/" class="bc-link">Trang chủ</RouterLink>
          <span class="bc-sep">/</span>
          <RouterLink to="/courts" class="bc-link">Đặt sân</RouterLink>
          <span class="bc-sep">/</span>
          <RouterLink :to="`/payment/${bookingCode}`" class="bc-link">Xác nhận đơn #{{ bookingCode }}</RouterLink>
          <span class="bc-sep">/</span>
          <span class="bc-current">Chia tiền nhóm</span>
        </div>
      </div>
    </div>

    <!-- Main Content Container -->
    <main class="split-main">
      <div class="split-container">
        <!-- Status & Urgency Header Card -->
        <section class="status-banner">
          <div class="banner-content">
            <div class="banner-left">
              <div class="status-pill" :class="{ 'is-complete': isFullyPaid }">
                <span class="pulse-dot"></span>
                {{ isFullyPaid ? 'Đã thu đủ 100% chi phí' : `Đang thu tiền nhóm (${paidCount}/${totalCount} đã trả)` }}
              </div>
              <h1 class="page-title">Chia Tiền & Quyết Toán Nhóm</h1>
              <p class="page-subtitle">
                Đơn đặt sân <strong>#{{ bookingCode }}</strong> tại {{ venueName }}
              </p>
            </div>

            <div class="banner-right">
              <div class="countdown-box">
                <div class="countdown-label">
                  <svg class="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                  <span>Hạn giữ sân còn lại</span>
                </div>
                <div class="countdown-digits">{{ formattedCountdown }}</div>
                <div class="countdown-bar-wrap">
                  <div class="countdown-bar-fill" :style="{ width: `${countdownPercent}%` }"></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Two Column Main Layout -->
        <div class="split-grid">
          <!-- LEFT COLUMN: Booking Breakdown & Split Mode & Member Tracker -->
          <div class="col-left">
            <!-- Booking Summary Card -->
            <div class="card aura-card summary-card">
              <div class="card-header">
                <div class="header-icon-wrap">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                </div>
                <div>
                  <h3 class="card-title">Chi Tiết Phiên Chơi & Dịch Vụ</h3>
                  <p class="card-sub">{{ courtName }} • {{ bookingDate }} • {{ bookingTime }}</p>
                </div>
              </div>

              <div class="bill-items-list">
                <div v-for="item in items" :key="item.id" class="bill-item">
                  <div class="item-meta">
                    <span class="item-name">{{ item.name }}</span>
                    <span class="item-qty">x{{ item.qty }}</span>
                  </div>
                  <div class="item-price">{{ formatCurrency(item.total) }}</div>
                </div>
              </div>

              <div class="bill-total-row">
                <span class="total-label">Tổng Chi Phí Cần Chia:</span>
                <span class="total-val">{{ formatCurrency(totalBill) }}</span>
              </div>
            </div>

            <!-- Split Modes Selection Card -->
            <div class="card aura-card split-modes-card">
              <div class="section-title-row">
                <h3 class="card-title">Phương Thức Chia Tiền</h3>
                <span class="mode-badge">Chế độ linh hoạt</span>
              </div>

              <div class="mode-tabs">
                <button
                  type="button"
                  class="mode-tab"
                  :class="{ active: splitMode === 'equal' }"
                  @click="splitMode = 'equal'"
                >
                  <span class="material-symbols-outlined tab-icon">balance</span>
                  <span class="tab-text">
                    <strong>Chia đều</strong>
                    <small>Mỗi người 1 phần bằng nhau</small>
                  </span>
                </button>

                <button
                  type="button"
                  class="mode-tab"
                  :class="{ active: splitMode === 'itemized' }"
                  @click="splitMode = 'itemized'"
                >
                  <span class="material-symbols-outlined tab-icon">sports_tennis</span>
                  <span class="tab-text">
                    <strong>Bao tiền sân</strong>
                    <small>Host trả sân, chia tiền nước/cầu</small>
                  </span>
                </button>

                <button
                  type="button"
                  class="mode-tab"
                  :class="{ active: splitMode === 'time' }"
                  @click="splitMode = 'time'"
                >
                  <span class="material-symbols-outlined tab-icon">schedule</span>
                  <span class="tab-text">
                    <strong>Theo giờ chơi</strong>
                    <small>Ai chơi nhiều tính nhiều</small>
                  </span>
                </button>

                <button
                  type="button"
                  class="mode-tab"
                  :class="{ active: splitMode === 'custom' }"
                  @click="splitMode = 'custom'"
                >
                  <span class="material-symbols-outlined tab-icon">edit_square</span>
                  <span class="tab-text">
                    <strong>Tự nhập số tiền</strong>
                    <small>Tùy chỉnh số tiền từng người</small>
                  </span>
                </button>
              </div>
            </div>

            <!-- Member Payment Tracker Card -->
            <div class="card aura-card members-card">
              <div class="members-header">
                <div>
                  <h3 class="card-title">Danh Sách Thành Viên & Tiến Độ Thu</h3>
                  <p class="card-sub">
                    Đã thu <strong>{{ formatCurrency(totalCollected) }}</strong> / {{ formatCurrency(totalBill) }} ({{ percentCollected }}%)
                  </p>
                </div>
                <button type="button" class="btn-add-member" @click="showAddMemberModal = true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                  </svg>
                  Thêm Bạn Bè
                </button>
              </div>

              <!-- Overall Progress Bar -->
              <div class="progress-container">
                <div class="progress-bar">
                  <div class="progress-fill" :style="{ width: `${percentCollected}%` }"></div>
                </div>
                <div class="progress-labels">
                  <span>{{ paidCount }}/{{ totalCount }} người đã trả</span>
                  <span>Còn thiếu: {{ formatCurrency(remainingAmount) }}</span>
                </div>
              </div>

              <!-- Members Table / List -->
              <div class="members-list">
                <div
                  v-for="member in members"
                  :key="member.id"
                  class="member-row"
                  :class="{ 'is-paid': member.paid }"
                >
                  <div class="member-left">
                    <div class="member-avatar-wrap">
                      <img :src="member.avatar" :alt="member.name" class="member-avatar" />
                      <span v-if="member.role === 'host'" class="host-star" title="Trưởng nhóm">
                        <span class="material-symbols-outlined icon-crown">workspace_premium</span>
                      </span>
                    </div>

                    <div class="member-info">
                      <div class="member-name-row">
                        <span class="member-name">{{ member.name }}</span>
                        <span v-if="member.role === 'host'" class="badge-host">Host</span>
                      </div>
                      <div class="member-sub-info">
                        <span>{{ member.phone }}</span>
                        <span v-if="splitMode === 'time'">• Chơi {{ member.timeMinutes }}p</span>
                      </div>
                    </div>
                  </div>

                  <!-- Amount Column (Editable in custom mode) -->
                  <div class="member-amount-col">
                    <div v-if="splitMode === 'custom'" class="custom-input-wrap">
                      <input
                        v-model.number="member.amount"
                        type="number"
                        step="10000"
                        class="input-custom-amount"
                      />
                      <span class="curr-unit">đ</span>
                    </div>
                    <div v-else class="amount-display">
                      <span class="amount-val">{{ formatCurrency(member.amount) }}</span>
                    </div>
                  </div>

                  <!-- Status Column -->
                  <div class="member-status-col">
                    <span v-if="member.paid" class="status-tag paid">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                      Đã nộp ({{ member.method }})
                    </span>
                    <span v-else class="status-tag pending">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                      </svg>
                      Chờ thanh toán
                    </span>
                  </div>

                  <!-- Actions Column -->
                  <div class="member-actions-col">
                    <template v-if="!member.paid">
                      <button
                        type="button"
                        class="btn-mark-cash"
                        title="Đánh dấu đã nhận tiền mặt"
                        @click="markAsPaid(member, 'Tiền mặt')"
                      >
                        <span class="material-symbols-outlined icon-cash">payments</span>
                        <span>Thu tiền mặt</span>
                      </button>

                      <button
                        type="button"
                        class="btn-action-icon"
                        title="Mã QR riêng"
                        @click="openMemberQr(member)"
                      >
                        <span class="material-symbols-outlined">qr_code_2</span>
                      </button>

                      <button
                        type="button"
                        class="btn-action-icon"
                        title="Gửi nhắc nợ Zalo/SMS"
                        @click="sendReminder(member)"
                      >
                        <span class="material-symbols-outlined">notifications_active</span>
                      </button>

                      <button
                        type="button"
                        class="btn-action-icon"
                        title="Sao chép link riêng"
                        @click="copyMemberLink(member)"
                      >
                        <span class="material-symbols-outlined">link</span>
                      </button>
                    </template>

                    <button
                      v-if="member.role !== 'host'"
                      type="button"
                      class="btn-action-icon delete"
                      title="Xóa thành viên"
                      @click="removeMember(member.id)"
                    >
                      <span class="material-symbols-outlined">close</span>
                    </button>
                    <div v-else class="btn-action-placeholder"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- RIGHT COLUMN: QR Code, Share links, Guarantee & Live Feed -->
          <div class="col-right">
            <!-- Dynamic QR Code Card -->
            <div class="card aura-card qr-card">
              <div class="card-header-compact">
                <h3 class="card-title">Mã VietQR Quyết Toán Nhanh</h3>
                <span class="badge-napas">Napas 247</span>
              </div>

              <div class="qr-preview-box">
                <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="VietQR Split Bill" class="qr-image" />
                <div class="qr-amount-pill">
                  <span>Số tiền:</span>
                  <strong>{{ formatCurrency(remainingAmount > 0 ? remainingAmount : totalBill) }}</strong>
                </div>
                <p class="qr-hint">
                  Quét bằng ứng dụng Ngân hàng (TPBank, Vietcombank, MB, Techcombank...) hoặc MoMo / Viettel Money
                </p>
                <div class="qr-memo-box">
                  <span class="memo-title">Nội dung chuyển khoản:</span>
                  <div class="memo-code-row">
                    <code>AURAPAY {{ bookingCode }}</code>
                    <button
                      type="button"
                      class="btn-copy-sm"
                      @click="copyMemoCode"
                    >
                      Copy
                    </button>
                  </div>
                </div>
              </div>

              <div class="qr-actions-row">
                <button type="button" class="btn-qr-download" @click="openGroupQr">
                  <span class="material-symbols-outlined">fullscreen</span>
                  Phóng to mã QR
                </button>
                <button type="button" class="btn-qr-share" @click="copyShareLink">
                  <span class="material-symbols-outlined">share</span>
                  Sao chép Link Nhóm
                </button>
              </div>
            </div>

            <!-- Share & Social Channels Card -->
            <div class="card aura-card share-card">
              <h3 class="card-title">Chia Sẻ Link Nhóm Đến Bạn Bè</h3>
              <p class="card-sub">Gửi vào nhóm chat Zalo, Messenger hoặc Telegram để mọi người cùng thanh toán.</p>

              <div class="share-input-box">
                <input
                  type="text"
                  readonly
                  :value="dynamicShareUrl"
                  class="share-link-input"
                />
                <button type="button" class="btn-copy-link" @click="copyShareLink">
                  Copy
                </button>
              </div>

              <div class="social-share-buttons">
                <button
                  type="button"
                  class="btn-social zalo"
                  @click="triggerToast('💬 Đang mở Zalo chia sẻ link...')"
                >
                  <span class="material-symbols-outlined social-icon">chat</span>
                  Zalo Chat
                </button>
                <button
                  type="button"
                  class="btn-social messenger"
                  @click="triggerToast('⚡ Đang mở Messenger chia sẻ link...')"
                >
                  <span class="material-symbols-outlined social-icon">forum</span>
                  Messenger
                </button>
                <button
                  type="button"
                  class="btn-social telegram"
                  @click="triggerToast('✈️ Đang mở Telegram chia sẻ link...')"
                >
                  <span class="material-symbols-outlined social-icon">send</span>
                  Telegram
                </button>
              </div>
            </div>

            <!-- Host Guarantee Toggle Card -->
            <div class="card aura-card guarantee-card">
              <div class="guarantee-row">
                <div class="guarantee-info">
                  <div class="guarantee-title">
                    <span class="material-symbols-outlined icon-shield">verified_user</span>
                    <strong>Bảo Lãnh Đặt Sân Tự Động</strong>
                  </div>
                  <p class="guarantee-desc">
                    Trưởng nhóm đồng ý ứng trước khoản còn thiếu qua Ví Aura Pay nếu hết 15 phút mà nhóm chưa thu đủ, giúp không bị hủy lịch sân.
                  </p>
                </div>
                <label class="toggle-switch">
                  <input v-model="hostGuarantee" type="checkbox" />
                  <span class="slider"></span>
                </label>
              </div>
            </div>

            <!-- Live Transaction Activity Log -->
            <div class="card aura-card feed-card">
              <div class="feed-header">
                <div class="feed-live-indicator">
                  <span class="live-dot"></span>
                  <h3 class="card-title">Nhật Ký Nộp Tiền Trực Tiếp</h3>
                </div>
                <span class="feed-count">{{ liveTransactions.length }} giao dịch</span>
              </div>

              <div class="feed-list">
                <div v-for="tx in liveTransactions" :key="tx.id" class="feed-item">
                  <img :src="tx.avatar" :alt="tx.memberName" class="feed-avatar" />
                  <div class="feed-details">
                    <div class="feed-line-top">
                      <strong class="feed-name">{{ tx.memberName }}</strong>
                      <span class="feed-amount">+{{ formatCurrency(tx.amount) }}</span>
                    </div>
                    <div class="feed-line-bottom">
                      <span class="feed-method">{{ tx.method }}</span>
                      <span class="feed-time">{{ tx.time }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Full-Width Sticky Bottom Action Footer -->
    <footer class="split-footer-bar">
      <div class="footer-container">
        <div class="footer-left">
          <div class="footer-summary-box">
            <span class="sum-label">Trạng Thái Quyết Toán:</span>
            <strong class="sum-amount">
              {{ formatCurrency(totalCollected) }} / {{ formatCurrency(totalBill) }}
            </strong>
            <span class="sum-tag" :class="{ success: isFullyPaid, warning: !isFullyPaid }">
              {{ isFullyPaid ? 'Đã thu đủ 100%' : `Còn thiếu ${formatCurrency(remainingAmount)}` }}
            </span>
          </div>
        </div>

        <div class="footer-right">
          <button type="button" class="btn-footer-secondary" @click="showReceiptModal = true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <polyline points="10 9 9 9 8 9" />
            </svg>
            Xem & Tải Biên Lai PDF
          </button>

          <button
            type="button"
            class="btn-footer-primary"
            :class="{ 'btn-ready': isFullyPaid || hostGuarantee }"
            @click="handleFinishBooking"
          >
            <span>{{ isFullyPaid ? 'Xác Nhận & Vào Sân Ngay' : 'Hoàn Tất & Đảm Bảo Giữ Sân' }}</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="5" y1="12" x2="19" y2="12" />
              <polyline points="12 5 19 12 12 19" />
            </svg>
          </button>
        </div>
      </div>
    </footer>

    <!-- Modal: Add New Member -->
    <div v-if="showAddMemberModal" class="modal-backdrop" @click.self="showAddMemberModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <h3 class="modal-title">Thêm Bạn Bè Vào Nhóm Chia Tiền</h3>
          <button type="button" class="btn-close-modal" @click="showAddMemberModal = false">✕</button>
        </div>

        <form class="modal-body" @submit.prevent="addMember">
          <div class="form-group">
            <label class="form-label">Họ và tên thành viên *</label>
            <input
              v-model="newMemberName"
              type="text"
              required
              placeholder="VD: Nguyễn Văn A"
              class="modal-input"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Số điện thoại / Zalo</label>
            <input
              v-model="newMemberPhone"
              type="tel"
              placeholder="VD: 0988 777 666"
              class="modal-input"
            />
          </div>

          <div v-if="splitMode === 'time'" class="form-group">
            <label class="form-label">Thời lượng tham gia chơi (phút)</label>
            <input
              v-model.number="newMemberMinutes"
              type="number"
              min="15"
              max="240"
              step="15"
              class="modal-input"
            />
          </div>

          <div class="modal-actions">
            <button type="button" class="btn-cancel" @click="showAddMemberModal = false">Hủy</button>
            <button type="submit" class="btn-confirm">Thêm Thành Viên</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Zoom / Member QR Code -->
    <div v-if="showQrModal" class="modal-backdrop" @click.self="showQrModal = false">
      <div class="modal-card qr-modal-card">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">
              {{ selectedMemberForQr ? `Mã QR Cho ${selectedMemberForQr.name}` : 'Mã QR Thanh Toán Nhóm' }}
            </h3>
            <p class="modal-sub">
              Số tiền:
              <strong>{{ formatCurrency(selectedMemberForQr ? selectedMemberForQr.amount : remainingAmount) }}</strong>
            </p>
          </div>
          <button type="button" class="btn-close-modal" @click="showQrModal = false">✕</button>
        </div>

        <div class="modal-qr-body">
          <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="VietQR Napas" class="modal-qr-img" />
          <div class="bank-details-box">
            <div class="bank-row">
              <span>Ngân hàng thụ hưởng:</span>
              <strong>TPBank (Tiên Phong)</strong>
            </div>
            <div class="bank-row">
              <span>Số tài khoản:</span>
              <strong>83403032005</strong>
            </div>
            <div class="bank-row">
              <span>Chủ tài khoản:</span>
              <strong>AURA COURT</strong>
            </div>
            <div class="bank-row">
              <span>Nội dung CK:</span>
              <strong class="highlight-code">AURAPAY {{ bookingCode }}{{ selectedMemberForQr ? ` M${selectedMemberForQr.id}` : '' }}</strong>
            </div>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-confirm w-full" @click="showQrModal = false">Đóng</button>
        </div>
      </div>
    </div>

    <!-- Modal: Receipt / Invoice Preview -->
    <div v-if="showReceiptModal" class="modal-backdrop" @click.self="showReceiptModal = false">
      <div class="modal-card receipt-modal-card">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">Biên Lai Quyết Toán Nhóm</h3>
            <p class="modal-sub">Mã giao dịch: #{{ bookingCode }}</p>
          </div>
          <button type="button" class="btn-close-modal" @click="showReceiptModal = false">✕</button>
        </div>

        <div class="receipt-content">
          <div class="receipt-brand">
            <h2>AURA COURT CLUB</h2>
            <p>Hệ thống sân cầu lông tiêu chuẩn thi đấu BWF</p>
          </div>
          <hr class="receipt-divider" />
          <div class="receipt-meta-grid">
            <div>
              <span class="lbl">Địa điểm:</span>
              <p class="val">{{ venueName }}</p>
            </div>
            <div>
              <span class="lbl">Sân:</span>
              <p class="val">{{ courtName }}</p>
            </div>
            <div>
              <span class="lbl">Thời gian:</span>
              <p class="val">{{ bookingDate }} ({{ bookingTime }})</p>
            </div>
            <div>
              <span class="lbl">Trưởng nhóm:</span>
              <p class="val">{{ members[0]?.name }}</p>
            </div>
          </div>

          <table class="receipt-table">
            <thead>
              <tr>
                <th>Khoản chi / Thành viên</th>
                <th class="text-center">Số tiền</th>
                <th class="text-right">Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in members" :key="m.id">
                <td>{{ m.name }} ({{ m.role === 'host' ? 'Host' : 'Thành viên' }})</td>
                <td class="text-center font-bold">{{ formatCurrency(m.amount) }}</td>
                <td class="text-right">
                  <span :class="m.paid ? 'tag-green' : 'tag-gray'">
                    {{ m.paid ? `Đã trả (${m.method})` : 'Chưa nộp' }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <th>Tổng Cộng:</th>
                <th colspan="2" class="text-right text-lg">{{ formatCurrency(totalBill) }}</th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="modal-actions">
          <button
            type="button"
            class="btn-cancel"
            @click="triggerToast('📄 Đang xuất file PDF biên lai...')"
          >
            In / Tải PDF
          </button>
          <button type="button" class="btn-confirm" @click="showReceiptModal = false">Đóng</button>
        </div>
      </div>
    </div>

    <!-- Modal: Celebration / All Complete -->
    <div v-if="showCelebrationModal" class="modal-backdrop" @click.self="closeCelebration">
      <div class="modal-card celebration-card">
        <div class="celebration-icon">🎉</div>
        <h2 class="celebration-title">Xác Nhận Đặt Sân Thành Công!</h2>
        <p class="celebration-desc">
          Nhóm của bạn đã hoàn tất quyết toán cho đơn <strong>#{{ bookingCode }}</strong>. Lịch sân đã được kích hoạt trên hệ thống cửa tự động và bảng điện tử sân.
        </p>

        <div class="checkin-pass-card">
          <div class="pass-header">
            <span>AURA CHECK-IN PASS</span>
            <strong>#{{ bookingCode }}</strong>
          </div>
          <div class="pass-body">
            <div class="pass-info">
              <span class="pass-court">{{ courtName }}</span>
              <span class="pass-time">{{ bookingDate }} • {{ bookingTime }}</span>
            </div>
            <div class="pass-code">
              <span>Mã mở khóa cửa sân:</span>
              <strong>889244</strong>
            </div>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-confirm w-full" @click="closeCelebration">
            Về Trang Đặt Sân & Xem Lịch
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <transition name="toast-fade">
      <div v-if="showToast" class="split-toast">
        {{ toastMessage }}
      </div>
    </transition>
  </div>
</template>

<style scoped>
/* ==========================================================================
   Aura Court Soft Minimalism - Group Split Bill Styling
   ========================================================================== */

.split-page {
  min-height: 100vh;
  background-color: var(--bg);
  color: var(--ink);
  padding-bottom: 120px;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.split-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Breadcrumb */
.split-topbar {
  padding: 16px 0;
  border-bottom: 1px solid var(--border);
  background: var(--surface);
}

.breadcrumb-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--muted);
}

.bc-link {
  color: var(--muted);
  text-decoration: none;
  transition: color 0.2s ease;
}

.bc-link:hover {
  color: var(--pink-dark);
}

.bc-sep {
  opacity: 0.5;
}

.bc-current {
  color: var(--ink);
  font-weight: 600;
}

/* Status Banner */
.status-banner {
  margin: 24px 0;
  padding: 24px 32px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.banner-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  background: var(--live-bg);
  color: var(--pink-dark);
  border: 1px solid var(--pink);
  margin-bottom: 10px;
}

.status-pill.is-complete {
  background: var(--success-bg);
  color: var(--success-text);
  border-color: #a7f3d0;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: currentColor;
  animation: pulse-ring 1.8s infinite;
}

@keyframes pulse-ring {
  0% { transform: scale(0.95); opacity: 1; }
  50% { transform: scale(1.3); opacity: 0.6; }
  100% { transform: scale(0.95); opacity: 1; }
}

.page-title {
  font-size: 26px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px 0;
  letter-spacing: -0.01em;
}

.page-subtitle {
  font-size: 14px;
  color: var(--muted);
  margin: 0;
}

.countdown-box {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 20px;
  min-width: 220px;
  text-align: center;
}

.countdown-label {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.icon-clock {
  width: 14px;
  height: 14px;
  color: var(--pink-dark);
}

.countdown-digits {
  font-size: 28px;
  font-weight: 800;
  color: var(--pink-dark);
  font-variant-numeric: tabular-nums;
  margin: 4px 0;
}

.countdown-bar-wrap {
  width: 100%;
  height: 4px;
  background: var(--border);
  border-radius: 9999px;
  overflow: hidden;
}

.countdown-bar-fill {
  height: 100%;
  background: var(--pink-dark);
  transition: width 1s linear;
}

/* Two-column layout */
.split-grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 24px;
  align-items: start;
}

@media (max-width: 960px) {
  .split-grid {
    grid-template-columns: 1fr;
  }
}

/* Common Card Styles */
.aura-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
}

.header-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--pink-light);
  color: var(--pink-dark);
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-icon-wrap svg {
  width: 20px;
  height: 20px;
}

.card-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.card-sub {
  font-size: 13px;
  color: var(--muted);
  margin: 2px 0 0 0;
}

/* Bill Breakdown */
.bill-items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 0;
  border-top: 1px dashed var(--border);
  border-bottom: 1px dashed var(--border);
}

.bill-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.item-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.item-qty {
  font-size: 12px;
  color: var(--muted);
  background: var(--surface-subtle);
  padding: 2px 6px;
  border-radius: 4px;
}

.item-price {
  font-weight: 600;
  color: var(--ink);
}

.bill-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 14px;
}

.total-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--muted);
}

.total-val {
  font-size: 20px;
  font-weight: 800;
  color: var(--pink-dark);
}

/* Split Modes */
.section-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.mode-badge {
  font-size: 11px;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.05em;
  padding: 3px 8px;
  background: var(--surface-subtle);
  border-radius: 6px;
  color: var(--muted);
}

.mode-tabs {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

@media (max-width: 600px) {
  .mode-tabs {
    grid-template-columns: 1fr;
  }
}

.mode-tab {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
}

.mode-tab:hover {
  border-color: var(--pink);
  transform: translateY(-1px);
}

.mode-tab.active {
  background: var(--surface);
  border: 2px solid var(--pink-dark);
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.1);
}

.tab-icon {
  font-size: 20px;
}

.tab-text strong {
  display: block;
  font-size: 14px;
  color: var(--ink);
}

.tab-text small {
  display: block;
  font-size: 11px;
  color: var(--muted);
  margin-top: 2px;
}

/* Members Card & Tracker */
.members-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}

.btn-add-member {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  background: var(--pink-light);
  color: var(--pink-dark);
  border: 1px solid var(--pink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-add-member:hover {
  background: var(--pink);
}

.btn-add-member svg {
  width: 14px;
  height: 14px;
}

.progress-container {
  margin-bottom: 20px;
}

.progress-bar {
  height: 8px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 9999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #34d399, #059669);
  transition: width 0.4s ease;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--muted);
  margin-top: 6px;
  font-weight: 500;
}

.members-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.member-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 18px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s ease;
}

.member-row.is-paid {
  background: var(--surface);
  border-color: var(--border);
}

.member-left {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 160px;
}

.member-avatar-wrap {
  position: relative;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}

.member-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--surface);
}

.host-star {
  position: absolute;
  top: -6px;
  right: -6px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface);
  border-radius: 50%;
  padding: 1px;
}

.host-star .icon-crown {
  font-size: 15px;
  color: #f59e0b;
}

.member-info {
  flex: 1;
  min-width: 120px;
}

.member-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.member-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
}

.badge-host {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  background: var(--pink-dark);
  color: #fff;
  padding: 1px 6px;
  border-radius: 4px;
}

.member-sub-info {
  font-size: 12px;
  color: var(--muted);
  margin-top: 2px;
}

.member-amount-col {
  text-align: right;
  min-width: 100px;
  padding: 0 8px;
}

.amount-val {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.custom-input-wrap {
  display: flex;
  align-items: center;
  gap: 4px;
}

.input-custom-amount {
  width: 90px;
  padding: 4px 8px;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--surface);
  color: var(--ink);
  text-align: right;
}

.curr-unit {
  font-size: 12px;
  color: var(--muted);
}

.member-status-col {
  min-width: 140px;
  display: flex;
  justify-content: center;
}

.status-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 8px;
  white-space: nowrap;
}

.status-tag.paid {
  background: var(--success-bg);
  color: var(--success-text);
}

.status-tag.paid svg {
  width: 13px;
  height: 13px;
}

.status-tag.pending {
  background: var(--warning-bg);
  color: var(--warning-text);
}

.status-tag.pending svg {
  width: 13px;
  height: 13px;
}

.member-actions-col {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  min-width: 220px;
}

.btn-action-icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--ink);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.btn-action-icon:hover {
  color: var(--pink-dark);
  border-color: var(--pink);
  background: var(--pink-light);
}

.btn-action-icon.delete:hover {
  color: #ef4444;
  border-color: #fca5a5;
  background: #fee2e2;
}

.btn-action-icon svg {
  width: 15px;
  height: 15px;
}

.btn-mark-cash {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-mark-cash .icon-cash {
  font-size: 16px;
  color: #10b981;
}

.btn-mark-cash:hover {
  background: var(--success-bg);
  color: var(--success-text);
  border-color: #a7f3d0;
}

.btn-action-placeholder {
  width: 34px;
  height: 34px;
}

/* RIGHT COLUMN: QR Card */
.card-header-compact {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.badge-napas {
  font-size: 11px;
  font-weight: 700;
  background: #1e3a8a;
  color: #fff;
  padding: 2px 8px;
  border-radius: 4px;
}

.qr-preview-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 16px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 16px;
}

.qr-image {
  width: 180px;
  height: 180px;
  border-radius: 8px;
  background: #fff;
  padding: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.qr-amount-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  font-size: 13px;
  color: var(--muted);
}

.qr-amount-pill strong {
  font-size: 16px;
  color: var(--pink-dark);
}

.qr-hint {
  font-size: 12px;
  color: var(--muted);
  margin: 6px 0 12px 0;
}

.qr-memo-box {
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 12px;
  text-align: left;
}

.memo-title {
  font-size: 11px;
  color: var(--muted);
  display: block;
}

.memo-code-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.memo-code-row code {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.btn-copy-sm {
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 600;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 4px;
  cursor: pointer;
}

.qr-actions-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.btn-qr-download,
.btn-qr-share {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-qr-download {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  color: var(--ink);
}

.btn-qr-download:hover {
  background: var(--soft);
}

.btn-qr-share {
  background: var(--pink-light);
  border: 1px solid var(--pink);
  color: var(--pink-dark);
}

.btn-qr-share:hover {
  background: var(--pink);
}

.btn-qr-download svg,
.btn-qr-share svg {
  width: 14px;
  height: 14px;
}

/* Share Card */
.share-input-box {
  display: flex;
  gap: 8px;
  margin: 14px 0 16px 0;
}

.share-link-input {
  flex: 1;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  font-size: 13px;
  color: var(--ink);
  font-family: inherit;
}

.btn-copy-link {
  padding: 0 18px;
  border-radius: 8px;
  background: var(--pink-dark);
  color: #fff;
  border: none;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s ease;
}

.btn-copy-link:hover {
  opacity: 0.9;
}

.social-share-buttons {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.btn-social {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 8px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-social .social-icon {
  font-size: 18px;
}

.btn-social.zalo .social-icon {
  color: #0068ff;
}

.btn-social.messenger .social-icon {
  color: #0084ff;
}

.btn-social.telegram .social-icon {
  color: #229ed9;
}

.btn-social:hover {
  transform: translateY(-1px);
  border-color: var(--pink);
}

/* Guarantee Card */
.guarantee-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.guarantee-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--ink);
}

.guarantee-title .icon-shield {
  font-size: 20px;
  color: var(--pink-dark);
}

.guarantee-desc {
  font-size: 12px;
  color: var(--muted);
  margin: 4px 0 0 0;
  line-height: 1.4;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
  flex-shrink: 0;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--soft);
  transition: 0.3s;
  border-radius: 26px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: var(--pink-dark);
}

input:checked + .slider:before {
  transform: translateX(22px);
}

/* Live Feed Card */
.feed-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.feed-live-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
}

.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #10b981;
  box-shadow: 0 0 8px #10b981;
}

.feed-count {
  font-size: 12px;
  color: var(--muted);
}

.feed-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.feed-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: var(--surface-subtle);
  border-radius: 10px;
  border: 1px solid var(--border);
}

.feed-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
}

.feed-details {
  flex: 1;
}

.feed-line-top {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.feed-name {
  color: var(--ink);
}

.feed-amount {
  font-weight: 700;
  color: #059669;
}

.feed-line-bottom {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: var(--muted);
  margin-top: 2px;
}

/* Sticky Footer Bar */
.split-footer-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  width: 100%;
  background: var(--topbar-bg);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-top: 1px solid var(--border);
  padding: 14px 0;
  z-index: 90;
  box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.12);
}

.footer-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  box-sizing: border-box;
}

@media (max-width: 768px) {
  .footer-container {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .footer-right {
    justify-content: stretch;
  }
  .btn-footer-primary,
  .btn-footer-secondary {
    flex: 1;
    justify-content: center;
  }
}

.footer-summary-box {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sum-label {
  font-size: 13px;
  color: var(--muted);
}

.sum-amount {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.sum-tag {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 6px;
}

.sum-tag.success {
  background: var(--success-bg);
  color: var(--success-text);
}

.sum-tag.warning {
  background: var(--warning-bg);
  color: var(--warning-text);
}

.footer-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-footer-secondary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-footer-secondary:hover {
  background: var(--soft);
}

.btn-footer-secondary svg {
  width: 15px;
  height: 15px;
}

.btn-footer-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 10px;
  background: var(--pink-dark);
  color: #fff;
  border: none;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
}

.btn-footer-primary:hover {
  opacity: 0.95;
  transform: translateY(-1px);
}

.btn-footer-primary svg {
  width: 16px;
  height: 16px;
}

/* Modals Common */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 20px;
}

.modal-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  animation: modal-pop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modal-pop {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}

.modal-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.modal-sub {
  font-size: 13px;
  color: var(--muted);
  margin: 2px 0 0 0;
}

.btn-close-modal {
  background: none;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
}

.modal-body {
  padding: 20px 24px;
}

.form-group {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 6px;
}

.modal-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  font-size: 14px;
  color: var(--ink);
  box-sizing: border-box;
}

.modal-input:focus {
  outline: none;
  border-color: var(--pink-dark);
  background: var(--surface);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  background: var(--surface-subtle);
}

.btn-cancel {
  padding: 10px 16px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
}

.btn-confirm {
  padding: 10px 20px;
  border-radius: 8px;
  background: var(--pink-dark);
  color: #fff;
  border: none;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.w-full {
  width: 100%;
}

/* Modal QR Body */
.qr-modal-card {
  max-width: 440px;
}

.modal-qr-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
}

.modal-qr-img {
  width: 220px;
  height: 220px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: #fff;
  padding: 10px;
  margin-bottom: 16px;
}

.bank-details-box {
  width: 100%;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px 16px;
}

.bank-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  padding: 4px 0;
}

.bank-row span {
  color: var(--muted);
}

.bank-row strong {
  color: var(--ink);
}

.highlight-code {
  color: var(--pink-dark) !important;
  font-family: monospace;
}

/* Receipt Modal */
.receipt-modal-card {
  max-width: 580px;
}

.receipt-content {
  padding: 24px;
}

.receipt-brand {
  text-align: center;
  margin-bottom: 16px;
}

.receipt-brand h2 {
  font-size: 20px;
  font-weight: 800;
  color: var(--pink-dark);
  margin: 0;
}

.receipt-brand p {
  font-size: 12px;
  color: var(--muted);
  margin: 4px 0 0 0;
}

.receipt-divider {
  border: 0;
  border-top: 1px dashed var(--border);
  margin: 16px 0;
}

.receipt-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.receipt-meta-grid .lbl {
  font-size: 12px;
  color: var(--muted);
}

.receipt-meta-grid .val {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  margin: 2px 0 0 0;
}

.receipt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.receipt-table th,
.receipt-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
}

.receipt-table th {
  background: var(--surface-subtle);
  color: var(--muted);
  font-weight: 600;
}

.tag-green {
  color: #059669;
  font-weight: 600;
}

.tag-gray {
  color: var(--muted);
}

/* Celebration Modal */
.celebration-card {
  text-align: center;
  padding: 36px 28px;
}

.celebration-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.celebration-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
  margin: 0 0 8px 0;
}

.celebration-desc {
  font-size: 14px;
  color: var(--muted);
  line-height: 1.5;
  margin: 0 0 24px 0;
}

.checkin-pass-card {
  background: var(--surface-subtle);
  border: 2px dashed var(--pink-dark);
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 24px;
  text-align: left;
}

.pass-header {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 700;
  color: var(--pink-dark);
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

.pass-court {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
  display: block;
}

.pass-time {
  font-size: 13px;
  color: var(--muted);
  display: block;
  margin-top: 2px;
}

.pass-code {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pass-code span {
  font-size: 13px;
  color: var(--muted);
}

.pass-code strong {
  font-size: 18px;
  letter-spacing: 2px;
  color: var(--pink-dark);
}

/* Toast */
.split-toast {
  position: fixed;
  bottom: 90px;
  left: 50%;
  transform: translateX(-50%);
  background: #191c1d;
  color: #fff;
  padding: 12px 24px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  z-index: 200;
  pointer-events: none;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.3s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translate(-50%, 10px);
}

/* Dark Mode Overrides */
html.dark .btn-confirm,
html.dark .btn-footer-primary,
html.dark .btn-copy-link {
  background: var(--pink-dark);
  color: #1e1317;
}

html.dark .btn-add-member,
html.dark .btn-qr-share {
  background: rgba(248, 180, 196, 0.15);
  color: var(--pink-dark);
  border-color: rgba(248, 180, 196, 0.3);
}

html.dark .mode-tab.active {
  background: var(--surface);
  border: 2px solid var(--pink-dark);
  box-shadow: 0 4px 14px rgba(248, 180, 196, 0.15);
}

html.dark .checkin-pass-card {
  background: var(--surface-subtle);
  border-color: var(--pink-dark);
}

html.dark .split-toast {
  background: #f3f4f6;
  color: #191c1d;
}
</style>
