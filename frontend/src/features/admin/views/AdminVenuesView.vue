<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminVenues, type AdminVenueItem } from '../composables/useAdminVenues'

import VenueKpiBar from '../components/venues/VenueKpiBar.vue'
import VenueCard from '../components/venues/VenueCard.vue'
import VenueFormModal from '../components/venues/VenueFormModal.vue'
import VenueQrModal from '../components/venues/VenueQrModal.vue'

const { t } = useI18n()

const {
  loading,
  notice,
  error,
  searchQuery,
  statusFilter,
  showVenueModal,
  editingVenue,
  showQrModal,
  selectedVenueForQr,
  filteredVenues,
  totalVenuesCount,
  totalCourtsCount,
  loadData,
  showNotice,
  toggleVenueActive,
} = useAdminVenues()

function handleStartCreate() {
  editingVenue.value = null
  showVenueModal.value = true
}

function handleStartEdit(venue: AdminVenueItem) {
  editingVenue.value = venue
  showVenueModal.value = true
}

function handleShowQr(venue: AdminVenueItem) {
  selectedVenueForQr.value = venue
  showQrModal.value = true
}

function handleSaveVenue(payload: any) {
  if (editingVenue.value) {
    Object.assign(editingVenue.value, payload)
    showNotice(t('admin.venues.updateVenueSuccess', { name: payload.name }))
  } else {
    showNotice(t('admin.venues.createVenueSuccess', { name: payload.name }))
  }
  showVenueModal.value = false
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
        <AuraButton variant="primary" @click="handleStartCreate">
          <span class="material-symbols-outlined icon-18">add_business</span>
          <span>{{ t('admin.venues.addNewVenue') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- KPI Summary Bar -->
    <VenueKpiBar
      :total-venues="totalVenuesCount"
      :total-courts="totalCourtsCount"
    />

    <!-- Filter & Search Toolbar -->
    <div class="venues-toolbar">
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          :placeholder="t('admin.venues.searchPlaceholder')"
        />
      </div>

      <select v-model="statusFilter" class="status-filter-select">
        <option value="all">{{ t('admin.venues.allStatuses') }}</option>
        <option value="active">{{ t('admin.venues.statusActive') }}</option>
        <option value="inactive">{{ t('admin.venues.statusInactive') }}</option>
      </select>
    </div>

    <!-- Venue Cards Grid -->
    <div v-if="filteredVenues.length === 0" class="empty-venues-state">
      <span class="material-symbols-outlined empty-icon">domain_disabled</span>
      <h3>{{ t('admin.venues.noVenuesFound') }}</h3>
      <p>{{ t('admin.venues.noVenuesSub') }}</p>
    </div>

    <div v-else class="venues-grid">
      <VenueCard
        v-for="v in filteredVenues"
        :key="v.id"
        :venue="v"
        @toggle-active="toggleVenueActive"
        @edit="handleStartEdit"
        @show-qr="handleShowQr"
      />
    </div>

    <!-- Modals -->
    <VenueFormModal
      :show="showVenueModal"
      :venue="editingVenue"
      @close="showVenueModal = false"
      @save="handleSaveVenue"
    />

    <VenueQrModal
      :show="showQrModal"
      :venue="selectedVenueForQr"
      @close="showQrModal = false"
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
  gap: 12px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
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
}

.header-actions {
  display: flex;
  align-items: center;
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
}

.search-icon {
  font-size: 20px;
  color: var(--muted);
}

.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: var(--ink);
}

.status-filter-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.venues-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 16px;
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
  font-size: 48px;
  color: var(--muted);
  margin-bottom: 12px;
}

.icon-18 {
  font-size: 18px;
}
</style>
