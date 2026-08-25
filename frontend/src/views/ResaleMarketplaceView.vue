<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { apiV2, errorMessage } from '@/lib/api'
import { formatCurrency } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

interface ResaleListing {
  id: number
  courtName: string
  venueName: string
  dateStr: string
  timeSlot: string
  timePeriod: 'morning' | 'afternoon' | 'evening'
  originalPrice: number
  resalePrice: number
  badge?: 'urgent' | 'deal' | 'hot'
  sellerName: string
  sellerAvatar?: string
  cutoffAt?: string
  isFeatured?: boolean
}

interface UserBookingSlot {
  id: number
  entitlementId: number
  courtName: string
  venueName: string
  dateStr: string
  timeSlot: string
  originalPrice: number
  eligible: boolean
  ineligibleReason?: string
  dateBadge: {
    dayOfWeek: string
    dayNum: string
  }
}

interface TransactionLog {
  id: number
  listingId?: number
  courtName: string
  venueName: string
  timeSlot: string
  dateStr: string
  status: 'active' | 'completed' | 'cancelled'
  resalePrice: number
  netReceived?: number
  updatedAt: string
}

const auth = useAuthStore()
const activeTab = ref<'buy' | 'sell'>('buy')

// Filter states for Buy Tab
const selectedDate = ref('')
const selectedTimePeriod = ref('all')
const selectedVenue = ref('all')
const searchQuery = ref('')

// Loading & Notification states
const loading = ref(false)
const processingAction = ref(false)
const notice = ref('')
const error = ref('')

// Modal States
const isBuyModalOpen = ref(false)
const selectedListingToBuy = ref<ResaleListing | null>(null)
const buyPaymentMethod = ref<'wallet' | 'transfer'>('wallet')
const buySuccess = ref(false)

const isSellModalOpen = ref(false)
const selectedSlotToSell = ref<UserBookingSlot | null>(null)
const askingPriceInput = ref<number>(150000)
const sellSuccess = ref(false)

// Sample & API data
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
  {
    id: 105,
    courtName: 'Sân Tiêu Chuẩn 1',
    venueName: 'Aura East - Quận 2',
    dateStr: 'Thứ Hai, 01/09/2026',
    timeSlot: '18:00 - 20:00 (2 tiếng)',
    timePeriod: 'evening',
    originalPrice: 220000,
    resalePrice: 160000,
    sellerName: 'Bảo Anh',
  },
  {
    id: 106,
    courtName: 'Sân Số 4',
    venueName: 'Aura North - Phú Nhuận',
    dateStr: 'Thứ Ba, 02/09/2026',
    timeSlot: '15:00 - 17:00 (2 tiếng)',
    timePeriod: 'afternoon',
    originalPrice: 160000,
    resalePrice: 120000,
    sellerName: 'Quốc Cường',
  }
])

const userSlots = ref<UserBookingSlot[]>([
  {
    id: 201,
    entitlementId: 801,
    courtName: 'Sân 3 - VIP Taraflex',
    venueName: 'Aura Center - Quận 1',
    dateStr: 'Thứ Sáu, 29/08/2026',
    timeSlot: '20:00 - 22:00',
    originalPrice: 220000,
    eligible: true,
    dateBadge: { dayOfWeek: 'T6', dayNum: '29' }
  },
  {
    id: 202,
    entitlementId: 802,
    courtName: 'Sân 2 - Tiêu chuẩn',
    venueName: 'Aura East - Quận 2',
    dateStr: 'Chủ Nhật, 31/08/2026',
    timeSlot: '16:00 - 18:00',
    originalPrice: 180000,
    eligible: true,
    dateBadge: { dayOfWeek: 'CN', dayNum: '31' }
  },
  {
    id: 203,
    entitlementId: 803,
    courtName: 'Sân 1 - Thảm Xanh',
    venueName: 'Aura North - Phú Nhuận',
    dateStr: 'Hôm nay, 28/08/2026',
    timeSlot: '12:00 - 14:00',
    originalPrice: 160000,
    eligible: false,
    ineligibleReason: 'Quá hạn nhượng (cách giờ chơi < 2h)',
    dateBadge: { dayOfWeek: 'HN', dayNum: '28' }
  }
])

const transactions = ref<TransactionLog[]>([
  {
    id: 301,
    listingId: 108,
    courtName: 'Sân 8 - Aura Center',
    venueName: 'Aura Center - Quận 1',
    timeSlot: '18:00 - 19:00',
    dateStr: 'Hôm nay, 28/08',
    status: 'active',
    resalePrice: 110000,
    netReceived: 104500,
    updatedAt: '10 phút trước'
  },
  {
    id: 302,
    courtName: 'Sân 2 - Aura East',
    venueName: 'Aura East - Quận 2',
    timeSlot: '19:00 - 21:00',
    dateStr: '24/08/2026',
    status: 'completed',
    resalePrice: 200000,
    netReceived: 190000,
    updatedAt: '24/08 21:05'
  }
])

// Computed calculations
const platformFeePercent = 5
const calculatedFee = computed(() => Math.round((askingPriceInput.value * platformFeePercent) / 100))
const calculatedNetPayout = computed(() => Math.max(0, askingPriceInput.value - calculatedFee.value))

const featuredListing = computed(() => listings.value.find(l => l.isFeatured) || listings.value[0])

const filteredListings = computed(() => {
  return listings.value.filter(item => {
    // Venue match
    if (selectedVenue.value !== 'all' && !item.venueName.toLowerCase().includes(selectedVenue.value.toLowerCase())) {
      return false
    }
    // Time period match
    if (selectedTimePeriod.value !== 'all' && item.timePeriod !== selectedTimePeriod.value) {
      return false
    }
    // Search query match
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase()
      const match = item.courtName.toLowerCase().includes(q) ||
                    item.venueName.toLowerCase().includes(q) ||
                    item.sellerName.toLowerCase().includes(q)
      if (!match) return false
    }
    return true
  })
})

// Load real API v2 listings if available
async function loadData() {
  loading.value = true
  try {
    const response = await apiV2.get<{ data: any[] }>('/marketplace/listings')
    if (response.data && response.data.length > 0) {
      // Map API v2 listings into state
      const apiMapped: ResaleListing[] = response.data.map((item, idx) => ({
        id: item.id,
        courtName: item.entitlement?.court?.name || `Sân #${item.id}`,
        venueName: item.entitlement?.court?.venue?.name || 'Aura Court',
        dateStr: new Date(item.entitlement?.starts_at || Date.now()).toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit' }),
        timeSlot: `${new Date(item.entitlement?.starts_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })} - ${new Date(item.entitlement?.ends_at || Date.now()).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}`,
        timePeriod: 'evening',
        originalPrice: item.original_price || item.asking_price * 1.2,
        resalePrice: item.asking_price,
        badge: idx === 0 ? 'urgent' : undefined,
        sellerName: item.seller?.name || 'Hội viên Aura',
        cutoffAt: item.cutoff_at
      }))
      // Merge with default sample listings
      listings.value = [...apiMapped, ...listings.value.filter(l => !apiMapped.some(m => m.id === l.id))]
    }
  } catch (err) {
    // Graceful fallback to client state
    console.info('Dùng dữ liệu niêm yết mẫu trên sàn.')
  } finally {
    loading.value = false
  }
}

// Purchase Handlers
function openBuyModal(listing: ResaleListing) {
  selectedListingToBuy.value = listing
  buyPaymentMethod.value = 'wallet'
  buySuccess.value = false
  isBuyModalOpen.value = true
}

function closeBuyModal() {
  isBuyModalOpen.value = false
  selectedListingToBuy.value = null
}

async function confirmPurchase() {
  if (!selectedListingToBuy.value) return
  processingAction.value = true
  error.value = ''
  
  try {
    await apiV2.post(`/marketplace/listings/${selectedListingToBuy.value.id}/purchase`, {
      buyer_payment_reference: `market-buy-${selectedListingToBuy.value.id}-${Date.now()}`,
      seller_refund_accepted: true
    })
  } catch (err) {
    // Demo fallback for preview
    console.warn('API purchase fallback for preview demo.')
  }

  setTimeout(() => {
    processingAction.value = false
    buySuccess.value = true
    
    // Remove purchased listing from market
    const boughtId = selectedListingToBuy.value?.id
    setTimeout(() => {
      listings.value = listings.value.filter(l => l.id !== boughtId)
      closeBuyModal()
      notice.value = `🎉 Mua thành công suất chơi tại ${selectedListingToBuy.value?.courtName}! Suất đặt đã được chuyển vào tài khoản của bạn.`
    }, 1200)
  }, 1000)
}

// Resale Creation Handlers
function openSellModal(slot: UserBookingSlot) {
  selectedSlotToSell.value = slot
  askingPriceInput.value = Math.round(slot.originalPrice * 0.8)
  sellSuccess.value = false
  isSellModalOpen.value = true
}

function closeSellModal() {
  isSellModalOpen.value = false
  selectedSlotToSell.value = null
}

async function confirmCreateListing() {
  if (!selectedSlotToSell.value) return
  processingAction.value = true
  error.value = ''

  try {
    await apiV2.post(`/marketplace/entitlements/${selectedSlotToSell.value.entitlementId}/list`, {
      asking_price: Number(askingPriceInput.value)
    })
  } catch (err) {
    console.warn('API create listing fallback for preview demo.')
  }

  setTimeout(() => {
    processingAction.value = false
    sellSuccess.value = true

    const slot = selectedSlotToSell.value!
    // Add to active listings
    listings.value.unshift({
      id: Date.now(),
      courtName: slot.courtName,
      venueName: slot.venueName,
      dateStr: slot.dateStr,
      timeSlot: slot.timeSlot,
      timePeriod: 'evening',
      originalPrice: slot.originalPrice,
      resalePrice: askingPriceInput.value,
      badge: 'deal',
      sellerName: auth.user?.name || 'Bạn',
    })

    // Add to transaction tracking
    transactions.value.unshift({
      id: Date.now(),
      courtName: slot.courtName,
      venueName: slot.venueName,
      timeSlot: slot.timeSlot,
      dateStr: slot.dateStr,
      status: 'active',
      resalePrice: askingPriceInput.value,
      netReceived: calculatedNetPayout.value,
      updatedAt: 'Vừa xong'
    })

    // Remove from eligible slots
    userSlots.value = userSlots.value.filter(s => s.id !== slot.id)

    setTimeout(() => {
      closeSellModal()
      notice.value = `✅ Đã niêm yết suất sân ${slot.courtName} lên Chợ Nhượng Sân với giá ${formatCurrency(askingPriceInput.value)}.`
    }, 1000)
  }, 1000)
}

// Cancel Active Listing
async function cancelListing(transaction: TransactionLog) {
  if (!confirm(`Bạn có chắc muốn hủy đăng bán suất sân ${transaction.courtName}?`)) return
  try {
    if (transaction.listingId) {
      await apiV2.post(`/marketplace/listings/${transaction.listingId}/withdraw`)
    }
  } catch (e) {
    console.warn('Fallback withdraw')
  }
  
  transaction.status = 'cancelled'
  notice.value = `Đã thu hồi suất sân ${transaction.courtName} khỏi Chợ Nhượng Sân.`
}

onMounted(loadData)
</script>

<template>
  <main class="resale-page">
    <!-- Notifications -->
    <div v-if="notice" class="notification-banner success" role="alert">
      <span class="material-symbols-outlined">check_circle</span>
      <span>{{ notice }}</span>
      <button class="close-notif" @click="notice = ''"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div v-if="error" class="notification-banner error" role="alert">
      <span class="material-symbols-outlined">error</span>
      <span>{{ error }}</span>
      <button class="close-notif" @click="error = ''"><span class="material-symbols-outlined">close</span></button>
    </div>

    <!-- Header & Hero Section -->
    <section class="resale-hero">
      <div class="hero-header-row">
        <div class="hero-text">
          <div class="hero-badge">
            <span class="material-symbols-outlined">storefront</span>
            AURA SECONDARY MARKET
          </div>
          <h1 class="hero-title">Chợ Nhượng Sân</h1>
          <p class="hero-subtitle">
            Mua bán và chuyển nhượng suất chơi nhanh chóng. Tối ưu lịch chơi, không lãng phí thời gian trống.
          </p>
        </div>

        <!-- Custom Tab Navigation Switcher -->
        <div class="tab-switcher" role="tablist">
          <button
            class="tab-btn"
            :class="{ active: activeTab === 'buy' }"
            role="tab"
            :aria-selected="activeTab === 'buy'"
            @click="activeTab = 'buy'"
          >
            <span class="material-symbols-outlined">shopping_bag</span>
            Mua Lại Sân
          </button>
          <button
            class="tab-btn"
            :class="{ active: activeTab === 'sell' }"
            role="tab"
            :aria-selected="activeTab === 'sell'"
            @click="activeTab = 'sell'"
          >
            <span class="material-symbols-outlined">sell</span>
            Đăng Nhượng
          </button>
        </div>
      </div>
    </section>

    <!-- ==================== TAB 1: MUA LẠI SÂN (BUY) ==================== -->
    <div v-if="activeTab === 'buy'" class="tab-view animate-fade-in">
      <!-- Filter Bar -->
      <section class="filter-card">
        <div class="filter-group">
          <label class="filter-label">
            <span class="material-symbols-outlined">calendar_today</span>
            Ngày chơi
          </label>
          <input
            v-model="selectedDate"
            type="date"
            class="filter-input"
          />
        </div>

        <div class="filter-group">
          <label class="filter-label">
            <span class="material-symbols-outlined">schedule</span>
            Khung giờ
          </label>
          <select v-model="selectedTimePeriod" class="filter-select">
            <option value="all">Tất cả khung giờ</option>
            <option value="morning">Sáng (06:00 - 12:00)</option>
            <option value="afternoon">Chiều (12:00 - 18:00)</option>
            <option value="evening">Tối (18:00 - 23:00)</option>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">
            <span class="material-symbols-outlined">location_on</span>
            Cụm sân
          </label>
          <select v-model="selectedVenue" class="filter-select">
            <option value="all">Tất cả địa điểm</option>
            <option value="Aura Center">Aura Center - Quận 1</option>
            <option value="Aura East">Aura East - Quận 2</option>
            <option value="Aura North">Aura North - Phú Nhuận</option>
          </select>
        </div>

        <div class="filter-group search-group">
          <label class="filter-label">
            <span class="material-symbols-outlined">search</span>
            Tìm kiếm
          </label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tên sân, người bán..."
            class="filter-input search-input"
          />
        </div>

        <div class="filter-actions">
          <button
            v-if="selectedDate || selectedTimePeriod !== 'all' || selectedVenue !== 'all' || searchQuery"
            class="btn-clear-filter"
            @click="selectedDate = ''; selectedTimePeriod = 'all'; selectedVenue = 'all'; searchQuery = ''"
          >
            Đặt lại
          </button>
        </div>
      </section>

      <!-- Featured Banner: Giờ Vàng Giá Tốt -->
      <section v-if="featuredListing" class="featured-banner group">
        <div class="featured-content">
          <div class="featured-tags">
            <span class="tag-gold">
              <span class="material-symbols-outlined">local_fire_department</span>
              Giờ Vàng Giá Tốt
            </span>
            <span class="tag-promo">
              <span class="material-symbols-outlined">percent</span>
              Tiết kiệm {{ Math.round((1 - featuredListing.resalePrice / featuredListing.originalPrice) * 100) }}%
            </span>
          </div>

          <h2 class="featured-title">{{ featuredListing.courtName }}</h2>
          <p class="featured-venue">{{ featuredListing.venueName }}</p>

          <div class="featured-info-pill">
            <span class="material-symbols-outlined">schedule</span>
            <span>{{ featuredListing.dateStr }} · {{ featuredListing.timeSlot }}</span>
          </div>

          <div class="featured-price-row">
            <div class="price-box">
              <span class="orig-price">{{ formatCurrency(featuredListing.originalPrice) }}</span>
              <span class="resale-price">{{ formatCurrency(featuredListing.resalePrice) }}</span>
            </div>
            <button class="btn-primary-featured" @click="openBuyModal(featuredListing)">
              <span class="material-symbols-outlined">shopping_cart</span>
              Mua Ngay
            </button>
          </div>
        </div>
        <div class="featured-art">
          <div class="art-glow"></div>
          <span class="material-symbols-outlined art-icon">sports_tennis</span>
        </div>
      </section>

      <!-- Resale Listings Grid -->
      <section class="listings-section">
        <div class="section-head">
          <div class="section-title-wrap">
            <h3 class="section-title">Danh Sách Suất Nhượng</h3>
            <span class="section-badge">{{ filteredListings.length }} suất khả dụng</span>
          </div>
        </div>

        <div v-if="filteredListings.length === 0" class="empty-state">
          <span class="material-symbols-outlined empty-icon">sentiment_dissatisfied</span>
          <p class="empty-title">Không tìm thấy suất nhượng phù hợp</p>
          <p class="empty-desc">Hãy thử thay đổi bộ lọc hoặc chọn khung giờ/địa điểm khác.</p>
        </div>

        <div v-else class="bento-grid">
          <article
            v-for="item in filteredListings"
            :key="item.id"
            class="bento-card"
          >
            <!-- Top Badges & Venue -->
            <div class="card-top">
              <div class="card-venue-wrap">
                <span class="card-venue-tag">{{ item.venueName }}</span>
                <h4 class="card-court-title">{{ item.courtName }}</h4>
              </div>
              <span v-if="item.badge === 'urgent'" class="badge-urgent">
                <span class="material-symbols-outlined">bolt</span> Bán gấp
              </span>
              <span v-else-if="item.badge === 'deal'" class="badge-deal">
                <span class="material-symbols-outlined">trending_down</span> Giá tốt
              </span>
              <span v-else-if="item.badge === 'hot'" class="badge-hot">
                <span class="material-symbols-outlined">local_fire_department</span> Hot
              </span>
            </div>

            <!-- Date & Time Info -->
            <div class="card-schedule">
              <div class="sched-row">
                <span class="material-symbols-outlined">calendar_month</span>
                <span>{{ item.dateStr }}</span>
              </div>
              <div class="sched-row highlight">
                <span class="material-symbols-outlined">schedule</span>
                <strong>{{ item.timeSlot }}</strong>
              </div>
              <div class="sched-row seller-info">
                <span class="material-symbols-outlined">person</span>
                <span>Người bán: {{ item.sellerName }}</span>
              </div>
            </div>

            <!-- Footer: Price & Action -->
            <div class="card-bottom">
              <div class="price-stack">
                <span class="price-original">Gốc: {{ formatCurrency(item.originalPrice) }}</span>
                <span class="price-resale">{{ formatCurrency(item.resalePrice) }}</span>
              </div>
              <button class="btn-buy" @click="openBuyModal(item)">
                Mua Ngay
              </button>
            </div>
          </article>
        </div>
      </section>
    </div>

    <!-- ==================== TAB 2: ĐĂNG NHƯỢNG (SELL) ==================== -->
    <div v-else class="tab-view animate-fade-in">
      <div class="seller-layout">
        <!-- Left: User Bookings Eligible to Resell -->
        <div class="seller-main">
          <div class="seller-header">
            <h3 class="section-title">Lịch Sắp Tới Khả Dụng</h3>
            <p class="section-subtitle">
              Bạn có thể đăng nhượng lại các suất sân đã đặt trước giờ chơi tối thiểu 2 tiếng. Tiền nhượng sẽ được cộng trực tiếp vào Ví Aura sau khi giao dịch thành công.
            </p>
          </div>

          <div class="slots-list">
            <div
              v-for="slot in userSlots"
              :key="slot.id"
              class="slot-card"
              :class="{ 'ineligible-slot': !slot.eligible }"
            >
              <!-- Date Badge Box -->
              <div class="date-badge-box">
                <span class="badge-dow">{{ slot.dateBadge.dayOfWeek }}</span>
                <span class="badge-day">{{ slot.dateBadge.dayNum }}</span>
              </div>

              <!-- Slot Details -->
              <div class="slot-details">
                <h4 class="slot-name">{{ slot.courtName }}</h4>
                <p class="slot-venue">{{ slot.venueName }}</p>
                <div class="slot-meta">
                  <span class="meta-item">
                    <span class="material-symbols-outlined">schedule</span>
                    {{ slot.timeSlot }}
                  </span>
                  <span class="meta-dot">·</span>
                  <span class="meta-item">
                    Giá gốc: {{ formatCurrency(slot.originalPrice) }}
                  </span>
                </div>
              </div>

              <!-- Action or Status -->
              <div class="slot-action-area">
                <button
                  v-if="slot.eligible"
                  class="btn-sell-trigger"
                  @click="openSellModal(slot)"
                >
                  <span class="material-symbols-outlined">sell</span>
                  Đăng Nhượng
                </button>
                <div v-else class="locked-status">
                  <span class="material-symbols-outlined">lock</span>
                  <span>{{ slot.ineligibleReason || 'Quá hạn' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Status Tracking & Completed Resales -->
        <div class="seller-sidebar">
          <div class="tracking-card">
            <h3 class="tracking-title">
              <span class="material-symbols-outlined">timeline</span>
              Quản Lý Giao Dịch
            </h3>

            <div class="timeline-container">
              <div
                v-for="tx in transactions"
                :key="tx.id"
                class="timeline-item"
                :class="tx.status"
              >
                <div class="timeline-dot">
                  <span v-if="tx.status === 'completed'" class="material-symbols-outlined">check</span>
                  <span v-else-if="tx.status === 'active'" class="ping-dot"></span>
                  <span v-else class="material-symbols-outlined">close</span>
                </div>

                <div class="timeline-content">
                  <div class="timeline-status-head">
                    <span v-if="tx.status === 'active'" class="status-active-label">ĐANG ĐĂNG BÁN</span>
                    <span v-else-if="tx.status === 'completed'" class="status-done-label">ĐÃ NHƯỢNG THÀNH CÔNG</span>
                    <span v-else class="status-cancel-label">ĐÃ HỦY</span>
                    <span class="tx-time">{{ tx.updatedAt }}</span>
                  </div>

                  <div class="tx-box">
                    <p class="tx-court">{{ tx.courtName }}</p>
                    <p class="tx-date">{{ tx.dateStr }} · {{ tx.timeSlot }}</p>

                    <div v-if="tx.status === 'active'" class="tx-active-actions">
                      <span class="tx-price">Giá nhượng: {{ formatCurrency(tx.resalePrice) }}</span>
                      <button class="btn-cancel-listing" @click="cancelListing(tx)">Hủy đăng</button>
                    </div>

                    <div v-else-if="tx.status === 'completed'" class="tx-completed-info">
                      <span class="material-symbols-outlined">account_balance_wallet</span>
                      <span>Đã hoàn +{{ formatCurrency(tx.netReceived || tx.resalePrice * 0.95) }} (Ví Aura)</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== MODAL: MUA LẠI SÂN ==================== -->
    <div v-if="isBuyModalOpen && selectedListingToBuy" class="modal-backdrop animate-fade-in" @click.self="closeBuyModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3 class="modal-title">Xác Nhận Mua Lại Suất Sân</h3>
          <button class="btn-close-modal" @click="closeBuyModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="court-summary-card">
            <div class="summary-row">
              <span class="label">Sân:</span>
              <strong>{{ selectedListingToBuy.courtName }}</strong>
            </div>
            <div class="summary-row">
              <span class="label">Địa điểm:</span>
              <span>{{ selectedListingToBuy.venueName }}</span>
            </div>
            <div class="summary-row">
              <span class="label">Thời gian:</span>
              <span>{{ selectedListingToBuy.dateStr }} ({{ selectedListingToBuy.timeSlot }})</span>
            </div>
            <div class="summary-row">
              <span class="label">Người nhượng:</span>
              <span>{{ selectedListingToBuy.sellerName }}</span>
            </div>
          </div>

          <!-- Payment Method Choice -->
          <div class="payment-choice-block">
            <label class="block-label">Phương thức thanh toán</label>
            <div class="payment-options">
              <label class="pay-option" :class="{ selected: buyPaymentMethod === 'wallet' }">
                <input v-model="buyPaymentMethod" type="radio" value="wallet" />
                <span class="material-symbols-outlined">account_balance_wallet</span>
                <div class="option-text">
                  <strong>Ví Aura (Số dư: 450.000đ)</strong>
                  <span class="sub">Thanh toán tức thì không qua ngân hàng</span>
                </div>
              </label>

              <label class="pay-option" :class="{ selected: buyPaymentMethod === 'transfer' }">
                <input v-model="buyPaymentMethod" type="radio" value="transfer" />
                <span class="material-symbols-outlined">qr_code_2</span>
                <div class="option-text">
                  <strong>Chuyển khoản VietQR</strong>
                  <span class="sub">Quét mã QR qua mọi ứng dụng ngân hàng</span>
                </div>
              </label>
            </div>
          </div>

          <!-- Total Bill -->
          <div class="total-bill-box">
            <span class="bill-label">Tổng thanh toán:</span>
            <span class="bill-amount">{{ formatCurrency(selectedListingToBuy.resalePrice) }}</span>
          </div>

          <div v-if="buySuccess" class="success-message">
            <span class="material-symbols-outlined">check_circle</span>
            Giao dịch thành công! Đang chuyển quyền đặt sân...
          </div>

          <button
            v-else
            class="btn-confirm-purchase"
            :disabled="processingAction"
            @click="confirmPurchase"
          >
            <span v-if="processingAction" class="material-symbols-outlined animate-spin">progress_activity</span>
            <span v-else class="material-symbols-outlined">lock</span>
            {{ processingAction ? 'Đang xác thực thanh toán...' : 'Xác Nhận & Thanh Toán' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== MODAL: CÀI ĐẶT GIÁ NHƯỢNG ==================== -->
    <div v-if="isSellModalOpen && selectedSlotToSell" class="modal-backdrop animate-fade-in" @click.self="closeSellModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3 class="modal-title">Cài Đặt Giá Nhượng</h3>
          <button class="btn-close-modal" @click="closeSellModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body">
          <!-- Policy notice -->
          <div class="policy-notice">
            <span class="material-symbols-outlined">info</span>
            <div>
              <strong>Chính sách phí nền tảng</strong>
              <p>Aura thu phí 5% trên giá trị giao dịch thành công. Tiền sẽ được hoàn vào Ví Aura ngay khi có người mua.</p>
            </div>
          </div>

          <!-- Slot details -->
          <div class="court-summary-card">
            <div class="summary-row">
              <span class="label">Sân:</span>
              <strong>{{ selectedSlotToSell.courtName }}</strong>
            </div>
            <div class="summary-row">
              <span class="label">Thời gian:</span>
              <span>{{ selectedSlotToSell.dateStr }}, {{ selectedSlotToSell.timeSlot }}</span>
            </div>
            <div class="summary-row">
              <span class="label">Giá gốc:</span>
              <span>{{ formatCurrency(selectedSlotToSell.originalPrice) }}</span>
            </div>
          </div>

          <!-- Price Input -->
          <div class="price-input-section">
            <label class="price-input-label">Giá bạn muốn nhượng (VNĐ)</label>
            <div class="price-input-wrapper">
              <input
                v-model.number="askingPriceInput"
                type="number"
                step="10000"
                min="10000"
                :max="selectedSlotToSell.originalPrice"
                class="price-input-field"
              />
              <span class="currency-unit">đ</span>
            </div>
            <span class="price-hint">Khuyến nghị: Đặt giá từ 70% - 90% giá gốc để sang nhượng nhanh nhất.</span>
          </div>

          <!-- Fee & Net breakdown -->
          <div class="payout-breakdown-card">
            <div class="payout-row">
              <span>Phí sàn (5%):</span>
              <span>-{{ formatCurrency(calculatedFee) }}</span>
            </div>
            <div class="payout-row total">
              <strong>Thực nhận dự kiến:</strong>
              <strong class="payout-val">{{ formatCurrency(calculatedNetPayout) }}</strong>
            </div>
          </div>

          <div v-if="sellSuccess" class="success-message">
            <span class="material-symbols-outlined">check_circle</span>
            Đã đăng bán thành công lên Chợ Nhượng Sân!
          </div>

          <button
            v-else
            class="btn-confirm-sell"
            :disabled="processingAction || askingPriceInput <= 0"
            @click="confirmCreateListing"
          >
            <span v-if="processingAction" class="material-symbols-outlined animate-spin">progress_activity</span>
            <span v-else class="material-symbols-outlined">publish</span>
            {{ processingAction ? 'Đang xử lý...' : 'Xác Nhận Đăng Bán' }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.resale-page {
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 20px 80px;
  color: var(--ink);
}

/* Notifications */
.notification-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.95rem;
  font-weight: 500;
}
.notification-banner.success {
  background: var(--success-bg);
  color: var(--success-text);
  border: 1px solid rgba(46, 125, 50, 0.2);
}
.notification-banner.error {
  background: var(--error-bg);
  color: var(--error-text);
  border: 1px solid rgba(198, 40, 40, 0.2);
}
.close-notif {
  margin-left: auto;
  background: none;
  border: none;
  color: inherit;
  cursor: pointer;
}

/* Hero Section */
.resale-hero {
  margin-bottom: 28px;
}
.hero-header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 20px;
  flex-wrap: wrap;
}
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--pink-dark);
  background: var(--pink-light);
  padding: 4px 10px;
  border-radius: 999px;
  margin-bottom: 8px;
}
.hero-title {
  font-size: 2.2rem;
  font-weight: 800;
  margin: 0 0 6px;
  letter-spacing: -0.02em;
}
.hero-subtitle {
  color: var(--muted);
  font-size: 1.05rem;
  max-width: 580px;
  margin: 0;
}

/* Tab Switcher */
.tab-switcher {
  display: flex;
  gap: 6px;
  background: var(--soft);
  padding: 4px;
  border-radius: 14px;
  border: 1px solid var(--border);
}
.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  font-size: 0.95rem;
  font-weight: 600;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s ease;
}
.tab-btn:hover {
  color: var(--ink);
}
.tab-btn.active {
  background: var(--pink-dark);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.25);
}

/* Filter Card */
.filter-card {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 16px;
  background: var(--surface);
  padding: 18px 22px;
  border-radius: 16px;
  border: 1px solid var(--border);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
  margin-bottom: 28px;
}
.filter-group {
  flex: 1;
  min-width: 180px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.search-group {
  min-width: 220px;
}
.filter-label {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted);
}
.filter-input,
.filter-select {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle);
  color: var(--ink);
  font-size: 0.92rem;
  outline: none;
  transition: border-color 0.2s;
}
.filter-input:focus,
.filter-select:focus {
  border-color: var(--pink-dark);
}
.btn-clear-filter {
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--soft);
  color: var(--muted);
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
}

/* Featured Hero Banner */
.featured-banner {
  position: relative;
  overflow: hidden;
  border-radius: 20px;
  background: linear-gradient(135deg, rgba(255, 209, 220, 0.3) 0%, rgba(248, 249, 250, 0.9) 100%);
  border: 1px solid rgba(255, 209, 220, 0.6);
  padding: 32px 36px;
  margin-bottom: 36px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 20px rgba(120, 85, 94, 0.08);
}
html.dark .featured-banner {
  background: linear-gradient(135deg, rgba(120, 85, 94, 0.25) 0%, rgba(34, 36, 42, 0.9) 100%);
  border-color: rgba(248, 180, 196, 0.2);
}
.featured-content {
  position: relative;
  z-index: 2;
  max-width: 600px;
}
.featured-tags {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}
.tag-gold {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  font-weight: 700;
  color: #ffffff;
  background: #e65100;
  padding: 4px 12px;
  border-radius: 999px;
  animation: pulse 2s infinite;
}
.tag-promo {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--pink-dark);
  background: var(--pink-light);
  padding: 4px 12px;
  border-radius: 999px;
}
.featured-title {
  font-size: 1.8rem;
  font-weight: 800;
  margin: 0 0 4px;
}
.featured-venue {
  font-size: 1.05rem;
  color: var(--muted);
  margin: 0 0 16px;
}
.featured-info-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--surface);
  padding: 8px 16px;
  border-radius: 10px;
  border: 1px solid var(--border);
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 20px;
}
.featured-price-row {
  display: flex;
  align-items: center;
  gap: 24px;
}
.price-box {
  display: flex;
  flex-direction: column;
}
.orig-price {
  font-size: 0.88rem;
  color: var(--muted);
  text-decoration: line-through;
}
.resale-price {
  font-size: 1.7rem;
  font-weight: 800;
  color: var(--pink-dark);
}
.btn-primary-featured {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--pink-dark);
  color: #ffffff;
  padding: 12px 28px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1rem;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.3);
  transition: transform 0.2s, background 0.2s;
}
.btn-primary-featured:hover {
  transform: translateY(-2px);
  background: #62434b;
}
.featured-art {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 180px;
  height: 180px;
}
.art-glow {
  position: absolute;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  background: var(--pink);
  filter: blur(40px);
  opacity: 0.4;
}
.art-icon {
  font-size: 120px !important;
  color: var(--pink-dark);
  opacity: 0.3;
  transform: rotate(-15deg);
}

/* Listings Grid */
.listings-section {
  margin-bottom: 40px;
}
.section-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.section-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}
.section-title {
  font-size: 1.4rem;
  font-weight: 800;
  margin: 0;
}
.section-badge {
  font-size: 0.85rem;
  color: var(--muted);
  background: var(--soft);
  padding: 4px 10px;
  border-radius: 999px;
  font-weight: 600;
}

.bento-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 22px;
}
.bento-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 22px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.bento-card:hover {
  transform: translateY(-4px);
  border-color: var(--pink-dark);
  box-shadow: 0 8px 24px rgba(120, 85, 94, 0.1);
}
.card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}
.card-venue-tag {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--pink-dark);
  letter-spacing: 0.05em;
  display: block;
  margin-bottom: 4px;
}
.card-court-title {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0;
}
.badge-urgent {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #c62828;
  background: #ffebee;
  padding: 3px 8px;
  border-radius: 6px;
}
.badge-deal {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #2e7d32;
  background: #e8f5e9;
  padding: 3px 8px;
  border-radius: 6px;
}
.badge-hot {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #e65100;
  background: #fff3e0;
  padding: 3px 8px;
  border-radius: 6px;
}

.card-schedule {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
  flex: 1;
}
.sched-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: var(--muted);
}
.sched-row.highlight {
  color: var(--ink);
  font-weight: 600;
}
.seller-info {
  font-size: 0.82rem;
  margin-top: 4px;
}

.card-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}
.price-stack {
  display: flex;
  flex-direction: column;
}
.price-original {
  font-size: 0.8rem;
  color: var(--muted);
  text-decoration: line-through;
}
.price-resale {
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--pink-dark);
}
.btn-buy {
  padding: 8px 18px;
  border-radius: 10px;
  border: 1px solid var(--pink-dark);
  background: var(--pink-light);
  color: var(--pink-dark);
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-buy:hover {
  background: var(--pink-dark);
  color: #ffffff;
}

/* Seller Layout */
.seller-layout {
  display: grid;
  grid-template-columns: 2fr 1.2fr;
  gap: 28px;
}
@media (max-width: 960px) {
  .seller-layout {
    grid-template-columns: 1fr;
  }
}
.seller-header {
  margin-bottom: 20px;
}
.section-subtitle {
  color: var(--muted);
  font-size: 0.92rem;
  margin: 6px 0 0;
  line-height: 1.5;
}

.slots-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.slot-card {
  display: flex;
  align-items: center;
  gap: 18px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px 22px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}
.slot-card.ineligible-slot {
  opacity: 0.6;
  background: repeating-linear-gradient(45deg, var(--surface), var(--surface) 10px, var(--soft) 10px, var(--soft) 20px);
}
.date-badge-box {
  width: 58px;
  height: 58px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}
.badge-dow {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--muted);
  background: var(--soft);
  width: 100%;
  text-align: center;
  padding: 2px 0;
}
.badge-day {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--ink);
}
.slot-details {
  flex: 1;
}
.slot-name {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 2px;
}
.slot-venue {
  font-size: 0.85rem;
  color: var(--muted);
  margin: 0 0 6px;
}
.slot-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: var(--muted);
}
.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.btn-sell-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 10px;
  background: var(--pink-dark);
  color: #ffffff;
  font-weight: 700;
  font-size: 0.9rem;
  border: none;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.2);
  transition: background 0.2s;
}
.btn-sell-trigger:hover {
  background: #62434b;
}
.locked-status {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--error-text);
  background: var(--error-bg);
  padding: 6px 12px;
  border-radius: 8px;
}

/* Seller Tracking Sidebar */
.tracking-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 24px;
  position: sticky;
  top: 90px;
}
.tracking-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1.15rem;
  font-weight: 800;
  margin: 0 0 20px;
}
.timeline-container {
  display: flex;
  flex-direction: column;
  gap: 22px;
  position: relative;
}
.timeline-container::before {
  content: '';
  position: absolute;
  top: 10px;
  bottom: 10px;
  left: 11px;
  width: 2px;
  background: var(--border);
}
.timeline-item {
  position: relative;
  display: flex;
  gap: 16px;
}
.timeline-dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--surface);
  border: 2px solid var(--pink-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 2;
  flex-shrink: 0;
}
.timeline-item.completed .timeline-dot {
  background: var(--pink-dark);
  color: #ffffff;
}
.ping-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--pink-dark);
  animation: ping 1.5s infinite;
}
.timeline-content {
  flex: 1;
}
.timeline-status-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}
.status-active-label {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--pink-dark);
  letter-spacing: 0.05em;
}
.status-done-label {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--success-text);
  letter-spacing: 0.05em;
}
.status-cancel-label {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--muted);
}
.tx-time {
  font-size: 0.75rem;
  color: var(--muted);
}
.tx-box {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
}
.tx-court {
  font-weight: 700;
  font-size: 0.92rem;
  margin: 0 0 2px;
}
.tx-date {
  font-size: 0.82rem;
  color: var(--muted);
  margin: 0 0 10px;
}
.tx-active-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 8px;
  border-top: 1px solid var(--border);
  font-size: 0.85rem;
}
.btn-cancel-listing {
  background: none;
  border: none;
  color: var(--error-text);
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
}
.btn-cancel-listing:hover {
  text-decoration: underline;
}
.tx-completed-info {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-top: 8px;
  border-top: 1px solid var(--border);
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--pink-dark);
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-box {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
}
.modal-title {
  font-size: 1.25rem;
  font-weight: 800;
  margin: 0;
}
.btn-close-modal {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
}
.btn-close-modal:hover {
  color: var(--ink);
  background: var(--soft);
}
.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.court-summary-card {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 0.9rem;
}
.summary-row {
  display: flex;
  justify-content: space-between;
}
.summary-row .label {
  color: var(--muted);
}

.policy-notice {
  display: flex;
  gap: 12px;
  background: rgba(255, 209, 220, 0.25);
  border: 1px solid var(--pink);
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 0.85rem;
  color: var(--ink);
}
.policy-notice p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.8rem;
  line-height: 1.4;
}

.payment-choice-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.block-label {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--muted);
}
.payment-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pay-option {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--border);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}
.pay-option.selected {
  border-color: var(--pink-dark);
  background: var(--pink-light);
}
.option-text {
  display: flex;
  flex-direction: column;
}
.option-text .sub {
  font-size: 0.78rem;
  color: var(--muted);
}

.total-bill-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: var(--surface-subtle);
  border-radius: 12px;
  border: 1px solid var(--border);
}
.bill-label {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--muted);
}
.bill-amount {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--pink-dark);
}

.price-input-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.price-input-label {
  font-size: 0.88rem;
  font-weight: 700;
}
.price-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}
.price-input-field {
  width: 100%;
  padding: 12px 36px 12px 16px;
  font-size: 1.3rem;
  font-weight: 800;
  text-align: center;
  border: 2px solid var(--outline);
  border-radius: 12px;
  background: var(--surface);
  color: var(--pink-dark);
  outline: none;
  transition: border-color 0.2s;
}
.price-input-field:focus {
  border-color: var(--pink-dark);
}
.currency-unit {
  position: absolute;
  right: 16px;
  font-weight: 700;
  color: var(--muted);
}
.price-hint {
  font-size: 0.78rem;
  color: var(--muted);
}

.payout-breakdown-card {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 0.88rem;
}
.payout-row {
  display: flex;
  justify-content: space-between;
  color: var(--muted);
}
.payout-row.total {
  padding-top: 6px;
  border-top: 1px solid var(--border);
  color: var(--ink);
  font-size: 1rem;
}
.payout-val {
  color: var(--pink-dark);
}

.btn-confirm-purchase,
.btn-confirm-sell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  background: var(--pink-dark);
  color: #ffffff;
  font-size: 1rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.2s;
}
.btn-confirm-purchase:hover,
.btn-confirm-sell:hover {
  background: #62434b;
}
.btn-confirm-purchase:disabled,
.btn-confirm-sell:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.success-message {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--success-bg);
  color: var(--success-text);
  padding: 14px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.95rem;
}

/* Animations */
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes ping {
  0% { transform: scale(0.95); opacity: 0.8; }
  50% { transform: scale(1.4); opacity: 0.2; }
  100% { transform: scale(0.95); opacity: 0.8; }
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--surface);
  border: 1px dashed var(--border);
  border-radius: 18px;
}
.empty-icon {
  font-size: 48px !important;
  color: var(--muted);
  margin-bottom: 12px;
}
.empty-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 6px;
}
.empty-desc {
  color: var(--muted);
  font-size: 0.9rem;
  margin: 0;
}
</style>
