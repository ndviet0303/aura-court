<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court } from '@/features/court/types/court'
import { useAdminManageCourts, type CourtForm } from '../composables/useAdminManageCourts'
import { useAdminPricingMatrix } from '../composables/useAdminPricingMatrix'

import ManageHeader from '../components/manage/ManageHeader.vue'
import ManageKpiBar from '../components/manage/ManageKpiBar.vue'
import AdminCourtsTable from '../components/manage/AdminCourtsTable.vue'
import CourtFormModal from '../components/manage/CourtFormModal.vue'
import CourtPricingModal from '../components/manage/CourtPricingModal.vue'
import CourtBlockModal from '../components/manage/CourtBlockModal.vue'
import CouponManagerModal from '../components/manage/CouponManagerModal.vue'
import AdminRefundsPanel from '../components/manage/AdminRefundsPanel.vue'

const { t } = useI18n()

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
  { value: 'all', label: t('admin.manage.allVenuesFilter', { count: venues.value.length }), icon: 'domain' },
  ...venues.value.map((v) => ({
    value: v.id,
    label: resolveLocalizedText(v.name),
    sublabel: v.address || undefined,
    icon: 'location_on',
  })),
])

const statusFilterOptions = computed(() => [
  { value: 'all', label: t('admin.manage.allStatusFilter'), icon: 'filter_list' },
  { value: 'active', label: t('admin.manage.statusOpen'), icon: 'check_circle' },
  { value: 'inactive', label: t('admin.manage.statusClosed'), icon: 'pause_circle' },
])

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
            : c,
        )
        notice.value = t('admin.manage.simulatedUpdateCourt')
      } else {
        const syntheticId = -Date.now()
        const newCourt: Court = {
          id: syntheticId,
          name: payload.name,
          venue_id: payload.venue_id,
          sport_zone_id: payload.sport_zone_id,
          venue: venues.value.find((v) => v.id === payload.venue_id) || {
            id: payload.venue_id,
            name: 'Downtown Arena',
            slug: 'downtown-arena',
            address: 'District 1',
            google_maps_url: 'https://maps.app.goo.gl/demo',
            is_active: true,
            share_url: `${window.location.origin}/venues/downtown-arena/directions`,
          },
          sport_zone: sportZones.value.find((z) => z.id === payload.sport_zone_id) || null,
          description: payload.description || undefined,
          image_url: payload.image_url || undefined,
          is_active: payload.is_active,
          price_slots: [],
          blocks: [],
        }
        courts.value = [newCourt, ...courts.value]
        notice.value = t('admin.manage.simulatedCreateCourt')
      }
      showCourtModal.value = false
      return
    }

    if (editingCourtId.value) {
      await adminApi.updateCourt(editingCourtId.value, payload)
      notice.value = t('admin.manage.updateCourtSuccess')
    } else {
      await adminApi.createCourt(payload)
      notice.value = t('admin.manage.createCourtSuccess')
    }
    showCourtModal.value = false
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('admin.manage.saveCourtError'))
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
      notice.value = t('admin.manage.simulatedCreateBlock')
      return
    }
    await adminApi.createBlock(selectedCourtForBlock.value.id, payload)
    notice.value = t('admin.manage.createBlockSuccess')
    await load()
  } catch (e) {
    error.value = errorMessage(e, t('admin.manage.createBlockError'))
  } finally {
    blockSaving.value = false
  }
}

async function handleDeleteBlock(blockId: number) {
  if (!selectedCourtForBlock.value) return
  try {
    if (isDemoAdmin.value) {
      selectedCourtForBlock.value.blocks = selectedCourtForBlock.value.blocks?.filter((b) => b.id !== blockId)
      notice.value = t('admin.manage.simulatedDeleteBlock')
      return
    }
    await adminApi.deleteBlock(selectedCourtForBlock.value.id, blockId)
    notice.value = t('admin.manage.deleteBlockSuccess')
    await load()
  } catch (e) {
    error.value = errorMessage(e, t('admin.manage.deleteBlockError'))
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
      notice.value = t('admin.manage.simulatedCreateCoupon')
      return
    }
    await adminApi.createCoupon(payload)
    notice.value = t('admin.manage.createCouponSuccess')
    await load()
  } catch (e) {
    error.value = errorMessage(e, t('admin.manage.createCouponError'))
  } finally {
    couponSaving.value = false
  }
}

async function handleApproveRefund(refundId: number) {
  try {
    if (isDemoAdmin.value) {
      refunds.value = refunds.value.filter((r) => r.id !== refundId)
      notice.value = t('admin.manage.simulatedApproveRefund')
      return
    }
    await adminApi.processRefund(refundId, { status: 'approved' })
    notice.value = t('admin.manage.approveRefundSuccess')
    await load()
  } catch (e) {
    error.value = errorMessage(e, t('admin.manage.approveRefundError'))
  }
}

async function handleRejectRefund(refundId: number) {
  try {
    if (isDemoAdmin.value) {
      refunds.value = refunds.value.filter((r) => r.id !== refundId)
      notice.value = t('admin.manage.simulatedRejectRefund')
      return
    }
    await adminApi.processRefund(refundId, { status: 'rejected' })
    notice.value = t('admin.manage.rejectRefundSuccess')
    await load()
  } catch (e) {
    error.value = errorMessage(e, t('admin.manage.rejectRefundError'))
  }
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="admin-manage-page">
    <!-- Top Header -->
    <ManageHeader
      :coupons-count="coupons.length"
      @open-coupons="showCouponModal = true"
      @refresh="load"
    />

    <!-- KPI Stats Cards -->
    <ManageKpiBar
      :total-courts-count="totalCourtsCount"
      :active-courts-count="activeCourtsCount"
      :total-blocks-count="totalBlocksCount"
      :pending-refunds-count="pendingRefundsCount"
    />

    <!-- Alert Banners -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Main Navigation Subtabs -->
    <div class="manage-subtabs-row">
      <button
        type="button"
        class="subtab-btn"
        :class="{ active: activeTab === 'courts' }"
        @click="activeTab = 'courts'"
      >
        <span class="material-symbols-outlined icon-18">grid_view</span>
        <span>{{ t('admin.manage.tabCourts') }} ({{ totalCourtsCount }})</span>
      </button>

      <button
        type="button"
        class="subtab-btn"
        :class="{ active: activeTab === 'refunds' }"
        @click="activeTab = 'refunds'"
      >
        <span class="material-symbols-outlined icon-18">currency_exchange</span>
        <span>{{ t('admin.manage.tabRefunds') }} ({{ pendingRefundsCount }})</span>
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

.manage-subtabs-row {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 8px;
}

.subtab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.subtab-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
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

.icon-18 {
  font-size: 18px;
}
</style>
