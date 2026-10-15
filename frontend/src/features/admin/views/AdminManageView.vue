<script setup lang="ts">
import { onMounted } from 'vue'
import { useAdminManageCourts } from '../composables/useAdminManageCourts'
import { useAdminPricingMatrix } from '../composables/useAdminPricingMatrix'

import ManageHeader from '../components/manage/ManageHeader.vue'
import ManageKpiBar from '../components/manage/ManageKpiBar.vue'
import AdminCourtsTable from '../components/manage/AdminCourtsTable.vue'
import CourtFormModal from '../components/manage/CourtFormModal.vue'
import CourtPricingModal from '../components/manage/CourtPricingModal.vue'
import CourtBlockModal from '../components/manage/CourtBlockModal.vue'
import CouponManagerModal from '../components/manage/CouponManagerModal.vue'

// Courts and Main Operations Composable
const {
  venues,
  courts,
  sportZones,
  coupons,
  error,
  notice,
  saving,
  loading,
  searchQuery,
  selectedVenueFilter,
  statusFilter,
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

    <!-- Courts Management Grid -->
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

    <!-- Modals -->
    <CourtFormModal
      v-model:name="courtForm.name"
      v-model:address="courtForm.address"
      v-model:sport-zone-id="courtForm.sport_zone_id"
      v-model:surface-type="courtForm.surface_type"
      v-model:capacity="courtForm.capacity"
      v-model:is-active="courtForm.is_active"
      v-model:description="courtForm.description"
      v-model:image-url="courtForm.image_url"
      :show="showCourtModal"
      :editing-court-id="editingCourtId"
      :venue-id="courtForm.venue_id"
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
</style>
