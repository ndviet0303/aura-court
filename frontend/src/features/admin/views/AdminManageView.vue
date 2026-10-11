<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdminManageCourts } from '../composables/useAdminManageCourts'
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

// Courts and Main Operations Composable
const {
  venues,
  courts,
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
  activeTab,
  showCourtModal,
  editingCourtId,
  courtForm,
  showBlockModal,
  selectedCourtForBlock,
  blockSaving,
  showCouponModal,
  couponSaving,
  filteredCourts,
  totalCourtsCount,
  activeCourtsCount,
  totalBlocksCount,
  venueFilterOptions,
  statusFilterOptions,
  load,
  loadRefunds,
  toggleCourtStatus,
  openCreateCourtModal,
  openEditCourtModal,
  saveCourt,
  openBlockModal,
  addBlock,
  deleteBlock,
  openCouponModal,
  createCoupon,
  updateCoupon,
  transitionCoupon,
  transitionCampaign,
} = useAdminManageCourts()

// Pricing Matrix Composable
const {
  selectedCourtForPrice,
  showPriceModal,
  selectedWeekdayTab,
  bulkScope,
  bulkStartHour,
  bulkEndHour,
  bulkPrice,
  bulkSaving,
  priceError,
  priceNotice,
  weekdayList,
  morningSlots,
  afternoonSlots,
  peakSlots,
  nightSlots,
  modalPricingStats,
  openPriceModal,
  applyBulkPricing,
  deletePriceSlot,
} = useAdminPricingMatrix()

onMounted(() => {
  load()
})
</script>

<template>
  <div class="admin-manage-page">
    <!-- Top Header -->
    <ManageHeader :coupons-count="coupons.length" @open-coupons="openCouponModal" @refresh="load" />

    <!-- KPI Stats Cards -->
    <ManageKpiBar
      :total-courts-count="totalCourtsCount"
      :active-courts-count="activeCourtsCount"
      :total-blocks-count="totalBlocksCount"
      :loading="loading"
    />

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Navigation Subtabs -->
    <div class="manage-subtabs-row">
      <button
        type="button"
        class="subtab-btn"
        :class="{ active: activeTab === 'courts' }"
        @click="activeTab = 'courts'"
      >
        <span class="material-symbols-outlined icon-18">grid_view</span>
        <span>{{ t('admin.manage.tabCourts') }}</span>
        <span class="tab-count-badge">{{ totalCourtsCount }}</span>
      </button>

      <button
        type="button"
        class="subtab-btn"
        :class="{ active: activeTab === 'refunds' }"
        @click="activeTab = 'refunds'"
      >
        <span class="material-symbols-outlined icon-18">history</span>
        <span>{{ t('admin.manage.tabRefunds') }}</span>
        <span class="tab-count-badge">{{ refunds.length }}</span>
      </button>
    </div>

    <!-- Tab 1: Courts Management Grid -->
    <div v-if="activeTab === 'courts'">
      <AdminCourtsTable
        :courts="filteredCourts"
        :venues="venues"
        :selected-venue-filter="selectedVenueFilter"
        :status-filter="statusFilter"
        :search-query="searchQuery"
        :venue-filter-options="venueFilterOptions"
        :status-filter-options="statusFilterOptions"
        :loading="loading"
        @update:selected-venue-filter="selectedVenueFilter = $event"
        @update:status-filter="statusFilter = $event"
        @update:search-query="searchQuery = $event"
        @create-court="openCreateCourtModal"
        @edit-court="openEditCourtModal"
        @open-price-modal="openPriceModal"
        @open-block-modal="openBlockModal"
        @toggle-status="toggleCourtStatus"
      />
    </div>

    <!-- Tab 2: Refunds Management Panel -->
    <div v-else>
      <AdminRefundsPanel
        :refunds="refunds"
        :loading="loading"
        :reload="loadRefunds"
        @changed="loadRefunds"
      />
    </div>

    <!-- Modals -->
    <CourtFormModal
      v-model:name="courtForm.name"
      v-model:venue-id="courtForm.venue_id"
      v-model:sport-zone-id="courtForm.sport_zone_id"
      v-model:surface-type="courtForm.surface_type"
      v-model:capacity="courtForm.capacity"
      v-model:is-active="courtForm.is_active"
      v-model:description="courtForm.description"
      v-model:image-url="courtForm.image_url"
      :show="showCourtModal"
      :editing-court-id="editingCourtId"
      :venues="venues"
      :sport-zones="sportZones"
      :saving="saving"
      @close="showCourtModal = false"
      @save="saveCourt"
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
      :night-slots="nightSlots"
      :pricing-stats="modalPricingStats"
      @close="showPriceModal = false"
      @update:selected-weekday-tab="selectedWeekdayTab = $event"
      @update:bulk-scope="bulkScope = $event"
      @update:bulk-start-hour="bulkStartHour = $event"
      @update:bulk-end-hour="bulkEndHour = $event"
      @update:bulk-price="bulkPrice = $event"
      @apply-bulk="applyBulkPricing(load)"
      @delete-slot="deletePriceSlot($event, load)"
    />

    <CourtBlockModal
      :show="showBlockModal"
      :court="selectedCourtForBlock"
      :saving="blockSaving"
      @close="showBlockModal = false"
      @add-block="addBlock"
      @delete-block="deleteBlock"
    />

    <CouponManagerModal
      :show="showCouponModal"
      :coupons="coupons"
      :venues="venues"
      :courts="courts"
      :saving="couponSaving"
      @close="showCouponModal = false"
      @create-coupon="createCoupon"
      @update-coupon="updateCoupon"
      @transition-coupon="transitionCoupon"
      @transition-campaign="transitionCampaign"
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px;
  border-radius: 14px;
  background: var(--surface);
  border: 1px solid var(--outline);
  width: fit-content;
}

.subtab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.subtab-btn:hover:not(.active) {
  color: var(--ink);
  background: var(--surface-subtle);
}

.subtab-btn.active {
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  box-shadow: 0 2px 10px rgba(107, 45, 63, 0.25);
}

:global(html.dark) .subtab-btn.active {
  background: #6b2d3f;
  color: #faf7f3;
  border-color: rgba(201, 161, 90, 0.3);
  box-shadow: 0 2px 12px rgba(107, 45, 63, 0.45);
}

.tab-count-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--surface-subtle);
  color: var(--muted);
}

.subtab-btn.active .tab-count-badge {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}

:global(html.dark) .subtab-btn.active .tab-count-badge {
  background: rgba(250, 247, 243, 0.15);
  color: #faf7f3;
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
