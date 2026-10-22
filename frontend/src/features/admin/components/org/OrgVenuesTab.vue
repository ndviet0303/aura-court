<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton } from '@/shared/components'
import SelectDropdown, { type DropdownOption } from '@/shared/components/forms/SelectDropdown.vue'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'
import { OPERATING_STATUSES, type OperatingStatus } from '@/features/court/types/court'
import type { OrgVenue } from '../../composables/useOrganizationOwner'
import OperatingStatusBadge from '../lifecycle/OperatingStatusBadge.vue'

const props = defineProps<{
  venues: OrgVenue[]
  loading?: boolean
}>()

const { t } = useI18n()
const router = useRouter()

const searchQuery = ref('')
const statusFilter = ref<'all' | OperatingStatus>('all')

const statusOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.lifecycle.allStatuses'), icon: 'filter_list' },
  ...OPERATING_STATUSES.map((value) => ({ value, label: t(`admin.lifecycle.status.${value}`) })),
])

const filteredVenues = computed(() => {
  return props.venues.filter((v) => {
    // Search query
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const nameMatch = resolveLocalizedText(v.name).toLowerCase().includes(q)
      const addrMatch = (v.address || '').toLowerCase().includes(q)
      if (!nameMatch && !addrMatch) return false
    }

    // Status filter
    if (statusFilter.value !== 'all' && v.status !== statusFilter.value) return false

    return true
  })
})

function goToVenuesManagement() {
  router.push('/admin/venues')
}
</script>

<template>
  <div class="org-venues-tab-card">
    <!-- Header -->
    <div class="card-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">location_on</span>
        <div>
          <h3 class="tab-title">{{ t('admin.org.venuesTitle') }}</h3>
          <p class="tab-sub">{{ t('admin.org.venuesSubtitle') }}</p>
        </div>
      </div>

      <AuraButton variant="secondary" @click="goToVenuesManagement">
        <span class="material-symbols-outlined icon-18">add_business</span>
        <span>{{ t('admin.org.manageVenueBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="venues-toolbar">
      <div class="search-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          v-model="searchQuery"
          type="text"
          class="venues-search-input"
          :placeholder="t('admin.org.searchVenuesPlaceholder')"
        />
        <button v-if="searchQuery" type="button" class="clear-btn" @click="searchQuery = ''">
          ✕
        </button>
      </div>

      <div class="filter-wrap">
        <SelectDropdown v-model="statusFilter" :options="statusOptions" variant="bordered" />
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="venues-list">
      <div v-for="i in 3" :key="i" class="venue-item-row skeleton-row">
        <SkeletonLoader type="rect" height="68px" />
      </div>
    </div>

    <!-- Venues List -->
    <div v-else-if="filteredVenues.length > 0" class="venues-list">
      <div v-for="v in filteredVenues" :key="v.id" class="venue-item-row">
        <div class="v-main">
          <div class="v-badge">
            <span class="material-symbols-outlined venue-icon">stadium</span>
          </div>
          <div class="v-info">
            <h4 class="v-name">{{ resolveLocalizedText(v.name) }}</h4>
            <span class="v-addr">
              <span class="material-symbols-outlined icon-14">pin_drop</span>
              {{ v.address || 'Chưa cập nhật địa chỉ' }}
            </span>
          </div>
        </div>

        <div class="v-meta">
          <span class="courts-chip font-mono">
            <span class="material-symbols-outlined icon-14">sports_tennis</span>
            {{ t('admin.org.courtsScale', { count: v.courts_count || 0 }) }}
          </span>

          <OperatingStatusBadge :status="v.status" :suspension="v.active_suspension" />

          <AuraButton variant="secondary" size="sm" @click="goToVenuesManagement">
            <span class="material-symbols-outlined icon-16">settings</span>
          </AuraButton>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-venues-state">
      <span class="material-symbols-outlined empty-icon">domain_disabled</span>
      <h4 class="empty-title">{{ t('admin.org.noVenuesFound') }}</h4>
      <p class="empty-sub">{{ t('admin.org.noVenuesSub') }}</p>
      <AuraButton variant="primary" class="mt-3" @click="goToVenuesManagement">
        <span class="material-symbols-outlined icon-18">add_business</span>
        <span>{{ t('admin.org.manageVenueBtn') }}</span>
      </AuraButton>
    </div>
  </div>
</template>

<style scoped>
.org-venues-tab-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.tab-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.tab-sub {
  margin: 2px 0 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.venues-toolbar {
  display: flex;
  gap: 14px;
  align-items: center;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 240px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 18px;
  pointer-events: none;
}

.venues-search-input {
  width: 100%;
  padding: 10px 36px 10px 36px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  box-sizing: border-box;
}

.venues-search-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.clear-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
}

.filter-wrap {
  width: 200px;
}

.venues-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.venue-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 16px;
  transition: all 0.2s ease;
}

.venue-item-row:hover {
  border-color: var(--pink-dark, #db2777);
  background: var(--surface);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
}

.v-main {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.v-badge {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.venue-icon {
  font-size: 22px;
}

.v-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.v-name {
  margin: 0;
  font-size: 14.5px;
  font-weight: 700;
  color: var(--ink);
}

.v-addr {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.v-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.courts-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 700;
  padding: 5px 10px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--outline);
  color: var(--ink);
}

.empty-venues-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  border: 1.5px dashed var(--outline);
  border-radius: 16px;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted);
  margin-bottom: 10px;
}

.empty-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.empty-sub {
  margin: 6px 0 0 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 360px;
}

.mt-3 {
  margin-top: 14px;
}

.skeleton-row {
  padding: 0;
  border: 0;
  background: transparent;
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

:global(html.dark) .org-venues-tab-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .venues-search-input {
  background: var(--surface-subtle, #262024);
  color: var(--ink, #faf7f3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .venue-item-row {
  background: var(--surface-subtle, #262024);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .venue-item-row:hover {
  background: var(--soft, #30292e);
  border-color: var(--pink-dark, #e58e9f);
}

:global(html.dark) .v-name {
  color: var(--ink, #faf7f3);
}

:global(html.dark) .v-addr {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .courts-count-pill {
  background: var(--surface, #1e191c);
  color: var(--ink, #faf7f3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .v-badge {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}
</style>
