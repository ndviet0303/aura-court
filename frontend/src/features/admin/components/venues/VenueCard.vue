<script setup lang="ts">
import type { AdminVenueItem } from '../../composables/useAdminVenues'

defineProps<{
  venue: AdminVenueItem
}>()

const emit = defineEmits<{
  (e: 'toggleActive', venue: AdminVenueItem): void
  (e: 'edit', venue: AdminVenueItem): void
  (e: 'showQr', venue: AdminVenueItem): void
}>()
</script>

<template>
  <div class="venue-admin-card" :class="{ 'is-inactive': !venue.is_active }">
    <div class="venue-card-top">
      <div class="venue-meta-info">
        <div class="venue-name-row">
          <h3 class="venue-title">{{ venue.name }}</h3>
          <span class="status-chip" :class="venue.is_active ? 'chip-active' : 'chip-maintenance'">
            {{ venue.is_active ? 'ACTIVE' : 'MAINTENANCE' }}
          </span>
        </div>
        <p class="venue-address">📍 {{ venue.address || 'Chưa cấu hình địa chỉ' }}</p>
      </div>

      <!-- Active switch -->
      <label class="switch-control-wrap" title="Bật/Tắt hoạt động cơ sở">
        <input
          type="checkbox"
          :checked="venue.is_active"
          @change="emit('toggleActive', venue)"
        />
        <span class="slider"></span>
      </label>
    </div>

    <div class="venue-stats-bar">
      <span>🏸 Quy mô: <strong>{{ venue.courts_count || 0 }} sân</strong></span>
      <span>🔗 Slug: <code class="font-mono">{{ venue.slug }}</code></span>
    </div>

    <div class="venue-card-actions">
      <button class="btn-action-sm" @click="emit('showQr', venue)">
        📱 Mã QR Đặt Sân
      </button>
      <button class="btn-action-sm" @click="emit('edit', venue)">
        ✏️ Chỉnh Sửa
      </button>
      <a
        v-if="venue.google_maps_url"
        :href="venue.google_maps_url"
        target="_blank"
        rel="noopener noreferrer"
        class="btn-action-sm link-maps"
      >
        🗺️ Bản Đồ
      </a>
    </div>
  </div>
</template>

<style scoped>
.venue-admin-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: transform 0.15s, box-shadow 0.15s;
}

.venue-admin-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
}

.venue-admin-card.is-inactive {
  opacity: 0.75;
}

.venue-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.venue-meta-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.venue-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.venue-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.status-chip {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
}

.chip-active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.chip-maintenance { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.venue-address {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.venue-stats-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
}

.venue-card-actions {
  display: flex;
  gap: 8px;
  margin-top: auto;
}

.btn-action-sm {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
}

.switch-control-wrap {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  cursor: pointer;
}

.switch-control-wrap input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #cbd5e1;
  transition: 0.2s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:checked + .slider:before {
  transform: translateX(20px);
}
</style>
