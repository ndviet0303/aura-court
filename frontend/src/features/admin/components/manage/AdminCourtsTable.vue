<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { Court, Venue } from '@/features/court/types/court'
import { AuraButton, SelectDropdown } from '@/shared/components'

defineProps<{
  courts: Court[]
  venues: Venue[]
  selectedVenueFilter: number | 'all'
  statusFilter: 'all' | 'active' | 'inactive'
  searchQuery: string
  venueFilterOptions: Array<{ value: any; label: string; sublabel?: string; icon?: string }>
  statusFilterOptions: Array<{ value: any; label: string; icon?: string }>
}>()

const emit = defineEmits<{
  (e: 'update:selectedVenueFilter', val: number | 'all'): void
  (e: 'update:statusFilter', val: 'all' | 'active' | 'inactive'): void
  (e: 'update:searchQuery', val: string): void
  (e: 'createCourt'): void
  (e: 'editCourt', court: Court): void
  (e: 'openPriceModal', court: Court): void
  (e: 'openBlockModal', court: Court): void
  (e: 'toggleStatus', court: Court): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="courts-table-wrapper">
    <!-- Search & Filter Controls -->
    <div class="courts-filter-toolbar">
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-ico">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="court-search-input"
          :placeholder="t('admin.manage.searchCourtPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="filter-dropdowns-group">
        <SelectDropdown
          :model-value="selectedVenueFilter"
          :options="venueFilterOptions"
          @update:model-value="emit('update:selectedVenueFilter', $event === 'all' ? 'all' : Number($event))"
        />

        <SelectDropdown
          :model-value="statusFilter"
          :options="statusFilterOptions"
          @update:model-value="emit('update:statusFilter', $event as 'all' | 'active' | 'inactive')"
        />

        <AuraButton variant="primary" @click="emit('createCourt')">
          <span class="material-symbols-outlined icon-18">add</span>
          <span>{{ t('admin.manage.addNewCourt') }}</span>
        </AuraButton>
      </div>
    </div>

    <!-- Courts Cards Grid -->
    <div v-if="courts.length === 0" class="empty-courts-state">
      <span class="material-symbols-outlined empty-ico">sports_tennis</span>
      <h3>{{ t('admin.manage.noCourtsFound') }}</h3>
      <p>{{ t('admin.manage.noCourtsSub') }}</p>
    </div>

    <div v-else class="courts-admin-grid">
      <div
        v-for="court in courts"
        :key="court.id"
        class="court-admin-card"
        :class="{ inactive: court.is_active === false }"
      >
        <div class="court-card-img">
          <img
            v-if="court.image_url"
            :src="court.image_url"
            :alt="resolveLocalizedText(court.name)"
            loading="lazy"
          />
          <div v-else class="no-img-box">
            <span class="material-symbols-outlined fallback-icon">sports_tennis</span>
          </div>

          <span
            class="status-chip"
            :class="court.is_active !== false ? 'chip-active' : 'chip-inactive'"
          >
            {{ court.is_active !== false ? t('admin.manage.statusOpen') : t('admin.manage.statusClosed') }}
          </span>

          <span v-if="court.sport_zone" class="zone-chip">
            {{ resolveLocalizedText(court.sport_zone.name) }}
          </span>
        </div>

        <div class="court-card-body">
          <div class="court-title-row">
            <h4 class="court-name">{{ resolveLocalizedText(court.name) }}</h4>
            <span class="venue-name">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ resolveLocalizedText(court.venue?.name) }}</span>
            </span>
          </div>

          <p class="court-desc">{{ resolveLocalizedText(court.description) || t('admin.manage.standardDesc') }}</p>

          <div class="court-stats-row">
            <span class="stat-badge">
              <span class="material-symbols-outlined icon-14">schedule</span>
              <span>{{ t('admin.manage.priceSlotsCount', { count: court.price_slots?.length || 0 }) }}</span>
            </span>
            <span class="stat-badge text-yellow">
              <span class="material-symbols-outlined icon-14">build</span>
              <span>{{ t('admin.manage.blocksCount', { count: court.blocks?.length || 0 }) }}</span>
            </span>
          </div>

          <div class="court-actions-footer">
            <button
              type="button"
              class="btn-court-action"
              :title="t('admin.manage.btnPricing')"
              @click="emit('openPriceModal', court)"
            >
              <span class="material-symbols-outlined icon-14">price_change</span>
              <span>{{ t('admin.manage.btnPricing') }}</span>
            </button>
            <button
              type="button"
              class="btn-court-action"
              :title="t('admin.manage.btnBlock')"
              @click="emit('openBlockModal', court)"
            >
              <span class="material-symbols-outlined icon-14">lock</span>
              <span>{{ t('admin.manage.btnBlock') }}</span>
            </button>
            <button
              type="button"
              class="btn-court-action"
              :title="t('admin.manage.btnEdit')"
              @click="emit('editCourt', court)"
            >
              <span class="material-symbols-outlined icon-14">edit</span>
              <span>{{ t('admin.manage.btnEdit') }}</span>
            </button>
            <button
              type="button"
              class="btn-court-action btn-toggle"
              :title="court.is_active !== false ? t('admin.manage.statusClosed') : t('admin.manage.statusOpen')"
              @click="emit('toggleStatus', court)"
            >
              <span class="material-symbols-outlined icon-16">{{ court.is_active !== false ? 'pause' : 'play_arrow' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.courts-table-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.courts-filter-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-wrap {
  position: relative;
  flex: 1;
  min-width: 260px;
}

.search-ico {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  color: var(--muted);
}

.court-search-input {
  width: 100%;
  padding: 10px 14px 10px 38px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.filter-dropdowns-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.empty-courts-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-ico {
  font-size: 48px;
  color: var(--muted);
  margin-bottom: 12px;
}

.courts-admin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}

.court-admin-card {
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  transition: transform 0.15s, box-shadow 0.15s;
}

.court-admin-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.court-admin-card.inactive {
  opacity: 0.65;
}

.court-card-img {
  position: relative;
  width: 100%;
  height: 160px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
}

.court-card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.no-img-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.fallback-icon {
  font-size: 44px;
  color: var(--muted);
  opacity: 0.4;
}

.status-chip {
  position: absolute;
  top: 10px;
  left: 10px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.chip-active {
  background: #10b981;
  color: #fff;
}

.chip-inactive {
  background: #ef4444;
  color: #fff;
}

.zone-chip {
  position: absolute;
  top: 10px;
  right: 10px;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  font-size: 11px;
}

.court-card-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}

.court-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.court-name {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.venue-name {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 12px;
  color: var(--muted);
}

.court-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.court-stats-row {
  display: flex;
  gap: 8px;
  font-size: 11px;
  margin-top: auto;
}

.stat-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline);
}

.text-yellow {
  color: #f59e0b;
}

.court-actions-footer {
  display: flex;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
}

.btn-court-action {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 6px 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
}

.btn-court-action:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.btn-toggle {
  flex: 0 0 34px;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}
</style>
