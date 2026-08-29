<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court } from '@/features/court/types/court'
import { useAdminManageCourts, type CourtForm } from '../composables/useAdminManageCourts'
import { useAdminPricingMatrix } from '../composables/useAdminPricingMatrix'

import AdminCourtsTable from '../components/manage/AdminCourtsTable.vue'
import CourtFormModal from '../components/manage/CourtFormModal.vue'
import CourtPricingModal from '../components/manage/CourtPricingModal.vue'
import CourtBlockModal from '../components/manage/CourtBlockModal.vue'
import CouponManagerModal from '../components/manage/CouponManagerModal.vue'
import AdminRefundsPanel from '../components/manage/AdminRefundsPanel.vue'

// Composables
const {
  courts,
  venues,
  sportZones,
  refunds,
  coupons,
  error,
  notice,
  saving,
  loading,
  searchQuery,
  selectedVenueFilter,
  statusFilter,
  activeVenues,
  filteredCourts,
  totalCourtsCount,
  activeCourtsCount,
  totalBlocksCount,
  pendingRefundsCount,
  isDemoAdmin,
  load,
  toggleCourtStatus,
} = useAdminManageCourts()

const {
  selectedCourtForPrice,
  selectedWeekdayTab,
  bulkScope,
  bulkStartHour,
  bulkEndHour,
  bulkPrice,
  bulkSaving,
  priceError,
  priceNotice,
  weekdayList,
  activeWeekdaySlots,
  modalPricingStats,
  morningSlots,
  afternoonSlots,
  peakSlots,
  nightSlots,
  openPriceModal,
  applyBulkPricing,
  deletePriceSlot,
} = useAdminPricingMatrix()

// Modal Triggers & Form States
const showCourtModal = ref(false)
const editingCourtId = ref<number | null>(null)
const courtForm = ref<CourtForm>({
  name: '',
  venue_id: 0,
  sport_zone_id: null,
  description: '',
  image_url: '',
  is_active: true,
})

const showPriceModal = ref(false)
const showBlockModal = ref(false)
const selectedCourtForBlock = ref<Court | null>(null)
const blockSaving = ref(false)

const showCouponModal = ref(false)
const couponSaving = ref(false)

// Active Sub-Tab: 'courts' | 'refunds'
const activeTab = ref<'courts' | 'refunds'>('courts')

const venueFilterOptions = computed(() => [
  { value: 'all', label: `Tất cả địa điểm (${venues.value.length})`, icon: 'domain' },
  ...venues.value.map((v) => ({
    value: v.id,
    label: v.name,
    sublabel: v.address || undefined,
    icon: 'location_on',
  })),
])

const statusFilterOptions = [
  { value: 'all', label: 'Tất cả trạng thái', icon: 'filter_list' },
  { value: 'active', label: 'Đang mở cửa', icon: 'check_circle' },
  { value: 'inactive', label: 'Đã tạm ngừng', icon: 'pause_circle' },
]

function handleStartCreateCourt() {
  editingCourtId.value = null
  courtForm.value = {
    name: '',
    venue_id: activeVenues.value[0]?.id || 0,
    sport_zone_id: null,
    description: '',
    image_url: '',
    is_active: true,
  }
  showCourtModal.value = true
}

function handleStartEditCourt(court: Court) {
  editingCourtId.value = court.id
  courtForm.value = {
    name: court.name,
    venue_id: court.venue?.id || activeVenues.value[0]?.id || 0,
    sport_zone_id: court.sport_zone_id || null,
    description: court.description || '',
    image_url: court.image_url || '',
    is_active: court.is_active !== false,
  }
  showCourtModal.value = true
}

async function handleSaveCourt() {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const payload = {
      ...courtForm.value,
      sport_zone_id: courtForm.value.sport_zone_id || null,
      image_url: courtForm.value.image_url || null,
    }

    if (isDemoAdmin.value) {
      if (editingCourtId.value) {
        courts.value = courts.value.map((c) =>
          c.id === editingCourtId.value
            ? {
                ...c,
                ...payload,
                venue: venues.value.find((v) => v.id === payload.venue_id) || c.venue,
                sport_zone: sportZones.value.find((z) => z.id === payload.sport_zone_id) || null,
                image_url: payload.image_url || undefined,
              }
            : c
        )
        notice.value = 'Đã mô phỏng cập nhật sân.'
      } else {
        const syntheticId = -Date.now()
        const newCourt: Court = {
          id: syntheticId,
          name: payload.name,
          venue_id: payload.venue_id,
          sport_zone_id: payload.sport_zone_id,
          venue: venues.value.find((v) => v.id === payload.venue_id) || {
            id: payload.venue_id,
            name: 'Địa điểm đã chọn',
            slug: 'selected-venue',
            address: 'Địa chỉ mẫu',
            google_maps_url: 'https://maps.app.goo.gl/demo',
            is_active: true,
            share_url: `${window.location.origin}/venues/selected-venue/directions`,
          },
          sport_zone: sportZones.value.find((z) => z.id === payload.sport_zone_id) || null,
          description: payload.description || undefined,
          image_url: payload.image_url || undefined,
          is_active: payload.is_active,
          price_slots: [],
          blocks: [],
        }
        courts.value = [newCourt, ...courts.value]
        notice.value = 'Đã mô phỏng tạo sân mới.'
      }
      showCourtModal.value = false
      return
    }

    if (editingCourtId.value) {
      await adminApi.updateCourt(editingCourtId.value, payload)
      notice.value = 'Đã cập nhật sân thành công.'
    } else {
      await adminApi.createCourt(payload)
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

function handleOpenPrice(court: Court) {
  openPriceModal(court)
  showPriceModal.value = true
}

function handleOpenBlock(court: Court) {
  selectedCourtForBlock.value = court
  showBlockModal.value = true
}

async function handleAddBlock(payload: { starts_at: string; ends_at: string; reason: string }) {
  if (!selectedCourtForBlock.value) return
  blockSaving.value = true
  try {
    if (isDemoAdmin.value) {
      const syntheticBlock = {
        id: -Date.now(),
        court_id: selectedCourtForBlock.value.id,
        starts_at: payload.starts_at,
        ends_at: payload.ends_at,
        reason: payload.reason,
      }
      selectedCourtForBlock.value.blocks = [syntheticBlock, ...(selectedCourtForBlock.value.blocks || [])]
      notice.value = 'Đã mô phỏng tạo lịch khoá sân.'
      return
    }
    await adminApi.createBlock(selectedCourtForBlock.value.id, payload)
    notice.value = 'Đã tạo lịch khoá sân thành công.'
    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Không thể tạo lịch khoá sân.')
  } finally {
    blockSaving.value = false
  }
}

async function handleDeleteBlock(blockId: number) {
  if (!selectedCourtForBlock.value) return
  try {
    if (isDemoAdmin.value) {
      selectedCourtForBlock.value.blocks = selectedCourtForBlock.value.blocks?.filter((b) => b.id !== blockId)
      notice.value = 'Đã mô phỏng xoá lịch khoá sân.'
      return
    }
    await adminApi.deleteBlock(selectedCourtForBlock.value.id, blockId)
    notice.value = 'Đã xoá lịch khoá sân.'
    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Không thể xoá lịch khoá sân.')
  }
}

async function handleCreateCoupon(payload: { code: string; discount_type: 'fixed' | 'percent'; discount_value: number; usage_limit: number }) {
  couponSaving.value = true
  try {
    if (isDemoAdmin.value) {
      coupons.value.unshift({
        id: -Date.now(),
        code: payload.code,
        discount_type: payload.discount_type,
        discount_value: payload.discount_value,
        usage_count: 0,
        usage_limit: payload.usage_limit,
        is_active: true,
      })
      notice.value = 'Đã mô phỏng tạo mã giảm giá mới.'
      return
    }
    await adminApi.createCoupon(payload)
    notice.value = 'Đã tạo mã ưu đãi thành công.'
    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Không thể tạo mã ưu đãi.')
  } finally {
    couponSaving.value = false
  }
}

async function handleApproveRefund(refundId: number) {
  try {
    if (isDemoAdmin.value) {
      refunds.value = refunds.value.filter((r) => r.id !== refundId)
      notice.value = 'Đã mô phỏng duyệt hoàn tiền.'
      return
    }
    await adminApi.processRefund(refundId, { status: 'approved' })
    notice.value = 'Đã duyệt yêu cầu hoàn tiền.'
    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Không thể duyệt hoàn tiền.')
  }
}

async function handleRejectRefund(refundId: number) {
  try {
    if (isDemoAdmin.value) {
      refunds.value = refunds.value.filter((r) => r.id !== refundId)
      notice.value = 'Đã mô phỏng từ chối hoàn tiền.'
      return
    }
    await adminApi.processRefund(refundId, { status: 'rejected' })
    notice.value = 'Đã từ chối hoàn tiền.'
    await load()
  } catch (e) {
    error.value = errorMessage(e, 'Không thể từ chối hoàn tiền.')
  }
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="admin-manage-page">
    <!-- Top Header -->
    <header class="admin-page-header">
      <div class="header-left">
        <h1 class="page-title">⚙️ Quản Trị Hệ Thống Sân & Hoạt Động</h1>
        <p class="page-sub">Quản lý danh sách sân thi đấu, bảng giá linh hoạt, lịch bảo trì và hoàn tiền.</p>
      </div>

      <div class="header-right">
        <button class="btn-top-action" @click="showCouponModal = true">
          🎟️ Mã Khuyến Mãi ({{ coupons.length }})
        </button>
        <button class="btn-top-action" @click="load">
          🔄 Làm mới
        </button>
      </div>
    </header>

    <!-- KPI Stats Cards -->
    <div class="manage-kpi-bar">
      <div class="kpi-card">
        <span class="kpi-lbl">Tổng số sân</span>
        <strong class="kpi-num">{{ totalCourtsCount }}</strong>
      </div>
      <div class="kpi-card text-green">
        <span class="kpi-lbl">Sân đang mở</span>
        <strong class="kpi-num">{{ activeCourtsCount }}</strong>
      </div>
      <div class="kpi-card text-yellow">
        <span class="kpi-lbl">Lịch bảo trì</span>
        <strong class="kpi-num">{{ totalBlocksCount }}</strong>
      </div>
      <div class="kpi-card text-pink">
        <span class="kpi-lbl">Hoàn tiền chờ duyệt</span>
        <strong class="kpi-num">{{ pendingRefundsCount }}</strong>
      </div>
    </div>

    <!-- Alert Banners -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Main Navigation Tabs -->
    <div class="manage-subtabs-row">
      <button
        class="subtab-btn"
        :class="{ active: activeTab === 'courts' }"
        @click="activeTab = 'courts'"
      >
        🏸 Danh Sách Sân ({{ totalCourtsCount }})
      </button>

      <button
        class="subtab-btn"
        :class="{ active: activeTab === 'refunds' }"
        @click="activeTab = 'refunds'"
      >
        💳 Duyệt Hoàn Tiền ({{ pendingRefundsCount }})
      </button>
    </div>

    <!-- Tab 1: Courts Table & Grid -->
    <div v-if="activeTab === 'courts'">
      <AdminCourtsTable
        :courts="filteredCourts"
        :venues="venues"
        :selected-venue-filter="selectedVenueFilter"
        :status-filter="statusFilter"
        :search-query="searchQuery"
        :venue-filter-options="venueFilterOptions"
        :status-filter-options="statusFilterOptions"
        @update:selected-venue-filter="selectedVenueFilter = $event"
        @update:status-filter="statusFilter = $event"
        @update:search-query="searchQuery = $event"
        @create-court="handleStartCreateCourt"
        @edit-court="handleStartEditCourt"
        @open-price-modal="handleOpenPrice"
        @open-block-modal="handleOpenBlock"
        @toggle-status="toggleCourtStatus"
      />
    </div>

    <!-- Tab 2: Refunds Management Panel -->
    <div v-else>
      <AdminRefundsPanel
        :refunds="refunds"
        :loading="loading"
        @approve="handleApproveRefund"
        @reject="handleRejectRefund"
      />
    </div>

    <!-- Modals -->
    <CourtFormModal
      :show="showCourtModal"
      :editing-court-id="editingCourtId"
      :court-form="courtForm"
      :venues="venues"
      :sport-zones="sportZones"
      :is-demo-admin="isDemoAdmin"
      :saving="saving"
      @close="showCourtModal = false"
      @save="handleSaveCourt"
    />

    <CourtPricingModal
      :show="showPriceModal"
      :court="selectedCourtForPrice"
      :selected-weekday-tab="selectedWeekdayTab"
      :bulk-scope="bulkScope"
      :bulk-start-hour="bulkStartHour"
      :bulk-end-hour="bulkEndHour"
      :bulk-price="bulkPrice"
      :bulk-saving="bulkSaving"
      :price-notice="priceNotice"
      :price-error="priceError"
      :weekday-list="weekdayList"
      :morning-slots="morningSlots"
      :afternoon-slots="afternoonSlots"
      :peak-slots="peakSlots"
      :nightSlots="nightSlots"
      :pricing-stats="modalPricingStats"
      @close="showPriceModal = false"
      @update:selected-weekday-tab="selectedWeekdayTab = $event"
      @update:bulk-scope="bulkScope = $event"
      @update:bulk-start-hour="bulkStartHour = $event"
      @update:bulk-end-hour="bulkEndHour = $event"
      @update:bulk-price="bulkPrice = $event"
      @apply-bulk="applyBulkPricing(isDemoAdmin, load)"
      @delete-slot="deletePriceSlot($event, isDemoAdmin, load)"
    />

    <CourtBlockModal
      :show="showBlockModal"
      :court="selectedCourtForBlock"
      :saving="blockSaving"
      @close="showBlockModal = false"
      @add-block="handleAddBlock"
      @delete-block="handleDeleteBlock"
    />

    <CouponManagerModal
      :show="showCouponModal"
      :coupons="coupons"
      :saving="couponSaving"
      @close="showCouponModal = false"
      @create-coupon="handleCreateCoupon"
    />
  </div>
</template>

<style scoped>
.admin-manage-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.admin-page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.header-right {
  display: flex;
  gap: 8px;
}

.btn-top-action {
  padding: 8px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.manage-kpi-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 18px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.kpi-lbl {
  font-size: 11px;
  color: var(--muted);
}

.kpi-num {
  font-size: 22px;
}

.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-pink { color: var(--pink-dark, #db2777); }

.manage-subtabs-row {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 8px;
}

.subtab-btn {
  padding: 10px 20px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.subtab-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

@media (max-width: 800px) {
  .manage-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
