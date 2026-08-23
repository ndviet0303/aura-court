<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '@/lib/api'
import type { Court, CourtBlock, PriceSlot, Refund, Venue } from '@/types'
import TimePicker from '@/components/TimePicker.vue'
import DateTimePicker from '@/components/DateTimePicker.vue'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatDateTime } from '@/i18n'

const { t } = useI18n()
const auth = useAuthStore()
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email?.endsWith('@demo.auracourt.vn'))

// Types
type CourtForm = { name: string; venue_id: number; description: string; image_url: string; is_active: boolean }
type PriceForm = { weekday: number; starts_at: string; ends_at: string; price: number }
type BlockForm = { starts_at: string; ends_at: string; reason: string }
type Coupon = { id: number; code: string; discount_type: 'fixed' | 'percent'; discount_value: number; usage_count: number; usage_limit?: number | null; is_active: boolean }

// State
const courts = ref<Court[]>([])
const venues = ref<Venue[]>([])
const refunds = ref<Refund[]>([])
const coupons = ref<Coupon[]>([])
const error = ref('')
const notice = ref('')
const saving = ref(false)
const loading = ref(false)

// Filter & Search
const searchQuery = ref('')
const selectedVenueFilter = ref<number | 'all'>('all')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')

// Modals State
const showCourtModal = ref(false)
const editingCourt = ref<number | null>(null)
const courtForm = ref<CourtForm>({ name: '', venue_id: 0, description: '', image_url: '', is_active: true })

const showPriceModal = ref(false)
const selectedCourtForPrice = ref<Court | null>(null)
const priceForm = ref<PriceForm>({ weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 120000 })

const showBlockModal = ref(false)
const selectedCourtForBlock = ref<Court | null>(null)
const blockForm = ref<BlockForm>({ starts_at: '', ends_at: '', reason: 'Bảo dưỡng định kỳ' })

const showCouponModal = ref(false)
const couponForm = ref({ code: '', discount_type: 'percent' as 'fixed' | 'percent', discount_value: 10, usage_limit: 20 })

const activeVenues = computed(() => venues.value.filter((venue) => venue.is_active))

// Filtered Courts
const filteredCourts = computed(() => {
  return courts.value.filter((c) => {
    const matchVenue = selectedVenueFilter.value === 'all' || c.venue?.id === selectedVenueFilter.value
    const matchStatus = statusFilter.value === 'all' || (statusFilter.value === 'active' ? c.is_active !== false : c.is_active === false)
    const matchSearch = !searchQuery.value.trim() || c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || (c.venue?.name || '').toLowerCase().includes(searchQuery.value.toLowerCase())
    return matchVenue && matchStatus && matchSearch
  })
})

// Stats
const totalCourtsCount = computed(() => courts.value.length)
const activeCourtsCount = computed(() => courts.value.filter((c) => c.is_active !== false).length)
const totalBlocksCount = computed(() => courts.value.reduce((sum, c) => sum + (c.blocks?.length || 0), 0))
const pendingRefundsCount = computed(() => refunds.value.length)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [loadedCourts, loadedVenues, loadedRefunds, loadedCoupons] = await Promise.all([
      api.get<Court[]>('/admin/courts'),
      api.get<Venue[]>('/admin/venues'),
      api.get<{ data: Refund[] }>('/admin/refunds'),
      api.get<{ data: Coupon[] }>('/admin/coupons'),
    ])
    courts.value = loadedCourts
    venues.value = loadedVenues
    refunds.value = loadedRefunds.data || []
    coupons.value = loadedCoupons.data || []
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được dữ liệu quản trị.')
  } finally {
    loading.value = false
  }
}

// Court CRUD Modal
function startCreateCourt() {
  editingCourt.value = null
  courtForm.value = {
    name: '',
    venue_id: activeVenues.value[0]?.id || 0,
    description: '',
    image_url: '',
    is_active: true,
  }
  showCourtModal.value = true
}

function startEditCourt(court: Court) {
  editingCourt.value = court.id
  courtForm.value = {
    name: court.name,
    venue_id: court.venue?.id || activeVenues.value[0]?.id || 0,
    description: court.description || '',
    image_url: court.image_url || '',
    is_active: court.is_active !== false,
  }
  showCourtModal.value = true
}

async function saveCourt() {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const payload = { ...courtForm.value, image_url: courtForm.value.image_url || null }
    if (isDemoAdmin.value) {
      if (editingCourt.value) {
        courts.value = courts.value.map((c) =>
          c.id === editingCourt.value
            ? {
                ...c,
                ...payload,
                venue: venues.value.find((v) => v.id === payload.venue_id) || c.venue,
                image_url: payload.image_url || undefined,
              }
            : c
        )
        notice.value = 'Đã mô phỏng cập nhật sân (không ghi vào dữ liệu thật).'
      } else {
        const syntheticId = -Date.now()
        const venue = activeVenues.value.find((item) => item.id === payload.venue_id)
        if (!venue) throw new Error('Chưa có địa điểm hoạt động để gán cho sân.')
        courts.value = [
          ...courts.value,
          {
            ...payload,
            id: syntheticId,
            venue,
            image_url: payload.image_url || undefined,
            price_slots: [],
            blocks: [],
          },
        ]
        notice.value = 'Đã mô phỏng tạo sân mới (không ghi vào dữ liệu thật).'
      }
      showCourtModal.value = false
      return
    }

    if (editingCourt.value) {
      await api.patch(`/admin/courts/${editingCourt.value}`, payload)
      notice.value = 'Đã cập nhật thông tin sân.'
    } else {
      await api.post('/admin/courts', payload)
      notice.value = 'Đã tạo sân mới thành công.'
    }
    showCourtModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu thông tin sân.')
  } finally {
    saving.value = false
  }
}

async function toggleCourtStatus(court: Court) {
  const nextStatus = court.is_active === false
  if (isDemoAdmin.value) {
    court.is_active = nextStatus
    notice.value = nextStatus ? 'Đã mô phỏng mở lại sân.' : 'Đã mô phỏng ngừng mở sân.'
    return
  }
  try {
    if (nextStatus) {
      await api.patch(`/admin/courts/${court.id}`, { is_active: true })
      notice.value = `Đã mở lại ${court.name}.`
    } else {
      await api.delete(`/admin/courts/${court.id}`)
      notice.value = `Đã ngừng mở ${court.name}.`
    }
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể thay đổi trạng thái sân.')
  }
}

// Price Slot Modal
function openPriceModal(court: Court) {
  selectedCourtForPrice.value = court
  priceForm.value = { weekday: 1, starts_at: '06:00', ends_at: '22:00', price: 120000 }
  showPriceModal.value = true
}

async function savePriceSlot() {
  if (!selectedCourtForPrice.value) return
  saving.value = true
  error.value = ''
  try {
    const court = selectedCourtForPrice.value
    if (isDemoAdmin.value) {
      const prices = [...(court.price_slots || [])]
      const index = prices.findIndex(
        (p) => p.weekday === priceForm.value.weekday && p.starts_at.slice(0, 5) === priceForm.value.starts_at
      )
      const next = { ...priceForm.value, id: index >= 0 ? prices[index]?.id : -Date.now(), court_id: court.id, is_active: true }
      if (index >= 0) prices[index] = next
      else prices.push(next)
      court.price_slots = prices
      notice.value = `Đã mô phỏng lưu giá cho ${court.name}.`
      return
    }

    await api.post(`/admin/courts/${court.id}/prices`, priceForm.value)
    notice.value = `Đã lưu khung giá cho ${court.name}.`
    await load()
    // refresh modal court ref
    selectedCourtForPrice.value = courts.value.find((c) => c.id === court.id) || court
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu khung giá.')
  } finally {
    saving.value = false
  }
}

async function deletePriceSlot(price: PriceSlot) {
  if (!selectedCourtForPrice.value || !price.id || !confirm('Xóa khung giá này?')) return
  const court = selectedCourtForPrice.value
  if (isDemoAdmin.value) {
    court.price_slots = (court.price_slots || []).filter((item) => item.id !== price.id)
    notice.value = 'Đã mô phỏng xóa khung giá.'
    return
  }
  try {
    await api.delete(`/admin/courts/${court.id}/prices/${price.id}`)
    notice.value = 'Đã xóa khung giá.'
    await load()
    selectedCourtForPrice.value = courts.value.find((c) => c.id === court.id) || court
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể xóa khung giá.')
  }
}

// Block Modal
function openBlockModal(court: Court) {
  selectedCourtForBlock.value = court
  const now = new Date()
  const startIso = new Date(now.getTime() + 3600000).toISOString().slice(0, 16)
  const endIso = new Date(now.getTime() + 7200000).toISOString().slice(0, 16)
  blockForm.value = { starts_at: startIso, ends_at: endIso, reason: 'Bảo dưỡng định kỳ' }
  showBlockModal.value = true
}

async function saveBlock() {
  if (!selectedCourtForBlock.value) return
  if (!blockForm.value.starts_at || !blockForm.value.ends_at) {
    error.value = 'Vui lòng nhập đầy đủ thời gian khóa sân.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const court = selectedCourtForBlock.value
    if (isDemoAdmin.value) {
      court.blocks = [...(court.blocks || []), { ...blockForm.value, id: -Date.now(), court_id: court.id }]
      notice.value = `Đã mô phỏng khóa lịch ${court.name}.`
      showBlockModal.value = false
      return
    }

    await api.post(`/admin/courts/${court.id}/blocks`, blockForm.value)
    notice.value = `Đã khóa lịch ${court.name}.`
    showBlockModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể khóa lịch.')
  } finally {
    saving.value = false
  }
}

async function deleteBlock(court: Court, block: CourtBlock) {
  if (!confirm('Gỡ bỏ lịch khóa này và mở lại khung giờ cho khách?')) return
  if (isDemoAdmin.value) {
    court.blocks = (court.blocks || []).filter((item) => item.id !== block.id)
    notice.value = 'Đã mô phỏng mở khóa lịch.'
    return
  }
  try {
    await api.delete(`/admin/courts/${court.id}/blocks/${block.id}`)
    notice.value = 'Đã mở khóa lịch.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể mở khóa lịch.')
  }
}

// Refund Management
async function processRefund(refund: Refund, status: 'processed' | 'rejected') {
  if (isDemoAdmin.value) {
    refunds.value = refunds.value.filter((item) => item.id !== refund.id)
    notice.value = status === 'processed' ? 'Đã mô phỏng duyệt hoàn tiền.' : 'Đã mô phỏng từ chối hoàn tiền.'
    return
  }
  try {
    await api.patch(`/admin/refunds/${refund.id}`, { status })
    notice.value = status === 'processed' ? 'Đã duyệt hoàn tiền thành công.' : 'Đã từ chối hoàn tiền.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể xử lý hoàn tiền.')
  }
}

// Coupon Modal
function openCreateCouponModal() {
  couponForm.value = { code: '', discount_type: 'percent', discount_value: 10, usage_limit: 20 }
  showCouponModal.value = true
}

async function createCoupon() {
  saving.value = true
  error.value = ''
  try {
    if (isDemoAdmin.value) {
      coupons.value.push({
        id: -Date.now(),
        code: couponForm.value.code.toUpperCase(),
        discount_type: couponForm.value.discount_type,
        discount_value: couponForm.value.discount_value,
        usage_limit: couponForm.value.usage_limit,
        usage_count: 0,
        is_active: true,
      })
      notice.value = 'Đã mô phỏng tạo mã giảm giá.'
      showCouponModal.value = false
      return
    }

    await api.post('/admin/coupons', {
      ...couponForm.value,
      code: couponForm.value.code.toUpperCase(),
    })
    notice.value = 'Đã tạo mã giảm giá thành công.'
    showCouponModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể tạo mã giảm giá.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="page-shell admin-manage-shell">
    <!-- Admin Navigation Tab Bar -->
    <nav class="admin-tab-nav" aria-label="Admin Navigation">
      <RouterLink to="/admin" class="admin-nav-item">
        <span class="material-symbols-outlined">analytics</span>
        <span>{{ t('admin.reports') }}</span>
      </RouterLink>
      <RouterLink to="/admin/manage" class="admin-nav-item active">
        <span class="material-symbols-outlined">sports_tennis</span>
        <span>{{ t('admin.manageCourts') }}</span>
      </RouterLink>
      <RouterLink to="/admin/schedule" class="admin-nav-item">
        <span class="material-symbols-outlined">schedule</span>
        <span>{{ t('admin.scheduleManage') }}</span>
      </RouterLink>
      <RouterLink to="/admin/venues" class="admin-nav-item">
        <span class="material-symbols-outlined">location_on</span>
        <span>{{ t('admin.manageVenues') }}</span>
      </RouterLink>
      <RouterLink to="/admin/pricing" class="admin-nav-item">
        <span class="material-symbols-outlined">price_change</span>
        <span>{{ t('admin.pricingCalendar') }}</span>
      </RouterLink>
      <RouterLink to="/admin/organization" class="admin-nav-item">
        <span class="material-symbols-outlined">corporate_fare</span>
        <span>{{ t('admin.organization') }}</span>
      </RouterLink>
    </nav>

    <!-- Header Section -->
    <header class="admin-manage-header">
      <div class="header-content">
        <div class="eyebrow-tag">
          <span class="material-symbols-outlined icon-16">sports_tennis</span>
          <span>VẬN HÀNH · MANAGEMENT</span>
        </div>
        <h1 class="page-title">Quản lý Sân đấu & Bảng giá</h1>
        <p class="muted max-w-2xl">
          Quản lý toàn bộ danh sách sân, thiết lập bảng giá theo khung giờ, lên lịch khóa bảo trì và duyệt yêu cầu hoàn tiền.
        </p>
      </div>

      <div class="header-actions">
        <button
          type="button"
          class="btn btn-primary flex items-center gap-xs"
          @click="startCreateCourt"
        >
          <span class="material-symbols-outlined icon-20">add</span>
          <span>Thêm sân mới</span>
        </button>
      </div>
    </header>

    <!-- Stats Summary Row -->
    <section class="stats-overview-grid">
      <div class="stat-card">
        <div class="stat-icon-wrap icon-primary">
          <span class="material-symbols-outlined">sports_tennis</span>
        </div>
        <div>
          <span class="stat-label">Tổng số sân</span>
          <h3 class="stat-number">{{ totalCourtsCount }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap icon-success">
          <span class="material-symbols-outlined">check_circle</span>
        </div>
        <div>
          <span class="stat-label">Sân đang mở</span>
          <h3 class="stat-number">{{ activeCourtsCount }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap icon-warning">
          <span class="material-symbols-outlined">build</span>
        </div>
        <div>
          <span class="stat-label">Đợt khóa bảo trì</span>
          <h3 class="stat-number">{{ totalBlocksCount }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrap icon-error">
          <span class="material-symbols-outlined">currency_exchange</span>
        </div>
        <div>
          <span class="stat-label">Yêu cầu hoàn tiền</span>
          <h3 class="stat-number">{{ pendingRefundsCount }}</h3>
        </div>
      </div>
    </section>

    <!-- Notifications & Alert Banners -->
    <div v-if="notice" class="notice-banner">
      <span class="material-symbols-outlined text-green">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="error-banner">
      <span class="material-symbols-outlined text-error">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="toolbar-filter-card">
      <div class="search-input-box">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm theo tên sân hoặc địa điểm..."
        />
      </div>

      <div class="filter-controls-group">
        <select v-model="selectedVenueFilter" class="filter-select">
          <option value="all">Tất cả địa điểm ({{ venues.length }})</option>
          <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
        </select>

        <select v-model="statusFilter" class="filter-select">
          <option value="all">Tất cả trạng thái</option>
          <option value="active">Đang mở cửa</option>
          <option value="inactive">Đã tạm ngừng</option>
        </select>
      </div>
    </div>

    <!-- Courts Cards Grid -->
    <section class="courts-cards-grid">
      <article
        v-for="court in filteredCourts"
        :key="court.id"
        class="court-manage-card"
        :class="{ 'is-inactive': court.is_active === false }"
      >
        <!-- Thumbnail & Badges -->
        <div class="court-card-media">
          <img
            :src="court.image_url || '/aura/court-1.jpg'"
            :alt="court.name"
            class="court-card-img"
          />
          <div class="media-overlay"></div>

          <!-- Active status toggle button -->
          <div class="status-toggle-corner">
            <button
              type="button"
              class="status-badge-btn"
              :class="court.is_active !== false ? 'badge-active' : 'badge-inactive'"
              @click="toggleCourtStatus(court)"
            >
              <span class="status-dot"></span>
              <span>{{ court.is_active !== false ? 'Đang mở' : 'Đã ngừng' }}</span>
            </button>
          </div>

          <div class="media-bottom-info">
            <span class="venue-name-chip">
              <span class="material-symbols-outlined icon-14">location_on</span>
              {{ court.venue?.name || 'Aura Club' }}
            </span>
          </div>
        </div>

        <!-- Court Body Details -->
        <div class="court-card-body">
          <h2 class="court-title-text">{{ court.name }}</h2>
          <p class="court-description-text">
            {{ court.description || 'Chưa có mô tả chi tiết cho sân đấu này.' }}
          </p>

          <!-- Price & Blocks Badges -->
          <div class="court-meta-chips">
            <span class="meta-chip">
              <span class="material-symbols-outlined icon-16 text-pink">sell</span>
              <strong>{{ court.price_slots?.length || 0 }}</strong> khung giá
            </span>
            <span class="meta-chip" :class="{ 'chip-warning': court.blocks?.length }">
              <span class="material-symbols-outlined icon-16">block</span>
              <strong>{{ court.blocks?.length || 0 }}</strong> lịch khóa
            </span>
          </div>

          <!-- Price Slots Preview -->
          <div v-if="court.price_slots?.length" class="price-slots-preview">
            <span
              v-for="p in (court.price_slots || []).slice(0, 3)"
              :key="p.id || `${p.weekday}-${p.starts_at}`"
              class="mini-price-pill"
            >
              T{{ p.weekday }} {{ p.starts_at.slice(0, 5) }} · {{ formatCurrency(p.price) }}
            </span>
            <span v-if="(court.price_slots?.length || 0) > 3" class="mini-price-pill more-pill">
              +{{ (court.price_slots?.length || 0) - 3 }} khung nữa
            </span>
          </div>

          <!-- Active Blocks Preview -->
          <div v-if="court.blocks?.length" class="blocks-preview-box">
            <div v-for="b in court.blocks" :key="b.id" class="mini-block-item">
              <span class="material-symbols-outlined icon-14 text-error">lock</span>
              <span class="text-xs truncate">{{ b.reason || 'Bảo trì' }} ({{ formatDateTime(b.starts_at) }})</span>
              <button
                type="button"
                class="btn-quick-unlock"
                title="Mở khóa lịch"
                @click="deleteBlock(court, b)"
              >
                ×
              </button>
            </div>
          </div>

          <!-- Action Buttons Bar -->
          <div class="court-actions-toolbar">
            <button
              type="button"
              class="card-action-btn primary-action"
              @click="openPriceModal(court)"
            >
              <span class="material-symbols-outlined icon-16">price_change</span>
              <span>Cài đặt giá</span>
            </button>

            <button
              type="button"
              class="card-action-btn secondary-action"
              @click="openBlockModal(court)"
            >
              <span class="material-symbols-outlined icon-16">lock_clock</span>
              <span>Khóa sân</span>
            </button>

            <button
              type="button"
              class="card-action-btn icon-only-action"
              title="Chỉnh sửa sân"
              @click="startEditCourt(court)"
            >
              <span class="material-symbols-outlined icon-18">edit</span>
            </button>
          </div>
        </div>
      </article>
    </section>

    <!-- Empty State -->
    <div v-if="!loading && !filteredCourts.length" class="empty-state-card">
      <span class="material-symbols-outlined empty-icon">sports_tennis</span>
      <h3>Không tìm thấy sân phù hợp</h3>
      <p class="muted">Thử thay đổi bộ lọc hoặc thêm sân mới.</p>
      <button type="button" class="btn btn-primary mt-3" @click="startCreateCourt">
        + Thêm sân mới
      </button>
    </div>

    <!-- Management Sections Row (Refunds & Coupons) -->
    <div class="bottom-management-grid">
      <!-- Section 1: Refund Requests -->
      <section class="panel-card refunds-panel">
        <div class="panel-head-row">
          <div class="panel-title-group">
            <span class="material-symbols-outlined text-coral icon-22">currency_exchange</span>
            <h2 class="panel-title">Yêu cầu hoàn tiền</h2>
          </div>
          <span class="badge badge-error">{{ refunds.length }} đang chờ</span>
        </div>

        <div v-if="!refunds.length" class="empty-panel-box">
          <span class="material-symbols-outlined icon-24 text-muted">check_circle</span>
          <p class="muted text-sm">Không có yêu cầu hoàn tiền nào đang chờ xử lý.</p>
        </div>

        <div v-else class="table-wrap">
          <table class="aura-admin-table">
            <thead>
              <tr>
                <th>Mã đặt sân</th>
                <th>Khách hàng</th>
                <th>Số tiền</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="refund in refunds" :key="refund.id">
                <td>
                  <span class="code-badge">{{ refund.booking?.code || 'BK-AURA' }}</span>
                </td>
                <td>
                  <div class="user-cell">
                    <span class="user-name-text">{{ refund.booking?.user?.name || 'Khách' }}</span>
                    <span class="user-email-text">{{ refund.booking?.user?.email || '' }}</span>
                  </div>
                </td>
                <td>
                  <strong class="refund-amount-text">{{ formatCurrency(refund.amount) }}</strong>
                </td>
                <td class="table-actions-cell">
                  <button
                    type="button"
                    class="btn-table-action btn-approve"
                    @click="processRefund(refund, 'processed')"
                  >
                    Duyệt
                  </button>
                  <button
                    type="button"
                    class="btn-table-action btn-reject"
                    @click="processRefund(refund, 'rejected')"
                  >
                    Từ chối
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Section 2: Coupons & Discounts -->
      <section class="panel-card coupons-panel">
        <div class="panel-head-row">
          <div class="panel-title-group">
            <span class="material-symbols-outlined text-pink icon-22">confirmation_number</span>
            <h2 class="panel-title">Mã giảm giá & Ưu đãi</h2>
          </div>
          <button
            type="button"
            class="circle-add-btn"
            title="Tạo mã giảm giá"
            @click="openCreateCouponModal"
          >
            <span class="material-symbols-outlined icon-20">add</span>
          </button>
        </div>

        <div class="coupons-chips-grid">
          <div
            v-for="coupon in coupons"
            :key="coupon.id"
            class="coupon-ticket-card"
          >
            <div class="ticket-left">
              <span class="ticket-code">{{ coupon.code }}</span>
              <span class="ticket-discount">
                {{ coupon.discount_type === 'percent' ? `Giảm ${coupon.discount_value}%` : `Giảm ${formatCurrency(coupon.discount_value)}` }}
              </span>
            </div>
            <div class="ticket-right">
              <span class="ticket-usage">
                {{ coupon.usage_count }} / {{ coupon.usage_limit || '∞' }} lượt
              </span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- MODAL 1: Create / Edit Court -->
    <div v-if="showCourtModal" class="modal-backdrop" @click.self="showCourtModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-pink">sports_tennis</span>
            <h3 class="font-semibold text-lg">{{ editingCourt ? 'Cập nhật sân' : 'Thêm sân mới' }}</h3>
          </div>
          <button type="button" class="btn-close" @click="showCourtModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body space-y-4" @submit.prevent="saveCourt">
          <div class="form-group">
            <label class="form-label">Tên sân:</label>
            <input
              v-model="courtForm.name"
              type="text"
              class="form-input"
              placeholder="VD: Sân 1 - Thảm BWF Cầu Giấy"
              required
              maxlength="120"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Địa điểm / Cụm sân:</label>
            <select v-model.number="courtForm.venue_id" class="form-input" required>
              <option v-for="venue in activeVenues" :key="venue.id" :value="venue.id">
                {{ venue.name }} ({{ venue.address }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Ảnh đại diện URL (tùy chọn):</label>
            <input
              v-model="courtForm.image_url"
              type="url"
              class="form-input"
              placeholder="https://images.unsplash.com/..."
            />
          </div>

          <div class="form-group">
            <label class="form-label">Mô tả đặc điểm sân:</label>
            <textarea
              v-model="courtForm.description"
              class="form-input"
              rows="3"
              placeholder="VD: Sân thảm BWF tiêu chuẩn 5 lớp, đèn LED chống chói 500 Lux..."
            ></textarea>
          </div>

          <div class="form-group">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="courtForm.is_active" type="checkbox" />
              <span class="font-medium text-sm">Cho phép sân hoạt động và mở đặt lịch</span>
            </label>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showCourtModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="saving || !activeVenues.length"
            >
              {{ saving ? t('common.submitting') : 'Lưu sân' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: Court Pricing Setup -->
    <div v-if="showPriceModal" class="modal-backdrop" @click.self="showPriceModal = false">
      <div class="modal-card max-w-xl">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-pink">price_change</span>
            <div>
              <h3 class="font-semibold text-lg">Bảng giá: {{ selectedCourtForPrice?.name }}</h3>
              <p class="muted text-xs">Cấu hình đơn giá theo từng thứ và khung giờ</p>
            </div>
          </div>
          <button type="button" class="btn-close" @click="showPriceModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body space-y-4">
          <!-- Form to add new price slot -->
          <form class="p-3 rounded-xl bg-surface-low border border-border space-y-3" @submit.prevent="savePriceSlot">
            <h4 class="font-semibold text-sm">+ Thêm / Cập nhật khung giá:</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
              <div class="form-group">
                <label class="text-xs font-semibold">Thứ (1-7):</label>
                <select v-model.number="priceForm.weekday" class="form-input text-xs">
                  <option :value="1">Thứ 2</option>
                  <option :value="2">Thứ 3</option>
                  <option :value="3">Thứ 4</option>
                  <option :value="4">Thứ 5</option>
                  <option :value="5">Thứ 6</option>
                  <option :value="6">Thứ 7</option>
                  <option :value="7">Chủ nhật</option>
                </select>
              </div>

              <div class="form-group">
                <label class="text-xs font-semibold">Bắt đầu:</label>
                <TimePicker v-model="priceForm.starts_at" />
              </div>

              <div class="form-group">
                <label class="text-xs font-semibold">Kết thúc:</label>
                <TimePicker v-model="priceForm.ends_at" />
              </div>

              <div class="form-group">
                <label class="text-xs font-semibold">Giá (₫/giờ):</label>
                <input
                  v-model.number="priceForm.price"
                  type="number"
                  step="1000"
                  class="form-input text-xs"
                  required
                />
              </div>
            </div>

            <div class="flex justify-end">
              <button type="submit" class="btn btn-primary text-xs py-1.5 px-3" :disabled="saving">
                {{ saving ? 'Đang lưu…' : 'Lưu khung giá' }}
              </button>
            </div>
          </form>

          <!-- Current price slots list -->
          <div class="space-y-2">
            <h4 class="font-semibold text-sm">Danh sách khung giá đang áp dụng:</h4>
            <div v-if="selectedCourtForPrice?.price_slots?.length" class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-56 overflow-y-auto">
              <div
                v-for="p in selectedCourtForPrice.price_slots"
                :key="p.id"
                class="p-2.5 rounded-lg bg-surface border border-border flex items-center justify-between"
              >
                <div>
                  <span class="font-semibold text-sm">Thứ {{ p.weekday }}: {{ p.starts_at.slice(0, 5) }} – {{ p.ends_at.slice(0, 5) }}</span>
                  <p class="text-xs text-primary font-bold">{{ formatCurrency(p.price) }}/giờ</p>
                </div>
                <button
                  type="button"
                  class="btn-delete-slot"
                  title="Xóa khung giá"
                  @click="deletePriceSlot(p)"
                >
                  <span class="material-symbols-outlined icon-16">delete</span>
                </button>
              </div>
            </div>
            <p v-else class="muted text-xs py-3 text-center">Chưa có khung giá riêng nào.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 3: Court Maintenance Block -->
    <div v-if="showBlockModal" class="modal-backdrop" @click.self="showBlockModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-coral">block</span>
            <h3 class="font-semibold text-lg">Khóa lịch: {{ selectedCourtForBlock?.name }}</h3>
          </div>
          <button type="button" class="btn-close" @click="showBlockModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body space-y-4" @submit.prevent="saveBlock">
          <div class="form-group">
            <label class="form-label">Thời gian bắt đầu khóa:</label>
            <DateTimePicker v-model="blockForm.starts_at" />
          </div>

          <div class="form-group">
            <label class="form-label">Thời gian kết thúc khóa:</label>
            <DateTimePicker v-model="blockForm.ends_at" />
          </div>

          <div class="form-group">
            <label class="form-label">Lý do khóa sân:</label>
            <input
              v-model="blockForm.reason"
              type="text"
              class="form-input"
              placeholder="VD: Thay thảm, bảo dưỡng đèn LED, sự kiện riêng..."
              required
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showBlockModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="saving"
            >
              {{ saving ? t('common.submitting') : 'Khóa lịch sân' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 4: Create Coupon -->
    <div v-if="showCouponModal" class="modal-backdrop" @click.self="showCouponModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-pink">confirmation_number</span>
            <h3 class="font-semibold text-lg">Tạo mã giảm giá mới</h3>
          </div>
          <button type="button" class="btn-close" @click="showCouponModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body space-y-4" @submit.prevent="createCoupon">
          <div class="form-group">
            <label class="form-label">Mã khuyến mãi (Code):</label>
            <input
              v-model="couponForm.code"
              type="text"
              class="form-input uppercase"
              placeholder="VD: AURA2026, WELCOME10"
              required
              maxlength="40"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="form-group">
              <label class="form-label">Loại giảm:</label>
              <select v-model="couponForm.discount_type" class="form-input">
                <option value="percent">Phần trăm (%)</option>
                <option value="fixed">Số tiền cố định (₫)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Giá trị giảm:</label>
              <input
                v-model.number="couponForm.discount_value"
                type="number"
                min="1"
                class="form-input"
                required
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Giới hạn số lượt dùng:</label>
            <input
              v-model.number="couponForm.usage_limit"
              type="number"
              min="1"
              class="form-input"
              required
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showCouponModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="saving"
            >
              {{ saving ? t('common.submitting') : 'Tạo mã giảm giá' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>
</template>

<style scoped>
.admin-manage-shell {
  max-width: 1280px;
  margin: 0 auto;
  padding: 30px 24px 90px;
}

/* Header */
.admin-manage-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 24px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

@media (min-width: 860px) {
  .admin-manage-header {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.eyebrow-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

/* Stats Overview Grid */
.stats-overview-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

@media (min-width: 1024px) {
  .stats-overview-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.stat-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 18px;
  border: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.stat-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-primary {
  background: rgba(255, 209, 220, 0.2);
  color: var(--pink-dark, #78555e);
}

.icon-success {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}

.icon-warning {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
}

.icon-error {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.stat-label {
  font-size: 12px;
  color: var(--muted, #6c757d);
  font-weight: 500;
}

.stat-number {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 2px 0 0;
  line-height: 1;
}

/* Notifications */
.notice-banner,
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 500;
}

.notice-banner {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.error-banner {
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

/* Toolbar Filter */
.toolbar-filter-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 14px 18px;
  border: 1px solid var(--border, #e0e4ea);
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

@media (min-width: 768px) {
  .toolbar-filter-card {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.search-input-box {
  position: relative;
  flex: 1;
  max-width: 420px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, #94a3b8);
  font-size: 18px;
}

.search-input {
  width: 100%;
  padding: 8px 14px 8px 38px;
  border-radius: 9999px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
}

.filter-controls-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.filter-select {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
}

/* Courts Cards Grid */
.courts-cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
  margin-bottom: 36px;
}

@media (min-width: 768px) {
  .courts-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1100px) {
  .courts-cards-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.court-manage-card {
  background: var(--surface, #ffffff);
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.court-manage-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.court-manage-card.is-inactive {
  opacity: 0.75;
}

.court-card-media {
  position: relative;
  height: 160px;
  overflow: hidden;
}

.court-card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.media-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.15) 0%, rgba(0, 0, 0, 0.75) 100%);
}

.status-toggle-corner {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
}

.status-badge-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  backdrop-filter: blur(6px);
}

.badge-active {
  background: rgba(16, 185, 129, 0.9);
  color: #ffffff;
}

.badge-inactive {
  background: rgba(239, 68, 68, 0.9);
  color: #ffffff;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ffffff;
}

.media-bottom-info {
  position: absolute;
  bottom: 10px;
  left: 12px;
  z-index: 2;
}

.venue-name-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  background: rgba(20, 22, 28, 0.82);
  backdrop-filter: blur(8px);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.15);
  font-size: 11px;
  font-weight: 600;
}

.court-card-body {
  padding: 16px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.court-title-text {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0 0 4px;
}

.court-description-text {
  font-size: 12px;
  color: var(--muted, #6c757d);
  margin: 0 0 12px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.court-meta-chips {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 8px;
  background: var(--surface-subtle, #f3f4f5);
  font-size: 11px;
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.chip-warning {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.25);
}

.price-slots-preview {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 12px;
}

.mini-price-pill {
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 10px;
  color: var(--muted, #6c757d);
}

.more-pill {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-weight: 600;
  border: none;
}

.blocks-preview-box {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 8px;
  padding: 6px 10px;
  margin-bottom: 12px;
  color: var(--ink, #191c1d);
}

.mini-block-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  color: var(--ink, #191c1d);
}

.btn-quick-unlock {
  border: none;
  background: transparent;
  color: #ef4444;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  padding: 0 4px;
}

.court-actions-toolbar {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.card-action-btn {
  padding: 7px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s ease;
}

.primary-action {
  flex: 1;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  justify-content: center;
}

.primary-action:hover {
  filter: brightness(1.08);
}

.secondary-action {
  background: var(--surface-subtle, #f3f4f5);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.secondary-action:hover {
  background: var(--soft, #e7e8e9);
}

.icon-only-action {
  padding: 6px;
  background: transparent;
  color: var(--muted, #6c757d);
  border: 1px solid var(--border, #e0e4ea);
}

.icon-only-action:hover {
  color: var(--ink, #191c1d);
  background: var(--surface-subtle, #f3f4f5);
}

/* Empty State */
.empty-state-card {
  text-align: center;
  padding: 60px 20px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px dashed var(--border, #cbd5e1);
  margin-bottom: 30px;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted, #94a3b8);
  margin-bottom: 8px;
}

/* Bottom Management Grid */
.bottom-management-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1024px) {
  .bottom-management-grid {
    grid-template-columns: 7fr 5fr;
  }
}

.panel-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 22px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.panel-head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.panel-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.badge-error {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.25);
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}

.empty-panel-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 30px 0;
}

/* Table */
.aura-admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.aura-admin-table th {
  text-align: left;
  padding: 10px 12px;
  color: var(--muted, #6c757d);
  font-weight: 600;
  border-bottom: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.aura-admin-table td {
  padding: 12px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
}

.code-badge {
  font-family: monospace;
  font-weight: 700;
  padding: 3px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, #f3f4f5);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  color: var(--ink, #191c1d);
}

.user-cell {
  display: flex;
  flex-direction: column;
}

.user-name-text {
  font-weight: 600;
}

.user-email-text {
  font-size: 11px;
  color: var(--muted, #6c757d);
}

.refund-amount-text {
  color: #ef4444;
  font-weight: 700;
}

.table-actions-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-table-action {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

.btn-approve {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}

.btn-approve:hover {
  background: rgba(16, 185, 129, 0.25);
}

.btn-reject {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.btn-reject:hover {
  background: rgba(239, 68, 68, 0.25);
}

/* Coupon Tickets */
.circle-add-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.circle-add-btn:hover {
  filter: brightness(1.1);
}

.coupons-chips-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.coupon-ticket-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px dashed var(--border, #cbd5e1);
}

.ticket-left {
  display: flex;
  flex-direction: column;
}

.ticket-code {
  font-weight: 800;
  font-size: 14px;
  letter-spacing: 0.05em;
  color: var(--ink, #191c1d);
}

.ticket-discount {
  font-size: 12px;
  font-weight: 600;
  color: var(--pink-dark, #78555e);
}

.ticket-usage {
  font-size: 11px;
  font-weight: 500;
  color: var(--muted, #6c757d);
  background: var(--surface, #ffffff);
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--border, #e0e4ea);
}

.btn-delete-slot {
  border: none;
  background: transparent;
  color: #ef4444;
  cursor: pointer;
  padding: 4px;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  background: var(--surface, #ffffff);
  border-radius: 20px;
  padding: 24px;
  width: 100%;
  max-width: 520px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
  animation: modal-enter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  color: var(--ink, #191c1d);
}

@keyframes modal-enter {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.btn-close {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 14px;
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  font-family: inherit;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.15);
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.btn {
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:hover {
  filter: brightness(1.08);
}

.btn-secondary {
  background: var(--surface-subtle, #e7e8e9);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.btn-secondary:hover {
  background: var(--soft, #dfe0e0);
}
</style>
