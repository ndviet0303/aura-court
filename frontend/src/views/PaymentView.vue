<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/lib/api'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatTime } from '@/i18n'
import AuraLoading from '@/components/AuraLoading.vue'
import type { AvailabilitySlot, Booking, Court } from '@/types'

const { t, locale } = useI18n()
const store = useBookingStore()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

// State
const status = ref('pending_payment')
const error = ref('')
const copyNotice = ref('')
const selectedMethod = ref<'qr' | 'card'>('qr')
const showSuccessModal = ref(false)

// Coupon / Promo Code State
const couponCode = ref('')
const couponLoading = ref(false)
const couponError = ref('')
const couponSuccess = ref('')

// Card mock inputs (Stripe)
const cardNumber = ref('')
const cardExpiry = ref('')
const cardCvc = ref('')
const cardName = ref('')
const isCardProcessing = ref(false)

let timer: number | undefined
let countdownTimer: number | undefined
const remainingSeconds = ref(900) // 15 mins default

const payment = computed(() => store.payment)
const booking = computed<Booking | null>(() => store.current)

const court = computed<Court | null>(() => {
  if (booking.value?.court) return booking.value.court
  if (store.selectedCourt) {
    return store.courts.find((c: Court) => c.id === store.selectedCourt) || null
  }
  return null
})

const playerDetails = computed(() => ({
  name: auth.user?.name || 'Alexander Mitchell',
  email: auth.user?.email || 'a.mitchell@aura.com',
  phone: auth.user?.phone || '+84 (0) 90 123 4567',
}))

// Formatted Date and Times
const formattedBookingDate = computed(() => {
  if (booking.value?.slots && booking.value.slots.length > 0) {
    const firstSlot = booking.value.slots[0]
    if (firstSlot?.starts_at) {
      const d = new Date(firstSlot.starts_at)
      return d.toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      })
    }
  }
  if (store.selectedSlots.length > 0) {
    const firstSlot = store.selectedSlots[0]
    if (firstSlot?.startsAt) {
      const d = new Date(firstSlot.startsAt)
      return d.toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      })
    }
  }
  return new Date().toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
})

const formattedTimeRange = computed(() => {
  const slots = booking.value?.slots?.map((s) => ({ startsAt: s.starts_at, endsAt: s.ends_at })) || store.selectedSlots
  if (!slots.length) return '18:00 — 20:00 (2h)'
  const sorted = [...slots].sort((a, b) => a.startsAt.localeCompare(b.startsAt))
  const start = formatTime(sorted[0]?.startsAt || '')
  const end = formatTime(sorted[sorted.length - 1]?.endsAt || '')
  const count = slots.length
  return `${start} — ${end} (${count}h)`
})

const totalHours = computed(() => {
  const slots = booking.value?.slots || store.selectedSlots
  return slots.length || 1
})

const calculatedSubtotal = computed(() => {
  if (booking.value) {
    return (booking.value.total_amount || 0) + (booking.value.discount_amount || 0) + (booking.value.membership_discount_amount || 0)
  }
  if (store.selectedSlots.length) {
    return store.selectedSlots.reduce((sum: number, s: AvailabilitySlot) => sum + s.price, 0)
  }
  return payment.value?.amount || 0
})

const appliedCoupon = computed(() => booking.value?.coupon || null)
const couponDiscount = computed(() => booking.value?.discount_amount || 0)
const membershipDiscount = computed(() => booking.value?.membership_discount_amount || 0)

const totalDiscount = computed(() => {
  if (booking.value) {
    return (booking.value.discount_amount || 0) + (booking.value.membership_discount_amount || 0)
  }
  return 0
})

const finalAmount = computed(() => {
  if (payment.value?.amount) return payment.value.amount
  if (booking.value?.total_amount) return booking.value.total_amount
  return calculatedSubtotal.value - totalDiscount.value
})

const entryCode = computed(() => {
  const code = booking.value?.code || payment.value?.code || '99210'
  const digits = code.replace(/\D/g, '')
  if (digits.length >= 4) return digits.slice(-4).split('').join(' ')
  return '8 4 2 1'
})

const formattedCountdown = computed(() => {
  const minutes = Math.floor(remainingSeconds.value / 60)
  const seconds = remainingSeconds.value % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
})

// Format card number with spaces every 4 digits
function formatCardNumber(e: Event) {
  const target = e.target as HTMLInputElement
  const raw = target.value.replace(/\D/g, '').slice(0, 16)
  cardNumber.value = raw.replace(/(\d{4})(?=\d)/g, '$1 ')
}

// Format expiry MM/YY
function formatExpiry(e: Event) {
  const target = e.target as HTMLInputElement
  const raw = target.value.replace(/\D/g, '').slice(0, 4)
  if (raw.length >= 3) {
    cardExpiry.value = `${raw.slice(0, 2)}/${raw.slice(2)}`
  } else {
    cardExpiry.value = raw
  }
}

// Copy helpers
async function copyToClipboard(text: string, label: string) {
  try {
    await navigator.clipboard.writeText(text)
    copyNotice.value = `${t('payment.copied')} (${label})`
    setTimeout(() => {
      copyNotice.value = ''
    }, 2500)
  } catch {
    copyNotice.value = t('payment.copySuccess')
  }
}

// Coupon Handlers
async function applyCoupon() {
  if (!couponCode.value.trim()) return
  couponLoading.value = true
  couponError.value = ''
  couponSuccess.value = ''
  error.value = ''
  try {
    await store.applyCoupon(couponCode.value.trim())
    couponSuccess.value = t('payment.couponAppliedSuccess')
    couponCode.value = ''
    setTimeout(() => {
      couponSuccess.value = ''
    }, 4000)
  } catch (cause: unknown) {
    couponError.value = errorMessage(cause, t('common.error'))
  } finally {
    couponLoading.value = false
  }
}

async function removeCoupon() {
  couponLoading.value = true
  couponError.value = ''
  couponSuccess.value = ''
  error.value = ''
  try {
    await store.applyCoupon('')
    couponCode.value = ''
  } catch (cause: unknown) {
    couponError.value = errorMessage(cause, t('common.error'))
  } finally {
    couponLoading.value = false
  }
}

async function handleCardPay() {
  isCardProcessing.value = true
  error.value = ''
  try {
    const result = await store.stripePay({
      cardNumber: cardNumber.value,
      cardExpiry: cardExpiry.value,
      cardCvc: cardCvc.value,
      cardName: cardName.value,
    })
    status.value = result.status
    if (result.status === 'confirmed') {
      showSuccessModal.value = true
      if (timer) window.clearInterval(timer)
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    isCardProcessing.value = false
  }
}

async function poll() {
  try {
    const result = await store.refreshPaymentStatus()
    if (result) {
      status.value = result.status
      if (result.status === 'confirmed') {
        showSuccessModal.value = true
        if (timer) window.clearInterval(timer)
      } else if (['expired', 'cancelled', 'refunded'].includes(result.status) && timer) {
        window.clearInterval(timer)
      }
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

function updateCountdown() {
  if (remainingSeconds.value > 0) {
    remainingSeconds.value--
  } else if (timer) {
    window.clearInterval(timer)
    status.value = 'expired'
  }
}

onMounted(async () => {
  try {
    if (route.params.code) {
      const result = await store.loadPayment(String(route.params.code))
      status.value = result.status
      if (result.status === 'confirmed') {
        showSuccessModal.value = true
      }
    } else {
      store.restorePayment()
    }

    if (store.courts.length === 0) {
      await store.loadCourts()
    }

    await poll()

    if (status.value === 'pending_payment') {
      timer = window.setInterval(poll, 4000)
      countdownTimer = window.setInterval(updateCountdown, 1000)
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
})

onUnmounted(() => {
  if (timer) window.clearInterval(timer)
  if (countdownTimer) window.clearInterval(countdownTimer)
})
</script>

<template>
  <div class="payment-checkout-page">
    <!-- Ambient Background Lighting -->
    <div class="ambient-backdrop" aria-hidden="true">
      <div class="ambient-orb ambient-orb-1"></div>
      <div class="ambient-orb ambient-orb-2"></div>
    </div>

    <div class="checkout-container">
      <!-- Breadcrumb Stepper -->
      <nav class="checkout-stepper" aria-label="Progress">
        <RouterLink :to="court?.slug ? `/courts/${court.slug}/schedule` : '/courts'" class="step-item completed">
          <span class="step-num">01</span>
          <span class="step-label">{{ t('payment.stepSelection') }}</span>
        </RouterLink>
        <span class="material-symbols-outlined step-divider">chevron_right</span>
        <div class="step-item active">
          <span class="step-num">02</span>
          <span class="step-label">{{ t('payment.stepCheckout') }}</span>
        </div>
        <span class="material-symbols-outlined step-divider">chevron_right</span>
        <div class="step-item" :class="{ completed: status === 'confirmed' }">
          <span class="step-num">03</span>
          <span class="step-label">{{ t('payment.stepConfirmation') }}</span>
        </div>
      </nav>

      <!-- Main Content Layout -->
      <div v-if="payment || booking" class="checkout-grid">
        <!-- Left Column: Checkout & Payment Methods -->
        <section class="checkout-main-col">
          <header class="checkout-heading">
            <div class="eyebrow">{{ t('payment.eyebrow') }}</div>
            <h1 class="page-title">{{ t('payment.title') }}</h1>
          </header>

          <!-- Toast Copy Notification -->
          <Transition name="fade">
            <div v-if="copyNotice" class="copy-toast-banner" role="status">
              <span class="material-symbols-outlined icon-20">check_circle</span>
              <span>{{ copyNotice }}</span>
            </div>
          </Transition>

          <!-- Player Details Card -->
          <div class="panel player-details-panel">
            <div class="panel-header">
              <h2 class="panel-title">
                <span class="material-symbols-outlined title-icon">account_circle</span>
                <span>{{ t('payment.playerDetails') }}</span>
              </h2>
              <RouterLink to="/profile" class="edit-link">
                {{ t('payment.editProfile') }}
              </RouterLink>
            </div>

            <div class="player-fields-grid">
              <div class="field-item">
                <label class="field-label">{{ t('payment.fullName') }}</label>
                <div class="field-value">{{ playerDetails.name }}</div>
              </div>
              <div class="field-item">
                <label class="field-label">{{ t('payment.email') }}</label>
                <div class="field-value">{{ playerDetails.email }}</div>
              </div>
              <div class="field-item full-width">
                <label class="field-label">{{ t('payment.phone') }}</label>
                <div class="field-value">{{ playerDetails.phone }}</div>
              </div>
            </div>
          </div>

          <!-- Payment Methods Card -->
          <div class="panel payment-methods-panel">
            <div class="panel-header">
              <h2 class="panel-title">
                <span class="material-symbols-outlined title-icon">account_balance_wallet</span>
                <span>{{ t('payment.paymentMethod') }}</span>
              </h2>
              <span v-if="status === 'pending_payment'" class="timer-pill" :class="{ 'timer-urgent': remainingSeconds < 300 }">
                <span class="material-symbols-outlined icon-16">schedule</span>
                <span>{{ formattedCountdown }}</span>
              </span>
            </div>

            <!-- Method Selection Tabs: VietQR (SePay) & Visa/Master (Stripe) -->
            <div class="method-tabs-grid" role="radiogroup" aria-label="Payment methods">
              <!-- VietQR (SePay Gateway) -->
              <button
                type="button"
                role="radio"
                :aria-checked="selectedMethod === 'qr'"
                class="method-tab-btn"
                :class="{ active: selectedMethod === 'qr' }"
                @click="selectedMethod = 'qr'"
              >
                <div class="method-icon-wrap sepay-icon-wrap">
                  <span class="material-symbols-outlined">qr_code_2</span>
                </div>
                <div class="method-text">
                  <span class="method-name">{{ t('payment.methodQr') }}</span>
                  <span class="method-sub">SePay Gateway 24/7</span>
                </div>
                <span class="gateway-badge sepay-badge">SePay Auto</span>
              </button>

              <!-- Credit Card (Stripe Gateway) -->
              <button
                type="button"
                role="radio"
                :aria-checked="selectedMethod === 'card'"
                class="method-tab-btn"
                :class="{ active: selectedMethod === 'card' }"
                @click="selectedMethod = 'card'"
              >
                <div class="method-icon-wrap stripe-icon-wrap">
                  <span class="material-symbols-outlined">credit_card</span>
                </div>
                <div class="method-text">
                  <span class="method-name">{{ t('payment.methodCard') }}</span>
                  <span class="method-sub">Visa · Master · Stripe</span>
                </div>
                <span class="gateway-badge stripe-badge">Stripe PCI-DSS</span>
              </button>
            </div>

            <!-- Tab Content: VietQR via SePay -->
            <div v-if="selectedMethod === 'qr'" class="method-content-box qr-method-box">
              <div class="gateway-banner sepay-gateway-banner">
                <div class="gateway-info">
                  <span class="gateway-tag">SePay Automation</span>
                  <div class="qr-intro-title">{{ t('payment.scanQrTitle') }}</div>
                  <p class="qr-intro-desc">{{ t('payment.scanQrSub') }}</p>
                </div>
              </div>

              <div class="qr-transfer-layout">
                <!-- QR Code Display -->
                <div class="qr-frame-wrapper">
                  <div class="qr-card">
                    <img
                      v-if="payment?.qrUrl"
                      :src="payment.qrUrl"
                      :alt="t('payment.title')"
                      class="vietqr-image"
                    />
                    <div v-else class="qr-placeholder">
                      <span class="material-symbols-outlined icon-48">qr_code_2</span>
                      <span>{{ t('payment.autoChecking') }}</span>
                    </div>
                    <div class="qr-status-indicator" :class="{ confirmed: status === 'confirmed' }">
                      <span class="pulse-dot"></span>
                      <span>{{ status === 'confirmed' ? t('payment.confirmedDesc') : t('payment.autoChecking') }}</span>
                    </div>
                  </div>
                </div>

                <!-- Banking Details List with One-Click Copy -->
                <div class="bank-details-list">
                  <div class="bank-detail-item">
                    <div class="detail-label">{{ t('payment.bank') }}</div>
                    <div class="detail-row">
                      <span class="detail-value font-bold">{{ payment?.bank || 'MB Bank / Vietcombank' }}</span>
                      <button
                        type="button"
                        class="copy-chip-btn"
                        @click="copyToClipboard(payment?.bank || 'MB Bank', t('payment.bank'))"
                      >
                        <span class="material-symbols-outlined icon-16">content_copy</span>
                        <span>{{ t('payment.copy') }}</span>
                      </button>
                    </div>
                  </div>

                  <div class="bank-detail-item">
                    <div class="detail-label">{{ t('payment.accountNumber') }}</div>
                    <div class="detail-row">
                      <span class="detail-value highlight-num">{{ payment?.accountNumber || t('payment.notConfigured') }}</span>
                      <button
                        v-if="payment?.accountNumber"
                        type="button"
                        class="copy-chip-btn"
                        @click="copyToClipboard(payment.accountNumber, t('payment.accountNumber'))"
                      >
                        <span class="material-symbols-outlined icon-16">content_copy</span>
                        <span>{{ t('payment.copy') }}</span>
                      </button>
                    </div>
                  </div>

                  <div class="bank-detail-item">
                    <div class="detail-label">{{ t('payment.transferContent') }}</div>
                    <div class="detail-row">
                      <span class="detail-value code-badge">{{ payment?.code || booking?.code }}</span>
                      <button
                        v-if="payment?.code || booking?.code"
                        type="button"
                        class="copy-chip-btn"
                        @click="copyToClipboard(payment?.code || booking?.code || '', t('payment.transferContent'))"
                      >
                        <span class="material-symbols-outlined icon-16">content_copy</span>
                        <span>{{ t('payment.copy') }}</span>
                      </button>
                    </div>
                  </div>

                  <div class="bank-detail-item">
                    <div class="detail-label">{{ t('payment.amount') }}</div>
                    <div class="detail-row">
                      <span class="detail-value price-total">{{ formatCurrency(finalAmount) }}</span>
                      <button
                        type="button"
                        class="copy-chip-btn"
                        @click="copyToClipboard(String(finalAmount), t('payment.amount'))"
                      >
                        <span class="material-symbols-outlined icon-16">content_copy</span>
                        <span>{{ t('payment.copy') }}</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab Content: Credit Card via Stripe -->
            <div v-else-if="selectedMethod === 'card'" class="method-content-box card-method-box">
              <div class="gateway-banner stripe-gateway-banner">
                <div class="gateway-info">
                  <span class="gateway-tag stripe-tag">Stripe Gateway</span>
                  <div class="qr-intro-title">{{ t('payment.stripeSecured') }}</div>
                  <p class="qr-intro-desc">{{ t('payment.methodCardDesc') }}</p>
                </div>
              </div>

              <!-- Visual Card Preview -->
              <div class="credit-card-preview">
                <div class="card-top-row">
                  <div class="card-chip"></div>
                  <span class="stripe-card-logo">Stripe</span>
                </div>
                <div class="card-number-display">
                  {{ cardNumber || '•••• •••• •••• 4242' }}
                </div>
                <div class="card-bottom-row">
                  <div class="card-holder">
                    <span class="card-sub">{{ t('payment.cardName') }}</span>
                    <span class="card-name-val">{{ cardName || playerDetails.name }}</span>
                  </div>
                  <div class="card-expiry-box">
                    <span class="card-sub">{{ t('payment.cardExpiry') }}</span>
                    <span class="card-expiry-val">{{ cardExpiry || '12/28' }}</span>
                  </div>
                </div>
              </div>

              <!-- Stripe Card Inputs -->
              <form class="card-inputs-grid" @submit.prevent="handleCardPay">
                <div class="form-group full-width">
                  <label class="field-label">{{ t('payment.cardNumber') }}</label>
                  <div class="input-with-icon">
                    <input
                      :value="cardNumber"
                      type="text"
                      maxlength="19"
                      class="text-input"
                      :placeholder="t('payment.cardPlaceholder')"
                      @input="formatCardNumber"
                    />
                    <div class="card-brand-icons">
                      <span class="brand-pill visa">VISA</span>
                      <span class="brand-pill mastercard">MC</span>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="field-label">{{ t('payment.cardExpiry') }}</label>
                  <input
                    :value="cardExpiry"
                    type="text"
                    maxlength="5"
                    class="text-input"
                    placeholder="MM/YY"
                    @input="formatExpiry"
                  />
                </div>

                <div class="form-group">
                  <label class="field-label">{{ t('payment.cardCvc') }}</label>
                  <input
                    v-model="cardCvc"
                    type="password"
                    maxlength="4"
                    class="text-input"
                    placeholder="•••"
                  />
                </div>

                <div class="form-group full-width">
                  <label class="field-label">{{ t('payment.cardName') }}</label>
                  <input
                    v-model="cardName"
                    type="text"
                    class="text-input"
                    :placeholder="playerDetails.name"
                  />
                </div>

                <div class="form-group full-width stripe-security-note">
                  <span class="material-symbols-outlined icon-16">lock</span>
                  <span>{{ t('payment.securityNotice') }}</span>
                </div>
              </form>
            </div>

            <div v-if="error" class="error-banner" role="alert">
              <span class="material-symbols-outlined icon-20">error</span>
              <span>{{ error }}</span>
            </div>
          </div>
        </section>

        <!-- Right Column: Sticky Order Summary (Dark Luxury Theme) -->
        <aside class="checkout-summary-col">
          <div class="summary-card-dark">
            <!-- Subtle Radial Glow -->
            <div class="summary-glow" aria-hidden="true"></div>

            <div class="summary-header">
              <span class="material-symbols-outlined summary-icon">receipt_long</span>
              <h2 class="summary-title">{{ t('payment.bookingSummary') }}</h2>
            </div>

            <!-- Court Preview Card -->
            <div class="summary-court-preview">
              <div class="court-img-wrapper">
                <img
                  :src="court?.image_url || '/placeholder-court.jpg'"
                  :alt="court?.name || 'Aura Court'"
                  class="court-thumb-img"
                />
              </div>
              <div class="court-meta">
                <span class="court-tag">{{ court?.venue?.name || t('payment.courtLevel') }}</span>
                <h3 class="court-title">{{ court?.name || 'Elite Court 01' }}</h3>
                <span class="court-loc">{{ court?.venue?.address || 'Aura Badminton Club, Level 2' }}</span>
              </div>
            </div>

            <!-- Date & Time Slot Details -->
            <div class="summary-details-block">
              <div class="summary-row">
                <span class="row-label">{{ t('payment.bookingDate') }}</span>
                <span class="row-value font-semibold">{{ formattedBookingDate }}</span>
              </div>
              <div class="summary-row">
                <span class="row-label">{{ t('payment.timeSlot') }}</span>
                <span class="row-value font-semibold">{{ formattedTimeRange }}</span>
              </div>
              <div class="summary-row">
                <span class="row-label">{{ t('payment.hoursCount', { hours: totalHours }) }}</span>
                <span class="row-value badge-pill">{{ t('payment.slotsCount', { count: totalHours }) }}</span>
              </div>
            </div>

            <!-- Voucher / Coupon Code Block -->
            <div v-if="status === 'pending_payment'" class="summary-coupon-block">
              <div class="coupon-block-header">
                <span class="material-symbols-outlined icon-16">confirmation_number</span>
                <span class="coupon-block-label">{{ t('payment.applyCoupon') }}</span>
              </div>

              <!-- If coupon is applied -->
              <div v-if="appliedCoupon || couponDiscount > 0" class="applied-coupon-pill">
                <div class="applied-coupon-info">
                  <span class="material-symbols-outlined coupon-check-icon">check_circle</span>
                  <div class="applied-coupon-text">
                    <strong class="applied-coupon-code">{{ appliedCoupon?.code || booking?.coupon?.code || 'COUPON' }}</strong>
                    <span class="applied-coupon-desc">-{{ formatCurrency(couponDiscount) }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  class="btn-remove-coupon"
                  :disabled="couponLoading"
                  :title="t('payment.removeCouponBtn')"
                  @click="removeCoupon"
                >
                  <AuraLoading v-if="couponLoading" size="sm" :showLogo="false" />
                  <span v-else class="material-symbols-outlined icon-16">close</span>
                </button>
              </div>

              <!-- Input to apply coupon -->
              <form v-else class="coupon-input-group" @submit.prevent="applyCoupon">
                <input
                  v-model="couponCode"
                  type="text"
                  class="coupon-text-input"
                  :placeholder="t('payment.couponPlaceholder')"
                  maxlength="40"
                  :disabled="couponLoading"
                />
                <button
                  type="submit"
                  class="btn-apply-coupon"
                  :disabled="!couponCode.trim() || couponLoading"
                >
                  <AuraLoading v-if="couponLoading" size="sm" :showLogo="false" />
                  <span v-else>{{ t('payment.applyCouponBtn') }}</span>
                </button>
              </form>

              <!-- Feedback messages -->
              <div v-if="couponError" class="coupon-msg error" role="alert">
                <span class="material-symbols-outlined icon-14">error</span>
                <span>{{ couponError }}</span>
              </div>
              <div v-if="couponSuccess" class="coupon-msg success" role="status">
                <span class="material-symbols-outlined icon-14">check_circle</span>
                <span>{{ couponSuccess }}</span>
              </div>
            </div>

            <!-- Cost Calculations -->
            <div class="summary-costs-block">
              <div class="summary-row">
                <span class="row-label">{{ t('payment.subtotal') }}</span>
                <span class="row-value">{{ formatCurrency(calculatedSubtotal) }}</span>
              </div>
              <div v-if="membershipDiscount > 0" class="summary-row discount-row">
                <span class="row-label">{{ t('payment.memberDiscount') }}</span>
                <span class="row-value">- {{ formatCurrency(membershipDiscount) }}</span>
              </div>
              <div v-if="couponDiscount > 0" class="summary-row discount-row coupon-highlight">
                <span class="row-label">
                  {{ t('payment.couponDiscount') }}
                  <span v-if="appliedCoupon?.code || booking?.coupon?.code" class="code-badge">
                    ({{ appliedCoupon?.code || booking?.coupon?.code }})
                  </span>
                </span>
                <span class="row-value">- {{ formatCurrency(couponDiscount) }}</span>
              </div>
              <div class="summary-row">
                <span class="row-label">{{ t('payment.serviceFee') }}</span>
                <span class="row-value free-text">{{ t('payment.free') }}</span>
              </div>
            </div>

            <!-- Total Amount & Main Action -->
            <div class="summary-total-section">
              <div class="total-group">
                <span class="total-label">{{ t('payment.totalAmount') }}</span>
                <span class="total-price">{{ formatCurrency(finalAmount) }}</span>
              </div>

              <!-- Main Button according to State & Method -->
              <div v-if="status === 'pending_payment'" class="summary-actions">
                <button
                  v-if="selectedMethod === 'card'"
                  type="button"
                  class="btn-pay-now stripe-btn"
                  :disabled="isCardProcessing"
                  @click="handleCardPay"
                >
                  <AuraLoading v-if="isCardProcessing" size="sm" :showLogo="false" />
                  <span v-else>{{ t('payment.payViaStripe') }}</span>
                  <span class="material-symbols-outlined">arrow_forward</span>
                </button>
                <button
                  v-else
                  type="button"
                  class="btn-pay-now secondary-confirm"
                  @click="poll"
                >
                  <span class="material-symbols-outlined">sync</span>
                  <span>{{ t('payment.confirmIHavePaid') }}</span>
                </button>
              </div>

              <div v-else-if="status === 'confirmed'" class="summary-actions">
                <RouterLink to="/booking" class="btn-pay-now success-btn">
                  <span class="material-symbols-outlined">check_circle</span>
                  <span>{{ t('payment.viewBookings') }}</span>
                </RouterLink>
              </div>
            </div>
          </div>

          <!-- Trust & Policy Badges -->
          <div class="trust-badges-list">
            <div class="trust-item">
              <span class="material-symbols-outlined trust-icon">verified_user</span>
              <span>{{ t('payment.securityNotice') }}</span>
            </div>
            <div class="trust-item">
              <span class="material-symbols-outlined trust-icon">history</span>
              <span>{{ t('payment.cancellationNotice') }}</span>
            </div>
          </div>
        </aside>
      </div>

      <!-- No Payment Session Fallback -->
      <div v-else class="panel empty-payment-panel">
        <div class="empty-icon-wrap">
          <span class="material-symbols-outlined icon-48">receipt_long</span>
        </div>
        <h2 class="empty-title">{{ t('payment.noPaymentSession') }}</h2>
        <p class="empty-desc">{{ t('payment.noPaymentDesc') }}</p>
        <RouterLink to="/courts" class="btn primary btn-choose-court">
          <span class="material-symbols-outlined">sports_tennis</span>
          <span>{{ t('payment.chooseCourt') }}</span>
        </RouterLink>
      </div>
    </div>

    <!-- Booking Confirmed Success Modal -->
    <Transition name="modal-pop">
      <div v-if="showSuccessModal" class="success-modal-backdrop" role="dialog" aria-modal="true">
        <div class="success-modal-card">
          <!-- Confetti / Success Animation -->
          <div class="modal-success-icon-wrap">
            <span class="material-symbols-outlined modal-check-icon">check_circle</span>
          </div>

          <div class="modal-text-group">
            <h2 class="modal-title">{{ t('payment.confirmedTitle') }}</h2>
            <p class="modal-desc">{{ t('payment.confirmedDesc') }}</p>
          </div>

          <!-- Ticket Pass Information -->
          <div class="modal-ticket-pass">
            <div class="ticket-row">
              <span class="ticket-label">{{ t('payment.bookingId') }}:</span>
              <span class="ticket-val font-mono">#{{ booking?.code || payment?.code || 'AURA-99210' }}</span>
            </div>
            <div class="ticket-row">
              <span class="ticket-label">{{ t('payment.entryCode') }}:</span>
              <span class="ticket-val entry-pin">{{ entryCode }}</span>
            </div>
            <div class="ticket-row">
              <span class="ticket-label">{{ t('payment.courtName') }}:</span>
              <span class="ticket-val">{{ court?.name || 'Aura Court 01' }}</span>
            </div>
            <div class="ticket-row">
              <span class="ticket-label">{{ t('payment.timeSlot') }}:</span>
              <span class="ticket-val">{{ formattedBookingDate }} · {{ formattedTimeRange }}</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="modal-actions-group">
            <RouterLink to="/booking" class="btn-modal-primary" @click="showSuccessModal = false">
              <span class="material-symbols-outlined">confirmation_number</span>
              <span>{{ t('payment.viewBookings') }}</span>
            </RouterLink>
            <RouterLink to="/" class="btn-modal-secondary" @click="showSuccessModal = false">
              <span>{{ t('payment.backToHome') }}</span>
            </RouterLink>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* ==========================================================================
   Payment & Checkout Screen Styles
   ========================================================================== */

.payment-checkout-page {
  position: relative;
  min-height: 100vh;
  padding-bottom: 80px;
  overflow-x: hidden;
}

/* Ambient Backdrops */
.ambient-backdrop {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  overflow: hidden;
}

.ambient-orb {
  position: absolute;
  border-radius: 9999px;
  filter: blur(120px);
  opacity: 0.45;
}

.ambient-orb-1 {
  top: -10%;
  left: -5%;
  width: 45%;
  height: 55%;
  background: radial-gradient(circle, var(--pink) 0%, rgba(255, 209, 220, 0) 70%);
}

.ambient-orb-2 {
  top: 40%;
  right: -10%;
  width: 40%;
  height: 50%;
  background: radial-gradient(circle, var(--soft) 0%, rgba(229, 232, 236, 0) 70%);
}

html.dark .ambient-orb-1 {
  opacity: 0.25;
  background: radial-gradient(circle, var(--pink-dark) 0%, transparent 70%);
}

html.dark .ambient-orb-2 {
  opacity: 0.2;
  background: radial-gradient(circle, #2b2e36 0%, transparent 70%);
}

.checkout-container {
  position: relative;
  z-index: 1;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
}

/* Stepper */
.checkout-stepper {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 28px;
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--muted);
}

.step-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  color: var(--muted);
  transition: color 0.2s ease;
}

.step-item.completed {
  color: var(--pink-dark);
}

html.dark .step-item.completed {
  color: var(--pink);
}

.step-item.active {
  color: var(--ink);
  font-weight: 800;
}

.step-num {
  opacity: 0.85;
}

.step-divider {
  font-size: 16px;
  color: var(--outline);
}

/* Heading */
.checkout-heading {
  margin-bottom: 24px;
}

.checkout-heading .eyebrow {
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--pink-dark);
  margin-bottom: 4px;
}

html.dark .checkout-heading .eyebrow {
  color: var(--pink);
}

.checkout-heading .page-title {
  font-size: 2.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink);
  line-height: 1.15;
}

/* Layout Grid */
.checkout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 32px;
  align-items: flex-start;
}

@media (min-width: 992px) {
  .checkout-grid {
    grid-template-columns: 7fr 5fr;
  }
}

/* Toast Copy Banner */
.copy-toast-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--mint);
  color: #0d542b;
  padding: 10px 16px;
  border-radius: 10px;
  margin-bottom: 16px;
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

html.dark .copy-toast-banner {
  background: #1c382b;
  color: #7ee0a4;
}

/* Panels */
.panel {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.03);
  margin-bottom: 24px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.panel-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.title-icon {
  color: var(--pink-dark);
  font-size: 24px;
}

html.dark .title-icon {
  color: var(--pink);
}

.edit-link {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--pink-dark);
  text-decoration: none;
  transition: opacity 0.2s ease;
}

html.dark .edit-link {
  color: var(--pink);
}

.edit-link:hover {
  text-decoration: underline;
}

/* Player Details */
.player-fields-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media (min-width: 600px) {
  .player-fields-grid {
    grid-template-columns: 1fr 1fr;
  }
  .player-fields-grid .full-width {
    grid-column: span 2;
  }
}

.field-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
}

.field-value {
  padding: 10px 0;
  border-bottom: 2px solid var(--border);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--ink);
}

/* Timer Pill */
.timer-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  color: var(--ink);
  font-size: 0.8125rem;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 9999px;
}

.timer-urgent {
  background: var(--coral);
  color: #93000a;
  border-color: #ffb4ab;
  animation: pulse-border 1.5s infinite;
}

/* Method Selection Tabs (2 tabs: SePay VietQR & Stripe Card) */
.method-tabs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
  margin-bottom: 24px;
}

@media (max-width: 640px) {
  .method-tabs-grid {
    grid-template-columns: 1fr;
  }
}

.method-tab-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  position: relative;
  gap: 8px;
  padding: 18px 14px;
  background: var(--surface-subtle);
  border: 2px solid var(--border);
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.method-tab-btn:hover {
  border-color: var(--pink);
  background: var(--card-bg);
}

.method-tab-btn.active {
  border-color: var(--pink-dark);
  background: rgba(255, 209, 220, 0.2);
}

html.dark .method-tab-btn.active {
  border-color: var(--pink);
  background: rgba(120, 85, 94, 0.3);
}

.gateway-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 2px 6px;
  border-radius: 4px;
}

.sepay-badge {
  background: #0ea5e9;
  color: #ffffff;
}

.stripe-badge {
  background: #6366f1;
  color: #ffffff;
}

.method-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: var(--card-bg);
  color: var(--muted);
  font-size: 28px;
  transition: color 0.2s ease;
}

.sepay-icon-wrap {
  color: #0284c7;
}

.stripe-icon-wrap {
  color: #6366f1;
}

.method-tab-btn.active .method-icon-wrap {
  background: #ffffff;
}

html.dark .method-tab-btn.active .method-icon-wrap {
  background: #2b2e36;
}

.method-text {
  display: flex;
  flex-direction: column;
}

.method-name {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--ink);
}

.method-sub {
  font-size: 0.75rem;
  color: var(--muted);
}

/* Method Content Boxes */
.method-content-box {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 20px;
}

.gateway-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.gateway-tag {
  display: inline-block;
  font-size: 0.6875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  background: rgba(14, 165, 233, 0.12);
  color: #0284c7;
  padding: 2px 8px;
  border-radius: 4px;
  margin-bottom: 4px;
}

.gateway-tag.stripe-tag {
  background: rgba(99, 102, 241, 0.12);
  color: #6366f1;
}

.qr-intro-title {
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 2px;
}

.qr-intro-desc {
  font-size: 0.875rem;
  color: var(--muted);
  margin: 0;
}

/* QR Transfer Layout */
.qr-transfer-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  align-items: center;
}

@media (min-width: 700px) {
  .qr-transfer-layout {
    grid-template-columns: 220px 1fr;
  }
}

.qr-frame-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.qr-card {
  background: #ffffff;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid var(--border);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 220px;
}

.vietqr-image {
  width: 100%;
  height: auto;
  aspect-ratio: 1/1;
  object-fit: contain;
  border-radius: 6px;
}

.qr-placeholder {
  width: 100%;
  aspect-ratio: 1/1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  gap: 8px;
  font-size: 0.75rem;
}

.qr-status-indicator {
  margin-top: 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.6875rem;
  font-weight: 600;
  color: #0284c7;
}

.qr-status-indicator.confirmed {
  color: #10b981;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  background-color: #0284c7;
  border-radius: 9999px;
  animation: pulse-ring 1.5s infinite;
}

.qr-status-indicator.confirmed .pulse-dot {
  background-color: #10b981;
}

/* Bank Details List */
.bank-details-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bank-detail-item {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px 14px;
}

.detail-label {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted);
  margin-bottom: 4px;
}

.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.detail-value {
  font-size: 0.9375rem;
  color: var(--ink);
}

.detail-value.highlight-num {
  font-family: monospace;
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: var(--pink-dark);
}

html.dark .detail-value.highlight-num {
  color: var(--pink);
}

.detail-value.code-badge {
  font-family: monospace;
  font-size: 1rem;
  font-weight: 800;
  background: rgba(255, 209, 220, 0.3);
  padding: 2px 8px;
  border-radius: 6px;
  color: var(--ink);
}

.detail-value.price-total {
  font-size: 1.125rem;
  font-weight: 800;
  color: var(--ink);
}

.copy-chip-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s ease;
}

.copy-chip-btn:hover {
  background: var(--pink);
  color: #2d141c;
}

/* Credit Card (Stripe) Preview */
.credit-card-preview {
  background: linear-gradient(135deg, #374151 0%, #1e1e24 100%);
  color: #ffffff;
  padding: 20px;
  border-radius: 14px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  position: relative;
  overflow: hidden;
}

.credit-card-preview::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 200px;
  height: 200px;
  background: rgba(99, 102, 241, 0.2);
  border-radius: 50%;
  filter: blur(40px);
}

.card-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-chip {
  width: 36px;
  height: 26px;
  background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
  border-radius: 4px;
}

.stripe-card-logo {
  font-weight: 800;
  font-size: 1.125rem;
  letter-spacing: -0.03em;
  color: #6366f1;
}

.card-number-display {
  font-family: monospace;
  font-size: 1.25rem;
  letter-spacing: 0.15em;
}

.card-bottom-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.card-sub {
  font-size: 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.6;
  display: block;
}

.card-name-val,
.card-expiry-val {
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.05em;
}

.card-inputs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.card-inputs-grid .full-width {
  grid-column: span 2;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.input-with-icon {
  position: relative;
}

.card-brand-icons {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  gap: 4px;
}

.brand-pill {
  font-size: 0.625rem;
  font-weight: 800;
  padding: 2px 4px;
  border-radius: 3px;
  background: var(--surface-subtle);
  color: var(--muted);
}

.brand-pill.visa {
  color: #1a1f71;
  background: #f0f3f8;
}

.brand-pill.mastercard {
  color: #eb001b;
  background: #fff0f0;
}

.text-input {
  width: 100%;
  background: var(--card-bg);
  border: 1px solid var(--border);
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.9375rem;
  color: var(--ink);
  outline: none;
  transition: border-color 0.2s ease;
}

.text-input:focus {
  border-color: var(--pink-dark);
}

.stripe-security-note {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
/* Coupon / Voucher Code Summary Styles */
.summary-coupon-block {
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  padding: 16px 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.coupon-block-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8125rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
}

.applied-coupon-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(255, 209, 220, 0.12);
  border: 1px solid rgba(255, 209, 220, 0.3);
  border-radius: 8px;
  padding: 8px 12px;
}

.applied-coupon-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.coupon-check-icon {
  color: #34d399;
  font-size: 18px;
}

.applied-coupon-text {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.applied-coupon-code {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--pink);
  letter-spacing: 0.05em;
}

.applied-coupon-desc {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.75);
}

.btn-remove-coupon {
  background: transparent;
  border: none;
  color: rgba(255, 255, 255, 0.6);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 4px;
  transition: color 0.15s ease, background 0.15s ease;
}

.btn-remove-coupon:hover:not(:disabled) {
  color: #ff8080;
  background: rgba(255, 255, 255, 0.08);
}

.coupon-input-group {
  display: flex;
  gap: 8px;
}

.coupon-text-input {
  flex: 1;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 0.8125rem;
  color: #ffffff;
  outline: none;
  transition: border-color 0.2s ease, background 0.2s ease;
  text-transform: uppercase;
}

.coupon-text-input:focus {
  border-color: var(--pink);
  background: rgba(255, 255, 255, 0.12);
}

.coupon-text-input::placeholder {
  color: rgba(255, 255, 255, 0.4);
  text-transform: none;
}

.btn-apply-coupon {
  background: var(--pink);
  color: #2d141c;
  font-weight: 700;
  font-size: 0.8125rem;
  border: none;
  border-radius: 8px;
  padding: 8px 14px;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 0.2s ease, transform 0.15s ease;
}

.btn-apply-coupon:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
}

.btn-apply-coupon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.coupon-msg {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  margin-top: 2px;
}

.coupon-msg.error {
  color: #ff9b9b;
}

.coupon-msg.success {
  color: #6ee7b7;
}

.code-badge {
  font-size: 0.6875rem;
  background: rgba(255, 209, 220, 0.2);
  color: var(--pink);
  padding: 1px 6px;
  border-radius: 4px;
  font-weight: 700;
  margin-left: 4px;
}

.error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--coral);
  color: #93000a;
  padding: 12px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  margin-top: 16px;
}

/* ==========================================================================
   Right Column: Sticky Dark Order Summary
   ========================================================================== */

.checkout-summary-col {
  position: sticky;
  top: 96px;
}

.summary-card-dark {
  background: #191c1d;
  color: #ffffff;
  border-radius: 18px;
  padding: 28px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
  position: relative;
  overflow: hidden;
}

.summary-glow {
  position: absolute;
  top: -40px;
  right: -40px;
  width: 160px;
  height: 160px;
  background: rgba(255, 209, 220, 0.25);
  border-radius: 50%;
  filter: blur(50px);
  pointer-events: none;
}

.summary-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
}

.summary-icon {
  color: var(--pink);
  font-size: 24px;
}

.summary-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
}

/* Court Preview */
.summary-court-preview {
  display: flex;
  gap: 16px;
  background: rgba(255, 255, 255, 0.06);
  padding: 14px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.court-img-wrapper {
  width: 72px;
  height: 72px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.1);
}

.court-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.court-meta {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.court-tag {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--pink);
}

.court-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #ffffff;
  margin: 2px 0;
}

.court-loc {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Rows & Details */
.summary-details-block,
.summary-costs-block {
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  padding: 16px 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.875rem;
}

.summary-row .row-label {
  color: rgba(255, 255, 255, 0.7);
}

.summary-row .row-value {
  color: #ffffff;
}

.summary-row.discount-row {
  color: var(--pink);
}

.summary-row.discount-row .row-label,
.summary-row.discount-row .row-value {
  color: var(--pink);
  font-weight: 600;
}

.free-text {
  color: #34d399 !important;
  font-weight: 600;
}

.badge-pill {
  background: rgba(255, 255, 255, 0.12);
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 0.75rem;
}

/* Total Section */
.summary-total-section {
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  padding-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.total-group {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.total-label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.6);
}

.total-price {
  font-size: 1.75rem;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.02em;
}

.summary-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn-pay-now {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: var(--pink);
  color: #2d141c;
  border: none;
  padding: 14px 20px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-pay-now.stripe-btn {
  background: #6366f1;
  color: #ffffff;
}

.btn-pay-now:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
}

.btn-pay-now.secondary-confirm {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
}

.btn-pay-now.secondary-confirm:hover {
  background: rgba(255, 255, 255, 0.2);
}

.btn-pay-now.success-btn {
  background: #10b981;
  color: #ffffff;
}

/* Trust Badges */
.trust-badges-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px 8px;
}

.trust-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
  color: var(--muted);
}

.trust-icon {
  font-size: 18px;
  color: var(--pink-dark);
}

html.dark .trust-icon {
  color: var(--pink);
}

/* ==========================================================================
   Empty Session State
   ========================================================================== */

.empty-payment-panel {
  text-align: center;
  max-width: 540px;
  margin: 40px auto;
  padding: 48px 24px;
}

.empty-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--surface-subtle);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  color: var(--muted);
}

.empty-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 8px;
}

.empty-desc {
  font-size: 0.9375rem;
  color: var(--muted);
  margin-bottom: 24px;
}

.btn-choose-court {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  text-decoration: none;
}

/* ==========================================================================
   Success Confirmation Modal
   ========================================================================== */

.success-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.success-modal-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 20px;
  max-width: 460px;
  width: 100%;
  padding: 32px 24px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 20px;
}

.modal-success-icon-wrap {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(16, 185, 129, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-check-icon {
  font-size: 48px;
  color: #10b981;
  animation: scale-up 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.modal-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--ink);
  margin-bottom: 6px;
}

.modal-desc {
  font-size: 0.875rem;
  color: var(--muted);
  margin: 0;
}

.modal-ticket-pass {
  width: 100%;
  background: var(--surface-subtle);
  border: 1px dashed var(--border);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  text-align: left;
}

.ticket-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8125rem;
}

.ticket-label {
  color: var(--muted);
}

.ticket-val {
  font-weight: 600;
  color: var(--ink);
}

.ticket-val.entry-pin {
  font-size: 1.125rem;
  font-weight: 800;
  letter-spacing: 0.2em;
  color: var(--pink-dark);
}

html.dark .ticket-val.entry-pin {
  color: var(--pink);
}

.modal-actions-group {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn-modal-primary {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: var(--pink-dark);
  color: #ffffff;
  padding: 12px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none;
  transition: opacity 0.2s ease;
}

html.dark .btn-modal-primary {
  background: var(--pink);
  color: #2d141c;
}

.btn-modal-primary:hover {
  opacity: 0.9;
}

.btn-modal-secondary {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--muted);
  text-decoration: none;
  padding: 6px;
}

.btn-modal-secondary:hover {
  color: var(--ink);
}

/* ==========================================================================
   Animations
   ========================================================================== */

@keyframes pulse-ring {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(2, 132, 199, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(2, 132, 199, 0);
  }
}

@keyframes pulse-border {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.75;
  }
}

@keyframes scale-up {
  0% {
    transform: scale(0.5);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.modal-pop-enter-active,
.modal-pop-leave-active {
  transition: all 0.3s ease;
}

.modal-pop-enter-from,
.modal-pop-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>
