<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, ErrorModal } from '@/shared/components'
import { OPERATING_STATUSES } from '@/features/court/types/court'
import { useAdminVenues, type AdminVenueItem } from '../composables/useAdminVenues'
import { useLifecycleCommands } from '../composables/useLifecycleCommands'
import type { LifecycleCommand } from '../types/lifecycle'

import VenueKpiBar from '../components/venues/VenueKpiBar.vue'
import VenueCard from '../components/venues/VenueCard.vue'
import VenueCardSkeleton from '../components/venues/VenueCardSkeleton.vue'
import VenueFormModal from '../components/venues/VenueFormModal.vue'
import VenueQrModal from '../components/venues/VenueQrModal.vue'
import VenueReceivingConnectionModal from '../components/venues/VenueReceivingConnectionModal.vue'
import OperationalStopDialog from '../components/lifecycle/OperationalStopDialog.vue'

const { t } = useI18n()
const receivingVenue = ref<AdminVenueItem | null>(null)

const {
  loading,
  saving,
  notice,
  error,
  searchQuery,
  statusFilter,
  showVenueModal,
  editingVenue,
  bookingPolicy,
  loadingBookingPolicy,
  savingBookingPolicy,
  showQrModal,
  selectedVenueForQr,
  filteredVenues,
  totalVenuesCount,
  activeVenuesCount,
  totalCourtsCount,
  mapsLinkedCount,
  loadData,
  showNotice,
  saveVenue,
  loadBookingPolicy,
  saveBookingPolicy,
} = useAdminVenues()

const lifecycle = reactive(
  useLifecycleCommands(async (message) => {
    await loadData()
    showNotice(message)
  }),
)

function handleCommand(venue: AdminVenueItem, command: LifecycleCommand) {
  const organizationId = venue.organization_id
  if (organizationId) void lifecycle.run({ type: 'venue', id: venue.id, organizationId }, command)
}

function handleStartCreate() {
  editingVenue.value = null
  bookingPolicy.value = null
  showVenueModal.value = true
}

function handleStartEdit(venue: AdminVenueItem) {
  editingVenue.value = venue
  bookingPolicy.value = null
  showVenueModal.value = true
  void loadBookingPolicy(venue)
}

function handleShowQr(venue: AdminVenueItem) {
  selectedVenueForQr.value = venue
  showQrModal.value = true
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-venues-view">
    <!-- Header Section -->
    <header class="venues-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">location_on</span>
          <h1 class="page-title">{{ t('admin.venues.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.venues.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="secondary" :disabled="loading" @click="loadData">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }"
            >refresh</span
          >
          <span>{{ t('admin.venues.refreshBtn') }}</span>
        </AuraButton>
        <AuraButton variant="primary" @click="handleStartCreate">
          <span class="material-symbols-outlined icon-18">add_business</span>
          <span>{{ t('admin.venues.addNewVenue') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">
      <span class="material-symbols-outlined icon-16">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-pill-error">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- KPI Summary Bar -->
    <VenueKpiBar
      :total-venues="totalVenuesCount"
      :active-venues="activeVenuesCount"
      :total-courts="totalCourtsCount"
      :maps-linked="mapsLinkedCount"
      :loading="loading"
    />

    <!-- Filter & Search Toolbar -->
    <div class="venues-toolbar">
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          v-model="searchQuery"
          type="text"
          class="venues-search-input"
          :placeholder="t('admin.venues.searchPlaceholder')"
        />
        <button v-if="searchQuery" type="button" class="clear-search-btn" @click="searchQuery = ''">
          ✕
        </button>
      </div>

      <div class="toolbar-right">
        <select v-model="statusFilter" class="status-filter-select">
          <option value="all">{{ t('admin.venues.allStatuses') }}</option>
          <option v-for="status in OPERATING_STATUSES" :key="status" :value="status">
            {{ t(`admin.lifecycle.status.${status}`) }}
          </option>
        </select>
        <span v-if="!loading" class="results-count-badge">
          {{ t('admin.venues.resultsCount', { count: filteredVenues.length }) }}
        </span>
      </div>
    </div>

    <!-- Skeleton Loading Grid -->
    <div v-if="loading" class="venues-grid">
      <VenueCardSkeleton v-for="i in 6" :key="i" />
    </div>

    <!-- Empty Venues State -->
    <div v-else-if="filteredVenues.length === 0" class="empty-venues-state">
      <span class="material-symbols-outlined empty-icon">domain_disabled</span>
      <h3 class="empty-title">{{ t('admin.venues.noVenuesFound') }}</h3>
      <p class="empty-sub">{{ t('admin.venues.noVenuesSub') }}</p>
      <AuraButton variant="primary" class="mt-4" @click="handleStartCreate">
        <span class="material-symbols-outlined icon-18">add_business</span>
        <span>{{ t('admin.venues.addNewVenue') }}</span>
      </AuraButton>
    </div>

    <!-- Real Venue Cards Grid -->
    <div v-else class="venues-grid">
      <VenueCard
        v-for="v in filteredVenues"
        :key="v.id"
        :venue="v"
        :owner="lifecycle.isOwnerOf(v.organization_id)"
        :busy="lifecycle.busy"
        @command="handleCommand"
        @edit="handleStartEdit"
        @show-qr="handleShowQr"
        @show-receiving="(venue) => (receivingVenue = venue)"
      />
    </div>

    <!-- Modals -->
    <VenueFormModal
      :show="showVenueModal"
      :venue="editingVenue"
      :saving="saving"
      :booking-policy="bookingPolicy"
      :loading-booking-policy="loadingBookingPolicy"
      :saving-booking-policy="savingBookingPolicy"
      @close="showVenueModal = false"
      @save="saveVenue"
      @save-policy="saveBookingPolicy"
    />

    <VenueQrModal :show="showQrModal" :venue="selectedVenueForQr" @close="showQrModal = false" />

    <VenueReceivingConnectionModal :venue="receivingVenue" @close="receivingVenue = null" />

    <OperationalStopDialog
      :command="lifecycle.stopRequest?.command ?? null"
      target="venue"
      owner
      :loading="lifecycle.busy"
      @close="lifecycle.closeStop"
      @submit="lifecycle.submitStop"
    />
    <ErrorModal
      :open="lifecycle.failureOpen"
      v-bind="lifecycle.failureModal"
      @close="lifecycle.closeFailure"
    />
  </div>
</template>

<style scoped>
.admin-venues-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.venues-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.4;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.alert-pill,
.alert-pill-error {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  background: #ef4444;
}

.venues-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-wrap {
  flex: 1;
  min-width: 260px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: border-color 0.15s ease;
}

.search-input-wrap:focus-within {
  border-color: var(--pink-dark, #db2777);
}

.search-icon {
  font-size: 20px;
  color: var(--muted);
}

.venues-search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: var(--ink);
}

.clear-search-btn {
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 2px 6px;
  font-size: 12px;
  border-radius: 4px;
}

.clear-search-btn:hover {
  color: var(--ink);
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-filter-select {
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  cursor: pointer;
  outline: none;
}

.status-filter-select:focus {
  border-color: var(--pink-dark, #db2777);
}

.results-count-badge {
  font-size: 12px;
  color: var(--muted);
  font-weight: 600;
}

.venues-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 18px;
}

.empty-venues-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-icon {
  font-size: 52px;
  color: var(--muted);
  margin-bottom: 12px;
}

.empty-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.empty-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 400px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}
.mt-4 {
  margin-top: 16px;
}
</style>
