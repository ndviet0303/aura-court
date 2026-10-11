<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { AdminVenueItem } from '../../composables/useAdminVenues'

defineProps<{
  venue: AdminVenueItem
}>()

const emit = defineEmits<{
  (e: 'toggleActive', venue: AdminVenueItem): void
  (e: 'edit', venue: AdminVenueItem): void
  (e: 'showQr', venue: AdminVenueItem): void
  (e: 'showReadiness', venue: AdminVenueItem): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="venue-admin-card" :class="{ 'is-inactive': !venue.is_active }">
    <div class="venue-card-top">
      <div class="venue-meta-info">
        <div class="venue-name-row">
          <h3 class="venue-title">{{ resolveLocalizedText(venue.name) }}</h3>
          <span class="status-chip" :class="venue.is_active ? 'chip-active' : 'chip-maintenance'">
            {{
              venue.is_active ? t('admin.venues.statusActive') : t('admin.venues.statusInactive')
            }}
          </span>
        </div>
        <p class="venue-address">
          <span class="material-symbols-outlined icon-14">location_on</span>
          <span>{{ resolveLocalizedText(venue.address) || t('admin.venues.noAddress') }}</span>
        </p>
      </div>

      <!-- Active switch toggle -->
      <label class="switch-control-wrap" :title="t('admin.venues.toggleStatusTooltip')">
        <input
          type="checkbox"
          :checked="venue.is_active"
          :disabled="!!venue.status && !['published', 'active'].includes(venue.status)"
          @change="emit('toggleActive', venue)"
        />
        <span class="slider"></span>
      </label>
    </div>

    <!-- Sport Zones Tags if available -->
    <div v-if="venue.sport_zones && venue.sport_zones.length > 0" class="venue-zones-row">
      <span v-for="zone in venue.sport_zones" :key="zone.id" class="zone-tag">
        {{ resolveLocalizedText(zone.name) }}
      </span>
    </div>

    <div class="venue-stats-bar">
      <span class="stat-item">
        <span class="material-symbols-outlined icon-16">sports_tennis</span>
        <span>{{ t('admin.venues.courtsScale', { count: venue.courts_count || 0 }) }}</span>
      </span>
      <span class="stat-item slug-stat">
        <span class="material-symbols-outlined icon-16">link</span>
        <code class="font-mono">{{ venue.slug }}</code>
      </span>
    </div>

    <div class="venue-card-actions">
      <button type="button" class="btn-action-sm" @click="emit('showReadiness', venue)">
        <span class="material-symbols-outlined icon-16">account_balance</span>
        <span>{{ t('admin.venues.readinessBtn') }}</span>
      </button>
      <button type="button" class="btn-action-sm" @click="emit('showQr', venue)">
        <span class="material-symbols-outlined icon-16">qr_code_2</span>
        <span>{{ t('admin.venues.qrBtn') }}</span>
      </button>
      <button type="button" class="btn-action-sm" @click="emit('edit', venue)">
        <span class="material-symbols-outlined icon-16">edit</span>
        <span>{{ t('admin.venues.editBtn') }}</span>
      </button>
      <a
        v-if="venue.google_maps_url"
        :href="venue.google_maps_url"
        target="_blank"
        rel="noopener noreferrer"
        class="btn-action-sm link-maps"
        :title="t('admin.venues.mapsBtn')"
      >
        <span class="material-symbols-outlined icon-16">map</span>
        <span>{{ t('admin.venues.mapsBtn') }}</span>
      </a>
      <RouterLink
        :to="`/courts/${venue.slug}/directions`"
        target="_blank"
        class="btn-action-sm link-directions"
        :title="t('admin.venues.directionsBtn')"
      >
        <span class="material-symbols-outlined icon-16">directions</span>
        <span>{{ t('admin.venues.directionsBtn') }}</span>
      </RouterLink>
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
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease,
    border-color 0.15s ease;
}

.venue-admin-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
  border-color: rgba(219, 39, 119, 0.3);
}

.venue-admin-card.is-inactive {
  opacity: 0.75;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
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
  flex: 1;
}

.venue-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.venue-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.status-chip {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.chip-active {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.chip-maintenance {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.venue-address {
  margin: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
}

.venue-zones-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.zone-tag {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
  background: rgba(219, 39, 119, 0.08);
  color: var(--pink-dark, #db2777);
}

.venue-stats-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
  color: var(--ink);
}

.stat-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.slug-stat code {
  font-size: 11px;
  color: var(--muted);
}

.venue-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: auto;
  padding-top: 4px;
}

.btn-action-sm {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.15s ease;
}

.btn-action-sm:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.04);
}

.switch-control-wrap {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  cursor: pointer;
  flex-shrink: 0;
}

.switch-control-wrap input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: 0.2s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: '';
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

input:checked + .slider {
  background-color: #10b981;
}

input:checked + .slider:before {
  transform: translateX(20px);
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}
</style>
