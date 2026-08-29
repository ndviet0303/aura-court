<script setup lang="ts">
import { onMounted } from 'vue'
import { useAdminVenues, type AdminVenueItem } from '../composables/useAdminVenues'

import VenueKpiBar from '../components/venues/VenueKpiBar.vue'
import VenueCard from '../components/venues/VenueCard.vue'
import VenueFormModal from '../components/venues/VenueFormModal.vue'
import VenueQrModal from '../components/venues/VenueQrModal.vue'

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
    showNotice(`Đã cập nhật cơ sở ${payload.name}!`)
  } else {
    // create simulation
    showNotice(`Đã tạo cơ sở mới ${payload.name}!`)
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
        <h1 class="page-title">🏢 Quản lý Cụm sân & Cơ sở (Venues)</h1>
        <p class="page-sub">Quản trị toàn bộ hệ thống cơ sở thi đấu, định vị Google Maps, mã QR đặt sân và trạng thái hoạt động.</p>
      </div>

      <div class="header-actions">
        <button class="btn-create-venue" @click="handleStartCreate">
          ➕ Thêm Cơ Sở Mới
        </button>
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
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm cơ sở theo tên, địa chỉ, quận huyện..."
        />
      </div>

      <select v-model="statusFilter" class="status-filter-select">
        <option value="all">Tất cả trạng thái</option>
        <option value="active">Đang mở cửa</option>
        <option value="inactive">Đang bảo trì / Tạm dừng</option>
      </select>
    </div>

    <!-- Venue Cards Grid -->
    <div v-if="filteredVenues.length === 0" class="empty-venues-state">
      <span>🏢</span>
      <h3>Không tìm thấy cơ sở nào</h3>
      <p>Thử tìm kiếm với từ khóa khác hoặc thêm cơ sở mới.</p>
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

.btn-create-venue {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
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
}

.search-input, .status-filter-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.search-input {
  width: 100%;
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

.empty-venues-state span {
  font-size: 48px;
  margin-bottom: 12px;
}
</style>
